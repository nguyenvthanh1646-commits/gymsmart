<?php
/**
 * admin/users.php — quản lý người dùng.
 * Xem danh sách, tìm kiếm, lọc theo vai trò, đổi vai trò USER/ADMIN, xóa tài khoản, xuất CSV.
 * Cố ý KHÔNG hiển thị mật khẩu/hash và không cho admin xem hoặc đặt lại mật khẩu của người khác.
 * Hành động (POST) được xử lý trong includes/admin-users.php.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin-users.php';

requireAdmin();
handleUserAction($pdo, '/admin/users.php', '/admin/users.php');
$flash = takeFlash();
$selfId = (int)$_SESSION['user_id'];

$users = $pdo->query(
    "SELECT id, full_name, email, role, created_at,
            CASE WHEN age IS NOT NULL AND height_cm IS NOT NULL AND weight_kg IS NOT NULL
                  AND gender IS NOT NULL AND activity_level IS NOT NULL
                  AND experience_level IS NOT NULL AND goal IS NOT NULL
                 THEN 1 ELSE 0 END AS profile_done
     FROM users ORDER BY id DESC"
)->fetchAll();

$pageTitle = 'Quản lý người dùng';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$admNavActive = 'users';
include __DIR__ . '/../includes/admin-nav.php';
?>

<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Người dùng</h1>
            <p class="adm-sub">Xem tài khoản, đổi vai trò, xóa tài khoản.</p>
        </div>
        <a class="adm-btn adm-btn-ghost" href="/admin/export.php?type=users">Xuất CSV</a>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status">
            <?= htmlspecialchars($flash[1]) ?>
        </div>
    <?php endif; ?>

    <div class="adm-panel">
        <div class="adm-tools adm-tools--2">
            <input class="adm-input" type="search" id="adm-q" placeholder="Tìm theo tên hoặc email..." aria-label="Tìm người dùng">
            <select class="adm-select" id="adm-role" aria-label="Lọc theo vai trò">
                <option value="">Mọi vai trò</option>
                <option value="USER">USER</option>
                <option value="ADMIN">ADMIN</option>
            </select>
        </div>
        <div class="adm-count" id="adm-count">Tổng: <?= count($users) ?> tài khoản</div>

        <?php if (empty($users)): ?>
            <div class="adm-empty">Chưa có tài khoản nào.</div>
        <?php else: ?>
        <table class="adm-table">
            <thead>
                <tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Hồ sơ</th><th>Đăng ký</th><th></th></tr>
            </thead>
            <tbody id="adm-rows">
            <?php foreach ($users as $u): ?>
                <?php $isSelf = (int)$u['id'] === $selfId; ?>
                <tr data-search="<?= htmlspecialchars(mb_strtolower($u['full_name'] . ' ' . $u['email'])) ?>"
                    data-role="<?= htmlspecialchars($u['role']) ?>">
                    <td class="adm-cell-id adm-id">#<?= (int)$u['id'] ?></td>
                    <td class="adm-cell-name adm-name">
                        <a class="adm-link" href="/admin/user-detail.php?id=<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></a>
                        <?php if ($isSelf): ?><span class="adm-badge adm-self">Bạn</span><?php endif; ?>
                    </td>
                    <td data-label="Email"><?= htmlspecialchars($u['email']) ?></td>
                    <td data-label="Vai trò"><span class="adm-badge adm-b-<?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                    <td data-label="Hồ sơ">
                        <?php if ((int)$u['profile_done'] === 1): ?>
                            <span class="adm-badge adm-ok">Đủ</span>
                        <?php else: ?>
                            <span class="adm-badge adm-warn">Thiếu</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Đăng ký"><?= htmlspecialchars(substr((string)($u['created_at'] ?? ''), 0, 10)) ?></td>
                    <td class="adm-cell-actions">
                        <div class="adm-actions">
                            <a class="adm-btn adm-btn-ghost adm-btn-sm" href="/admin/user-detail.php?id=<?= (int)$u['id'] ?>">Chi tiết</a>
                            <?php if (!$isSelf): ?>
                                <button type="button" class="adm-btn adm-btn-ghost adm-btn-sm adm-act"
                                        data-action="set_role" data-id="<?= (int)$u['id'] ?>"
                                        data-name="<?= htmlspecialchars($u['full_name']) ?>"
                                        data-role="<?= $u['role'] === 'ADMIN' ? 'USER' : 'ADMIN' ?>">
                                    <?= $u['role'] === 'ADMIN' ? 'Hạ xuống USER' : 'Lên ADMIN' ?>
                                </button>
                                <?php if ($u['role'] !== 'ADMIN'): ?>
                                    <button type="button" class="adm-btn adm-btn-danger adm-btn-sm adm-act"
                                            data-action="delete" data-id="<?= (int)$u['id'] ?>"
                                            data-name="<?= htmlspecialchars($u['full_name']) ?>">Xóa</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="adm-empty" id="adm-none" hidden>Không có tài khoản nào khớp.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Hộp xác nhận dùng chung cho "đổi vai trò" và "xóa": JS điền nội dung trước khi mở -->
<dialog class="adm-dialog" id="adm-dialog">
    <h2 id="adm-dialog-title"></h2>
    <p id="adm-dialog-text"></p>
    <div class="adm-dialog-actions">
        <button type="button" class="adm-btn adm-btn-ghost" id="adm-cancel">Hủy</button>
        <form method="post" action="/admin/users.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="adm-dialog-action" value="">
            <input type="hidden" name="id" id="adm-dialog-id" value="">
            <input type="hidden" name="role" id="adm-dialog-role" value="">
            <button type="submit" class="adm-btn" id="adm-dialog-ok">Xác nhận</button>
        </form>
    </div>
</dialog>

<script>
(function () {
    function plain(s) {
        return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
    }
    var q = document.getElementById('adm-q');
    var role = document.getElementById('adm-role');
    var rows = document.querySelectorAll('#adm-rows tr');
    var count = document.getElementById('adm-count');
    var none = document.getElementById('adm-none');

    function applyFilter() {
        var text = plain(q.value.trim());
        var shown = 0;
        rows.forEach(function (tr) {
            var ok = plain(tr.dataset.search).indexOf(text) !== -1 && (!role.value || tr.dataset.role === role.value);
            tr.hidden = !ok;
            if (ok) shown++;
        });
        count.textContent = shown === rows.length ? 'Tổng: ' + rows.length + ' tài khoản'
                                                  : 'Hiển thị ' + shown + ' / ' + rows.length + ' tài khoản';
        if (none) none.hidden = shown !== 0;
    }
    q.addEventListener('input', applyFilter);
    role.addEventListener('input', applyFilter);

    // Hộp xác nhận
    var dialog = document.getElementById('adm-dialog');
    var ok = document.getElementById('adm-dialog-ok');
    document.querySelectorAll('.adm-act').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var del = btn.dataset.action === 'delete';
            document.getElementById('adm-dialog-action').value = btn.dataset.action;
            document.getElementById('adm-dialog-id').value = btn.dataset.id;
            document.getElementById('adm-dialog-role').value = btn.dataset.role || '';
            // textContent (không phải innerHTML): tên người dùng không thể chèn mã HTML vào hộp thoại
            document.getElementById('adm-dialog-title').textContent = del ? 'Xóa tài khoản?' : 'Đổi vai trò?';
            document.getElementById('adm-dialog-text').textContent = del
                ? 'Xóa "' + btn.dataset.name + '" cùng toàn bộ dữ liệu của họ (lịch tập, số đo...). Không hoàn tác được.'
                : 'Đổi vai trò của "' + btn.dataset.name + '" thành ' + btn.dataset.role + '.';
            ok.className = 'adm-btn' + (del ? ' adm-btn-solid-danger' : '');
            ok.textContent = del ? 'Xóa' : 'Đổi vai trò';
            dialog.showModal();
        });
    });
    document.getElementById('adm-cancel').addEventListener('click', function () { dialog.close(); });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
