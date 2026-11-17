<?php
/**
 * coach/dashboard.php — trang chủ của huấn luyện viên.
 * 4 con số: học viên đang tập, yêu cầu chờ duyệt, học viên chưa tập 7 ngày, tin nhắn chưa đọc.
 * Duyệt / từ chối yêu cầu ngay tại đây (POST xử lý trong coachHandleCoachAction, có CSRF, ghi nhật ký).
 * Chỉ COACH vào được: requireCoach() đọc lại vai trò trong DB ở mỗi lần mở trang.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';

requireCoach();
coachHandleCoachAction($pdo, '/coach/dashboard.php');

$coachId = (int)$_SESSION['user_id'];
$today   = date('Y-m-d');
$flash   = coachTakeFlash();
$profile = coachProfile($pdo, $coachId);
$counts  = coachDashboardCounts($pdo, $coachId, $today);
$pending = coachPendingRequests($pdo, $coachId);
$clients = coachActiveClients($pdo, $coachId);
$seats   = $profile ? coachSeatsLeft((int)$profile['max_clients'], (int)$profile['active_clients']) : 0;
$since7  = date('Y-m-d', strtotime($today . ' -6 days'));
$attention = array_values(array_filter($clients, fn($c) => $c['last_log'] === null || $c['last_log'] < $since7 || (int)$c['unread'] > 0 || (int)$c['change_requests'] > 0));

$pageTitle = 'Khu huấn luyện viên';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$coachNavActive = 'dashboard';
include __DIR__ . '/../includes/coach-nav.php';

$ico = function (string $inner): string {
    return '<span class="adm-stat-ico"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg></span>';
};
?>
<div class="adm-wrap">
    <section class="adm-hero">
        <div>
            <span class="adm-hero__chip">Huấn luyện viên</span>
            <h1>Xin chào, <?= htmlspecialchars($_SESSION['full_name'] ?? 'HLV') ?></h1>
            <p><?= htmlspecialchars(coachDateLabel($today)) ?> ·
                <?php if (!$profile || (int)$profile['accepting'] !== 1): ?>Bạn đang tạm ngừng nhận học viên mới.<?php else: ?>Còn <?= (int)$seats ?> chỗ nhận học viên mới.<?php endif; ?></p>
        </div>
        <div class="adm-hero__cta">
            <a class="adm-btn" href="/coach/clients.php">Học viên của tôi</a>
            <a class="adm-btn adm-btn-light" href="/coach/profile.php">Sửa hồ sơ HLV</a>
        </div>
    </section>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <div class="adm-stats">
        <div class="adm-stat"><?= $ico('<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.7-3.4 3.3-5.2 6.5-5.2s5.8 1.8 6.5 5.2M16 4.8a3.5 3.5 0 010 6.4M18 14.8c2 .6 3.1 2.3 3.5 5.2"/>') ?><div><div class="adm-stat-num"><?= (int)$counts['clients'] ?></div><div class="adm-stat-label">Học viên đang tập</div></div></div>
        <div class="adm-stat <?= $counts['pending'] > 0 ? 'adm-stat-warn' : '' ?>"><?= $ico('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>') ?><div><div class="adm-stat-num"><?= (int)$counts['pending'] ?></div><div class="adm-stat-label">Yêu cầu chờ duyệt</div></div></div>
        <div class="adm-stat <?= $counts['inactive7'] > 0 ? 'adm-stat-warn' : '' ?>"><?= $ico('<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4M9 14l6 4M15 14l-6 4"/>') ?><div><div class="adm-stat-num"><?= (int)$counts['inactive7'] ?></div><div class="adm-stat-label">Chưa tập 7 ngày</div></div></div>
        <div class="adm-stat <?= $counts['unread'] > 0 ? 'adm-stat-warn' : '' ?>"><?= $ico('<path d="M4 5h16v11H8l-4 4z"/>') ?><div><div class="adm-stat-num"><?= (int)$counts['unread'] ?></div><div class="adm-stat-label">Tin nhắn chưa đọc</div></div></div>
    </div>

    <div class="adm-two">
        <section class="adm-panel adm-card-pad" aria-labelledby="h-pending">
            <h2 id="h-pending">Yêu cầu chờ duyệt</h2>
            <?php if (!$pending): ?>
                <p class="adm-sub">Không có yêu cầu nào. Học viên tìm thấy bạn ở trang Huấn luyện viên<?= ($profile && (int)$profile['accepting'] !== 1) ? ' khi bạn bật lại "Nhận học viên" trong hồ sơ' : '' ?>.</p>
            <?php else: ?>
                <ul class="ch-feed">
                    <?php foreach ($pending as $p): ?>
                        <li>
                            <div class="ch-coach__head">
                                <?= coachAvatar($p['avatar'], $p['full_name'], 44) ?>
                                <div>
                                    <b><?= htmlspecialchars($p['full_name']) ?></b>
                                    <div class="ch-small ch-muted">
                                        <?= $p['age'] ? (int)$p['age'] . ' tuổi · ' : '' ?><?= htmlspecialchars(COACH_GENDER_LABELS[$p['gender']] ?? '') ?>
                                        <?= $p['goal'] ? ' · ' . htmlspecialchars(COACH_GOAL_LABELS[$p['goal']] ?? $p['goal']) : '' ?>
                                        <?= $p['experience_level'] ? ' · ' . htmlspecialchars(COACH_LEVEL_LABELS[$p['experience_level']] ?? '') : '' ?>
                                        · gửi <?= htmlspecialchars(mb_strtolower(coachTimeAgo($p['created_at']))) ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($p['request_note'] !== ''): ?><div class="ch-quote"><?= htmlspecialchars($p['request_note']) ?></div><?php endif; ?>
                            <div class="adm-actions" style="margin-top:12px;justify-content:flex-start">
                                <form method="post" action="/coach/dashboard.php" data-confirm="Từ chối yêu cầu của <?= htmlspecialchars($p['full_name']) ?>?">
                                    <?= csrfField() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="relation_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="adm-btn adm-btn-ghost adm-btn-sm" data-busy-text="Đang xử lý…">Từ chối</button>
                                </form>
                                <form method="post" action="/coach/dashboard.php">
                                    <?= csrfField() ?><input type="hidden" name="action" value="accept"><input type="hidden" name="relation_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="adm-btn adm-btn-sm" data-busy-text="Đang xử lý…"<?= $seats === 0 ? ' disabled title="Đã đủ học viên"' : '' /* audit:ok chữ viết sẵn */ ?>>Nhận học viên</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($seats === 0): ?><p class="adm-hint">Bạn đã đủ học viên. Tăng số học viên tối đa trong <a href="/coach/profile.php">hồ sơ</a> để nhận thêm.</p><?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="adm-panel adm-card-pad" aria-labelledby="h-attention">
            <h2 id="h-attention">Cần chú ý</h2>
            <?php if (!$clients): ?>
                <p class="adm-sub">Bạn chưa có học viên nào. Duyệt yêu cầu bên trái để bắt đầu.</p>
            <?php elseif (!$attention): ?>
                <p class="adm-sub">Mọi học viên đều tập đều trong 7 ngày qua và không có tin chưa đọc. Tốt lắm!</p>
            <?php else: ?>
                <ul class="adm-list">
                    <?php foreach ($attention as $c): ?>
                        <li>
                            <span><a class="adm-link" href="/coach/client.php?id=<?= (int)$c['client_id'] ?>"><b><?= htmlspecialchars($c['full_name']) ?></b></a><br>
                                <small>
                                    <?= $c['last_log'] === null ? 'Chưa ghi buổi tập nào' : ($c['last_log'] < $since7 ? 'Tập lần cuối ' . htmlspecialchars(coachDateLabel($c['last_log'], false)) : 'Tập gần nhất ' . htmlspecialchars(coachDateLabel($c['last_log'], false))) ?>
                                </small></span>
                            <span style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
                                <?php if ($c['last_log'] === null || $c['last_log'] < $since7): ?><span class="ch-badge ch-badge--warn">Chưa tập 7 ngày</span><?php endif; ?>
                                <?php if ((int)$c['unread'] > 0): ?><span class="ch-badge ch-badge--info"><?= (int)$c['unread'] ?> tin mới</span><?php endif; ?>
                                <?php if ((int)$c['change_requests'] > 0): ?><span class="ch-badge ch-badge--accent">Xin sửa</span><?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
