<?php
/**
 * tools/seed-experts.php — tạo sẵn 3 chuyên gia sức khỏe MẪU để demo trang Tư vấn sức khỏe (A-55).
 *
 * Chạy ở gốc repo (sau khi database đã có sql/health_experts.sql):
 *     C:\xampp\php\php.exe tools/seed-experts.php
 *
 * - Đây là HỒ SƠ MẪU, không phải bác sĩ / chuyên gia có thật: tên do nhóm đặt, nơi công tác và số chứng chỉ ghi rõ "hồ sơ mẫu",
 *   KHÔNG có file chứng chỉ nên trang Tư vấn hiện nhãn "Hồ sơ mẫu, chưa kiểm tra chứng chỉ" (không gắn nhãn đã xác minh).
 * - Không đi qua expertSubmitApplication (hàm đó bắt buộc file chứng chỉ thật): ghi thẳng 1 đơn APPROVED để còn dấu vết trong
 *   expert_applications, rồi tạo hồ sơ và đổi vai trò thành EXPERT. ADMIN có id nhỏ nhất được ghi là người duyệt.
 * - Chạy lại an toàn: email đã có thì chỉ cập nhật hồ sơ, không tạo trùng.
 * - Đăng nhập: email bên dưới, mật khẩu chung SEED_PASSWORD.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy từ dòng lệnh.');
}

require_once __DIR__ . '/../public/includes/db.php';
require_once __DIR__ . '/../public/includes/expert-db.php';

if (!expertTablesReady($pdo)) {
    exit("Database chưa có bảng tư vấn sức khỏe. Chạy trước: php tools/install.php --upgrade\n");
}
$adminId = (int)$pdo->query("SELECT id FROM users WHERE role = 'ADMIN' ORDER BY id LIMIT 1")->fetchColumn();
if ($adminId === 0) {
    exit("Chưa có tài khoản ADMIN nào. Hãy tạo một ADMIN trước.\n");
}

const SEED_PASSWORD = 'Expert@12345';
const SAMPLE = 'Hồ sơ mẫu do nhóm GymSmart tạo để demo, không phải chuyên gia có thật và chưa có chứng chỉ được kiểm tra.';

// [email, họ tên, ngành, chức danh, năm, chủ đề, giới thiệu]
$experts = [
    ['cg.quocbao@gymsmart.test', 'Đặng Quốc Bảo', 'DOCTOR', 'Bác sĩ Y học thể thao', 9, 'INJURY,CONDITION,RECOVERY',
        'Tư vấn chấn thương khi tập (gối, vai, lưng), tập luyện an toàn khi có bệnh nền như tăng huyết áp, tiểu đường, và khi nào cần đi khám. ' . SAMPLE],
    ['cg.mailinh@gymsmart.test', 'Hoàng Mai Linh', 'NUTRITIONIST', 'Chuyên gia dinh dưỡng thể thao', 6, 'NUTRITION,WOMEN',
        'Tư vấn chế độ ăn để giảm mỡ, tăng cơ, ăn quanh buổi tập, dinh dưỡng cho phụ nữ tập luyện, mang thai và sau sinh. ' . SAMPLE],
    ['cg.thanhtung@gymsmart.test', 'Bùi Thanh Tùng', 'PHYSIO', 'Kỹ thuật viên vật lý trị liệu', 7, 'INJURY,RECOVERY',
        'Tư vấn phục hồi sau chấn thương, bài tập giảm đau lưng, vai gáy do ngồi nhiều, giãn cơ và phục hồi giữa các buổi tập. ' . SAMPLE],
];

$hash = password_hash(SEED_PASSWORD, PASSWORD_DEFAULT);
$find = $pdo->prepare('SELECT id, role FROM users WHERE email = ?');
$now = coachNow();
echo "Chuyên gia mẫu (mật khẩu chung " . SEED_PASSWORD . "):\n";
foreach ($experts as $i => [$email, $name, $profession, $title, $years, $topics, $bio]) {
    $workplace = 'Hồ sơ mẫu (demo), không phải cơ sở y tế thật';
    $license = 'MAU-DEMO-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
    $find->execute([$email]);
    $row = $find->fetch(PDO::FETCH_ASSOC);
    if ($row !== false && !in_array($row['role'], ['USER', 'EXPERT'], true)) {
        echo sprintf("  %-28s BỎ QUA: email đã thuộc tài khoản %s\n", $email, $row['role']);
        continue;
    }
    coachTx($pdo, function () use ($pdo, $row, $email, $name, $profession, $title, $years, $topics, $bio, $workplace, $license, $hash, $adminId, $now, &$status) {
        if ($row === false) {
            $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'EXPERT')")->execute([$name, $email, $hash]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO expert_applications (user_id, profession, title, workplace, license_no, experience_years, topics, bio, certificate_file, status, admin_note, reviewed_by, reviewed_at, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 'APPROVED', ?, ?, ?, ?)")
                ->execute([$id, $profession, $title, $workplace, $license, $years, $topics, $bio, 'Hồ sơ mẫu tạo bằng tools/seed-experts.php, không có chứng chỉ.', $adminId, $now, $now]);
            $pdo->prepare('INSERT INTO expert_profiles (user_id, profession, title, workplace, license_no, experience_years, topics, bio, accepting, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)')
                ->execute([$id, $profession, $title, $workplace, $license, $years, $topics, $bio, $now, $now]);
            $status = 'tạo mới';
        } else {
            $id = (int)$row['id'];
            $pdo->prepare("UPDATE users SET full_name = ?, role = 'EXPERT' WHERE id = ?")->execute([$name, $id]);
            $pdo->prepare('DELETE FROM expert_profiles WHERE user_id = ?')->execute([$id]);
            $pdo->prepare('INSERT INTO expert_profiles (user_id, profession, title, workplace, license_no, experience_years, topics, bio, accepting, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)')
                ->execute([$id, $profession, $title, $workplace, $license, $years, $topics, $bio, $now, $now]);
            $status = 'đã có, cập nhật hồ sơ';
        }
    });
    echo sprintf("  %-28s %-16s %-22s %s  (%s)\n", $email, $name, EXPERT_PROFESSIONS[$profession], implode(', ', healthTopicLabels($topics)), $status);
}
echo "Xong. Xem ở trang /consult.php\n";
