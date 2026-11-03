<?php
/**
 * tools/seed-index-states.php — tạo các tài khoản USER ứng với các trạng thái của trang chủ sau đăng nhập,
 * để kiểm thử giao diện và chụp ảnh. Không sửa seed gốc, không đụng tới tài khoản khác.
 *
 * Chạy ở gốc repo:  php tools/seed-index-states.php
 *
 * Mỗi lần chạy xóa rồi tạo lại đúng 5 tài khoản này (mật khẩu chung: Demo@12345):
 *   index.moi@gymsmart.test    người dùng mới: chưa có hồ sơ, chưa có gì
 *   index.hoso@gymsmart.test   đã có hồ sơ, chưa có lịch tập
 *   index.lich@gymsmart.test   đã có lịch tập, chưa có nhật ký, số đo, mục tiêu dinh dưỡng
 *   index.daydu@gymsmart.test  đầy đủ: lịch tập, nhật ký, số đo, mục tiêu dinh dưỡng
 *   index.homnay@gymsmart.test có buổi tập đúng ngày chạy script, đã ghi 1 bài (để thử thẻ "Hôm nay" và ghi nhanh)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy từ dòng lệnh.');
}

require_once __DIR__ . '/../public/includes/db.php';
require_once __DIR__ . '/../public/includes/tdee.php';
require_once __DIR__ . '/../public/includes/workout-plan.php';
require_once __DIR__ . '/../public/includes/workout-plan-db.php';

const INDEX_PASSWORD = 'Demo@12345';
const INDEX_EMAILS = [
    'moi'   => 'index.moi@gymsmart.test',
    'hoso'  => 'index.hoso@gymsmart.test',
    'lich'  => 'index.lich@gymsmart.test',
    'daydu' => 'index.daydu@gymsmart.test',
    'homnay' => 'index.homnay@gymsmart.test',
];

/** Tạo một tài khoản. $profile rỗng = chưa nhập hồ sơ. Trả về id. */
function createIndexUser(PDO $pdo, string $name, string $email, array $profile = []): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, age, height_cm, weight_kg, gender,
                            activity_level, experience_level, goal)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, password_hash(INDEX_PASSWORD, PASSWORD_DEFAULT), 'USER',
        $profile['age'] ?? null, $profile['height_cm'] ?? null, $profile['weight_kg'] ?? null, $profile['gender'] ?? null,
        $profile['activity_level'] ?? null, $profile['experience_level'] ?? null, $profile['goal'] ?? null]);
    return (int)$pdo->lastInsertId();
}

// Các bảng con có ON DELETE CASCADE nên xóa user là xóa luôn lịch, nhật ký, số đo, mục tiêu
$placeholders = implode(', ', array_fill(0, count(INDEX_EMAILS), '?'));
$pdo->prepare("DELETE FROM users WHERE email IN ($placeholders)")->execute(array_values(INDEX_EMAILS));

$profile = [
    'age' => 24, 'height_cm' => 170, 'weight_kg' => 68.5, 'gender' => 'MALE',
    'activity_level' => 'MODERATE', 'experience_level' => 'BEGINNER', 'goal' => 'GAIN_MUSCLE',
];

createIndexUser($pdo, 'Trần Minh Mới', INDEX_EMAILS['moi']);
createIndexUser($pdo, 'Lê Hoàng Hồ Sơ', INDEX_EMAILS['hoso'], $profile);

$userLich = createIndexUser($pdo, 'Phạm Thu Lịch', INDEX_EMAILS['lich'], $profile);
createWorkoutPlanForUser($pdo, $userLich, 3, 'BEGINNER');

$userDayDu = createIndexUser($pdo, 'Nguyễn An Đầy Đủ', INDEX_EMAILS['daydu'], $profile);
$planId = createWorkoutPlanForUser($pdo, $userDayDu, 3, 'BEGINNER');

// Nhật ký: 2 tuần trước tập đủ; tuần này tập các buổi đã qua, trừ buổi gần hôm nay nhất (để có buổi "chưa tập")
$rows = $pdo->prepare('SELECT id, exercise_id, day_of_week, sets, reps FROM workout_plan_exercises WHERE workout_plan_id = ?');
$rows->execute([$planId]);
$planRows = $rows->fetchAll();

