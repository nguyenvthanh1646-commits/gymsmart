<?php
/**
 * dash-coach.php — dòng thông báo nhỏ về HLV trên trang chủ của học viên (home-user.php include file này).
 * Hiện khi: đang chờ HLV duyệt, hoặc đang có HLV và có tin nhắn / nhận xét chưa đọc / yêu cầu sửa vừa được trả lời.
 * Không có HLV hoặc chưa import bảng HLV thì không in gì. Dùng biến $pdo của index.php.
 */
require_once __DIR__ . '/coach-db.php';

$dcNotice = coachClientNotice($pdo, (int)$_SESSION['user_id']);
if ($dcNotice === null) {
    return;
}
$dcRel = $dcNotice['relation'];
$dcParts = [];
if ($dcNotice['messages'] > 0) {
    $dcParts[] = '<a href="/my-coach.php?tab=messages">' . (int)$dcNotice['messages'] . ' tin nhắn mới</a>';
}
if ($dcNotice['feedback'] > 0) {
    $dcParts[] = '<a href="/my-coach.php?tab=feedback">' . (int)$dcNotice['feedback'] . ' nhận xét mới</a>';
}
if ($dcNotice['replies'] > 0) {
    $dcParts[] = '<a href="/my-coach.php">HLV đã trả lời yêu cầu sửa</a>';
}
if ($dcRel['status'] === 'ACTIVE' && !$dcParts) {
    return;   // có HLV nhưng không có gì mới: không làm rối trang chủ
}
?>
<p class="du-notice du-notice--coach" role="status">
    <?php if ($dcRel['status'] === 'PENDING'): ?>
        <b>Đang chờ HLV <?= htmlspecialchars($dcRel['coach_name']) ?> duyệt.</b> <a href="/my-coach.php">Xem yêu cầu</a>
    <?php else: ?>
        <b>HLV <?= htmlspecialchars($dcRel['coach_name']) ?>:</b> <?= implode(' · ', $dcParts) /* audit:ok các phần tử ghép từ số nguyên (int) và chữ viết sẵn */ ?>
    <?php endif; ?>
</p>
