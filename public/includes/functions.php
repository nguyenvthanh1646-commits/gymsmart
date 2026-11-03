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

/**
 * Quy ước cột exercises.image_url: chỉ lưu TÊN FILE nằm trong public/img/exercises/ (ví dụ "12.jpg").
 * Hàm này kiểm tra tên file rồi dựng đường dẫn ảnh để in vào src. Tên lạ (có "/", "..", "javascript:"...) thì trả null.
 */
function exerciseImageFileOk(?string $file): bool
{
    return is_string($file) && preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]{0,99}\.(jpe?g|png|webp|svg)$/i', $file) === 1;
}

function exerciseImageSrc(?string $file): ?string
{
    return exerciseImageFileOk($file) ? '/img/exercises/' . $file : null;
}

/**
 * Vài bài tập cho khách xem trước ở trang chủ: mỗi nhóm cơ lấy bài có id nhỏ nhất và có ảnh.
 * Lỗi database thì trả mảng rỗng để trang vẫn chạy và hiện trạng thái rỗng.
 */
function guestPreviewExercises(PDO $pdo, int $limit = 4): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT id, name, muscle_group, difficulty, image_url
             FROM exercises
             WHERE id IN (
                 SELECT MIN(id) FROM exercises
                 WHERE image_url IS NOT NULL AND image_url <> ''
                 GROUP BY muscle_group
             )
             ORDER BY id
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Số liệu thật của thư viện bài tập. Lỗi database thì trả 0 để trang ẩn câu có số. */
function guestExerciseStats(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS total, COUNT(DISTINCT muscle_group) AS muscle_groups FROM exercises');
        $stmt->execute();
        $row = $stmt->fetch();
        return ['exercises' => (int)$row['total'], 'muscleGroups' => (int)$row['muscle_groups']];
    } catch (Throwable $e) {
        return ['exercises' => 0, 'muscleGroups' => 0];
    }
}
