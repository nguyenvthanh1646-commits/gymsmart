<?php
/**
 * meal-log.php — nhật ký ăn trong ngày (C1, A-50). Bảng meal_logs + user_foods (sql/meal_logs.sql).
 *
 * Nguồn số liệu thực phẩm:
 *   - foodCatalog() trong meal-plan.php: 20 thực phẩm dùng chung với Gợi ý lịch ăn, giá trị XẤP XỈ trên 100 g (đã nấu chín).
 *   - user_foods: người dùng tự nhập đạm / tinh bột / béo trên 100 g theo NHÃN BAO BÌ của sản phẩm họ ăn.
 *   Ứng dụng không tự bịa thêm số liệu món ăn. Calo luôn tính bằng 4 × đạm + 4 × tinh bột + 9 × béo (cùng quy đổi với tdee.php).
 * Hàm tính toán là hàm THUẦN (test ở test-meal-log.php). Mọi hàm DB nhận $userId từ session ở nơi gọi.
 */
require_once __DIR__ . '/meal-plan.php';

const MEAL_KINDS = ['breakfast' => 'Bữa sáng', 'lunch' => 'Bữa trưa', 'dinner' => 'Bữa tối', 'snack' => 'Bữa phụ'];
const MEAL_MAX_GRAMS = 3000;
const USER_FOODS_MAX = 50;

/** DB đã có bảng nhật ký ăn chưa. */
function mealLogReady(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $pdo->query('SELECT 1 FROM meal_logs LIMIT 1');
            $ready = true;
        } catch (PDOException $e) {
            $ready = false;
        }
    }
    return $ready;
}

/**
 * Hàm thuần: số liệu của $grams gam thực phẩm có giá trị trên 100 g là $p, $c, $f.
 * @return array ['kcal','protein','carbs','fat'] làm tròn 1 chữ số
 */
function mealPortion(float $p, float $c, float $f, float $grams): array
{
    $k = $grams / 100;
    $prot = round($p * $k, 1);
    $carb = round($c * $k, 1);
    $fat = round($f * $k, 1);
    return ['kcal' => round(($p * 4 + $c * 4 + $f * 9) * $k, 1), 'protein' => $prot, 'carbs' => $carb, 'fat' => $fat];
}

/** Hàm thuần: kiểm tra một thực phẩm tự thêm (giá trị trên 100 g). Trả câu lỗi hoặc null. */
function userFoodError(string $name, $p, $c, $f): ?string
{
    $len = mb_strlen(trim($name));
    if ($len < 2 || $len > 80) {
        return 'Tên thực phẩm cần từ 2 đến 80 ký tự.';
    }
    foreach (['đạm' => $p, 'tinh bột' => $c, 'chất béo' => $f] as $label => $v) {
        if (!is_numeric($v) || (float)$v < 0 || (float)$v > 100) {
            return "Lượng $label trên 100 g phải từ 0 đến 100 g.";
        }
    }
    if ((float)$p + (float)$c + (float)$f > 100) {
        return 'Tổng đạm, tinh bột và béo trong 100 g không thể vượt 100 g. Hãy xem lại nhãn.';
    }
    return null;
}

/**
 * Hàm thuần: tổng trong ngày so với mục tiêu.
 * @param array      $rows   [['meal_kind','kcal','protein_g','carbs_g','fat_g'], ...]
 * @param array|null $target ['target_kcal','protein_g','carbs_g','fat_g'] hoặc null nếu chưa có mục tiêu
 * @return array ['total' => [kcal, protein, carbs, fat], 'byMeal' => [kind => kcal], 'left' => [...]|null, 'percent' => [...]|null]
 */
