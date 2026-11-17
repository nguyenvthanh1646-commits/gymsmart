<?php
/**
 * nutrition-db.php — lớp đọc/ghi DB của trang Dinh dưỡng.
 *
 * Bảng nutrition_preferences là phần bổ sung (sql/nutrition_preferences.sql), máy nào chưa import thì câu lệnh sẽ lỗi.
 * Vì vậy hàm đọc/ghi bắt PDOException: đọc trả về null, ghi trả về false, và trang dùng lựa chọn trong session.
 */

require_once __DIR__ . '/workout-plan-db.php';

/** Lựa chọn mặc định cho người chưa chỉnh gì. */
function nutritionPrefsDefaults(): array
{
    return ['diet' => 'goal', 'p' => 25, 'c' => 45, 'f' => 30, 'meals' => 4, 'exclusions' => [], 'pace' => 'standard', 'cycle' => false];
}

/**
 * Làm sạch lựa chọn đọc từ session hoặc DB: khóa thiếu hoặc giá trị sai thì quay về mặc định.
 * Dữ liệu trong session kiểu cũ chỉ có diet, p, c, f nên vẫn dùng được.
 */
function normalizeNutritionPrefs(array $raw): array
{
    $prefs = nutritionPrefsDefaults();

    $diet = $raw['diet'] ?? '';
    if (is_string($diet) && array_key_exists($diet, dietStyles())) {
        $prefs['diet'] = $diet;
        foreach (['p', 'c', 'f'] as $key) {
            if (isset($raw[$key]) && is_numeric($raw[$key])) {
                $prefs[$key] = (int)$raw[$key];
            }
        }
        // Tỉ lệ tùy chỉnh sai thì không dùng kiểu tùy chỉnh, tránh chia macro phi lý
        if ($diet === 'custom' && validateCustomPercent($prefs['p'], $prefs['c'], $prefs['f']) !== null) {
            $prefs = nutritionPrefsDefaults();
        }
    }

    $meals = isset($raw['meals']) && is_numeric($raw['meals']) ? (int)$raw['meals'] : 0;
    if (in_array($meals, [3, 4, 5], true)) {
        $prefs['meals'] = $meals;
    }
    $prefs['exclusions'] = normalizeExclusions($raw['exclusions'] ?? []);
    if (isset($raw['pace']) && in_array($raw['pace'], ['gentle', 'standard', 'fast'], true)) {
        $prefs['pace'] = $raw['pace'];
    }
    $prefs['cycle'] = !empty($raw['cycle']);
    return $prefs;
}

/** Đọc lựa chọn đã lưu. Trả về null nếu chưa có dòng nào hoặc chưa import bảng. */
function loadNutritionPrefs(PDO $pdo, int $userId): ?array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT diet_style, custom_protein_pct, custom_carbs_pct, custom_fat_pct, meals_per_day, excluded_foods, weight_pace, cycle_calories
             FROM nutrition_preferences WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') {   // 42S02 = chưa import bảng, là trường hợp đã tính trước nên không ghi log
            error_log('loadNutritionPrefs: ' . $e->getMessage());
        }
        return null;
    }
    if ($row === false) {
        return null;
    }
    return normalizeNutritionPrefs([
        'diet'       => $row['diet_style'],
        'p'          => $row['custom_protein_pct'],
        'c'          => $row['custom_carbs_pct'],
        'f'          => $row['custom_fat_pct'],
        'meals'      => $row['meals_per_day'],
        'exclusions' => $row['excluded_foods'],
        'pace'       => $row['weight_pace'],
        'cycle'      => (int)$row['cycle_calories'] === 1,
    ]);
}

/** Lưu lựa chọn (chưa có dòng thì thêm, có rồi thì cập nhật). Trả về false nếu không ghi được. */
function saveNutritionPrefs(PDO $pdo, int $userId, array $prefs): bool
{
    $prefs = normalizeNutritionPrefs($prefs);
    $values = [
        $prefs['diet'], $prefs['p'], $prefs['c'], $prefs['f'], $prefs['meals'],
        implode(',', $prefs['exclusions']), $prefs['pace'], $prefs['cycle'] ? 1 : 0,
    ];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM nutrition_preferences WHERE user_id = ?');
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() !== false) {
            $stmt = $pdo->prepare(
                'UPDATE nutrition_preferences
                 SET diet_style = ?, custom_protein_pct = ?, custom_carbs_pct = ?, custom_fat_pct = ?,
                     meals_per_day = ?, excluded_foods = ?, weight_pace = ?, cycle_calories = ?
                 WHERE user_id = ?'
            );
            $stmt->execute([...$values, $userId]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO nutrition_preferences
                 (diet_style, custom_protein_pct, custom_carbs_pct, custom_fat_pct, meals_per_day, excluded_foods, weight_pace, cycle_calories, user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([...$values, $userId]);
        }
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') {
            error_log('saveNutritionPrefs: ' . $e->getMessage());
        }
        return false;
    }
    return true;
}

/** Các lần cân gần đây của người dùng, đủ cho khoảng 28 ngày kể cả khi cân mỗi ngày. */
function loadRecentWeights(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT recorded_date, weight_kg FROM body_metrics WHERE user_id = ? ORDER BY recorded_date DESC LIMIT 60'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Các thứ có bài tập trong lịch đang dùng (1 = Thứ Hai ... 7 = Chủ nhật).
 * Trả về mảng rỗng nếu chưa có lịch tập.
 */
function loadTrainingWeekdays(PDO $pdo, int $userId): array
{
    $plan = fetchActivePlan($pdo, $userId);
    if ($plan === null) {
        return [];
    }
    $days = array_keys($plan['days']);
    sort($days);
    return $days;
}
