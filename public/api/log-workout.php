<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận POST.']);
    exit;
}
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập.']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/workout-plan-db.php';

$userId = (int)$_SESSION['user_id'];
$planRowId = (int)($_POST['row_id'] ?? 0);
$exerciseId = (int)($_POST['exercise_id'] ?? 0);
$sets = (int)($_POST['sets'] ?? 0);
$reps = (int)($_POST['reps'] ?? 0);
$weight = (float)($_POST['weight'] ?? 0);

if ($sets <= 0 || $reps <= 0) {
    echo json_encode(['success' => false, 'message' => 'Số Set và Rep phải lớn hơn 0.']);
    exit;
}

try {
    saveWorkoutLog($pdo, $userId, $planRowId, $exerciseId, $sets, $reps, $weight);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('log-workout error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống khi lưu kết quả.']);
}