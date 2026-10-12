<?php
/**
 * admin/users.php — danh sách người dùng (chỉ xem).
 * Admin xem ai đã đăng ký và hồ sơ của họ đã đủ chưa.
 * Cố ý KHÔNG hiển thị mật khẩu/hash và không cho sửa ở đây.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireAdmin();

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
            <p class="adm-sub">Danh sách tài khoản đã đăng ký (chỉ xem).</p>
        </div>
    </div>

    <div class="adm-panel">
        <div class="adm-tools" style="grid-template-columns: 1fr;">
            <input class="adm-input" type="search" id="adm-q" placeholder="Tìm theo tên hoặc email..." aria-label="Tìm người dùng">
        </div>
        <div class="adm-count" id="adm-count">Tổng: <?= count($users) ?> tài khoản</div>

        <?php if (empty($users)): ?>
            <div class="adm-empty">Chưa có tài khoản nào.</div>
        <?php else: ?>
        <table class="adm-table">
            <thead>
                <tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Hồ sơ</th><th>Ngày đăng ký</th></tr>
            </thead>
            <tbody id="adm-rows">
            <?php foreach ($users as $u): ?>
                <tr data-search="<?= htmlspecialchars(mb_strtolower($u['full_name'] . ' ' . $u['email'])) ?>">
                    <td class="adm-cell-id adm-id">#<?= (int)$u['id'] ?></td>
                    <td class="adm-cell-name adm-name"><?= htmlspecialchars($u['full_name']) ?></td>
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
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="adm-empty" id="adm-none" hidden>Không có tài khoản nào khớp.</div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    function plain(s) {
        return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
    }
    var q = document.getElementById('adm-q');
    var rows = document.querySelectorAll('#adm-rows tr');
    var count = document.getElementById('adm-count');
    var none = document.getElementById('adm-none');
    q.addEventListener('input', function () {
        var text = plain(q.value.trim());
        var shown = 0;
        rows.forEach(function (tr) {
            var ok = plain(tr.dataset.search).indexOf(text) !== -1;
            tr.hidden = !ok;
            if (ok) shown++;
        });
        count.textContent = shown === rows.length ? 'Tổng: ' + rows.length + ' tài khoản'
                                                  : 'Hiển thị ' + shown + ' / ' + rows.length + ' tài khoản';
        if (none) none.hidden = shown !== 0;
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>