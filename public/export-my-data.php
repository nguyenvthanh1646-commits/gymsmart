<?php
/**
 * export-my-data.php?type=logs|metrics — người dùng tải nhật ký tập hoặc số đo CỦA CHÍNH MÌNH về file CSV (mở bằng Excel).
 * Chỉ đọc nên dùng GET. user_id lấy từ session, không nhận từ URL, nên không tải được dữ liệu người khác.
 * BOM UTF-8 và chống CSV injection nằm trong includes/csv.php.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/report-db.php';
require_once __DIR__ . '/includes/csv.php';
require_once __DIR__ . '/includes/error-page.php';

$userId = (int)$_SESSION['user_id'];
$type = $_GET['type'] ?? '';
$muscle = ['CHEST' => 'Ngực', 'BACK' => 'Lưng', 'SHOULDERS' => 'Vai', 'LEGS' => 'Chân', 'ARMS' => 'Tay', 'ABS' => 'Bụng', 'FULL_BODY' => 'Toàn thân'];

if ($type === 'logs') {
    $rows = array_map(fn($r) => [$r['log_date'], $r['name'], $muscle[$r['muscle_group']] ?? $r['muscle_group'],
        (int)$r['sets_completed'], (int)$r['reps_completed'], (float)$r['weight_kg_used'], (string)$r['note']],
        reportAllLogsForExport($pdo, $userId));
    csvDownload('gymsmart-nhat-ky-tap-' . date('Ymd'), ['Ngày', 'Bài tập', 'Nhóm cơ', 'Số set', 'Số lặp', 'Tạ (kg)', 'Ghi chú'], $rows);
}
if ($type === 'metrics') {
    $rows = array_map(fn($r) => [$r['recorded_date'], $r['weight_kg'], $r['body_fat_percent'], $r['chest_cm'], $r['waist_cm'], $r['arm_cm']],
        reportAllMetricsForExport($pdo, $userId));
    csvDownload('gymsmart-so-do-' . date('Ymd'), ['Ngày đo', 'Cân nặng (kg)', 'Mỡ (%)', 'Ngực (cm)', 'Eo (cm)', 'Tay (cm)'], $rows);
}
showError(400, 'Loại dữ liệu không hợp lệ', 'Chỉ tải được nhật ký tập hoặc số đo.', [['/report.php', 'Về trang Báo cáo']]);
