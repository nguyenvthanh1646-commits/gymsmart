<?php
/**
 * api/body-metrics.php — trả lịch sử số đo cơ thể của CHÍNH user đang đăng nhập, dùng để vẽ biểu đồ (C-4).
 *
 *   GET /api/body-metrics.php                          → toàn bộ
 *   GET /api/body-metrics.php?from=2026-09-01          → từ ngày đó
 *   GET /api/body-metrics.php?from=2026-09-01&to=2026-09-30
 *
 * Thành công (200):
 *   { "success": true,
 *     "data": [ { "id": 3, "date": "2026-09-15", "weightKg": 79, "bodyFatPercent": null,
 *                 "chestCm": null, "waistCm": 88.5, "armCm": null }, ... ] }   // cũ → mới, sẵn cho trục thời gian
 *   GET /api/body-metrics.php?client_id=12              → HLV xem số đo của học viên đang tập với mình (khu /coach/)
 * Lỗi: 400 (ngày sai), 401 (chưa đăng nhập), 404 (client_id không phải học viên ACTIVE của HLV), 405 (không phải GET),
 *      500 (lỗi máy chủ) — đều dạng { "success": false, "error": "..." }
 *
 * Bảo mật: user_id lấy từ session (người khác không đọc được số đo của bạn bằng cách đổi URL);
 * câu SQL dùng prepared statement; không trả id người dùng, email hay dữ liệu thừa.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/db.php';

apiBoot();   // chỉ GET + bắt buộc đăng nhập (JSON 401, không chuyển hướng như requireLogin())
$userId = (int)$_SESSION['user_id'];
[$from, $to] = apiDateRange();

// HLV xem biểu đồ của học viên: ?client_id=... Chỉ được khi người gọi đang là COACH (đọc lại trong DB, không tin session)
// VÀ đang có quan hệ ACTIVE với học viên đó. Sai thì 404 như "không có", để không lộ id nào là học viên có thật.
if (isset($_GET['client_id'])) {
    require_once __DIR__ . '/../includes/coach-db.php';
    $clientId = filter_var($_GET['client_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($clientId === false || !coachTablesReady($pdo) || coachActiveRelationId($pdo, $userId, $clientId) === null) {
        apiError(404, 'Không tìm thấy học viên này trong danh sách của bạn.');
    }
    $userId = $clientId;   // từ đây đọc số đo của học viên (đã kiểm tra quyền ở trên)
}

// Ghép điều kiện lọc ngày: chỉ thêm khi có tham số; giá trị luôn đi qua dấu ? (không nối vào chuỗi SQL)
$sql = 'SELECT id, recorded_date, weight_kg, body_fat_percent, chest_cm, waist_cm, arm_cm
        FROM body_metrics WHERE user_id = ?';
$params = [$userId];
if ($from !== null) {
    $sql .= ' AND recorded_date >= ?';
    $params[] = $from;
}
if ($to !== null) {
    $sql .= ' AND recorded_date <= ?';
    $params[] = $to;
}
$sql .= ' ORDER BY recorded_date ASC, id ASC LIMIT 2000';   // trần 2000 dòng: chặn trả quá nhiều dữ liệu

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$data = [];
foreach ($stmt->fetchAll() as $r) {
    $data[] = [
        'id'             => (int)$r['id'],
        'date'           => $r['recorded_date'],
        'weightKg'       => apiNum($r['weight_kg']),
        'bodyFatPercent' => apiNum($r['body_fat_percent']),
        'chestCm'        => apiNum($r['chest_cm']),
        'waistCm'        => apiNum($r['waist_cm']),
        'armCm'          => apiNum($r['arm_cm']),
    ];
}
apiJson(200, ['success' => true, 'data' => $data]);
