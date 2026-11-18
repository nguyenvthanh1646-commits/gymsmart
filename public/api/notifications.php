<?php
/**
 * api/notifications.php — dữ liệu cho chuông thông báo (A-44).
 *
 * GET                       → { "success": true, "data": { "unread": 3, "items": [ {id,type,title,body,link,read,created_at}, ... ] } }
 *                             (?count=1 chỉ trả "unread", dùng để cập nhật số trên chuông định kỳ)
 * POST action=read&id=5     → đánh dấu đã đọc 1 thông báo, trả { success, link }
 * POST action=read_all      → đánh dấu đã đọc tất cả, trả { success, updated }
 * POST là form kèm csrf_token. Lỗi: { "success": false, "error": "..." } mã 401/403/404/405/422/500.
 * user_id luôn lấy từ session, nên chỉ đọc / đánh dấu được thông báo của chính mình.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function notifOut(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'GET' && $method !== 'POST') {
    notifOut(405, ['success' => false, 'error' => 'Phương thức không hợp lệ.']);
}
if (empty($_SESSION['user_id'])) {
    notifOut(401, ['success' => false, 'error' => 'Bạn cần đăng nhập.']);
}
if ($method === 'POST' && !verifyCsrf()) {
    notifOut(403, ['success' => false, 'error' => 'Phiên làm việc đã hết hạn, hãy tải lại trang.']);
}
$userId = (int)$_SESSION['user_id'];
session_write_close();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notifications.php';

try {
    if ($method === 'GET') {
        $unread = notifUnreadCount($pdo, $userId);
        if (isset($_GET['count'])) {
            notifOut(200, ['success' => true, 'data' => ['unread' => $unread]]);
        }
        notifOut(200, ['success' => true, 'data' => ['unread' => $unread, 'items' => notifList($pdo, $userId)]]);
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'read') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $link = $id === false ? null : notifMarkRead($pdo, $userId, $id);
        if ($link === null) {
            notifOut(404, ['success' => false, 'error' => 'Không tìm thấy thông báo.']);
        }
        notifOut(200, ['success' => true, 'link' => $link]);
    }
    if ($action === 'read_all') {
        notifOut(200, ['success' => true, 'updated' => notifMarkAllRead($pdo, $userId)]);
    }
    notifOut(422, ['success' => false, 'error' => 'Thao tác không hợp lệ.']);
} catch (PDOException $e) {
    error_log('api/notifications: ' . $e->getMessage());   // thường do DB cũ chưa có bảng notifications
    notifOut(500, ['success' => false, 'error' => 'Chưa tải được thông báo.']);
}
