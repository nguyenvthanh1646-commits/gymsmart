<?php
/**
 * equipment-db.php — truy vấn cho dụng cụ / bài tương đương / kỷ lục cá nhân (A-47). Logic thuần nằm ở equipment.php.
 * DB cũ chưa chạy sql/exercise_equipment.sql (chưa có cột equipment): các hàm vẫn chạy, coi mọi bài là "không rõ dụng cụ"
 * và trang ẩn phần lọc theo dụng cụ (exerciseEquipmentReady() = false).
 */
require_once __DIR__ . '/equipment.php';

/** Bảng exercises đã có cột equipment chưa (nhớ kết quả trong 1 lần chạy trang). */
function exerciseEquipmentReady(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $pdo->query('SELECT equipment FROM exercises LIMIT 1');
            $ready = true;
        } catch (PDOException $e) {
            $ready = false;
        }
    }
    return $ready;
}

/** Toàn bộ bài tập kèm dụng cụ: [['id','name','muscle_group','equipment'], ...] */
function exerciseLibraryWithEquipment(PDO $pdo): array
{
    $sql = exerciseEquipmentReady($pdo)
        ? 'SELECT id, name, muscle_group, equipment FROM exercises ORDER BY id'
        : "SELECT id, name, muscle_group, 'NONE' AS equipment FROM exercises ORDER BY id";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); // audit:ok (2 câu SQL viết sẵn, không có dữ liệu người dùng)
}

/** Nhật ký của user để tính kỷ lục; $exerciseIds rỗng = mọi bài. Định dạng hợp với personalRecords(). */
function recordLogs(PDO $pdo, int $userId, array $exerciseIds = []): array
{
    $sql = 'SELECT l.id, l.exercise_id, e.name, l.log_date AS date, l.weight_kg_used AS weight, l.reps_completed AS reps
            FROM workout_logs l JOIN exercises e ON e.id = l.exercise_id WHERE l.user_id = ?';
    $params = [$userId];
    $ids = array_values(array_unique(array_map('intval', $exerciseIds)));
    if ($ids) {
        $sql .= ' AND l.exercise_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';   // chỉ ghép dấu ?
        $params = array_merge($params, $ids);
    }
    $st = $pdo->prepare($sql); // audit:ok
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
