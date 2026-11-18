<?php
/**
 * dashboard.php — dữ liệu cho trang chủ sau đăng nhập (index.php).
 *
 * Gồm 2 phần:
 *   - Hàm thuần (không đụng DB, test được bằng test-dashboard.php): tính tuần này, buổi kế tiếp, nút chính, xu hướng cân nặng...
 *   - Hàm đọc DB: mọi truy vấn có user_id lấy từ session, dùng prepared statement.
 *
 * Mỗi thẻ có "status" riêng: ok | none (chưa có dữ liệu) | error (lỗi đọc DB).
 * Một thẻ lỗi không làm hỏng cả trang, vì mỗi nhóm truy vấn có try/catch riêng.
 */

require_once __DIR__ . '/workout-plan.php';
require_once __DIR__ . '/workout-plan-db.php';
require_once __DIR__ . '/meal-plan.php';

const DASH_DAY_NAMES  = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'];
const DASH_DAY_SHORT  = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];
const DASH_GOAL_LABELS = ['LOSE_FAT' => 'giảm mỡ', 'MAINTAIN' => 'duy trì', 'GAIN_MUSCLE' => 'tăng cơ'];

// Ước tính nhóm tự đặt, không phải số đo: mỗi hiệp khoảng 2,5 phút gồm cả thời gian nghỉ. Giao diện luôn ghi "khoảng".
const DASH_MINUTES_PER_SET = 2.5;
// Mục tiêu duy trì: chênh trong khoảng này (kg) vẫn coi là giữ được cân nặng
const DASH_MAINTAIN_RANGE_KG = 0.5;
const DASH_METRICS_LIMIT = 12; // số lần đo gần nhất đưa vào biểu đồ nhỏ

// ===================== Hàm thuần =====================

/** Số thập phân kiểu Việt Nam: 68,5 */
function dashNumber(float $value, int $decimals = 1): string
{
    return number_format($value, $decimals, ',', '.');
}

/** "Y-m-d" thành "Chủ nhật, 01/11/2026" */
function dashDateLabel(string $date): string
{
    $d = new DateTimeImmutable($date);
    return DASH_DAY_NAMES[(int)$d->format('N')] . ', ' . $d->format('d/m/Y');
}

/** Tóm tắt một buổi tập: nhãn, các nhóm cơ, số bài, số hiệp, thời lượng ước tính (làm tròn lên bội số của 5 phút). */
function dashSessionSummary(array $rows, ?string $label = null): array
{
    $muscles = [];
    foreach ($rows as $row) {
        $muscles[$row['muscle_group']] = WORKOUT_MUSCLE_LABELS[$row['muscle_group']] ?? $row['muscle_group'];
    }
    $sets = array_sum(array_column($rows, 'sets'));
    return [
        'label'   => $label ?? '',
        'muscles' => implode(', ', $muscles),
        'count'   => count($rows),
        'sets'    => $sets,
        'minutes' => (int)(ceil($sets * DASH_MINUTES_PER_SET / 5) * 5),
    ];
}

/**
 * Tính 7 ngày của tuần chứa $today (thứ Hai đến Chủ nhật).
 * $planDays: [thứ => [dòng lịch có row_id]], $logs: các dòng ['log_date', 'workout_plan_exercise_id'] trong tuần.
 *
 * Quy ước: buổi có ít nhất 1 bài của ngày đó được ghi thì tính là đã tập (kèm n/m bài).
 * Ghi vào ngày không có trong lịch, hoặc bài không thuộc buổi của ngày đó, là "tập thêm" và không cộng vào tiến độ.
 * status: done | today | missed | upcoming | rest | extra
 */
function dashBuildWeek(string $today, array $planDays, array $logs): array
{
    $todayDate = new DateTimeImmutable($today);
    $todayN = (int)$todayDate->format('N');
    $monday = $todayDate->modify('-' . ($todayN - 1) . ' days');

    $byDate = [];
    foreach ($logs as $log) {
        $byDate[$log['log_date']][] = (int)($log['workout_plan_exercise_id'] ?? 0);
    }

    $days = [];
    $planned = 0;
    $done = 0;
    for ($n = 1; $n <= 7; $n++) {
        $date = $monday->modify('+' . ($n - 1) . ' days')->format('Y-m-d');
        $rowIds = array_column($planDays[$n] ?? [], 'row_id');
        $logged = array_unique($byDate[$date] ?? []);
        $hit = count(array_intersect($logged, $rowIds));
        $hasExtra = count(array_diff($logged, $rowIds)) > 0;

        if ($rowIds === []) {
            $status = $hasExtra ? 'extra' : 'rest';
        } else {
            $planned++;
            if ($hit > 0) {
                $status = 'done';
                $done++;
            } elseif ($n === $todayN) {
                $status = 'today';
            } else {
                $status = $n < $todayN ? 'missed' : 'upcoming';
            }
        }
        $days[$n] = [
            'day' => $n, 'date' => $date, 'status' => $status, 'isToday' => $n === $todayN,
            'total' => count($rowIds), 'logged' => $hit, 'hasExtra' => $hasExtra,
        ];
    }
    return [
        'days' => $days, 'planned' => $planned, 'done' => $done,
        'percent' => $planned > 0 ? (int)round($done / $planned * 100) : 0,
    ];
}