$today = (int)date('N'); // 1 = thứ Hai ... 7 = Chủ nhật
$pastDays = array_filter(array_unique(array_column($planRows, 'day_of_week')), fn($d) => (int)$d < $today);
$skipDay = $pastDays ? max($pastDays) : 0;

$insertLog = $pdo->prepare(
    'INSERT INTO workout_logs (user_id, workout_plan_exercise_id, exercise_id, log_date, sets_completed, reps_completed, weight_kg_used, note)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
foreach ($planRows as $row) {
    $day = (int)$row['day_of_week'];
    foreach ([2, 1, 0] as $weeksAgo) {
        if ($weeksAgo === 0 && ($day >= $today || $day === (int)$skipDay)) {
            continue;
        }
        $date = date('Y-m-d', strtotime("monday this week -{$weeksAgo} week +" . ($day - 1) . ' day'));
        $insertLog->execute([$userDayDu, $row['id'], $row['exercise_id'], $date, $row['sets'], $row['reps'], 20 + 2.5 * (2 - $weeksAgo), '']);
    }
}

// Số đo: 8 tuần, cân nặng tăng dần vì mục tiêu là tăng cơ
$insertMetric = $pdo->prepare(
    'INSERT INTO body_metrics (user_id, recorded_date, weight_kg, body_fat_percent, chest_cm, waist_cm, arm_cm)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
for ($week = 8; $week >= 0; $week--) {
    $i = 8 - $week;
    $insertMetric->execute([$userDayDu, date('Y-m-d', strtotime("-{$week} week")), 66.9 + 0.2 * $i, 18.0 - 0.1 * $i, 92.0 + 0.1 * $i, 78.0, 30.0 + 0.1 * $i]);
}

// Mục tiêu dinh dưỡng: tính bằng đúng hàm của dự án, không gõ số tay
$tdee = calculateTDEE(calculateBMR($profile['weight_kg'], $profile['height_cm'], $profile['age'], $profile['gender']), $profile['activity_level']);
$targets = calculateTargets($tdee, $profile['weight_kg'], $profile['goal'], $profile['experience_level']);
$pdo->prepare(
    'INSERT INTO nutrition_targets (user_id, tdee_kcal, target_kcal, protein_g, carbs_g, fat_g, calculated_date)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
)->execute([$userDayDu, $tdee, $targets['target_kcal'], $targets['protein_g'], $targets['carbs_g'], $targets['fat_g'], date('Y-m-d')]);

// Buổi tập đúng hôm nay: nếu lịch chưa có hôm nay thì chuyển buổi đầu tiên sang hôm nay, rồi ghi sẵn 1 bài
$userHomNay = createIndexUser($pdo, 'Võ Bình Hôm Nay', INDEX_EMAILS['homnay'], $profile);
$planHomNay = createWorkoutPlanForUser($pdo, $userHomNay, 3, 'BEGINNER');
$days = $pdo->prepare('SELECT DISTINCT day_of_week FROM workout_plan_exercises WHERE workout_plan_id = ? ORDER BY day_of_week');
$days->execute([$planHomNay]);
$dayList = array_map('intval', $days->fetchAll(PDO::FETCH_COLUMN));
if (!in_array($today, $dayList, true)) {
    $pdo->prepare('UPDATE workout_plan_exercises SET day_of_week = ? WHERE workout_plan_id = ? AND day_of_week = ?')
        ->execute([$today, $planHomNay, $dayList[0]]);
}
$first = $pdo->prepare('SELECT id, exercise_id, sets, reps FROM workout_plan_exercises WHERE workout_plan_id = ? AND day_of_week = ? ORDER BY order_index LIMIT 1');
$first->execute([$planHomNay, $today]);
$row = $first->fetch();
$pdo->prepare(
    'INSERT INTO workout_logs (user_id, workout_plan_exercise_id, exercise_id, log_date, sets_completed, reps_completed, weight_kg_used, note)
     VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?)'
)->execute([$userHomNay, $row['id'], $row['exercise_id'], $row['sets'], $row['reps'], 20, '']);

foreach (INDEX_EMAILS as $state => $email) {
    echo str_pad($state, 7), $email, ' / ', INDEX_PASSWORD, "\n";
}
