<?php
/**
 * coach-nav.php — thanh điều hướng của khu HLV (/coach/), cùng kiểu với khu quản trị (dùng class adm-nav của admin-style.php),
 * nhãn "HLV". Trong /coach/ navbar người dùng bị ẩn (xem navbar.php) nên thanh này có logo, tên và Đăng xuất.
 * Dùng: $coachNavActive = 'clients'; include __DIR__ . '/../includes/coach-nav.php';
 * Giá trị: 'dashboard' | 'clients' | 'messages' | 'profile'
 */
require_once __DIR__ . '/logo.php';
$coachNavActive = $coachNavActive ?? '';
$coachNavItems = [
    'dashboard' => ['/coach/dashboard.php', 'Tổng quan'],
    'clients'   => ['/coach/clients.php', 'Học viên'],
    'messages'  => ['/coach/messages.php', 'Tin nhắn'],
    'templates' => ['/coach/templates.php', 'Mẫu lịch'],
    'profile'   => ['/coach/profile.php', 'Hồ sơ HLV'],
];
$coachNavInitial = '?';
$coachNavParts = preg_split('/\s+/u', trim((string)($_SESSION['full_name'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
if ($coachNavParts) {
    $coachNavInitial = mb_strtoupper(mb_substr(end($coachNavParts), 0, 1));
}
?>
<nav class="adm-nav ch-nav" aria-label="Khu huấn luyện viên">
    <div class="adm-nav-in">
        <a class="adm-nav-brand" href="/coach/dashboard.php" aria-label="GymSmart HLV — Tổng quan"><?= gsLogoMark(32) ?><span>Gym<b>Smart</b></span><span class="adm-nav-tag">HLV</span></a>
        <div class="adm-nav-links">
            <?php foreach ($coachNavItems as $key => [$href, $label]): ?>
                <a href="<?= $href ?>"<?= $coachNavActive === $key ? ' class="is-active" aria-current="page"' : '' ?>><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="adm-nav-right">
            <a class="adm-nav-site" href="/exercises.php">Xem trang người dùng &#8599;</a>
            <?php include __DIR__ . '/notif-bell.php'; // chuông thông báo (A-44) ?>
            <span class="adm-nav-av" title="<?= htmlspecialchars((string)($_SESSION['full_name'] ?? '')) ?>"><?= htmlspecialchars($coachNavInitial) ?></span>
            <a class="adm-nav-out" href="/api/logout.php">Đăng xuất</a>
        </div>
    </div>
</nav>
