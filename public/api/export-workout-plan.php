<?php
// public/api/export-workout-plan.php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/workout-plan-db.php';

// Kiểm tra đăng nhập
requireLogin();
$userId = (int)$_SESSION['user_id'];

// Lấy dữ liệu lịch tập đang ACTIVE của user
$plan = fetchActivePlan($pdo, $userId);

if ($plan === null) {
    die('Bạn chưa có lịch tập nào để xuất.');
}

// Thiết lập header để ép trình duyệt tải file
$filename = 'Lich_Tap_' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Thêm ký tự BOM để Excel nhận diện đúng UTF-8 (không bị lỗi dấu tiếng Việt)
echo "\xEF\xBB\xBF"; 

$output = fopen('php://output', 'w');

// Ghi dòng tiêu đề
fputcsv($output, ['Ngày', 'Tên bài tập', 'Nhóm cơ', 'Số Set', 'Số Lặp']);

$dayNames = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];

// Duyệt qua từng ngày và từng bài tập để ghi vào file
foreach ($plan['days'] as $dow => $rows) {
    $dayLabel = $dayNames[$dow] ?? ('Ngày ' . $dow);
    foreach ($rows as $row) {
        $muscleLabel = WORKOUT_MUSCLE_LABELS[$row['muscle_group']] ?? $row['muscle_group'];
        fputcsv($output, [
            $dayLabel,
            $row['name'],
            $muscleLabel,
            $row['sets'],
            $row['reps']
        ]);
    }
}

fclose($output);
exit;