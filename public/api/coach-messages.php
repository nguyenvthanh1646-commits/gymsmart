<?php
/**
 * api/coach-messages.php — đọc tin nhắn giữa HLV và học viên (khung chat gọi định kỳ khi tab đang mở).
 *
 *   GET /api/coach-messages.php?with=12            → tối đa 100 tin gần nhất
 *   GET /api/coach-messages.php?with=12&after=345  → chỉ tin có id > 345 (tin mới)
 * "with" là id người bên kia. Máy chủ tự tìm quan hệ ACTIVE giữa người đang đăng nhập và người đó:
 *   - người đăng nhập là HLV của "with"  → coachActiveRelationId(me, with)
 *   - người đăng nhập là học viên của "with" → coachActiveRelationId(with, me) (kiểm tra luôn "with" vẫn là COACH)
 * Không có quan hệ ACTIVE → 404 (không phân biệt "không tồn tại" hay "không có quyền", để khỏi lộ thông tin).
 * Đọc xong thì đánh dấu đã đọc các tin của bên kia.
 *
 * Thành công: { "success": true, "data": { "messages": [ { "id", "mine", "content", "at", "label" } ], "unreadMarked": n } }
 * Lỗi: { "success": false, "error": "..." } với mã 400/401/404/405/500 (theo includes/api.php).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';

apiBoot();
$me = (int)$_SESSION['user_id'];
$with  = filter_var($_GET['with'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$after = filter_var($_GET['after'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($with === false || $after === false) {
    apiError(400, 'Tham số không hợp lệ.');
}
if (!coachTablesReady($pdo)) {
    apiError(404, 'Tính năng nhắn tin chưa được bật.');
}
$relationId = coachActiveRelationId($pdo, $me, $with) ?? coachActiveRelationId($pdo, $with, $me);
if ($relationId === null) {
    apiError(404, 'Cuộc trò chuyện không còn hoạt động. Quan hệ huấn luyện có thể đã kết thúc.');
}

$out = [];
foreach (coachMessages($pdo, $relationId, $after) as $m) {
    $out[] = [
        'id'      => (int)$m['id'],
        'mine'    => (int)$m['sender_id'] === $me,
        'content' => $m['content'],                  // chữ thô: JavaScript gán bằng textContent, không dùng innerHTML
        'at'      => $m['created_at'],
        'label'   => coachTimeAgo($m['created_at']),
    ];
}
coachMarkMessagesRead($pdo, $relationId, $me);
apiJson(200, ['success' => true, 'data' => ['messages' => $out]]);
