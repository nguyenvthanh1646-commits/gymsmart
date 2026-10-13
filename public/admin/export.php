<?php
/**
 * admin/export.php?type=users|exercises — tải dữ liệu về file CSV (mở được bằng Excel).
 * Chỉ ADMIN. Là thao tác chỉ ĐỌC nên dùng GET được.
 *
 * Điểm cần nhớ:
 *  - Cột mật khẩu (password_hash) KHÔNG bao giờ được xuất: câu SELECT liệt kê từng cột, không dùng SELECT *.
 *  - Thêm BOM UTF-8 ở đầu file để Excel hiển thị đúng tiếng Việt.
 *  - Chống "CSV injection": ô bắt đầu bằng = + - @ bị Excel hiểu là công thức, nên thêm dấu ' phía trước.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireAdmin();

// Mỗi loại: tên file, câu SELECT cố định (viết sẵn, không ghép dữ liệu người dùng)
$exports = [
    'users' => [
        'file' => 'gymsmart-nguoi-dung',
        'sql'  => "SELECT id, full_name, email, role, age, height_cm, weight_kg, gender,
                          activity_level, experience_level, goal, created_at
                   FROM users ORDER BY id",
    ],
    'exercises' => [
        'file' => 'gymsmart-bai-tap',
        'sql'  => "SELECT id, name, type, muscle_group, difficulty, image_url, video_url
                   FROM exercises ORDER BY id",
    ],
];

$type = $_GET['type'] ?? '';
if (!is_string($type) || !isset($exports[$type])) {
    showError(400, 'Loại dữ liệu không hợp lệ', 'Chỉ xuất được danh sách người dùng hoặc bài tập.', [['/admin/dashboard.php', 'Về tổng quan']]);
}

/** Ô bắt đầu bằng ký tự công thức thì thêm dấu ' để Excel coi là chữ thường */
function csvSafe($value): string
{
    $text = (string)$value;
    if ($text !== '' && strpos("=+-@\t\r", $text[0]) !== false) {
        return "'" . $text;
    }
    return $text;
}

$rows = $pdo->query($exports[$type]['sql'])->fetchAll(); // audit:ok câu SQL lấy từ mảng $exports viết sẵn, không có dữ liệu người dùng

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $exports[$type]['file'] . '-' . date('Ymd') . '.csv"');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
if (!empty($rows)) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, array_map('csvSafe', array_values($row)));
    }
}
fclose($out);
