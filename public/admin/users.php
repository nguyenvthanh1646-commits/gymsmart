<?php
/**
 * admin/users.php — quản lý người dùng.
 * Xem danh sách, tìm kiếm, lọc theo vai trò, đổi vai trò USER/ADMIN, xóa tài khoản, xuất CSV.
 * Vai trò COACH không đổi ở đây: cấp qua duyệt đơn (coach-applications.php), thu hồi ở coaches.php.
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

// Tìm kiếm, lọc vai trò và phân trang làm ở máy chủ (GET ?q=&role=&page=), để trang không phải tải toàn bộ tài khoản
const USERS_PER_PAGE = 20;
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$roleFilter = in_array($_GET['role'] ?? '', ['USER', 'COACH', 'ADMIN'], true) ? $_GET['role'] : '';
$page = max(1, (int)filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));

$where = [];
$params = [];
if ($q !== '') {
    // Thoát ký tự đặc biệt của LIKE (%, _) để người dùng gõ "50%" không thành mẫu tìm kiếm
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(full_name LIKE ? OR email LIKE ?)';
    array_push($params, $like, $like);
}
if ($roleFilter !== '') {
    $where[] = 'role = ?';
    $params[] = $roleFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';   // chỉ ghép các đoạn cố định ở trên, giá trị đi qua dấu ?

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql"); // audit:ok
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$allUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$pages = max(1, (int)ceil($total / USERS_PER_PAGE));
$page = min($page, $pages);
$offset = ($page - 1) * USERS_PER_PAGE;

// LIMIT/OFFSET là số nguyên do ta tự tính (đã ép kiểu int), không đến từ chuỗi người dùng
$stmt = $pdo->prepare(
    "SELECT id, full_name, email, role, created_at,
            CASE WHEN age IS NOT NULL AND height_cm IS NOT NULL AND weight_kg IS NOT NULL
                  AND gender IS NOT NULL AND activity_level IS NOT NULL
                  AND experience_level IS NOT NULL AND goal IS NOT NULL
                 THEN 1 ELSE 0 END AS profile_done
     FROM users $whereSql ORDER BY id DESC LIMIT " . (int)USERS_PER_PAGE . ' OFFSET ' . (int)$offset // audit:ok
);
$stmt->execute($params);
$users = $stmt->fetchAll();
$filtering = $q !== '' || $roleFilter !== '';

/** Link sang trang khác nhưng giữ nguyên bộ lọc đang dùng. */
function usersPageLink(int $n, string $q, string $role): string
{
    return '/admin/users.php?' . http_build_query(array_filter(['q' => $q, 'role' => $role, 'page' => $n], fn($v) => $v !== '' && $v !== 1));
}

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
        <form class="adm-tools" method="get" action="/admin/users.php">
            <input class="adm-input" type="search" name="q" value="<?= htmlspecialchars($q) ?>" maxlength="100" placeholder="Tìm theo tên hoặc email..." aria-label="Tìm người dùng">
            <select class="adm-select" name="role" id="adm-role" aria-label="Lọc theo vai trò">
                <option value="">Mọi vai trò</option>
                <?php foreach (['USER' => 'USER', 'COACH' => 'COACH (HLV)', 'ADMIN' => 'ADMIN'] as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $roleFilter === $value ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="adm-btn">Lọc</button>
        </form>
        <div class="adm-count">
            <?= htmlspecialchars($filtering ? "Tìm thấy $total / $allUsers tài khoản" : "Tổng: $allUsers tài khoản") ?>
            <?php if ($filtering): ?> · <a class="adm-link" href="/admin/users.php">Bỏ lọc</a><?php endif; ?>
        </div>

        <?php if (empty($users)): ?>
            <div class="adm-empty"><?= $filtering ? 'Không có tài khoản nào khớp.' : 'Chưa có tài khoản nào.' ?></div>
        <?php else: ?>
        <table class="adm-table">
            <thead>
                <tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Hồ sơ</th><th>Đăng ký</th><th></th></tr>
            </thead>
            <tbody id="adm-rows">
            <?php foreach ($users as $u): ?>
                <?php $isSelf = (int)$u['id'] === $selfId; ?>
                <tr>
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
                            <?php if (!$isSelf && $u['role'] === 'COACH'): ?>
                                <a class="adm-btn adm-btn-ghost adm-btn-sm" href="/admin/coaches.php">Quản lý HLV</a>
                            <?php elseif (!$isSelf): ?>
                                <button type="button" class="adm-btn adm-btn-ghost adm-btn-sm adm-act"
                                        data-action="set_role" data-id="<?= (int)$u['id'] ?>"
                                        data-name="<?= htmlspecialchars($u['full_name']) ?>"
                                        data-role="<?= $u['role'] === 'ADMIN' ? 'USER' : 'ADMIN' ?>">
                                    <?= $u['role'] === 'ADMIN' ? 'Hạ xuống USER' : 'Lên ADMIN' ?>
                                </button>
                            <?php endif; ?>
                            <?php if (!$isSelf && $u['role'] !== 'ADMIN'): ?>
                                <button type="button" class="adm-btn adm-btn-danger adm-btn-sm adm-act"
                                        data-action="delete" data-id="<?= (int)$u['id'] ?>"
                                        data-name="<?= htmlspecialchars($u['full_name']) ?>">Xóa</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($pages > 1): ?>
            <div class="adm-pager">
                <span>Trang <?= (int)$page ?> / <?= (int)$pages ?></span>
                <div>
                    <?php if ($page > 1): ?><a class="adm-btn adm-btn-ghost adm-btn-sm" href="<?= htmlspecialchars(usersPageLink($page - 1, $q, $roleFilter)) ?>">&larr; Trước</a><?php endif; ?>
                    <?php if ($page < $pages): ?><a class="adm-btn adm-btn-ghost adm-btn-sm" href="<?= htmlspecialchars(usersPageLink($page + 1, $q, $roleFilter)) ?>">Sau &rarr;</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
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
    // Đổi vai trò trong ô lọc thì gửi form luôn, đỡ phải bấm "Lọc"
    var role = document.getElementById('adm-role');
    role.addEventListener('change', function () { role.form.submit(); });

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
                ? 'Xóa "' + btn.dataset.name + '" cùng toàn bộ dữ liệu của họ (lịch tập, số đo, quan hệ với HLV hoặc học viên, tin nhắn...). Không hoàn tác được.'
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
