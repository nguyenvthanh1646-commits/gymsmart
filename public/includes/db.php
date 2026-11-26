<?php
/**
 * db.php — tạo kết nối PDO tới MySQL, dùng chung cho mọi trang/API.
 *
 * Cách dùng ở file khác:
 *   require_once __DIR__ . '/includes/db.php';
 *   $stmt = $pdo->prepare("SELECT * FROM exercises WHERE id = ?");
 *   $stmt->execute([$id]);
 *   $exercise = $stmt->fetch();
 *
 * Giải thích:
 * - PDO là lớp PHP dùng để giao tiếp với database an toàn hơn mysqli cũ.
 * - "prepare()" tạo câu lệnh SQL có dấu "?" thay cho giá trị thật (gọi là
 *   prepared statement) — PHP sẽ tự escape giá trị khi execute(), nên
 *   không bao giờ bị SQL Injection dù người dùng nhập gì vào form.
 * - KHÔNG BAO GIỜ viết kiểu: "SELECT * FROM users WHERE email = '$email'"
 *   vì $email có thể chứa mã độc SQL.
 */

require_once __DIR__ . '/../../config.php';

// Múi giờ: PHP (php.ini của XAMPP mặc định Europe/Berlin) và MySQL (theo giờ máy) có thể lệch nhau vài giờ.
// Khi lệch, "hôm nay" tính bằng date() của PHP khác CURDATE() của MySQL vào đầu ngày (ví dụ 0-6 giờ sáng).
// Nên đặt cả hai về cùng một múi giờ ngay khi kết nối. Đổi được bằng APP_TIMEZONE trong config.php.
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Ho_Chi_Minh');

// DB_PORT là tùy chọn: ai chưa khai báo trong config.php thì dùng cổng mặc định 3306
$port = defined('DB_PORT') ? DB_PORT : 3306;
// Chỉ khi chạy dòng lệnh (tools/*.php): biến môi trường GYMSMART_DB cho phép trỏ sang database thử,
// ví dụ khi tools/install.php nạp dữ liệu thử vào gymsmart_demo. Trang web luôn dùng DB_NAME trong config.php.
$dbName = (PHP_SAPI === 'cli' && getenv('GYMSMART_DB')) ? getenv('GYMSMART_DB') : DB_NAME;
$dsn  = 'mysql:host=' . DB_HOST . ';port=' . $port . ';dbname=' . $dbName . ';charset=utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // báo lỗi rõ ràng thay vì im lặng
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // trả kết quả dạng mảng dễ đọc
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Cho phiên MySQL này dùng cùng độ lệch giờ với PHP (date('P') cho dạng "+07:00"), để NOW()/CURDATE() khớp date()
    $pdo->exec('SET time_zone = ' . $pdo->quote(date('P'))); // audit:ok (giá trị do PHP tạo, đã quote)
} catch (PDOException $e) {
    // Chi tiết lỗi (host, tên user DB...) chỉ ghi vào log máy chủ (XAMPP: apache/logs/error.log),
    // không in ra trình duyệt để khỏi lộ thông tin hệ thống. Người dùng chỉ thấy thông báo chung.
    error_log('db.php: ' . $e->getMessage());
    http_response_code(500);
    die('Không kết nối được database. Kiểm tra MySQL trong XAMPP đã chạy và thông tin trong config.php.');
}
