<?php
/**
 * api/coach-message-send.php — gửi một tin nhắn giữa HLV và học viên.
 *
 * POST (form): with = id người nhận, content = nội dung (1-1000 ký tự), csrf_token
 * Trả về (kiểu POST cũ của dự án): { "success": true, "item": { "id", "mine", "content", "at", "label" } }
 *                                   { "success": false, "message": "..." }  mã 400/401/403/404/405/422/429/500
 * Quy tắc ở máy chủ: phải có quan hệ ACTIVE với người nhận (giống api/coach-messages.php), độ dài 1-1000 ký tự,
 * tối thiểu 2 giây giữa 2 tin và tối đa 60 tin/giờ cho mỗi người (coachSendMessage → coachMessageRateError).
 * sender_id luôn là người đang đăng nhập, không nhận từ form.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

ini_set('display_errors', '0');
function sendJson(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sendJson(405, ['success' => false, 'message' => 'Chỉ chấp nhận POST.']);
}
if (empty($_SESSION['user_id'])) {
    sendJson(401, ['success' => false, 'message' => 'Bạn cần đăng nhập lại.']);
}
if (!verifyCsrf()) {
    sendJson(403, ['success' => false, 'message' => 'Phiên làm việc đã hết hạn, hãy tải lại trang.']);
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';

$me = (int)$_SESSION['user_id'];
$with = filter_var($_POST['with'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($with === false) {
    sendJson(400, ['success' => false, 'message' => 'Người nhận không hợp lệ.']);
}
try {
    if (!coachTablesReady($pdo)) {
        sendJson(404, ['success' => false, 'message' => 'Tính năng nhắn tin chưa được bật.']);
    }
    $relationId = coachActiveRelationId($pdo, $me, $with) ?? coachActiveRelationId($pdo, $with, $me);
    if ($relationId === null) {
        sendJson(404, ['success' => false, 'message' => 'Không gửi được: quan hệ huấn luyện đã kết thúc hoặc không tồn tại.']);
    }
    $m = coachSendMessage($pdo, $relationId, $me, coachCleanText($_POST['content'] ?? ''));
    sendJson(200, ['success' => true, 'item' => [
        'id' => $m['id'], 'mine' => true, 'content' => $m['content'], 'at' => $m['created_at'], 'label' => coachTimeAgo($m['created_at']),
    ]]);
} catch (CoachRateLimitException $e) {
    sendJson(429, ['success' => false, 'message' => $e->getMessage()]);   // gửi quá nhanh / quá nhiều
} catch (CoachException $e) {
    sendJson(422, ['success' => false, 'message' => $e->getMessage()]);   // rỗng, quá dài...
} catch (Throwable $e) {
    error_log('coach-message-send: ' . $e->getMessage());
    sendJson(500, ['success' => false, 'message' => 'Lỗi máy chủ, thử lại sau.']);
}
