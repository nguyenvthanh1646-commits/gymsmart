<?php
/**
 * coach/clients.php — danh sách học viên ĐANG TẬP với HLV đang đăng nhập (chỉ quan hệ ACTIVE).
 * Mỗi dòng: mục tiêu, trình độ, ngày tập gần nhất, tin chưa đọc, yêu cầu xin sửa; nút xem hồ sơ, nhắn tin, kết thúc.
 * Danh sách lấy theo coach_id = $_SESSION['user_id'] nên không thấy học viên của HLV khác.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';

requireCoach();
coachHandleCoachAction($pdo, '/coach/clients.php');

$coachId = (int)$_SESSION['user_id'];
$flash   = coachTakeFlash();
$clients = coachActiveClients($pdo, $coachId);
$since7  = date('Y-m-d', strtotime('-6 days'));

$pageTitle = 'Học viên';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$coachNavActive = 'clients';
include __DIR__ . '/../includes/coach-nav.php';
?>
<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Học viên</h1>
            <p class="adm-sub">Học viên đang tập với bạn. Kết thúc quan hệ thì bạn mất quyền xem dữ liệu của họ ngay.</p>
        </div>
        <a class="adm-btn adm-btn-ghost" href="/coach/dashboard.php">Yêu cầu chờ duyệt</a>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <div class="adm-panel">
        <?php if (!$clients): ?>
            <div class="ch-empty" style="border:0">
                <div class="ch-empty__ico" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.6 3.7-5.5 7-5.5s6.2 1.9 7 5.5"/></svg>
                </div>
                <h2>Chưa có học viên</h2>
                <p>Học viên gửi yêu cầu từ trang Huấn luyện viên; bạn duyệt ở bảng điều khiển. Hồ sơ đầy đủ giúp học viên dễ chọn bạn hơn.</p>
                <a class="adm-btn" href="/coach/profile.php">Hoàn thiện hồ sơ HLV</a>
            </div>
        <?php else: ?>
        <div class="adm-count">Đang có <?= count($clients) ?> học viên</div>
        <table class="adm-table">
            <thead><tr><th>Học viên</th><th>Mục tiêu</th><th>Tập gần nhất</th><th>Tin mới</th><th>Tập cùng từ</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): $idle = $c['last_log'] === null || $c['last_log'] < $since7; ?>
                <tr>
                    <td class="adm-cell-name">
                        <div class="ch-coach__head">
                            <?= coachAvatar($c['avatar'], $c['full_name'], 40) ?>
                            <div>
                                <a class="adm-link adm-name" href="/coach/client.php?id=<?= (int)$c['client_id'] ?>"><?= htmlspecialchars($c['full_name']) ?></a>
                                <?php if ((int)$c['change_requests'] > 0): ?><br><span class="ch-badge ch-badge--accent">Xin sửa lịch/ăn</span><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td data-label="Mục tiêu"><?= htmlspecialchars(COACH_GOAL_LABELS[$c['goal']] ?? 'Chưa đặt') ?> · <?= htmlspecialchars(COACH_LEVEL_LABELS[$c['experience_level']] ?? '—') ?></td>
                    <td data-label="Tập gần nhất">
                        <?= $c['last_log'] ? htmlspecialchars(coachDateLabel($c['last_log'], false)) : 'Chưa có' ?>
                        <?php if ($idle): ?> <span class="ch-badge ch-badge--warn">Chưa tập 7 ngày</span><?php endif; ?>
                    </td>
                    <td data-label="Tin mới"><?= (int)$c['unread'] > 0 ? '<span class="ch-badge ch-badge--info">' . (int)$c['unread'] . ' tin</span>' : '—' ?></td>
                    <td data-label="Tập cùng từ"><?= htmlspecialchars(coachDateLabel((string)$c['accepted_at'], false)) ?></td>
                    <td class="adm-cell-actions"><div class="adm-actions">
                        <a class="adm-btn adm-btn-sm" href="/coach/client.php?id=<?= (int)$c['client_id'] ?>">Hồ sơ</a>
                        <a class="adm-btn adm-btn-ghost adm-btn-sm" href="/coach/messages.php?id=<?= (int)$c['client_id'] ?>">Nhắn tin</a>
                        <form method="post" action="/coach/clients.php" data-confirm="Kết thúc quan hệ với <?= htmlspecialchars($c['full_name']) ?>? Bạn sẽ không xem được dữ liệu của học viên này nữa.">
                            <?= csrfField() ?><input type="hidden" name="action" value="end"><input type="hidden" name="relation_id" value="<?= (int)$c['relation_id'] ?>">
                            <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm" data-busy-text="Đang xử lý…">Kết thúc</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
