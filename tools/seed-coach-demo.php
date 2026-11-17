<?php
/**
 * tools/seed-coach-demo.php — DỮ LIỆU THỬ cho phân hệ Huấn luyện viên (HLV). KHÔNG phải HLV hay học viên có thật.
 *
 * Chạy ở gốc repo (sau khi đã import sql/coach_features.sql):  php tools/seed-coach-demo.php
 *
 * Tạo lại (xóa rồi tạo) 6 tài khoản đuôi @gymsmart.test, mật khẩu chung Test@12345:
 *   hlv.admin@gymsmart.test   ADMIN
 *   hlv.coach1@gymsmart.test  COACH  (đi đúng luồng thật: nộp đơn → admin duyệt)  — đang có học viên 1
 *   hlv.coach2@gymsmart.test  COACH                                               — có 1 yêu cầu chờ duyệt (học viên 2)
 *   hlv.user1@gymsmart.test   USER   đang tập với HLV 1: lịch do HLV giao, macro do HLV đặt, nhật ký, nhận xét, tin nhắn
 *   hlv.user2@gymsmart.test   USER   đã gửi yêu cầu tới HLV 2, đang chờ
 *   hlv.user3@gymsmart.test   USER   chưa có HLV (dùng để thử gửi yêu cầu, hoặc thử nộp đơn làm HLV)
 * Họ tên đều bắt đầu bằng "[Thử]" và hồ sơ HLV ghi rõ là dữ liệu thử để không ai nhầm là người thật.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy từ dòng lệnh.');
}

require_once __DIR__ . '/../public/includes/db.php';
require_once __DIR__ . '/../public/includes/workout-plan-db.php';
require_once __DIR__ . '/../public/includes/coach-db.php';

if (!coachTablesReady($pdo)) {
    exit("Chưa có bảng HLV. Hãy import sql/coach_features.sql trước.\n");
}

const SEED_PASSWORD = 'Test@12345';
$emails = ['hlv.admin', 'hlv.coach1', 'hlv.coach2', 'hlv.user1', 'hlv.user2', 'hlv.user3'];
$emails = array_map(fn($e) => $e . '@gymsmart.test', $emails);

// Xóa bản cũ: các bảng con có ON DELETE CASCADE nên tự xóa theo
$pdo->prepare('DELETE FROM users WHERE email IN (?, ?, ?, ?, ?, ?)')->execute($emails);

$insertUser = $pdo->prepare(
    'INSERT INTO users (full_name, email, password_hash, role, age, height_cm, weight_kg, gender, activity_level, experience_level, goal)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$hash = password_hash(SEED_PASSWORD, PASSWORD_DEFAULT);
$people = [
    ['[Thử] Quản Trị',   'ADMIN', null, null, null, null, null, null, null],
    ['[Thử] Huấn Luyện Một', 'USER', 30, 175, 74, 'MALE', 'ACTIVE', 'ADVANCED', 'MAINTAIN'],
    ['[Thử] Huấn Luyện Hai', 'USER', 27, 162, 54, 'FEMALE', 'ACTIVE', 'ADVANCED', 'MAINTAIN'],
    ['[Thử] Học Viên An',    'USER', 23, 170, 72.5, 'MALE', 'MODERATE', 'BEGINNER', 'LOSE_FAT'],
    ['[Thử] Học Viên Bình',  'USER', 25, 158, 50, 'FEMALE', 'LIGHT', 'BEGINNER', 'GAIN_MUSCLE'],
    ['[Thử] Học Viên Chi',   'USER', 31, 165, 63, 'FEMALE', 'MODERATE', 'INTERMEDIATE', 'LOSE_FAT'],
];
$ids = [];
foreach ($people as $i => $p) {
    $insertUser->execute([$p[0], $emails[$i], $hash, $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8]]);
    $ids[$i] = (int)$pdo->lastInsertId();
}
[$adminId, $coach1, $coach2, $user1, $user2, $user3] = $ids;

// Đơn đăng ký HLV → admin duyệt (dùng đúng hàm của trang thật)
$applications = [
    $coach1 => [
        'headline' => 'HLV thử nghiệm: giảm mỡ cho người mới',
        'specialties' => ['FAT_LOSS', 'BEGINNER'],
        'experience_years' => '5', 'max_clients' => '8',
        'certifications' => "Hồ sơ thử nghiệm, không có chứng chỉ thật",
        'bio' => 'Đây là hồ sơ THỬ NGHIỆM do tools/seed-coach-demo.php tạo để kiểm tra phân hệ HLV. Không phải huấn luyện viên có thật.',
        'approach' => 'Ví dụ cho phần phương pháp: tăng tải từ từ, xem nhật ký mỗi tuần, nhắn tin khi học viên cần.',
        'availability' => 'Tối thứ 2 đến thứ 6 (ví dụ)',
    ],
    $coach2 => [
        'headline' => 'HLV thử nghiệm: tăng cơ và sức mạnh',
        'specialties' => ['HYPERTROPHY', 'STRENGTH', 'WOMEN'],
        'experience_years' => '3', 'max_clients' => '5',
        'certifications' => '',
        'bio' => 'Đây là hồ sơ THỬ NGHIỆM do tools/seed-coach-demo.php tạo để kiểm tra danh sách HLV và luồng duyệt học viên.',
        'approach' => 'Ví dụ cho phần phương pháp: chương trình 4 buổi mỗi tuần, ưu tiên bài đa khớp.',
        'availability' => 'Cuối tuần (ví dụ)',
    ],
];
foreach ($applications as $uid => $form) {
    $v = validateCoachApplication($form + ['agree_truth' => '1', 'agree_rules' => '1']);
    if ($v['errors']) {
        exit('Đơn mẫu không hợp lệ: ' . implode('; ', $v['errors']) . "\n");
    }
    $appId = coachSubmitApplication($pdo, $uid, $v['data']);
    coachApproveApplication($pdo, $appId, $adminId, 'Duyệt dữ liệu thử.');
}

// Học viên 1 đang tập với HLV 1
$rel1 = coachRequest($pdo, $user1, $coach1, 'Em mới tập, muốn giảm khoảng 5 kg. (lời nhắn thử)');
coachChangeRelation($pdo, 'coach', $coach1, $rel1, 'accept');
$pdo->prepare('UPDATE coach_clients SET created_at = ?, accepted_at = ? WHERE id = ?')
    ->execute([date('Y-m-d H:i:s', strtotime('-16 days')), date('Y-m-d H:i:s', strtotime('-15 days')), $rel1]);

$planId = createWorkoutPlanForUser($pdo, $user1, 3, 'BEGINNER', 'FULL_BODY');
coachMarkPlanAssigned($pdo, $coach1, $user1);

// Nhật ký 2 tuần gần nhất theo đúng bài trong lịch
$rows = $pdo->prepare('SELECT id, exercise_id, day_of_week, sets, reps FROM workout_plan_exercises WHERE workout_plan_id = ?');
$rows->execute([$planId]);
$insertLog = $pdo->prepare(
    'INSERT INTO workout_logs (user_id, workout_plan_exercise_id, exercise_id, log_date, sets_completed, reps_completed, weight_kg_used, note)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$lastDate = null;
foreach ($rows->fetchAll() as $row) {
    foreach ([2, 1] as $weeksAgo) {
        $date = date('Y-m-d', strtotime("monday this week -{$weeksAgo} week +" . ($row['day_of_week'] - 1) . ' day'));
        $insertLog->execute([$user1, $row['id'], $row['exercise_id'], $date, $row['sets'], $row['reps'], 20 + 2.5 * (2 - $weeksAgo),
            $weeksAgo === 1 ? 'Tăng tạ được (ghi chú thử)' : '']);
        $lastDate = max((string)$lastDate, $date);
    }
}

// Số đo 6 tuần
$insertMetric = $pdo->prepare('INSERT INTO body_metrics (user_id, recorded_date, weight_kg, waist_cm) VALUES (?, ?, ?, ?)');
for ($w = 6; $w >= 0; $w--) {
    $insertMetric->execute([$user1, date('Y-m-d', strtotime("-{$w} week")), 74.5 - 0.35 * (6 - $w), 88 - 0.4 * (6 - $w)]);
}

// Mục tiêu dinh dưỡng do HLV đặt: P 145 g, C 210 g, F 65 g → 4*145 + 4*210 + 9*65 = 2005 kcal
$macro = validateCoachMacros(145, 210, 65, 72.5);
coachSaveNutrition($pdo, $coach1, $user1, 2450, $macro['kcal'], 145, 210, 65, date('Y-m-d', strtotime('-3 days')));

// Nhận xét buổi tập gần nhất + 1 nhận xét chung
if ($lastDate !== null) {
    coachAddFeedback($pdo, $rel1, $user1, $lastDate, 'Nhận xét thử: buổi này tăng tạ đều, lần sau giữ lưng thẳng khi kéo.');
}
coachAddFeedback($pdo, $rel1, $user1, null, 'Nhận xét thử: tuần đầu đi tập đủ buổi, rất tốt.');

// Tin nhắn (chèn thẳng để không vướng giới hạn tốc độ gửi)
$insertMsg = $pdo->prepare('INSERT INTO coach_messages (coach_client_id, sender_id, content, read_at, created_at) VALUES (?, ?, ?, ?, ?)');
$conversation = [
    [$user1, 'Chào thầy, em tập xong buổi thứ Sáu rồi ạ. (tin thử)', true, '-2 days 19:05'],
    [$coach1, 'Tốt lắm. Squat tuần sau tăng 2,5 kg nhé. (tin thử)', true, '-2 days 20:12'],
    [$user1, 'Dạ, em hơi mỏi gối, có sao không ạ? (tin thử)', false, '-1 day 08:30'],
];
foreach ($conversation as [$sender, $text, $read, $when]) {
    $at = date('Y-m-d H:i:s', strtotime($when));
    $insertMsg->execute([$rel1, $sender, $text, $read ? $at : null, $at]);
}

// Học viên 2 chờ HLV 2 duyệt
coachRequest($pdo, $user2, $coach2, 'Em muốn tăng cơ tay và lưng. (lời nhắn thử)');

echo "Đã tạo DỮ LIỆU THỬ cho phân hệ HLV (mật khẩu chung " . SEED_PASSWORD . "):\n";
foreach ($emails as $i => $email) {
    echo sprintf("  %-28s %s (id %d)\n", $email, $i === 0 ? 'ADMIN' : ($i <= 2 ? 'COACH' : 'USER'), $ids[$i]);
}
