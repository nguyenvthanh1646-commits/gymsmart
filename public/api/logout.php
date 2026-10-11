<?php
/**
 * api/logout.php — đăng xuất.
 * Navbar đã trỏ tới /api/logout.php nên file này đặt trong thư mục public/api/.
 */

require_once __DIR__ . '/../includes/auth.php'; // đã gọi session_start()

// Xóa toàn bộ dữ liệu trong session rồi hủy session
$_SESSION = [];
session_destroy();

header('Location: /login.php');
exit;