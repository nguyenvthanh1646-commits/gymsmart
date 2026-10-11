<?php
/**
 * header.php — phần <head> + mở thẻ <body>, include ở đầu mỗi trang.
 * Biến $pageTitle có thể set trước khi include file này để đổi tiêu đề.
 */
$pageTitle = $pageTitle ?? 'GymSmart';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — GymSmart</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<main class="container">
