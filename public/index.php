<?php
require_once __DIR__ . '/includes/auth.php'; // bắt buộc require trước navbar vì navbar cần isLoggedIn()

$pageTitle = 'Trang chủ';
include __DIR__ . '/includes/header.php';
?>

<h1>Chào mừng đến với GymSmart</h1>
<p>Đây là trang chủ mẫu — dùng để kiểm tra layout header/footer/navbar đã chạy đúng chưa.</p>
<a href="/exercises.php" class="btn">Xem thư viện bài tập</a>

<?php include __DIR__ . '/includes/footer.php'; ?>
