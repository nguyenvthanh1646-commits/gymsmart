<?php
/**
 * tools/seed-demo.php — tạo dữ liệu mẫu để kiểm thử giao diện và chụp ảnh báo cáo.
 *
 * Chạy ở gốc repo:  php tools/seed-demo.php
 *
 * Tạo lại (xóa rồi tạo) 2 tài khoản, KHÔNG đụng tới seed bài tập hay tài khoản khác:
 *   demo.user@gymsmart.test  / Demo@12345  (USER, đủ hồ sơ, có lịch tập, nhật ký, số đo, mục tiêu dinh dưỡng)
 *   demo.admin@gymsmart.test / Admin@12345 (ADMIN)
 * Thêm 1 bài viết nháp do admin soạn (slug demo-bai-viet-nhap).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy từ dòng lệnh.');
}

require_once __DIR__ . '/../public/includes/db.php';
require_once __DIR__ . '/../public/includes/workout-plan.php';
require_once __DIR__ . '/../public/includes/workout-plan-db.php';

const DEMO_USER_EMAIL  = 'demo.user@gymsmart.test';
const DEMO_ADMIN_EMAIL = 'demo.admin@gymsmart.test';

// Xóa tài khoản demo cũ: các bảng con có ON DELETE CASCADE nên tự xóa theo
$pdo->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([DEMO_USER_EMAIL, DEMO_ADMIN_EMAIL]);
$pdo->prepare('DELETE FROM articles WHERE slug = ?')->execute(['demo-bai-viet-nhap']);

$insertUser = $pdo->prepare(
    'INSERT INTO users (full_name, email, password_hash, role, age, height_cm, weight_kg, gender,
                        activity_level, experience_level, goal)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insertUser->execute(['Nguyễn Văn Demo', DEMO_USER_EMAIL, password_hash('Demo@12345', PASSWORD_DEFAULT), 'USER',
    22, 172, 70.4, 'MALE', 'MODERATE', 'BEGINNER', 'LOSE_FAT']);
$userId = (int)$pdo->lastInsertId();

$insertUser->execute(['Trần Thị Quản Trị', DEMO_ADMIN_EMAIL, password_hash('Admin@12345', PASSWORD_DEFAULT), 'ADMIN',
    null, null, null, null, null, null, null]);
$adminId = (int)$pdo->lastInsertId();

// Lịch tập 3 buổi/tuần do chính thuật toán của dự án sinh ra
$planId = createWorkoutPlanForUser($pdo, $userId, 3, 'BEGINNER');

// Số đo cơ thể: 8 tuần, cân nặng giảm dần ~0,4 kg/tuần
$insertMetric = $pdo->prepare(
    'INSERT INTO body_metrics (user_id, recorded_date, weight_kg, body_fat_percent, chest_cm, waist_cm, arm_cm)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
for ($week = 8; $week >= 0; $week--) {
    $date = date('Y-m-d', strtotime("-{$week} week"));
    $i = 8 - $week;
    $insertMetric->execute([$userId, $date, 73.6 - 0.4 * $i, 22.0 - 0.3 * $i, 96.0, 86.0 - 0.5 * $i, 31.0 + 0.1 * $i]);
}

// Nhật ký buổi tập: 2 tuần gần nhất, theo đúng các bài trong lịch
$rows = $pdo->prepare('SELECT id, exercise_id, day_of_week, sets, reps FROM workout_plan_exercises WHERE workout_plan_id = ?');
$rows->execute([$planId]);
$insertLog = $pdo->prepare(
    'INSERT INTO workout_logs (user_id, workout_plan_exercise_id, exercise_id, log_date, sets_completed, reps_completed, weight_kg_used, note)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
foreach ($rows->fetchAll() as $row) {
    foreach ([2, 1] as $weeksAgo) {
        // ngày trong tuần (1 = thứ Hai) của tuần cách đây $weeksAgo tuần
        $date = date('Y-m-d', strtotime("monday this week -{$weeksAgo} week +" . ($row['day_of_week'] - 1) . ' day'));
        $insertLog->execute([$userId, $row['id'], $row['exercise_id'], $date, $row['sets'], $row['reps'],
            20 + 2.5 * (2 - $weeksAgo), $weeksAgo === 1 ? 'Tăng tạ được, form ổn' : '']);
    }
}

// Mục tiêu dinh dưỡng đã lưu (số làm tròn, đủ để trang lịch sử có dữ liệu)
$insertTarget = $pdo->prepare(
    'INSERT INTO nutrition_targets (user_id, tdee_kcal, target_kcal, protein_g, carbs_g, fat_g, calculated_date)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$insertTarget->execute([$userId, 2620, 2100, 113, 254, 70, date('Y-m-d', strtotime('-4 week'))]);
$insertTarget->execute([$userId, 2580, 2060, 113, 245, 69, date('Y-m-d')]);

// Bài viết nháp do admin soạn, để kiểm tra trang quản trị bài viết
$now = date('Y-m-d H:i:s');
$pdo->prepare(
    'INSERT INTO articles (slug, title, category, tags, summary, body, status, created_by, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute(['demo-bai-viet-nhap', 'Bài viết nháp để kiểm thử', 'general', 'Kiểm thử',
    'Bài nháp tạo bởi tools/seed-demo.php, dùng để kiểm tra luồng soạn và đăng bài.',
    "Đoạn mở đầu của bài nháp.\n\n## Tiêu đề mục\n\nNội dung thử.", 'DRAFT', $adminId, $now, $now]);

echo "Đã tạo USER  " . DEMO_USER_EMAIL . " / Demo@12345 (id $userId, lịch tập id $planId)\n";
echo "Đã tạo ADMIN " . DEMO_ADMIN_EMAIL . " / Admin@12345 (id $adminId)\n";
