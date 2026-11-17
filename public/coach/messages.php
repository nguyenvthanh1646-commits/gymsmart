<?php
/**
 * coach/messages.php — tin nhắn của HLV: danh sách học viên ĐANG TẬP (kèm số tin chưa đọc) + khung chat.
 *   ?id=... mở cuộc trò chuyện với học viên đó; phải là học viên ACTIVE của HLV (requireCoachClient), sai thì 404.
 * Không có ?id thì chỉ hiện danh sách (trên máy tính tự mở học viên đầu tiên có tin chưa đọc, hoặc người đầu tiên).
 * Nội dung tin do js/coach-chat.js tải qua api/coach-messages.php.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';

requireCoach();
$coachId = (int)$_SESSION['user_id'];
$clients = coachActiveClients($pdo, $coachId);

$openId = 0;
if (isset($_GET['id'])) {
    [$openId] = requireCoachClient($pdo, $_GET['id']);
}
$open = null;
foreach ($clients as $c) {
    if ((int)$c['client_id'] === $openId) {
        $open = $c;
    }
}

$pageTitle = 'Tin nhắn';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$coachNavActive = 'messages';
include __DIR__ . '/../includes/coach-nav.php';
?>
<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Tin nhắn</h1>
            <p class="adm-sub">Chỉ nhắn được với học viên đang tập với bạn. Tin mới tự cập nhật mỗi 15 giây khi trang đang mở.</p>
        </div>
    </div>

    <?php if (!$clients): ?>
        <div class="ch-empty">
            <div class="ch-empty__ico" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z"/></svg>
            </div>
            <h2>Chưa có cuộc trò chuyện</h2>
            <p>Khi bạn nhận học viên, cuộc trò chuyện với từng người sẽ hiện ở đây.</p>
            <a class="adm-btn" href="/coach/dashboard.php">Xem yêu cầu chờ duyệt</a>
        </div>
    <?php else: ?>
        <div class="cm<?= $open ? ' has-chat' : '' ?>">
            <ul class="cm-list" aria-label="Học viên">
                <?php foreach ($clients as $c): ?>
                    <li><a href="/coach/messages.php?id=<?= (int)$c['client_id'] ?>"<?= $open && (int)$c['client_id'] === $openId ? ' aria-current="page"' : '' ?>>
                        <?= coachAvatar($c['avatar'], $c['full_name'], 40) ?>
                        <span><b><?= htmlspecialchars($c['full_name']) ?></b><small><?= htmlspecialchars(COACH_GOAL_LABELS[$c['goal']] ?? '') ?></small></span>
                        <?php if ((int)$c['unread'] > 0): ?><span class="ch-dot" aria-label="<?= (int)$c['unread'] ?> tin chưa đọc"><?= (int)$c['unread'] ?></span><?php endif; ?>
                    </a></li>
                <?php endforeach; ?>
            </ul>
            <div class="cm-chat">
                <?php if ($open): ?>
                    <a class="adm-btn adm-btn-ghost adm-btn-sm cm-back" href="/coach/messages.php">&larr; Danh sách</a>
                    <?= coachChatBox($openId, $open['full_name'], $open['avatar'], 'Học viên · ' . (COACH_GOAL_LABELS[$open['goal']] ?? 'chưa đặt mục tiêu')) ?>
                    <p class="adm-hint"><a href="/coach/client.php?id=<?= $openId ?>">Xem hồ sơ và nhật ký của <?= htmlspecialchars($open['full_name']) ?></a></p>
                <?php else: ?>
                    <div class="ch-empty"><h2>Chọn một học viên</h2><p>Bấm vào tên học viên ở danh sách để mở cuộc trò chuyện.</p></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php if ($open): ?><script src="/js/coach-chat.js"></script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
