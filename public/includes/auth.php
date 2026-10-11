<?php
/**
 * auth.php — các hàm liên quan tới session/đăng nhập dùng chung.
 *
 * Lưu ý: file này CHỈ chứa hàm tiện ích dùng chung. Logic đăng ký/đăng
 * nhập thật (kiểm tra email/password, tạo user mới...) do A tự viết
 * trong register.php / api/login.php — không nằm ở đây.
 */

// session_start() phải gọi TRƯỚC khi có bất kỳ output nào (kể cả khoảng
// trắng trước thẻ <?php). Mỗi trang cần session chỉ cần require file này.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Kiểm tra user đã đăng nhập chưa.
 * Quy ước: sau khi đăng nhập thành công, lưu $_SESSION['user_id'] và
 * $_SESSION['role'] (A sẽ làm việc này trong api/login.php).
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Chặn truy cập nếu chưa đăng nhập — gọi ở đầu các trang cần auth.
 * Ví dụ dùng trong workout-plan.php:
 *   require_once __DIR__ . '/includes/auth.php';
 *   requireLogin();
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Chặn truy cập nếu không phải admin — dùng cho các trang quản trị
 * (CRUD bài tập, bài viết...).
 */
function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'ADMIN') {
        http_response_code(403);
        die('Bạn không có quyền truy cập trang này.');
    }
}
