<?php
// public/api/export-workout-plan.php
// Xuất lịch tập ACTIVE của CHÍNH người dùng ra file CSV mở được bằng Excel.
require_once __DIR__ . '/../includes/auth.php'; // tự bật session với cookie an toàn
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/workout-plan-db.php';

requireLogin();
$userId = (int)$_SESSION['user_id'];

$plan = fetchActivePlan($pdo, $userId);
if ($plan === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bạn chưa có lịch tập nào để xuất.';
    exit;
}

/**
 * Chống "CSV injection": ô văn bản bắt đầu bằng = + - @ (hoặc tab/xuống dòng) có thể bị Excel chạy như công thức.
 * Thêm dấu ' phía trước để Excel hiểu là chữ.
 */
function csvSafe(string $text): string
{
    return preg_match('/^[=+\-@\t\r]/', $text) ? "'" . $text : $text;
}

$filename = 'Lich_Tap_' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, no-store');

echo "\xEF\xBB\xBF"; // BOM để Excel đọc đúng tiếng Việt (UTF-8)

$output = fopen('php://output', 'w');
// Tham số ',', '"', '' khai báo rõ ràng để PHP 8.4 không in cảnh báo deprecated làm hỏng file
$put = fn(array $fields) => fputcsv($output, $fields, ',', '"', '');

$put(['Ngày', 'STT', 'Tên bài tập', 'Nhóm cơ', 'Số set', 'Số lặp']);

$dayNames = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];
foreach ($plan['days'] as $dow => $rows) {
    $dayLabel = $dayNames[$dow] ?? ('Ngày ' . $dow);
    foreach (array_values($rows) as $i => $row) {
        $muscleLabel = WORKOUT_MUSCLE_LABELS[$row['muscle_group']] ?? $row['muscle_group'];
        $put([
            $dayLabel,
            $i + 1,
            csvSafe((string)$row['name']),
            $muscleLabel,
            (int)$row['sets'],
            (int)$row['reps'],
        ]);
    }
}

fclose($output);
exit;