/** Ngày có buổi tập gần nhất sau hôm nay (hết tuần thì vòng sang tuần sau). Không có buổi nào thì trả null. */
function dashNextSessionDay(int $todayN, array $planDays): ?array
{
    for ($offset = 1; $offset <= 7; $offset++) {
        $n = ($todayN + $offset - 1) % 7 + 1;
        if (!empty($planDays[$n])) {
            return ['day' => $n, 'nextWeek' => $todayN + $offset > 7];
        }
    }
    return null;
}

/**
 * Nút chính ở đầu trang, theo thứ tự ưu tiên: thiếu hồ sơ → chưa có lịch → buổi hôm nay → tiến trình.
 * $planStatus: ok | none | error. $todayTotal/$todayLogged: số bài của buổi hôm nay và số bài đã ghi.
 */
function dashPrimaryAction(int $missingProfile, string $planStatus, int $todayTotal, int $todayLogged): array
{
    if ($missingProfile > 0) {
        return ['label' => 'Hoàn thiện hồ sơ', 'href' => '/profile.php'];
    }
    if ($planStatus === 'none') {
        return ['label' => 'Tạo lịch tập', 'href' => '/workout-plan.php'];
    }
    if ($planStatus === 'error') {
        return ['label' => 'Mở lịch tập', 'href' => '/workout-plan.php'];
    }
    if ($todayTotal > 0 && $todayLogged === 0) {
        return ['label' => 'Bắt đầu buổi tập hôm nay', 'href' => '/workout-session.php'];   // chế độ Đang tập (B1)
    }
    if ($todayTotal > 0 && $todayLogged < $todayTotal) {
        return ['label' => 'Tiếp tục buổi tập hôm nay', 'href' => '/workout-session.php'];
    }
    return ['label' => 'Xem tiến trình', 'href' => '/progress.php'];
}

/**
 * Xu hướng cân nặng từ các lần đo (cũ đến mới): chênh lệch lần mới nhất so với lần đầu trong danh sách
 * và câu mô tả theo mục tiêu. Dưới 2 lần đo thì không có xu hướng.
 * "matches": true = đúng hướng mục tiêu, false = chưa đúng, null = không xét được (chưa chọn mục tiêu).
 */
function dashWeightTrend(array $metrics, ?string $goal): ?array
{
    if (count($metrics) < 2) {
        return null;
    }
    $delta = round((float)$metrics[count($metrics) - 1]['weight_kg'] - (float)$metrics[0]['weight_kg'], 2);
    $matches = null;
    if ($goal === 'LOSE_FAT') {
        $matches = $delta < 0;
    } elseif ($goal === 'GAIN_MUSCLE') {
        $matches = $delta > 0;
    } elseif ($goal === 'MAINTAIN') {
        $matches = abs($delta) <= DASH_MAINTAIN_RANGE_KG;
    }
    return ['delta' => $delta, 'from' => $metrics[0]['recorded_date'], 'matches' => $matches];
}

