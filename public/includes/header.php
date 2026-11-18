<?php
/**
 * header.php — phần <head> + mở thẻ <body>, include ở đầu mỗi trang.
 * Biến $pageTitle có thể set trước khi include file này để đổi tiêu đề.
 * Biến $pageCss (tùy chọn) là mảng đường dẫn CSS riêng của trang, nạp trong <head> để trang không bị nhấp nháy.
 *
 * Nạp style.css (chung) → fonts.css (font) → brand.css (logo + thanh điều hướng) → ui.css (nút, thẻ, form...).
 * Thứ tự quan trọng: file nạp sau ghi đè file nạp trước.
 * Gửi thêm vài header bảo vệ trình duyệt (xem SECURITY_CHECKLIST.md, Bước 4b).
 */
$pageTitle = $pageTitle ?? 'GymSmart';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');            // trình duyệt không tự đoán kiểu file
    header('X-Frame-Options: SAMEORIGIN');                 // không cho trang khác nhúng website trong iframe
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Content-Security-Policy: trình duyệt chỉ tải script, CSS, font, dữ liệu fetch() từ chính website; ảnh thêm data:/blob:
    // (xem trước ảnh đại diện) và ảnh bìa video YouTube; khung nhúng chỉ cho youtube-nocookie.com (video bài tập).
    // Còn 'unsafe-inline' vì một số trang có <script> và style="..." viết thẳng trong HTML; dù vậy nếu bị chèn mã,
    // mã đó cũng không tải được script từ nơi khác hay gửi dữ liệu ra ngoài (connect-src 'self').
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: blob: https://img.youtube.com; font-src 'self'; connect-src 'self'; "
        . "frame-src https://www.youtube-nocookie.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b1020">
    <title><?= htmlspecialchars($pageTitle) ?> — GymSmart</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <!-- Nạp trước các file font hay dùng nhất để chữ không bị nhấp nháy khi mở trang -->
    <link rel="preload" href="/fonts/be-vietnam-pro-vietnamese-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/be-vietnam-pro-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/be-vietnam-pro-latin-700-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/fonts.css">
    <link rel="stylesheet" href="/css/brand.css">
    <link rel="stylesheet" href="/css/ui.css">
    <?php foreach ($pageCss ?? [] as $cssHref): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($cssHref) ?>">
    <?php endforeach; ?>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<main class="container">
