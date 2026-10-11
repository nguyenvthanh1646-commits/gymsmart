<?php
/**
 * functions.php — nơi chứa các hàm tiện ích dùng chung nhiều nơi.
 *
 * Quy ước: mỗi người khi viết tính năng của mình, nếu thấy 1 đoạn logic
 * dùng lại ở nhiều trang (VD: lấy thông tin user hiện tại, format ngày
 * tháng...) thì tách thành hàm và thêm vào đây, thay vì copy-paste.
 *
 * Ví dụ:
 *
 * function getCurrentUser(PDO $pdo): ?array
 * {
 *     if (!isset($_SESSION['user_id'])) {
 *         return null;
 *     }
 *     $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
 *     $stmt->execute([$_SESSION['user_id']]);
 *     return $stmt->fetch() ?: null;
 * }
 */

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