/** Toạ độ đường sparkline cho thuộc tính points của <polyline>: trục x theo ngày đo, trục y theo cân nặng. */
function dashSparklinePoints(array $metrics, int $width, int $height, int $pad = 4): string
{
    if (count($metrics) < 2) {
        return '';
    }
    $times = array_map(fn($m) => strtotime($m['recorded_date']), $metrics);
    $values = array_map(fn($m) => (float)$m['weight_kg'], $metrics);
    $spanX = max(max($times) - min($times), 1);
    $spanY = max($values) - min($values);
    $points = [];
    foreach ($metrics as $i => $_) {
        $x = ($times[$i] - min($times)) / $spanX * $width;
        // Cân nặng không đổi thì vẽ đường ngang ở giữa. Trục y của SVG hướng xuống nên phải đảo lại.
        $y = $spanY > 0 ? $height - $pad - ($values[$i] - min($values)) / $spanY * ($height - 2 * $pad) : $height / 2;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    return implode(' ', $points);
}

// ===================== Hàm đọc DB =====================

/** Lịch tập đang dùng. */
function dashLoadPlan(PDO $pdo, int $userId): array
{
    try {
        $plan = fetchActivePlan($pdo, $userId);
        return $plan === null ? ['status' => 'none'] : ['status' => 'ok'] + $plan;
    } catch (Throwable $e) {
        error_log('dashboard: lịch tập: ' . $e->getMessage());
        return ['status' => 'error'];
    }
}

/** Nhật ký trong tuần (từ thứ Hai đến Chủ nhật). Lỗi đọc DB thì trả null. */
function dashLoadWeekLogs(PDO $pdo, int $userId, string $today): ?array
{
    $d = new DateTimeImmutable($today);
    $monday = $d->modify('-' . ((int)$d->format('N') - 1) . ' days');
    try {
        $stmt = $pdo->prepare(
            'SELECT log_date, workout_plan_exercise_id FROM workout_logs
             WHERE user_id = ? AND log_date BETWEEN ? AND ?'
        );
        $stmt->execute([$userId, $monday->format('Y-m-d'), $monday->modify('+6 days')->format('Y-m-d')]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('dashboard: nhật ký tuần: ' . $e->getMessage());
        return null;
    }
}

/**
 * Mức tạ lần gần nhất của nhiều bài trong MỘT truy vấn (không lặp truy vấn theo từng bài).
 * Danh sách id ép về int rồi mới tạo dấu ? tương ứng. Trả về [exercise_id => kg].
 * Chỉ lấy mức tạ lớn hơn 0: để trống khi ghi được lưu thành 0 (như hít đất), hiện "lần trước 0 kg" không có ý nghĩa.
 */
function dashLoadLastWeights(PDO $pdo, int $userId, array $exerciseIds): array
{
    $ids = array_values(array_unique(array_map('intval', $exerciseIds)));
    if ($ids === []) {
        return [];
    }
    $marks = implode(', ', array_fill(0, count($ids), '?'));
    try {
        $stmt = $pdo->prepare(
            "SELECT exercise_id, weight_kg_used FROM workout_logs
             WHERE user_id = ? AND weight_kg_used > 0 AND exercise_id IN ($marks)
             ORDER BY log_date ASC, id ASC"
        );
        $stmt->execute(array_merge([$userId], $ids));
        $weights = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $weights[(int)$row['exercise_id']] = (float)$row['weight_kg_used']; // sắp cũ đến mới nên dòng sau ghi đè dòng trước
        }
        return $weights;
    } catch (Throwable $e) {
        error_log('dashboard: mức tạ gần nhất: ' . $e->getMessage());
        return [];
    }
}

/** Tên file ảnh của nhiều bài trong MỘT truy vấn. Lỗi đọc DB thì trả mảng rỗng, giao diện hiện biểu tượng thay ảnh. */
function dashLoadExerciseImages(PDO $pdo, array $exerciseIds): array
{
    $ids = array_values(array_unique(array_map('intval', $exerciseIds)));
    if ($ids === []) {
        return [];
    }
    $marks = implode(', ', array_fill(0, count($ids), '?'));
    try {
        $stmt = $pdo->prepare("SELECT id, image_url FROM exercises WHERE id IN ($marks)"); // audit:ok $marks chỉ gồm các dấu ?
        $stmt->execute($ids);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'image_url', 'id');
    } catch (Throwable $e) {
        error_log('dashboard: ảnh bài tập: ' . $e->getMessage());
        return [];
    }
}

/** Lần tính TDEE gần nhất. */
function dashLoadNutrition(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT tdee_kcal, target_kcal, protein_g, carbs_g, fat_g, calculated_date FROM nutrition_targets
             WHERE user_id = ? ORDER BY calculated_date DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return ['status' => 'none'];
        }
        return ['status' => 'ok', 'shares' => macroShares((float)$row['protein_g'], (float)$row['carbs_g'], (float)$row['fat_g'])] + $row;
    } catch (Throwable $e) {
        error_log('dashboard: dinh dưỡng: ' . $e->getMessage());
        return ['status' => 'error'];
    }
}

