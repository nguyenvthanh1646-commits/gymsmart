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
    // ===== Cấu hình cookie phiên an toàn (SECURITY_CHECKLIST, bước 4a) =====
    // Phải đặt TRƯỚC session_start(), sau khi session đã chạy thì đổi không còn tác dụng.

    // Chỉ nhận mã phiên từ cookie, không nhận qua URL (?PHPSESSID=...). Chặn việc gửi link
    // kèm sẵn mã phiên của kẻ xấu cho nạn nhân (session fixation).
    ini_set('session.use_only_cookies', '1');

    // Không chấp nhận mã phiên do client tự bịa ra (chưa từng được server cấp).
    ini_set('session.use_strict_mode', '1');

    // Bật cờ "secure" (chỉ gửi cookie qua HTTPS) khi trang đang chạy bằng HTTPS.
    // Chạy localhost bằng http thì tự bỏ qua, nếu ép secure ở localhost thì không đăng nhập được.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,          // cookie hết hạn khi đóng trình duyệt
        'path'     => '/',
        'httponly' => true,       // JavaScript không đọc được cookie phiên (giảm thiệt hại khi bị XSS)
        'samesite' => 'Lax',      // trình duyệt không gửi cookie trong yêu cầu chéo trang nguy hiểm (giảm CSRF)
        'secure'   => $isHttps,
    ]);

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
    global $pdo; // trang admin đã nạp includes/db.php trước khi gọi hàm này
    requireLogin();
    if ($pdo instanceof PDO) {
        refreshSessionRole($pdo);
    }
    if (($_SESSION['role'] ?? '') !== 'ADMIN') {
        showError(403, 'Bạn không có quyền truy cập', 'Trang này chỉ dành cho quản trị viên. Nếu bạn nghĩ đây là nhầm lẫn, hãy báo người quản trị.');
    }
}

/**
 * Đọc lại vai trò từ DB và cập nhật vào session.
 * Vì sao cần? role trong session chỉ được ghi lúc đăng nhập. Nếu admin khác hạ quyền (ADMIN -> USER)
 * hoặc xóa tài khoản này, session cũ vẫn ghi 'ADMIN' cho tới khi đăng xuất. Đọc lại ở mỗi trang admin
 * thì thay đổi có hiệu lực ngay. Tài khoản không còn trong DB thì hủy session, chuyển về đăng nhập.
 */
function refreshSessionRole(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $role = $stmt->fetchColumn();
    if ($role === false) {
        $_SESSION = [];
        session_destroy();
        header('Location: /login.php');
        exit;
    }
    $_SESSION['role'] = $role;
}

// Trang báo lỗi dùng chung (showError, showCsrfError)
require_once __DIR__ . '/error-page.php';
