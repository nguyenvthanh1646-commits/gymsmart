</main>
<?php
// footer.php — chân trang chung. Style nằm trong css/ui.css (class bắt đầu bằng "ft").
require_once __DIR__ . '/logo.php';
$ftLoggedIn = function_exists('isLoggedIn') && isLoggedIn();
?>
<footer class="ft">
    <div class="ft__in">
        <div class="ft__brand">
            <?= gsLogo('light', true, 34) ?>
            <p>Website hỗ trợ tập luyện cá nhân hóa: bài tập có video, lịch tập, TDEE/Macro và theo dõi tiến trình.</p>
        </div>
        <nav class="ft__links" aria-label="Chân trang">
            <a href="/index.php">Trang chủ</a>
            <?php if ($ftLoggedIn): ?>
                <a href="/exercises.php">Bài tập &amp; Video</a>
                <a href="/profile.php">Hồ sơ</a>
            <?php else: ?>
                <a href="/login.php">Đăng nhập</a>
                <a href="/register.php">Đăng ký</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="ft__copy">&copy; <?= date('Y') ?> GymSmart — Đồ án môn Phát triển ứng dụng Web</div>
</footer>
<script src="/js/main.js"></script>
</body>
</html>
