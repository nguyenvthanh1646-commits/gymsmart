<?php
// public/api/workout-plan-generate.php
// POST: days_per_week, level, split_type (tùy chọn)  ->  JSON. user_id lấy từ $_SESSION, KHÔNG nhận từ client.
require_once __DIR__ . '/../includes/auth.php';  // bật session với cookie an toàn (không gọi requireLogin: API trả JSON)
require_once __DIR__ . '/../includes/csrf.php';
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

// Chưa đăng nhập -> 401 (API trả JSON, không redirect)
if (empty($_SESSION['user_id'])) {
    jsonResponse(401, ['success' => false, 'message' => 'Bạn cần đăng nhập.']);
}
if (!verifyCsrf()) {
    jsonResponse(403, ['success' => false, 'message' => 'Phiên làm việc đã hết hạn, hãy tải lại trang.']);
}
$userId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/../includes/db.php';              // giả định tạo biến $pdo
require_once __DIR__ . '/../includes/workout-plan-db.php';
require_once __DIR__ . '/../includes/coach-db.php';

// Đang có HLV: học viên không tự tạo/sửa lịch (trừ khi HLV đã duyệt yêu cầu sửa còn hạn)
if ($lockMsg = coachPlanLockError($pdo, $userId)) {
    jsonResponse(403, ['success' => false, 'message' => $lockMsg]);
}

$daysPerWeek = filter_var($_POST['days_per_week'] ?? null, FILTER_VALIDATE_INT);
$level       = (string)($_POST['level'] ?? '');
// Kiểu chia (tùy chọn): rỗng = mặc định theo số ngày. Giá trị lạ bị generateWorkoutPlan từ chối (whitelist).
$splitType   = (string)($_POST['split_type'] ?? '');

try {
    $planId = createWorkoutPlanForUser($pdo, $userId, $daysPerWeek === false ? 0 : $daysPerWeek, $level, $splitType === '' ? null : $splitType);
    jsonResponse(200, ['success' => true, 'plan_id' => $planId]);
} catch (InvalidArgumentException $e) {
    jsonResponse(422, ['success' => false, 'message' => $e->getMessage()]);
} catch (RuntimeException $e) {
    jsonResponse(409, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('workout-plan-generate: ' . $e->getMessage());
    jsonResponse(500, ['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại.']);
}