<?php
/**
 * csrf.php — chống tấn công CSRF cho các form làm thay đổi dữ liệu (thêm/sửa/xóa).
 *
 * CSRF là gì? Kẻ xấu dựng một trang web có sẵn form gửi về website của ta.
 * Nếu admin đang đăng nhập mà bấm vào trang đó, trình duyệt tự gửi kèm cookie
 * session, nên server tưởng đó là thao tác thật của admin.
 *
 * Cách chống: mỗi form chứa một "mã bí mật" ngẫu nhiên được lưu trong session.
 * Trang của kẻ xấu không biết mã này nên không giả được form.
 *
 * Yêu cầu: session đã được bật (includes/auth.php làm việc này).
 */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes tạo dữ liệu ngẫu nhiên an toàn cho mật mã
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** In ô ẩn chứa mã, đặt bên trong thẻ <form method="post"> */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken()) . '">';
}

/** Kiểm tra mã gửi lên có đúng mã trong session không */
function verifyCsrf(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    // hash_equals so sánh an toàn, tránh bị dò mã qua thời gian xử lý
    return is_string($sent) && hash_equals(csrfToken(), $sent);
}