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

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // báo lỗi rõ ràng thay vì im lặng
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // trả kết quả dạng mảng dễ đọc
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Khi mới học, in lỗi ra để debug. Trước khi nộp bài, nên đổi thành
    // thông báo chung chung để không lộ thông tin hệ thống ra ngoài.
    die('Không kết nối được database: ' . $e->getMessage());
}