/** Các lần đo gần nhất, trả về theo thứ tự cũ đến mới. */
function dashLoadMetrics(PDO $pdo, int $userId, ?string $goal): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT recorded_date, weight_kg FROM body_metrics
             WHERE user_id = ? ORDER BY recorded_date DESC, id DESC LIMIT ' . (int)DASH_METRICS_LIMIT
        );
        $stmt->execute([$userId]);
        $metrics = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        if ($metrics === []) {
            return ['status' => 'none'];
        }
        return ['status' => 'ok', 'metrics' => $metrics, 'latest' => $metrics[count($metrics) - 1], 'trend' => dashWeightTrend($metrics, $goal)];
    } catch (Throwable $e) {
        error_log('dashboard: số đo: ' . $e->getMessage());
        return ['status' => 'error'];
    }
}

/** Slug các bài người dùng đã đọc. Bảng article_reads chưa import thì coi như chưa đọc bài nào. */
function dashLoadReadSlugs(PDO $pdo, int $userId): array
{
    try {
        $stmt = $pdo->prepare('SELECT slug FROM article_reads WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Gom toàn bộ dữ liệu cho trang chủ.
 * $today lấy từ MySQL (CURDATE) để khớp với ngày saveWorkoutLog() ghi vào nhật ký, vì giờ của PHP và MySQL có thể lệch nhau.
 */
function loadDashboard(PDO $pdo, int $userId, string $today, ?string $goal, int $missingProfile, array $mustReadArticles): array
{
    $todayN = (int)(new DateTimeImmutable($today))->format('N');
    $plan = dashLoadPlan($pdo, $userId);
    $planDays = $plan['days'] ?? [];
    $labels = $plan['status'] === 'ok' ? workoutSessionLabels($plan['split_type'], (int)$plan['days_per_week']) : [];

    // Tuần này: chỉ đọc nhật ký khi có lịch
    $week = ['status' => $plan['status']];
    $logs = [];
    if ($plan['status'] === 'ok') {
        $logs = dashLoadWeekLogs($pdo, $userId, $today);
        if ($logs === null) {
            $week = ['status' => 'error'];
            $logs = [];
        } else {
            $week = ['status' => 'ok'] + dashBuildWeek($today, $planDays, $logs);
        }
    }

    // Hôm nay: các bài trong buổi, bài nào đã ghi, mức tạ lần trước
    $todayRows = $planDays[$todayN] ?? [];
    $loggedToday = [];
    foreach ($logs as $log) {
        if ($log['log_date'] === $today) {
            $loggedToday[(int)($log['workout_plan_exercise_id'] ?? 0)] = true;
        }
    }
    foreach ($todayRows as &$row) {
        $row['logged'] = isset($loggedToday[$row['row_id']]);
    }
    unset($row);
    $weights = $todayRows === [] ? [] : dashLoadLastWeights($pdo, $userId, array_column($todayRows, 'exercise_id'));
    $todayLogged = count(array_filter($todayRows, fn($r) => $r['logged']));

    $next = dashNextSessionDay($todayN, $planDays);
    $nextRows = $next === null ? [] : $planDays[$next['day']];

    // Gắn ảnh vào các bài sẽ hiển thị. Buổi hôm nay có thì không hiện buổi kế tiếp, nên chỉ lấy ảnh của một trong hai.
    $shownRows = $todayRows !== [] ? $todayRows : $nextRows;
    $images = dashLoadExerciseImages($pdo, array_column($shownRows, 'exercise_id'));
    $withImage = fn(array $rows) => array_map(fn($r) => $r + ['image' => $images[$r['exercise_id']] ?? null], $rows);
    $todayRows = $withImage($todayRows);

    $nextSummary = $next === null ? null : ['day' => $next['day'], 'nextWeek' => $next['nextWeek'], 'rows' => $withImage($nextRows)]
        + dashSessionSummary($nextRows, $labels[$next['day']] ?? null);

    $read = dashLoadReadSlugs($pdo, $userId);
    $unread = array_filter($mustReadArticles, fn($slug) => !in_array($slug, $read, true), ARRAY_FILTER_USE_KEY);

    return [
        'today' => $today,
        'dateLabel' => dashDateLabel($today),
        'action' => dashPrimaryAction($missingProfile, $plan['status'], count($todayRows), $todayLogged),
        'plan' => $plan,
        'session' => [
            'rows' => $todayRows, 'logged' => $todayLogged, 'weights' => $weights, 'next' => $nextSummary,
            'summary' => $todayRows === [] ? null : dashSessionSummary($todayRows, $labels[$todayN] ?? null),
        ],
        'week' => $week,
        'nutrition' => dashLoadNutrition($pdo, $userId),
        'progress' => dashLoadMetrics($pdo, $userId, $goal),
        'goal' => $goal,          // dùng ở thẻ Tiến trình; không đọc qua biến $row vì vòng lặp bài tập ở dash-today.php ghi đè biến đó
        'unreadArticles' => $unread,
    ];
}
