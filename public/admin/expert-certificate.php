<?php
/**
 * admin/expert-certificate.php?id=<id đơn> — trả về file chứng chỉ hành nghề trong đơn đăng ký chuyên gia (A-55). CHỈ ADMIN.
 * Cùng cách làm với admin/certificate.php (đơn HLV): file nằm ngoài public/, tên file kiểm tra đúng dạng do máy chủ đặt
 * và nằm đúng thư mục (realpath), gửi kèm nosniff và CSP chặn chạy mã.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/expert.php';

requireAdmin();
$app = expertTablesReady($pdo) ? expertFindApplication($pdo, coachParseId($_GET['id'] ?? null)) : null;
$name = $app['certificate_file'] ?? null;
$dir = realpath(coachCertDir());
$path = (coachCertNameOk($name) && $dir !== false) ? realpath($dir . DIRECTORY_SEPARATOR . $name) : false;
if ($path === false || strpos($path, $dir . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    showError(404, 'Không tìm thấy file', 'Đơn này không có file chứng chỉ, hoặc file đã bị xóa.', [['/admin/expert-applications.php', 'Danh sách đơn']]);
}

$types = array_flip(COACH_CERT_TYPES);
$ext = pathinfo($path, PATHINFO_EXTENSION);
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="chung-chi-chuyen-gia-' . (int)$app['id'] . '.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
if ($ext !== 'pdf') {
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
}
readfile($path);
