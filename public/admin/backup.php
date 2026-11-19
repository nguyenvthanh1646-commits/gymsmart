<?php
/**
 * admin/backup.php — tải bản sao lưu database dạng .sql (G2, A-51).
 * Chỉ ADMIN, chỉ nhận POST + CSRF (không cho tải bằng một đường link GET, tránh bị lừa bấm), phải tick ô xác nhận.
 * Mỗi lần tải ghi admin_audit_log mã 'system.backup'. Nội dung do includes/health.php → healthWriteBackup() viết.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit-log.php';
require_once __DIR__ . '/../includes/health.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/health.php');
    exit;
}
if (!verifyCsrf()) {
    showCsrfError();
}
if (($_POST['confirm'] ?? '') !== '1') {
    $_SESSION['adm_health_flash'] = ['error', 'Hãy tick ô xác nhận trước khi tải bản sao lưu.'];
    header('Location: /admin/health.php');
    exit;
}

// Ghi nhật ký trước khi gửi file (sau khi gửi thì không còn đổi được header)
auditLog($pdo, 'system.backup', 'system', null, 'Tải bản sao lưu database');

set_time_limit(300);
$file = 'gymsmart-sao-luu-' . date('Y-m-d-His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$out = fopen('php://output', 'wb');
healthWriteBackup($pdo, $out);
fclose($out);
exit;
