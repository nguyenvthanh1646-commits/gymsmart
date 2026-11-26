<?php
/**
 * admin/certificate.php?id=<id đơn> — trả về file chứng chỉ đính kèm trong đơn đăng ký HLV. CHỈ ADMIN.
 *
 * Vì sao không để file trong public/? Chứng chỉ là giấy tờ cá nhân; để ngoài public/ (storage/certificates/) thì
 * không ai mở được bằng cách đoán URL, chỉ trang này đọc ra sau khi requireAdmin().
 * Tên file lấy từ DB nhưng vẫn kiểm tra đúng định dạng do máy chủ đặt (coachCertNameOk) và nằm đúng thư mục (realpath),
 * nên giá trị lạ như "../../config.php" không đọc được.
 * Loại nội dung gửi về trình duyệt lấy theo đuôi đã kiểm tra lúc tải lên; thêm nosniff để trình duyệt không tự đoán lại.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';

requireAdmin();
$app = coachTablesReady($pdo) && coachExtrasReady($pdo) ? coachFindApplication($pdo, coachParseId($_GET['id'] ?? null)) : null;
$name = $app['certificate_file'] ?? null;
$dir = realpath(coachCertDir());
$path = (coachCertNameOk($name) && $dir !== false) ? realpath($dir . DIRECTORY_SEPARATOR . $name) : false;
if ($path === false || strpos($path, $dir . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    showError(404, 'Không tìm thấy file', 'Đơn này không có file chứng chỉ, hoặc file đã bị xóa.', [['/admin/coach-applications.php', 'Danh sách đơn']]);
}

$types = array_flip(COACH_CERT_TYPES);   // đuôi => loại nội dung
$ext = pathinfo($path, PATHINFO_EXTENSION);
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="chung-chi-don-' . (int)$app['id'] . '.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
if ($ext !== 'pdf') {   // ảnh: khóa mọi thứ khác; PDF thì để trình xem PDF của trình duyệt chạy được
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
}
readfile($path);
