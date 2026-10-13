<?php
// public/api/workout-plan-edit.php  (B11)
// POST: action = update | replace | remove | add  -> JSON.
// user_id lấy từ $_SESSION; mọi thao tác chỉ tác động tới lịch ACTIVE của chính user đó.
session_start();
header('Content-Type: application/json; charset=utf-8');

function jsonResponse(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Chỉ chấp nhận POST.']);
}
if (empty($_SESSION['user_id'])) {
    jsonResponse(401, ['success' => false, 'message' => 'Bạn cần đăng nhập.']);
}
$userId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/workout-plan-db.php';

/** Đọc số nguyên từ $_POST; thiếu hoặc sai kiểu -> 0 (các hàm bên dưới sẽ từ chối giá trị 0). */
function postInt(string $key): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    return $value === false ? 0 : $value;
}

$action = $_POST['action'] ?? '';
if (!is_string($action) || !in_array($action, ['update', 'replace', 'remove', 'add'], true)) {
    jsonResponse(422, ['success' => false, 'message' => 'Thao tác không hợp lệ.']);
}

try {
    switch ($action) {
        case 'update':
            updatePlanExerciseVolume($pdo, $userId, postInt('row_id'), postInt('sets'), postInt('reps'));
            break;
        case 'replace':
            replacePlanExercise($pdo, $userId, postInt('row_id'), postInt('new_exercise_id'));
            break;
        case 'remove':
            removePlanExercise($pdo, $userId, postInt('row_id'));
            break;
        case 'add':
            addPlanExercise($pdo, $userId, postInt('day_of_week'), postInt('exercise_id'), postInt('sets'), postInt('reps'));
            break;
    }
    jsonResponse(200, ['success' => true]);
} catch (InvalidArgumentException $e) {
    jsonResponse(422, ['success' => false, 'message' => $e->getMessage()]);
} catch (PlanNotFoundException $e) {
    jsonResponse(404, ['success' => false, 'message' => $e->getMessage()]);
} catch (RuntimeException $e) {
    jsonResponse(409, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('workout-plan-edit: ' . $e->getMessage());
    jsonResponse(500, ['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại.']);
}