<?php
/**
 * api/log-session.php — lưu cả buổi tập từ chế độ "Đang tập" (workout-session.php) bằng MỘT lần bấm.
 *
 * POST (form), kèm csrf_token:
 *   rows[i][row_id]              id dòng workout_plan_exercises (phải thuộc lịch ACTIVE của chính người đăng nhập)
 *   rows[i][sets][j][weight]     mức tạ của set j (kg, 0-500; 0 = không dùng tạ)
 *   rows[i][sets][j][reps]       số lặp của set j (1-100)
 * Chỉ gửi các set đã đánh dấu xong. Máy chủ tự gộp các set thành một dòng nhật ký mỗi bài (summarizeSessionSets),
 * không tin số liệu đã gộp từ trình duyệt.
 * Trả về (kiểu cũ, giống log-workout.php): { "success": true, "message": "...", "saved": 5 } | { "success": false, "message": "..." }
 */
require_once __DIR__ . '/../includes/auth.php';  // bật session với cookie an toàn (không gọi requireLogin: API trả JSON)
require_once __DIR__ . '/../includes/csrf.php';
header('Content-Type: application/json; charset=utf-8');

const SESSION_MAX_EXERCISES = 20;
const SESSION_MAX_SETS = 10;

function sessionOut(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sessionOut(405, ['success' => false, 'message' => 'Chỉ chấp nhận POST.']);
}
if (empty($_SESSION['user_id'])) {
    sessionOut(401, ['success' => false, 'message' => 'Bạn cần đăng nhập.']);
}
if (!verifyCsrf()) {
    sessionOut(403, ['success' => false, 'message' => 'Phiên làm việc đã hết hạn, hãy tải lại trang.']);
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/workout-plan-db.php';
require_once __DIR__ . '/../includes/progression.php';

$userId = (int)$_SESSION['user_id'];
$rows = $_POST['rows'] ?? null;
if (!is_array($rows) || $rows === []) {
    sessionOut(422, ['success' => false, 'message' => 'Chưa có set nào được đánh dấu xong.']);
}
if (count($rows) > SESSION_MAX_EXERCISES) {
    sessionOut(422, ['success' => false, 'message' => 'Một buổi tối đa ' . SESSION_MAX_EXERCISES . ' bài.']);
}

$entries = [];
$seenRows = [];
foreach ($rows as $row) {
    $rowId = is_array($row) ? (int)($row['row_id'] ?? 0) : 0;
    $sets = is_array($row) && is_array($row['sets'] ?? null) ? $row['sets'] : [];
    if ($rowId <= 0 || $sets === [] || count($sets) > SESSION_MAX_SETS || isset($seenRows[$rowId])) {
        sessionOut(422, ['success' => false, 'message' => 'Dữ liệu buổi tập không hợp lệ, hãy tải lại trang.']);
    }
    $seenRows[$rowId] = true;
    $clean = [];
    foreach ($sets as $set) {
        $weight = is_array($set) ? (float)str_replace(',', '.', (string)($set['weight'] ?? '')) : -1;
        $reps = is_array($set) ? (int)($set['reps'] ?? 0) : 0;
        if (!is_finite($weight) || $weight < 0 || $weight > 500 || $reps < 1 || $reps > 100) {
            sessionOut(422, ['success' => false, 'message' => 'Mỗi set cần mức tạ 0-500 kg và số lặp 1-100.']);
        }
        $clean[] = ['weight' => $weight, 'reps' => $reps];
    }
    $sum = summarizeSessionSets($clean);
    if ($sum !== null) {
        $entries[] = ['row_id' => $rowId] + $sum;
    }
}

try {
    $saved = saveWorkoutSession($pdo, $userId, $entries);
    sessionOut(200, ['success' => true, 'saved' => $saved, 'message' => "Đã lưu $saved bài vào nhật ký."]);
} catch (InvalidArgumentException $e) {
    sessionOut(422, ['success' => false, 'message' => $e->getMessage()]);
} catch (PlanNotFoundException $e) {
    sessionOut(404, ['success' => false, 'message' => 'Lịch tập đã thay đổi. Tải lại trang rồi lưu lại.']);
} catch (Throwable $e) {
    error_log('log-session error: ' . $e->getMessage());
    sessionOut(500, ['success' => false, 'message' => 'Lỗi hệ thống khi lưu buổi tập.']);
}
