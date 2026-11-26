<?php
/**
 * notifications.php — trang "Thông báo": toàn bộ thông báo của chính mình, 20 cái mỗi trang (A-44).
 * Chạy được cả khi tắt JavaScript: mỗi thông báo là một form POST nhỏ (đánh dấu đã đọc rồi chuyển tới trang liên quan),
 * có nút "Đánh dấu đã đọc tất cả". Chuông trên thanh menu dẫn tới trang này khi không có JS.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';

$userId = (int)$_SESSION['user_id'];
const NOTIF_PAGE_SIZE = 20;

// POST: mở một thông báo (đánh dấu đã đọc rồi chuyển trang) hoặc đánh dấu đã đọc tất cả. Post/Redirect/Get.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    try {
        if (($_POST['action'] ?? '') === 'read_all') {
            notifMarkAllRead($pdo, $userId);
            header('Location: /notifications.php');
            exit;
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $link = $id === false ? null : notifMarkRead($pdo, $userId, $id);
        header('Location: ' . ($link ?: '/notifications.php'));   // link đã qua notifSafeLink: chỉ đường dẫn nội bộ
        exit;
    } catch (PDOException $e) {
        error_log('notifications.php: ' . $e->getMessage());
        header('Location: /notifications.php');
        exit;
    }
}

$page = max(1, (int)filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
$loadError = false;
try {
    $total = notifCount($pdo, $userId);
    $unread = notifUnreadCount($pdo, $userId);
    $pages = max(1, (int)ceil($total / NOTIF_PAGE_SIZE));
    $page = min($page, $pages);
    $items = notifList($pdo, $userId, NOTIF_PAGE_SIZE, ($page - 1) * NOTIF_PAGE_SIZE);
} catch (PDOException $e) {
    error_log('notifications.php: ' . $e->getMessage());
    $loadError = true;
    $items = [];
    $total = $unread = 0;
    $pages = 1;
}
$ago = function (string $at): string {
    $s = time() - strtotime($at);
    if ($s < 60) { return 'vừa xong'; }
    if ($s < 3600) { return floor($s / 60) . ' phút trước'; }
    if ($s < 86400) { return floor($s / 3600) . ' giờ trước'; }
    return date('d/m/Y H:i', strtotime($at));
};

$pageTitle = 'Thông báo';

include __DIR__ . '/includes/header.php';
?>
<div class="ntp">
    <div class="ntp-head">
        <div>
            <h1>Thông báo</h1>
            <p class="ntp-sub"><?= $unread > 0 ? (int)$unread . ' thông báo chưa đọc' : 'Bạn đã đọc hết thông báo.' ?></p>
        </div>
        <?php if ($unread > 0): ?>
            <form method="post" action="/notifications.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="read_all">
                <button type="submit" class="btn btn--ghost">Đánh dấu đã đọc tất cả</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($loadError): ?>
        <div class="ntp-empty"><p class="ntp-empty__title">Chưa tải được thông báo</p><p>Có lỗi khi đọc dữ liệu. Bạn thử tải lại trang.</p></div>
    <?php elseif ($items === []): ?>
        <div class="ntp-empty">
            <p class="ntp-empty__title">Chưa có thông báo nào</p>
            <p>Khi HLV nhận xét, nhắn tin hoặc đơn đăng ký HLV có kết quả, thông báo sẽ hiện ở đây và trên biểu tượng chuông.</p>
        </div>
    <?php else: ?>
        <ul class="ntp-list">
            <?php foreach ($items as $n): ?>
                <li>
                    <form method="post" action="/notifications.php" class="ntp-item<?= $n['read'] ? '' : ' is-unread' ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button type="submit">
                            <span class="ntp-item__dot" aria-hidden="true"></span>
                            <span class="ntp-item__txt">
                                <b><?= htmlspecialchars($n['title']) ?><?php if (!$n['read']): ?><span class="ntp-sr"> (chưa đọc)</span><?php endif; ?></b>
                                <?php if ($n['body'] !== ''): ?><span><?= htmlspecialchars($n['body']) ?></span><?php endif; ?>
                                <small><?= htmlspecialchars($ago($n['created_at'])) ?></small>
                            </span>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($pages > 1): ?>
            <nav class="ntp-pager" aria-label="Phân trang">
                <span>Trang <?= (int)$page ?> / <?= (int)$pages ?></span>
                <?php if ($page > 1): ?><a href="/notifications.php?page=<?= (int)$page - 1 ?>">&larr; Mới hơn</a><?php endif; ?>
                <?php if ($page < $pages): ?><a href="/notifications.php?page=<?= (int)$page + 1 ?>">Cũ hơn &rarr;</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
