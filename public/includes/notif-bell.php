<?php
/**
 * notif-bell.php — nút chuông thông báo (A-44). Include trong navbar.php (người dùng), coach-nav.php, admin-nav.php.
 * Không có JS: chuông là liên kết tới /notifications.php. Có JS (js/notifications.js): bấm mở khung danh sách,
 * bấm một thông báo thì đánh dấu đã đọc rồi chuyển trang, số trên chuông tự cập nhật mỗi phút khi tab đang mở.
 * Số chưa đọc lúc vẽ trang lấy từ DB; DB cũ chưa có bảng notifications thì hiện chuông không có số.
 */
if (!function_exists('isLoggedIn') || !isLoggedIn() || defined('GS_NOTIF_BELL')) {
    return;
}
define('GS_NOTIF_BELL', true);
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/notifications.php';

$bellUnread = 0;
try {
    $bellPdo = $GLOBALS['pdo'] ?? null;
    if (!$bellPdo instanceof PDO) {
        require_once __DIR__ . '/db.php';
        $bellPdo = $GLOBALS['pdo'] ?? ($pdo ?? null);
    }
    if ($bellPdo instanceof PDO) {
        $bellUnread = notifUnreadCount($bellPdo, (int)$_SESSION['user_id']);
    }
} catch (Throwable $e) {
    $bellUnread = 0;   // chưa import sql/notifications.sql
}
$bellLabel = $bellUnread > 0 ? "Thông báo, $bellUnread chưa đọc" : 'Thông báo';
?>
<div class="ntf" data-ntf data-csrf="<?= htmlspecialchars(csrfToken()) ?>">
    <a class="ntf__btn" href="/notifications.php" aria-label="<?= htmlspecialchars($bellLabel) ?>" aria-haspopup="true" aria-expanded="false" aria-controls="ntf-panel" data-ntf-btn>
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16zM10 20a2 2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span class="ntf__badge" data-ntf-badge<?= $bellUnread > 0 ? '' : ' hidden' ?>><?= $bellUnread > 99 ? '99+' : (int)$bellUnread ?></span>
    </a>
    <div class="ntf__panel" id="ntf-panel" role="dialog" aria-label="Thông báo" hidden data-ntf-panel>
        <div class="ntf__head">
            <b>Thông báo</b>
            <button type="button" class="ntf__all" data-ntf-all>Đánh dấu đã đọc tất cả</button>
        </div>
        <ul class="ntf__list" data-ntf-list aria-live="polite"></ul>
        <p class="ntf__state" data-ntf-state>Đang tải…</p>
        <a class="ntf__more" href="/notifications.php">Xem tất cả thông báo</a>
    </div>
</div>
<script src="/js/notifications.js" defer></script>
