<?php
/**
 * admin-nav.php — thanh điều hướng riêng của khu quản trị (khác menu của người dùng).
 * Dùng: $admNavActive = 'exercises'; include __DIR__ . '/../includes/admin-nav.php';
 * Giá trị $admNavActive: 'dashboard' | 'exercises' | 'users'
 */
require_once __DIR__ . '/logo.php';
$admNavActive = $admNavActive ?? '';
$admNavItems = [
    'dashboard' => ['/admin/dashboard.php', 'Tổng quan'],
    'exercises' => ['/admin/exercises.php', 'Bài tập'],
    'users'     => ['/admin/users.php', 'Người dùng'],
];
?>
<nav class="adm-nav" aria-label="Quản trị">
    <div class="adm-nav-in">
        <a class="adm-nav-brand" href="/admin/dashboard.php"><?= gsLogoMark(28) ?><span>Quản trị</span></a>
        <?php foreach ($admNavItems as $key => [$href, $label]): ?>
            <a href="<?= $href ?>" class="<?= $admNavActive === $key ? 'is-active' : '' ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
        <a class="adm-nav-out" href="/exercises.php">Xem như người dùng &rarr;</a>
    </div>
</nav>
