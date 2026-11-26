<?php
/**
 * tools/seed-coaches.php — tạo sẵn 5 HLV mẫu (3 nam, 2 nữ) kèm giá tập để website có danh sách HLV khi demo.
 *
 * Chạy ở gốc repo (sau khi database đã có sql/coach_features.sql và sql/coach_billing.sql):
 *     C:\xampp\php\php.exe tools/seed-coaches.php
 *
 * - Đây là HỒ SƠ MẪU, không phải huấn luyện viên có thật: tên do nhóm đặt, phần giới thiệu ghi rõ "hồ sơ mẫu",
 *   không ghi chứng chỉ, hướng dẫn thanh toán ghi rõ chưa có tài khoản nhận tiền thật.
 * - Đi đúng luồng thật: tạo tài khoản USER → nộp đơn (coachSubmitApplication) → ADMIN đầu tiên trong DB duyệt
 *   (coachApproveApplication) → lưu giá (coachSavePricing). Nên bảng coach_applications cũng có đơn tương ứng.
 * - Chạy lại an toàn: email đã có thì KHÔNG tạo lại, chỉ cập nhật hồ sơ và giá (không xóa học viên đang tập).
 * - Đăng nhập: email trong bảng dưới, mật khẩu chung SEED_PASSWORD.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy từ dòng lệnh.');
}

require_once __DIR__ . '/../public/includes/db.php';
require_once __DIR__ . '/../public/includes/coach-db.php';

if (!coachTablesReady($pdo) || !coachBillingReady($pdo)) {
    exit("Database chưa đủ bảng HLV / giá tập. Chạy trước: php tools/install.php --upgrade\n");
}
$adminId = (int)$pdo->query("SELECT id FROM users WHERE role = 'ADMIN' ORDER BY id LIMIT 1")->fetchColumn();
if ($adminId === 0) {
    exit("Chưa có tài khoản ADMIN nào để duyệt đơn HLV. Hãy tạo một ADMIN trước.\n");
}

const SEED_PASSWORD = 'Coach@12345';
const SAMPLE_NOTE = 'Hồ sơ mẫu do nhóm GymSmart tạo để demo website, không phải huấn luyện viên có thật.';
const SAMPLE_PAYMENT = 'Hồ sơ mẫu: chưa có tài khoản nhận tiền thật. Đây chỉ là dữ liệu demo, vui lòng không chuyển tiền.';

// [email, họ tên, tuổi, cao, nặng, giới tính, đơn đăng ký, giá 1 buổi, giá 1 tháng, số buổi thử]
$coaches = [
    ['hlv.hoanglong@gymsmart.test', 'Nguyễn Hoàng Long', 32, 178, 80, 'MALE', [
        'headline' => 'HLV tăng cơ và sức mạnh',
        'specialties' => ['HYPERTROPHY', 'STRENGTH'], 'experience_years' => '8', 'max_clients' => '12',
        'bio' => 'Tập trung vào các bài đa khớp (squat, deadlift, đẩy ngực, kéo xà), tăng tạ đều theo nhật ký từng buổi. Phù hợp người đã tập cơ bản muốn tăng cơ, tăng sức mạnh. ' . SAMPLE_NOTE,
        'approach' => 'Lịch 4 buổi mỗi tuần, tăng tải kép: đạt đủ số lần lặp 2 buổi liền thì tăng tạ. Xem nhật ký và nhận xét sau mỗi buổi.',
        'availability' => 'Sáng thứ 2 đến thứ 7',
    ], 400000, 3200000, 2],
    ['hlv.minhkhoa@gymsmart.test', 'Trần Minh Khoa', 27, 172, 68, 'MALE', [
        'headline' => 'HLV giảm mỡ cho người mới bắt đầu',
        'specialties' => ['FAT_LOSS', 'BEGINNER'], 'experience_years' => '4', 'max_clients' => '15',
        'bio' => 'Hướng dẫn người mới làm quen phòng tập: kỹ thuật cơ bản, lịch tập toàn thân 3 buổi mỗi tuần, ăn đủ đạm và thâm hụt calo vừa phải. ' . SAMPLE_NOTE,
        'approach' => 'Bắt đầu nhẹ, ưu tiên đúng kỹ thuật, theo dõi cân nặng và vòng eo mỗi tuần rồi chỉnh calo dần.',
        'availability' => 'Tối thứ 2 đến thứ 6',
    ], 250000, 1800000, 3],
    ['hlv.ducthinh@gymsmart.test', 'Lê Đức Thịnh', 35, 175, 72, 'MALE', [
        'headline' => 'HLV sức bền, cardio và phục hồi',
        'specialties' => ['ENDURANCE', 'MOBILITY'], 'experience_years' => '10', 'max_clients' => '10',
        'bio' => 'Kết hợp cardio (chạy, đạp xe, bài nhịp tim cao) với bài linh hoạt và phục hồi, dành cho người muốn khỏe hơn, bền hơn hoặc quay lại tập sau thời gian nghỉ. ' . SAMPLE_NOTE,
        'approach' => 'Tăng thời lượng cardio từ từ mỗi tuần, xen kẽ ngày giãn cơ và bài linh hoạt để hạn chế chấn thương.',
        'availability' => 'Sáng sớm các ngày trong tuần',
    ], 350000, null, 1],
    ['hlv.thuha@gymsmart.test', 'Phạm Thu Hà', 29, 160, 52, 'FEMALE', [
        'headline' => 'HLV tập luyện cho nữ, giảm mỡ và dinh dưỡng',
        'specialties' => ['WOMEN', 'FAT_LOSS', 'NUTRITION'], 'experience_years' => '6', 'max_clients' => '12',
        'bio' => 'Lịch tập cho nữ tập trung mông, đùi, lưng và cơ lõi, kèm hướng dẫn chia bữa và macro để giảm mỡ mà vẫn giữ cơ. ' . SAMPLE_NOTE,
        'approach' => 'Tập kháng lực 3-4 buổi mỗi tuần, đặt mục tiêu dinh dưỡng theo TDEE và điều chỉnh theo tiến độ hằng tuần.',
        'availability' => 'Chiều và tối, cả cuối tuần',
    ], 300000, 2400000, 2],
    ['hlv.ngocanh@gymsmart.test', 'Võ Ngọc Anh', 31, 163, 55, 'FEMALE', [
        'headline' => 'HLV linh hoạt, tư thế và người mới',
        'specialties' => ['MOBILITY', 'BEGINNER', 'WOMEN'], 'experience_years' => '5', 'max_clients' => '10',
        'bio' => 'Dành cho người ngồi nhiều, hay mỏi lưng vai gáy hoặc mới tập: bài tăng độ linh hoạt, cải thiện tư thế và xây nền sức mạnh cơ bản. ' . SAMPLE_NOTE,
        'approach' => 'Mỗi buổi gồm khởi động linh hoạt, bài sức mạnh nhẹ và giãn cơ cuối buổi. Tăng độ khó khi học viên làm chuẩn kỹ thuật.',
        'availability' => 'Tối thứ 3, 5, 7 và sáng Chủ nhật',
    ], null, 2000000, 3],
];

$hash = password_hash(SEED_PASSWORD, PASSWORD_DEFAULT);
$insertUser = $pdo->prepare(
    "INSERT INTO users (full_name, email, password_hash, role, age, height_cm, weight_kg, gender, activity_level, experience_level, goal)
     VALUES (?, ?, ?, 'USER', ?, ?, ?, ?, 'ACTIVE', 'ADVANCED', 'MAINTAIN')"
);
$findUser = $pdo->prepare('SELECT id, role FROM users WHERE email = ?');

$notifStart = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM notifications')->fetchColumn();
echo "HLV mẫu (mật khẩu chung " . SEED_PASSWORD . "):\n";
foreach ($coaches as [$email, $name, $age, $height, $weight, $gender, $form, $perSession, $perMonth, $trial]) {
    $v = validateCoachApplication($form + ['certifications' => '', 'agree_truth' => '1', 'agree_rules' => '1']);
    if ($v['errors']) {
        exit("Hồ sơ mẫu $name không hợp lệ: " . implode('; ', $v['errors']) . "\n");
    }
    $findUser->execute([$email]);
    $row = $findUser->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        $insertUser->execute([$name, $email, $hash, $age, $height, $weight, $gender]);
        $id = (int)$pdo->lastInsertId();
        $appId = coachSubmitApplication($pdo, $id, $v['data']);
        coachApproveApplication($pdo, $appId, $adminId, 'Tạo sẵn bằng tools/seed-coaches.php (hồ sơ mẫu).');
        $status = 'tạo mới';
    } elseif ($row['role'] === 'COACH') {
        $id = (int)$row['id'];
        coachSaveProfile($pdo, $id, $v['data'], true, $name);
        $status = 'đã có, cập nhật hồ sơ';
    } else {
        echo sprintf("  %-30s BỎ QUA: email đã thuộc tài khoản %s, không phải HLV\n", $email, $row['role']);
        continue;
    }
    coachSavePricing($pdo, $id, ['price_per_session' => $perSession, 'price_per_month' => $perMonth, 'trial_sessions' => $trial, 'payment_note' => SAMPLE_PAYMENT]);
    echo sprintf("  %-30s %-20s %-4s %-40s thử %d buổi  (%s)\n", $email, $name, $gender === 'MALE' ? 'Nam' : 'Nữ',
        coachPriceLine($perSession, $perMonth), $trial, $status);
}

// Thông báo "Đơn đăng ký HLV mới" gửi cho ADMIN trong lần chạy này: đánh dấu đã đọc vì đơn đã được duyệt luôn.
// Chỉ đụng các dòng tạo ra SAU mốc $notifStart, không đụng thông báo của đơn thật đang chờ.
$pdo->prepare("UPDATE notifications SET read_at = ? WHERE type = 'admin.coach_application' AND read_at IS NULL AND id > ?")
    ->execute([coachNow(), $notifStart]);
echo "Xong. Xem ở trang /coaches.php\n";
