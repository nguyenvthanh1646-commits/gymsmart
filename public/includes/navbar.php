<?php
/**
 * navbar.php — thanh điều hướng trên cùng (có logo, đánh dấu trang đang mở,
 * thu gọn thành nút ☰ trên điện thoại). Hiển thị khác nhau theo trạng thái:
 *   - chưa đăng nhập: Đăng nhập / Đăng ký
 *   - đã đăng nhập:  menu chức năng + chữ cái đầu tên + Đăng xuất
 *   - ADMIN:         thêm nút "Quản trị"
 * Cần session nên các trang include file này phải require auth.php trước (để có isLoggedIn()).
 * Style nằm trong css/brand.css (class bắt đầu bằng "nb-").
 */
require_once __DIR__ . '/logo.php';

$loggedIn = function_exists('isLoggedIn') && isLoggedIn();
$isAdmin  = $loggedIn && (($_SESSION['role'] ?? '') === 'ADMIN');
$navCurrent = basename($_SERVER['SCRIPT_NAME'] ?? '');
// Trong khu quản trị đã có menu riêng (admin-nav.php) nên ẩn menu người dùng cho gọn
$inAdmin  = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') === 0 && !defined('GS_ERROR_PAGE');
if ($inAdmin) {
    return; // khu quản trị dùng thanh riêng (admin-nav.php), không vẽ navbar người dùng ở đây
}

// [đường dẫn, chữ hiển thị, các file thuộc mục này (để tô sáng mục đang mở)]
$navItems = [
    ['/exercises.php',    'Bài tập & Video', ['exercises.php', 'exercise-detail.php']],
    ['/workout-plan.php', 'Lịch tập',        ['workout-plan.php']],
    ['/nutrition.php',    'TDEE/Macro',      ['nutrition.php']],
    ['/progress.php',     'Tiến trình',      ['progress.php']],
    ['/articles.php',     'Bài viết',        ['articles.php', 'article.php']],
    ['/profile.php',      'Hồ sơ',           ['profile.php']],
];
$initial = '';
if ($loggedIn) {
    $name = trim((string)($_SESSION['full_name'] ?? ''));
    // lấy chữ cái đầu của TÊN (từ cuối trong họ tên Việt Nam), viết hoa
    $parts = $name === '' ? [] : preg_split('/\s+/u', $name);
    $initial = $parts ? mb_strtoupper(mb_substr(end($parts), 0, 1)) : '?';
}
?>
<header class="nb" id="nb">
    <div class="nb__in">
        <a href="/index.php" aria-label="GymSmart — trang chủ"><?= gsLogo('dark', false, 36) ?></a>

        <button class="nb__toggle" type="button" aria-label="Mở menu" aria-expanded="false" aria-controls="nb-panel">
            <span></span>
        </button>

        <div class="nb__panel" id="nb-panel">
            <?php if ($loggedIn && !$inAdmin): ?>
                <nav class="nb__links" aria-label="Chính">
                    <?php foreach ($navItems as [$href, $label, $files]): ?>
                        <a href="<?= htmlspecialchars($href) ?>"
                           class="<?= in_array($navCurrent, $files, true) ? 'is-active' : '' ?>"><?= htmlspecialchars($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <div class="nb__right">
                <?php if ($loggedIn): ?>
                    <?php if ($isAdmin): ?>
                        <a class="nb__btn nb__btn--admin" href="/admin/dashboard.php">Quản trị</a>
                    <?php endif; ?>
                    <a class="nb__avatar" href="/profile.php" title="Hồ sơ" aria-label="Hồ sơ"><?= htmlspecialchars($initial) ?></a>
                    <a class="nb__btn nb__btn--ghost" href="/api/logout.php">Đăng xuất</a>
                <?php else: ?>
                    <a class="nb__btn nb__btn--ghost" href="/login.php">Đăng nhập</a>
                    <a class="nb__btn nb__btn--solid" href="/register.php">Đăng ký miễn phí</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
<script>
// Nút ☰ trên điện thoại: bật/tắt menu
(function () {
    var nb = document.getElementById('nb');
    var btn = nb.querySelector('.nb__toggle');
    btn.addEventListener('click', function () {
        var open = nb.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
})();
</script>
