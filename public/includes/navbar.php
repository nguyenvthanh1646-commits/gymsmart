<?php
/**
 * navbar.php — thanh điều hướng trên cùng (có logo, đánh dấu trang đang mở,
 * thu gọn thành nút ☰ trên điện thoại). Hiển thị khác nhau theo trạng thái:
 *   - chưa đăng nhập: Đăng nhập / Đăng ký (riêng trang chủ có thêm liên kết tới các mục trong trang)
 *   - đã đăng nhập:  menu chức năng + chữ cái đầu tên + Đăng xuất
 *   - ADMIN:         thêm nút "Quản trị"
 *   - COACH:         thêm nút "Khu HLV"
 * Cần session nên các trang include file này phải require auth.php trước (để có isLoggedIn()).
 * Style nằm trong css/brand.css (class bắt đầu bằng "nb-").
 */
require_once __DIR__ . '/logo.php';

$loggedIn = function_exists('isLoggedIn') && isLoggedIn();
$isAdmin  = $loggedIn && (($_SESSION['role'] ?? '') === 'ADMIN');
$isCoach  = $loggedIn && (($_SESSION['role'] ?? '') === 'COACH');
$navCurrent = basename($_SERVER['SCRIPT_NAME'] ?? '');
// Trong khu quản trị đã có menu riêng (admin-nav.php) nên ẩn menu người dùng cho gọn
// Khu HLV (/coach/) cũng có thanh riêng (coach-nav.php)
$inAdmin  = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') === 0 || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/coach/') === 0) && !defined('GS_ERROR_PAGE');
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
    ['/coaches.php',      'HLV',             ['coaches.php', 'my-coach.php', 'coach-apply.php']],
    ['/profile.php',      'Hồ sơ',           ['profile.php']],
];
$initial = '';
if ($loggedIn) {
    $name = trim((string)($_SESSION['full_name'] ?? ''));
    // lấy chữ cái đầu của TÊN (từ cuối trong họ tên Việt Nam), viết hoa
    $parts = $name === '' ? [] : preg_split('/\s+/u', $name);
    $initial = $parts ? mb_strtoupper(mb_substr(end($parts), 0, 1)) : '?';

    // Ảnh đại diện (cột users.avatar): đọc 1 lần rồi nhớ trong session. Nếu chưa có cột hoặc lỗi DB thì dùng chữ cái đầu.
    if (!array_key_exists('avatar', $_SESSION)) {
        $_SESSION['avatar'] = null;
        try {
            // Navbar có thể được vẽ bên trong showError() (phạm vi hàm, không thấy $pdo) nên lấy thêm $pdo toàn cục
            $navPdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
            if (!$navPdo instanceof PDO) { require_once __DIR__ . '/db.php'; $navPdo = $pdo ?? ($GLOBALS['pdo'] ?? null); }
            $st = $navPdo->prepare('SELECT avatar FROM users WHERE id = ?');
            $st->execute([(int)($_SESSION['user_id'] ?? 0)]);
            $_SESSION['avatar'] = $st->fetchColumn() ?: null;
        } catch (Throwable $e) { /* chưa import sql/users_avatar.sql: bỏ qua */ }
    }
}
// Khách ở trang chủ: các trang tính năng đều cần đăng nhập nên liên kết chỉ cuộn tới mục ngay trong trang
$navGuestHome = !$loggedIn && $navCurrent === 'index.php';
// Trang chủ sau đăng nhập dùng chủ đề tối (css/home-user.css), các trang khác giữ nền sáng
$navUserHome = $loggedIn && $navCurrent === 'index.php';
$navAvatar = ($loggedIn && is_string($_SESSION['avatar'] ?? null) && preg_match('#^uploads/avatars/[A-Za-z0-9._-]+\.jpg$#', $_SESSION['avatar'])) ? $_SESSION['avatar'] : null;
?>
<header class="nb<?= $navGuestHome ? ' nb--guest-home' : '' ?><?= $navUserHome ? ' nb--user-home' : '' ?>" id="nb">
    <div class="nb__in">
        <a href="/index.php" aria-label="GymSmart — trang chủ"><?= gsLogo('dark', false, 36) ?></a>

        <?php if ($loggedIn) { include __DIR__ . '/notif-bell.php'; } // chuông thông báo, nằm ngoài menu gập để luôn thấy trên điện thoại ?>

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

            <?php if ($navGuestHome): ?>
                <nav class="nb__links" aria-label="Chính">
                    <a href="#tinh-nang">Tính năng</a>
                    <a href="#kien-thuc">Bài viết</a>
                </nav>
            <?php endif; ?>

            <div class="nb__right">
                <?php if ($loggedIn): ?>
                    <?php if ($isAdmin): ?>
                        <a class="nb__btn nb__btn--admin" href="/admin/dashboard.php">Quản trị</a>
                    <?php endif; ?>
                    <?php if ($isCoach): ?>
                        <a class="nb__btn nb__btn--admin" href="/coach/dashboard.php">Khu HLV</a>
                    <?php endif; ?>
                    <a class="nb__avatar" href="/profile.php" title="Hồ sơ" aria-label="Hồ sơ"><?php if ($navAvatar): ?><img src="/<?= htmlspecialchars($navAvatar) ?>" alt="" width="38" height="38"><?php else: ?><?= htmlspecialchars($initial) ?><?php endif; ?></a>
                    <a class="nb__btn nb__btn--ghost" href="/api/logout.php">Đăng xuất</a>
                <?php else: ?>
                    <a class="nb__btn nb__btn--ghost" href="/login.php">Đăng nhập</a>
                    <a class="nb__btn nb__btn--solid" href="/register.php"><?= $navGuestHome ? 'Tạo tài khoản miễn phí' : 'Đăng ký miễn phí' ?></a>
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
