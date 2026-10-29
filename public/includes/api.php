<?php
/**
 * api.php — hàm dùng chung cho các file trong api/ trả JSON về cho JavaScript (fetch).
 *
 * Dùng ở đầu mỗi file api/*.php:
 *     require_once __DIR__ . '/../includes/auth.php';
 *     require_once __DIR__ . '/../includes/api.php';
 *     apiBoot();            // chỉ nhận GET, bắt buộc đăng nhập, lỗi bất ngờ vẫn trả JSON
 *     $userId = (int)$_SESSION['user_id'];
 *
 * Vì sao không dùng requireLogin()? Hàm đó chuyển hướng sang trang HTML, còn JavaScript cần nhận JSON
 * có mã 401 để biết phải làm gì. Mọi API đều lấy user_id từ $_SESSION, không bao giờ từ URL/form.
 *
 * Định dạng trả về (theo docs/API_SPEC.md, mục 0):
 *     thành công: { "success": true,  "data": ... }
 *     thất bại:   { "success": false, "error": "lời nhắn tiếng Việt" }
 */

/** Gửi JSON rồi dừng chương trình. */
function apiJson(int $code, array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();                    // bỏ mọi chữ lạ lỡ in ra trước đó (notice, khoảng trắng...)
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');     // dữ liệu cá nhân: không cho trình duyệt/proxy lưu lại
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function apiError(int $code, string $message): void
{
    apiJson($code, ['success' => false, 'error' => $message]);
}

/**
 * Chuẩn bị cho một API chỉ-đọc: tắt hiển thị lỗi PHP (lỗi vẫn ghi vào log Apache), chỉ nhận GET,
 * bắt buộc đăng nhập, và biến mọi lỗi/exception bất ngờ thành JSON gọn thay vì trang HTML.
 */
function apiBoot(): void
{
    ini_set('display_errors', '0');
    ob_start();
    set_exception_handler(function (Throwable $e) {
        error_log('api: ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
        apiError(500, 'Máy chủ gặp sự cố, thử lại sau.');
    });
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        apiError(405, 'Phương thức không hợp lệ.');
    }
    if (!isLoggedIn()) {
        apiError(401, 'Bạn cần đăng nhập.');
    }
}

/**
 * Đọc khoảng ngày ?from=YYYY-MM-DD&to=YYYY-MM-DD (cả hai đều tùy chọn).
 * Sai định dạng, ngày không có thật hoặc from > to thì trả 400. Trả về [from|null, to|null].
 */
function apiDateRange(): array
{
    $out = [];
    foreach (['from', 'to'] as $key) {
        $raw = $_GET[$key] ?? null;
        if ($raw === null || $raw === '') {
            $out[$key] = null;
            continue;
        }
        $d = is_string($raw) ? DateTime::createFromFormat('Y-m-d', $raw) : false;
        if ($d === false || $d->format('Y-m-d') !== $raw) {
            apiError(400, 'Tham số "' . $key . '" phải có dạng YYYY-MM-DD (ví dụ 2026-09-30).');
        }
        $out[$key] = $raw;
    }
    if ($out['from'] !== null && $out['to'] !== null && $out['from'] > $out['to']) {
        apiError(400, 'Ngày bắt đầu (from) không được sau ngày kết thúc (to).');
    }
    return [$out['from'], $out['to']];
}

/** Số hoặc null (ô để trống trong DB) → kiểu số của JSON; tránh trả chuỗi "78.50". */
function apiNum($v): ?float
{
    return $v === null ? null : round((float)$v, 2);
}
