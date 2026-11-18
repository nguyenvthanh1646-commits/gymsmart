<?php
/**
 * admin/export.php?type=users|exercises — tải dữ liệu về file CSV (mở được bằng Excel).
 * Chỉ ADMIN. Là thao tác chỉ ĐỌC nên dùng GET được.
 *
 * Điểm cần nhớ:
 *  - Cột mật khẩu (password_hash) KHÔNG bao giờ được xuất: câu SELECT liệt kê từng cột, không dùng SELECT *.
 *  - BOM UTF-8 và chống "CSV injection" (ô bắt đầu bằng = + - @) nằm trong includes/csv.php, dùng chung với export-my-data.php.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csv.php';

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


$rows = $pdo->query($exports[$type]['sql'])->fetchAll(); // audit:ok câu SQL lấy từ mảng $exports viết sẵn, không có dữ liệu người dùng

csvDownload($exports[$type]['file'] . '-' . date('Ymd'), $rows ? array_keys($rows[0]) : [], $rows);
