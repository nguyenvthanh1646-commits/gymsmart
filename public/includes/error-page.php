<?php
/**
 * error-page.php — trang báo lỗi có giao diện thống nhất (thay cho dòng chữ trơ từ exit('...')).
 *
 * Dùng:
 *   showError(404, 'Không tìm thấy bài tập', 'Bài tập này không tồn tại hoặc đã bị xóa.');
 *   showCsrfError();   // khi verifyCsrf() trả về false
 *
 * Hàm tự đặt mã HTTP (403, 404...), vẽ header + nội dung + footer rồi dừng chương trình (exit).
 * Được nạp từ includes/auth.php nên mọi trang đã require auth.php đều gọi được.
 */

/**
 * @param int         $code    mã HTTP, ví dụ 400, 403, 404
 * @param string      $title   tiêu đề lớn
 * @param string      $message giải thích ngắn cho người dùng (không đưa thông tin kỹ thuật nhạy cảm vào đây)
 * @param array|null  $actions danh sách nút: [['/duong-dan', 'Chữ trên nút'], ...]; null = nút mặc định
 */
function showError(int $code, string $title, string $message, ?array $actions = null): void
{
    http_response_code($code);
    // navbar.php thấy hằng này thì vẫn vẽ menu người dùng, kể cả khi lỗi xảy ra trong /admin/
    if (!defined('GS_ERROR_PAGE')) {
        define('GS_ERROR_PAGE', true);
    }

    if ($actions === null) {
        $actions = [];
        if (!function_exists('isLoggedIn') || !isLoggedIn()) {
            $actions[] = ['/login.php', 'Đăng nhập'];
        }
        $actions[] = ['/index.php', 'Về trang chủ'];
    }

    $pageTitle = $title;
    include __DIR__ . '/header.php';
    ?>
    <section class="err" role="alert">
        <div class="err__code" aria-hidden="true"><?= (int)$code ?></div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars($message) ?></p>
        <div class="err__actions">
            <?php foreach ($actions as $i => [$href, $label]): ?>
                <a class="btn<?= $i > 0 ? ' btn--ghost' : '' ?>" href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    include __DIR__ . '/footer.php';
    exit;
}

/** Mã bảo vệ (CSRF) của form sai hoặc hết hạn: gợi ý tải lại trang rồi gửi lại. */
function showCsrfError(): void
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/index.php');
    // Chỉ cho phép đường dẫn nội bộ (bắt đầu bằng "/" và không phải "//") để không bị dẫn sang trang lạ
    if ($uri === '' || $uri[0] !== '/' || strpos($uri, '//') === 0) {
        $uri = '/index.php';
    }
    showError(
        403,
        'Phiên làm việc đã hết hạn',
        'Mã bảo vệ của biểu mẫu không còn hợp lệ, thường do mở trang quá lâu hoặc đăng nhập ở tab khác. Hãy tải lại trang rồi gửi lại.',
        [[$uri, 'Tải lại trang'], ['/index.php', 'Về trang chủ']]
    );
}
