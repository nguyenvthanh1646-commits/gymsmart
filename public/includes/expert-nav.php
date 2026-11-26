<?php
/**
 * expert-nav.php — thanh điều hướng của khu chuyên gia (/expert/), cùng kiểu với khu HLV và khu quản trị (class adm-nav).
 * Trong /expert/ navbar người dùng bị ẩn (xem navbar.php) nên thanh này có logo, tên và Đăng xuất.
 * Dùng: $expertNavActive = 'dashboard'; include __DIR__ . '/../includes/expert-nav.php';
 * Giá trị: 'dashboard' | 'profile'
 */
require_once __DIR__ . '/logo.php';
$expertNavActive = $expertNavActive ?? '';
$expertNavItems = [
    'dashboard' => ['/expert/dashboard.php', 'Câu hỏi'],
    'profile'   => ['/expert/profile.php', 'Hồ sơ chuyên gia'],
];
$expertNavParts = preg_split('/\s+/u', trim((string)($_SESSION['full_name'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
$expertNavInitial = $expertNavParts ? mb_strtoupper(mb_substr(end($expertNavParts), 0, 1)) : '?';
?>
<nav class="adm-nav ch-nav" aria-label="Khu chuyên gia">
    <div class="adm-nav-in">
        <a class="adm-nav-brand" href="/expert/dashboard.php" aria-label="GymSmart Chuyên gia — Câu hỏi"><?= gsLogoMark(32) ?><span>Gym<b>Smart</b></span><span class="adm-nav-tag">Chuyên gia</span></a>
        <div class="adm-nav-links">
            <?php foreach ($expertNavItems as $key => [$href, $label]): ?>
                <a href="<?= $href ?>"<?= $expertNavActive === $key ? ' class="is-active" aria-current="page"' : '' ?>><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="adm-nav-right">
            <a class="adm-nav-site" href="/exercises.php">Xem trang người dùng &#8599;</a>
            <?php include __DIR__ . '/notif-bell.php'; ?>
            <span class="adm-nav-av" title="<?= htmlspecialchars((string)($_SESSION['full_name'] ?? '')) ?>"><?= htmlspecialchars($expertNavInitial) ?></span>
            <a class="adm-nav-out" href="/api/logout.php">Đăng xuất</a>
        </div>
    </div>
</nav>
