<?php
/**
 * navbar.php — thanh điều hướng trên cùng, hiển thị khác nhau tùy đã
 * đăng nhập hay chưa. Cần session nên các trang include file này phải
 * require auth.php trước (để có isLoggedIn()).
 */
$loggedIn = function_exists('isLoggedIn') && isLoggedIn();
?>
<header class="navbar">
    <a href="/index.php" class="navbar__logo">GymSmart</a>
    <nav class="navbar__links">
        <a href="/exercises.php">Bài tập &amp; Video</a>
        <a href="/workout-plan.php">Lịch tập</a>
        <a href="/nutrition.php">TDEE/Macro</a>
        <a href="/progress.php">Tiến trình</a>
        <?php if ($loggedIn): ?>
            <a href="/profile.php">Hồ sơ</a>
            <a href="/api/logout.php">Đăng xuất</a>
        <?php else: ?>
            <a href="/login.php">Đăng nhập</a>
            <a href="/register.php">Đăng ký</a>
        <?php endif; ?>
    </nav>
</header>
