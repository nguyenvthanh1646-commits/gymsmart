<?php
/**
 * api/chatbot-history.php — lịch sử hội thoại GymSmart AI đã lưu trên máy chủ (D1). Chỉ cho chế độ người tập.
 *
 * GET                         → { "success": true, "data": [ { "who": "me"|"bot", "text": "...", "at": 1763500000000 }, ... ] }
 *                               (cuộc trò chuyện mới nhất, tối đa 40 tin, cũ đến mới)
 * POST {"action":"new"}       → mở cuộc trò chuyện mới (cuộc cũ vẫn giữ, tối đa 5 cuộc)
 * POST {"action":"clear"}     → xóa TOÀN BỘ lịch sử chat của chính mình
 * POST gửi JSON, kèm header X-CSRF-Token như api/chatbot.php. Lỗi: { "success": false, "error": "..." } mã 401/403/405/422/500.
 * user_id luôn lấy từ session, không nhận từ request.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function historyOut(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'GET' && $method !== 'POST') {
    historyOut(405, ['success' => false, 'error' => 'Phương thức không hợp lệ.']);
}
if (empty($_SESSION['user_id'])) {
    historyOut(401, ['success' => false, 'error' => 'Bạn cần đăng nhập.']);
}
$userId = (int)$_SESSION['user_id'];
if ($method === 'POST') {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($sent === '' || !hash_equals(csrfToken(), $sent)) {
        historyOut(403, ['success' => false, 'error' => 'Phiên không hợp lệ, hãy tải lại trang.']);
    }
}
session_write_close();   // không cần ghi session nữa, nhả khóa cho các tab khác

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/chat-history.php';

try {
    if ($method === 'GET') {
        historyOut(200, ['success' => true, 'data' => chatHistoryLoad($pdo, $userId)]);
    }
    $body = json_decode((string)file_get_contents('php://input'), true);
    $action = is_array($body) ? ($body['action'] ?? '') : '';
    if ($action === 'new') {
        chatHistoryStartNew($pdo, $userId);
        historyOut(200, ['success' => true]);
    }
    if ($action === 'clear') {
        $n = chatHistoryClear($pdo, $userId);
        historyOut(200, ['success' => true, 'deleted' => $n]);
    }
    historyOut(422, ['success' => false, 'error' => 'Thao tác không hợp lệ.']);
} catch (PDOException $e) {
    // Thường do DB cũ thiếu bảng chatbot_*: khung chat vẫn chạy, chỉ là không lưu được lịch sử
    error_log('chatbot-history: ' . $e->getMessage());
    historyOut(500, ['success' => false, 'error' => 'Chưa lưu được lịch sử chat lúc này.']);
}
