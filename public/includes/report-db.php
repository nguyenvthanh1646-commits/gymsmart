<?php
/**
 * report-db.php — truy vấn cho trang Báo cáo & thành tích (report.php) và tải dữ liệu cá nhân (export-my-data.php).
 * Mọi hàm nhận $userId lấy từ session ở nơi gọi; chỉ đọc dữ liệu của chính người đó.
 */

/** Các ngày đã tập (không trùng, cũ đến mới). */
function reportTrainingDates(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare('SELECT DISTINCT log_date FROM workout_logs WHERE user_id = ? ORDER BY log_date');
    $st->execute([$userId]);
    return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Các ngày đã ghi số đo (không trùng, cũ đến mới). */
function reportMetricDates(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare('SELECT DISTINCT recorded_date FROM body_metrics WHERE user_id = ? ORDER BY recorded_date');
    $st->execute([$userId]);
    return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Nhật ký trong khoảng ngày [from, to]: [['date','name','sets','reps','weight'], ...] */
function reportLogsBetween(PDO $pdo, int $userId, string $from, string $to): array
{
    $st = $pdo->prepare(
        'SELECT l.log_date AS date, e.name, l.sets_completed AS sets, l.reps_completed AS reps, l.weight_kg_used AS weight
         FROM workout_logs l JOIN exercises e ON e.id = l.exercise_id
         WHERE l.user_id = ? AND l.log_date BETWEEN ? AND ? ORDER BY l.log_date, l.id'
    );
    $st->execute([$userId, $from, $to]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Số đo trong khoảng ngày [from, to], cũ đến mới: [['date','weight'], ...] */
function reportMetricsBetween(PDO $pdo, int $userId, string $from, string $to): array
{
    $st = $pdo->prepare('SELECT recorded_date AS date, weight_kg AS weight FROM body_metrics WHERE user_id = ? AND recorded_date BETWEEN ? AND ? ORDER BY recorded_date, id');
    $st->execute([$userId, $from, $to]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Toàn bộ nhật ký tập để tải CSV (mới nhất trước), kèm ghi chú; ô nào bắt đầu bằng ký tự công thức thì csvSafe() xử lý. */
function reportAllLogsForExport(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare(
        'SELECT l.log_date, e.name, e.muscle_group, l.sets_completed, l.reps_completed, l.weight_kg_used, l.note
         FROM workout_logs l JOIN exercises e ON e.id = l.exercise_id WHERE l.user_id = ? ORDER BY l.log_date DESC, l.id DESC'
    );
    $st->execute([$userId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Toàn bộ số đo để tải CSV (mới nhất trước). */
function reportAllMetricsForExport(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare('SELECT recorded_date, weight_kg, body_fat_percent, chest_cm, waist_cm, arm_cm FROM body_metrics WHERE user_id = ? ORDER BY recorded_date DESC, id DESC');
    $st->execute([$userId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
