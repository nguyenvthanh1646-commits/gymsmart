<?php
/**
 * api/nutrition-history.php — lịch sử các lần tính TDEE/Macro của CHÍNH user đang đăng nhập (bảng nutrition_targets),
 * dùng để vẽ biểu đồ calo và macro theo ngày (C-4).
 *
 *   GET /api/nutrition-history.php
 *   GET /api/nutrition-history.php?from=2026-09-01&to=2026-09-30
 *   GET /api/nutrition-history.php?client_id=12   → HLV xem của học viên đang tập với mình (khu /coach/), sai thì 404
 *
 * Thành công (200):
 *   { "success": true,
 *     "data": [ { "date": "2026-09-15", "tdeeKcal": 2400, "targetKcal": 1920,
 *                 "proteinG": 125, "carbsG": 200, "fatG": 53 }, ... ] }   // cũ → mới
 * Mỗi ngày chỉ có một dòng (trang Dinh dưỡng cập nhật dòng của ngày hôm nay thay vì thêm trùng).
 * Lỗi: 400, 401, 405, 500 — dạng { "success": false, "error": "..." }. Bảo mật giống api/body-metrics.php.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/db.php';

apiBoot();   // chỉ GET + bắt buộc đăng nhập (JSON 401)
$userId = (int)$_SESSION['user_id'];
[$from, $to] = apiDateRange();

// HLV xem của học viên: cùng cách kiểm tra với api/body-metrics.php (vai trò COACH trong DB + quan hệ ACTIVE)
if (isset($_GET['client_id'])) {
    require_once __DIR__ . '/../includes/coach-db.php';
    $clientId = filter_var($_GET['client_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($clientId === false || !coachTablesReady($pdo) || coachActiveRelationId($pdo, $userId, $clientId) === null) {
        apiError(404, 'Không tìm thấy học viên này trong danh sách của bạn.');
    }
    $userId = $clientId;
}

$sql = 'SELECT calculated_date, tdee_kcal, target_kcal, protein_g, carbs_g, fat_g
        FROM nutrition_targets WHERE user_id = ?';
$params = [$userId];
if ($from !== null) {
    $sql .= ' AND calculated_date >= ?';
    $params[] = $from;
}
if ($to !== null) {
    $sql .= ' AND calculated_date <= ?';
    $params[] = $to;
}
$sql .= ' ORDER BY calculated_date ASC, id ASC LIMIT 2000';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$data = [];
foreach ($stmt->fetchAll() as $r) {
    $data[] = [
        'date'       => $r['calculated_date'],
        'tdeeKcal'   => apiNum($r['tdee_kcal']),
        'targetKcal' => apiNum($r['target_kcal']),
        'proteinG'   => apiNum($r['protein_g']),
        'carbsG'     => apiNum($r['carbs_g']),
        'fatG'       => apiNum($r['fat_g']),
    ];
}
apiJson(200, ['success' => true, 'data' => $data]);