function mealDaySummary(array $rows, ?array $target): array
{
    $total = ['kcal' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
    $byMeal = array_fill_keys(array_keys(MEAL_KINDS), 0.0);
    foreach ($rows as $r) {
        $total['kcal'] += (float)$r['kcal'];
        $total['protein'] += (float)$r['protein_g'];
        $total['carbs'] += (float)$r['carbs_g'];
        $total['fat'] += (float)$r['fat_g'];
        if (isset($byMeal[$r['meal_kind']])) {
            $byMeal[$r['meal_kind']] += (float)$r['kcal'];
        }
    }
    $total = array_map(fn($v) => round($v, 1), $total);
    $left = $percent = null;
    if ($target !== null) {
        $goal = ['kcal' => (float)$target['target_kcal'], 'protein' => (float)$target['protein_g'], 'carbs' => (float)$target['carbs_g'], 'fat' => (float)$target['fat_g']];
        foreach ($goal as $k => $g) {
            $left[$k] = round($g - $total[$k], 1);
            $percent[$k] = $g > 0 ? (int)round($total[$k] / $g * 100) : 0;
        }
    }
    return ['total' => $total, 'byMeal' => array_map(fn($v) => round($v, 1), $byMeal), 'left' => $left, 'percent' => $percent];
}

// ===================== Truy vấn =====================

/** Mục tiêu dinh dưỡng mới nhất của người dùng, hoặc null. */
function mealTarget(PDO $pdo, int $userId): ?array
{
    $st = $pdo->prepare('SELECT target_kcal, protein_g, carbs_g, fat_g, calculated_date FROM nutrition_targets WHERE user_id = ? ORDER BY calculated_date DESC, id DESC LIMIT 1');
    $st->execute([$userId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mealLogDay(PDO $pdo, int $userId, string $date): array
{
    $st = $pdo->prepare('SELECT id, meal_kind, food_name, grams, kcal, protein_g, carbs_g, fat_g FROM meal_logs WHERE user_id = ? AND log_date = ? ORDER BY id');
    $st->execute([$userId, $date]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function userFoods(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare('SELECT id, name, protein_100g, carbs_100g, fat_100g FROM user_foods WHERE user_id = ? ORDER BY name');
    $st->execute([$userId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function userFoodAdd(PDO $pdo, int $userId, string $name, $p, $c, $f): int
{
    if ($e = userFoodError($name, $p, $c, $f)) {
        throw new InvalidArgumentException($e);
    }
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM user_foods WHERE user_id = ?');
    $cnt->execute([$userId]);
    if ((int)$cnt->fetchColumn() >= USER_FOODS_MAX) {
        throw new InvalidArgumentException('Tối đa ' . USER_FOODS_MAX . ' thực phẩm tự thêm. Xóa bớt cái không dùng nữa.');
    }
    $pdo->prepare('INSERT INTO user_foods (user_id, name, protein_100g, carbs_100g, fat_100g, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$userId, trim($name), round((float)$p, 1), round((float)$c, 1), round((float)$f, 1), date('Y-m-d H:i:s')]);
    return (int)$pdo->lastInsertId();
}

function userFoodDelete(PDO $pdo, int $userId, int $foodId): void
{
    $st = $pdo->prepare('DELETE FROM user_foods WHERE id = ? AND user_id = ?');
    $st->execute([$foodId, $userId]);
    if ($st->rowCount() === 0) {
        throw new InvalidArgumentException('Không tìm thấy thực phẩm này.');
    }
}

/**
 * Ghi một món đã ăn. $food = 'c:<mã trong foodCatalog>' hoặc 'u:<id thực phẩm tự thêm của chính user>'.
 * Số liệu được tính ở máy chủ và chụp lại vào dòng nhật ký.
 */
function mealLogAdd(PDO $pdo, int $userId, string $date, string $kind, string $food, $grams): int
{
    if (!isset(MEAL_KINDS[$kind])) {
        throw new InvalidArgumentException('Hãy chọn bữa ăn.');
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if ($d === false || $d->format('Y-m-d') !== $date || $date > date('Y-m-d')) {
        throw new InvalidArgumentException('Ngày không hợp lệ.');
    }
    $g = is_numeric($grams) ? (float)$grams : 0;
    if ($g <= 0 || $g > MEAL_MAX_GRAMS) {
        throw new InvalidArgumentException('Khối lượng phải từ 1 đến ' . MEAL_MAX_GRAMS . ' g.');
    }
    $foodKey = null;
    $userFoodId = null;
    if (preg_match('/^c:([a-z]{2,20})$/', $food, $m) && isset(foodCatalog()[$m[1]])) {
        $item = foodCatalog()[$m[1]];
        $foodKey = $m[1];
        [$name, $p, $c, $f] = [$item['name'], $item['p'], $item['c'], $item['f']];
    } elseif (preg_match('/^u:(\d+)$/', $food, $m)) {
        $st = $pdo->prepare('SELECT id, name, protein_100g, carbs_100g, fat_100g FROM user_foods WHERE id = ? AND user_id = ?');
        $st->execute([(int)$m[1], $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new InvalidArgumentException('Không tìm thấy thực phẩm này.');
        }
        $userFoodId = (int)$row['id'];
        [$name, $p, $c, $f] = [$row['name'], (float)$row['protein_100g'], (float)$row['carbs_100g'], (float)$row['fat_100g']];
    } else {
        throw new InvalidArgumentException('Hãy chọn thực phẩm.');
    }
    $v = mealPortion((float)$p, (float)$c, (float)$f, $g);
    $pdo->prepare('INSERT INTO meal_logs (user_id, log_date, meal_kind, food_key, user_food_id, food_name, grams, kcal, protein_g, carbs_g, fat_g, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$userId, $date, $kind, $foodKey, $userFoodId, $name, round($g, 1), $v['kcal'], $v['protein'], $v['carbs'], $v['fat'], date('Y-m-d H:i:s')]);
    return (int)$pdo->lastInsertId();
}

function mealLogDelete(PDO $pdo, int $userId, int $logId): void
{
    $st = $pdo->prepare('DELETE FROM meal_logs WHERE id = ? AND user_id = ?');
    $st->execute([$logId, $userId]);
    if ($st->rowCount() === 0) {
        throw new InvalidArgumentException('Không tìm thấy món này trong nhật ký của bạn.');
    }
}
