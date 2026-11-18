<?php
/**
 * guest-cta.php — dải mời đăng ký cho KHÁCH ở các trang xem được khi chưa đăng nhập (thư viện bài tập, bài viết; A-45).
 * Đã đăng nhập thì không in gì. Đặt $guestCtaText trước khi include để đổi câu mời theo trang.
 */
if (function_exists('isLoggedIn') && isLoggedIn()) {
    return;
}
$guestCtaText = $guestCtaText ?? 'Tạo tài khoản miễn phí để nhận lịch tập theo trình độ, ghi nhật ký từng set và hỏi GymSmart AI.';
?>
<aside class="guest-cta" aria-label="Mời đăng ký">
    <p><?= htmlspecialchars($guestCtaText) ?></p>
    <div class="guest-cta__acts">
        <a class="btn" href="/register.php">Tạo tài khoản miễn phí</a>
        <a class="btn btn--ghost" href="/login.php">Đăng nhập</a>
    </div>
</aside>
