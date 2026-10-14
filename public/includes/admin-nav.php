<?php
/**
 * admin-nav.php — thanh điều hướng riêng của khu quản trị (khác menu của người dùng).
 * Trong khu quản trị, navbar người dùng được ẩn (xem navbar.php) nên thanh này có luôn logo, tên và nút Đăng xuất.
 * Dùng: $admNavActive = 'exercises'; include __DIR__ . '/../includes/admin-nav.php';
 * Giá trị $admNavActive: 'dashboard' | 'exercises' | 'users' | 'articles' | 'stats' | 'audit'
 */
require_once __DIR__ . '/logo.php';
$admNavActive = $admNavActive ?? '';
$admNavItems = [
    'dashboard' => ['/admin/dashboard.php', 'Tổng quan'],
    'exercises' => ['/admin/exercises.php', 'Bài tập'],
    'users'     => ['/admin/users.php', 'Người dùng'],
    'articles'  => ['/admin/articles.php', 'Bài viết'],
    'stats'     => ['/admin/stats.php', 'Thống kê'],
    'audit'     => ['/admin/audit-log.php', 'Nhật ký'],
];
?>
<?php
$admInitial = '?';
$admParts = preg_split('/\s+/u', trim((string)($_SESSION['full_name'] ?? '')));
if ($admParts && $admParts[0] !== '') {
    $admInitial = mb_strtoupper(mb_substr(end($admParts), 0, 1)); // chữ cái đầu của TÊN
}
?>
<nav class="adm-nav" aria-label="Quản trị">
    <div class="adm-nav-in">
        <a class="adm-nav-brand" href="/admin/dashboard.php"><?= gsLogoMark(32) ?><span>Gym<b>Smart</b></span><span class="adm-nav-tag">Admin</span></a>
        <?php foreach ($admNavItems as $key => [$href, $label]): ?>
            <a href="<?= $href ?>" class="<?= $admNavActive === $key ? 'is-active' : '' ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
        <div class="adm-nav-right">
            <a class="adm-nav-site" href="/exercises.php">Xem trang người dùng &#8599;</a>
            <span class="adm-nav-av" title="<?= htmlspecialchars((string)($_SESSION['full_name'] ?? '')) ?>"><?= htmlspecialchars($admInitial) ?></span>
            <a class="adm-nav-out" href="/api/logout.php">Đăng xuất</a>
        </div>
    </div>
</nav>
