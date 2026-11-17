<?php
/**
 * admin/coaches.php — danh sách huấn luyện viên: số học viên đang có / tối đa, yêu cầu chờ, trạng thái nhận học viên.
 * Thu hồi vai trò HLV: dùng chung handleUserAction() (action = revoke_coach) với trang người dùng, có hộp xác nhận
 * báo trước số học viên sẽ bị kết thúc. Cấp vai trò HLV KHÔNG làm ở đây mà qua duyệt đơn (coach-applications.php).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin-users.php';
require_once __DIR__ . '/../includes/coach.php';

requireAdmin();
$ready = coachTablesReady($pdo);
if ($ready) {
    handleUserAction($pdo, '/admin/coaches.php', '/admin/coaches.php');
}
$flash = takeFlash();
$coaches = $ready ? coachListForAdmin($pdo) : [];
$counts = coachAdminCounts($pdo);

$pageTitle = 'Huấn luyện viên';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
include __DIR__ . '/../includes/admin-style-extra.php';
$admNavActive = 'coaches';
include __DIR__ . '/../includes/admin-nav.php';
?>
<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Huấn luyện viên</h1>
            <p class="adm-sub">Người dùng trở thành HLV khi đơn đăng ký được duyệt. Thu hồi thì mọi học viên của HLV đó được kết thúc.</p>
        </div>
        <a class="adm-btn" href="/admin/coach-applications.php">Đơn đăng ký<?= $counts['pending_apps'] > 0 ? ' (' . (int)$counts['pending_apps'] . ' chờ duyệt)' : '' ?></a>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <?php if (!$ready): ?>
        <div class="adm-hint-box">Chưa có bảng của phân hệ HLV. Hãy import <b>sql/coach_features.sql</b> trong phpMyAdmin.</div>
    <?php else: ?>
    <div class="adm-stats" style="grid-template-columns:repeat(3,1fr)">
        <div class="adm-stat"><div><div class="adm-stat-num"><?= (int)$counts['coaches'] ?></div><div class="adm-stat-label">Huấn luyện viên</div></div></div>
        <div class="adm-stat"><div><div class="adm-stat-num"><?= (int)$counts['active_pairs'] ?></div><div class="adm-stat-label">Cặp HLV – học viên đang tập</div></div></div>
        <div class="adm-stat <?= $counts['pending_apps'] > 0 ? 'adm-stat-warn' : '' ?>"><div><div class="adm-stat-num"><?= (int)$counts['pending_apps'] ?></div><div class="adm-stat-label">Đơn chờ duyệt</div></div></div>
    </div>

    <div class="adm-panel">
        <?php if (!$coaches): ?>
            <div class="adm-empty">Chưa có huấn luyện viên nào. Khi bạn duyệt một đơn đăng ký, HLV sẽ xuất hiện ở đây.</div>
        <?php else: ?>
        <table class="adm-table">
            <thead><tr><th>Huấn luyện viên</th><th>Chuyên môn</th><th>Học viên</th><th>Chờ duyệt</th><th>Nhận học viên</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($coaches as $c): ?>
                <tr>
                    <td class="adm-cell-name adm-name">
                        <a class="adm-link" href="/admin/user-detail.php?id=<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></a><br>
                        <small class="adm-sub"><?= htmlspecialchars((string)($c['headline'] ?? 'Chưa có hồ sơ')) ?></small>
                    </td>
                    <td data-label="Chuyên môn"><?= htmlspecialchars(implode(', ', coachSpecialtyLabels((string)($c['specialties'] ?? '')))) ?: '—' ?></td>
                    <td data-label="Học viên" class="adm-mono"><?= (int)$c['active_clients'] ?> / <?= (int)($c['max_clients'] ?? 0) ?></td>
                    <td data-label="Chờ duyệt" class="adm-mono"><?= (int)$c['pending_clients'] ?></td>
                    <td data-label="Nhận học viên">
                        <?php if ((int)($c['accepting'] ?? 0) === 1): ?><span class="adm-badge adm-ok">Đang nhận</span><?php else: ?><span class="adm-badge adm-warn">Tạm ngừng</span><?php endif; ?>
                    </td>
                    <td class="adm-cell-actions"><div class="adm-actions">
                        <button type="button" class="adm-btn adm-btn-danger adm-btn-sm adm-revoke"
                                data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['full_name']) ?>"
                                data-active="<?= (int)$c['active_clients'] ?>" data-pending="<?= (int)$c['pending_clients'] ?>">Thu hồi HLV</button>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<dialog class="adm-dialog" id="adm-dialog" aria-labelledby="adm-dialog-title">
    <h2 id="adm-dialog-title">Thu hồi vai trò HLV?</h2>
    <p id="adm-dialog-text"></p>
    <div class="adm-dialog-actions">
        <button type="button" class="adm-btn adm-btn-ghost" id="adm-cancel">Hủy</button>
        <form method="post" action="/admin/coaches.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="revoke_coach">
            <input type="hidden" name="id" id="adm-dialog-id" value="">
            <button type="submit" class="adm-btn adm-btn-solid-danger">Thu hồi</button>
        </form>
    </div>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('adm-dialog');
    if (!dialog) return;
    var opener = null;
    document.querySelectorAll('.adm-revoke').forEach(function (btn) {
        btn.addEventListener('click', function () {
            opener = btn;
            document.getElementById('adm-dialog-id').value = btn.dataset.id;
            // textContent: tên không thể chèn mã HTML vào hộp thoại
            document.getElementById('adm-dialog-text').textContent =
                '"' + btn.dataset.name + '" sẽ trở lại thành người dùng thường. ' +
                btn.dataset.active + ' học viên đang tập sẽ bị kết thúc, ' + btn.dataset.pending + ' yêu cầu đang chờ bị từ chối. ' +
                'Lịch tập và mục tiêu đã giao vẫn ở lại với học viên.';
            dialog.showModal();
        });
    });
    document.getElementById('adm-cancel').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('close', function () { if (opener) opener.focus(); });   // trả focus về nút đã mở hộp thoại
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
