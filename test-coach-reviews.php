<?php
// test-coach-reviews.php — kiểm tra đánh giá HLV (includes/coach-reviews.php): quy tắc thuần và luồng DB trên SQLite.
// Chạy ở gốc repo: php test-coach-reviews.php

require __DIR__ . '/public/includes/coach-reviews.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}
function errOf(callable $fn): ?string
{
    try {
        $fn();
    } catch (CoachException $e) {
        return $e->getMessage();
    }
    return null;
}
$now = '2026-11-18 12:00:00';

// ===== Quy tắc thuần =====
$e = coachReviewEligibility(['status' => 'PENDING', 'accepted_at' => null, 'ended_at' => null], 5, $now);
check('Yêu cầu còn chờ duyệt: chưa đánh giá được', !$e['ok']);
$e = coachReviewEligibility(['status' => 'ACTIVE', 'accepted_at' => '2026-11-10 09:00:00', 'ended_at' => null], 3, $now);
check('Tập mới 8 ngày: chưa được, báo còn 6 ngày', !$e['ok'] && str_contains($e['reason'], 'còn 6 ngày'));
$e = coachReviewEligibility(['status' => 'ACTIVE', 'accepted_at' => '2026-10-01 09:00:00', 'ended_at' => null], 0, $now);
check('Đủ ngày nhưng chưa ghi buổi tập nào: chưa được', !$e['ok'] && str_contains($e['reason'], 'ít nhất 1 buổi'));
check('Đủ 14 ngày và có tập: được', coachReviewEligibility(['status' => 'ACTIVE', 'accepted_at' => '2026-10-01 09:00:00', 'ended_at' => null], 2, $now)['ok']);
$e = coachReviewEligibility(['status' => 'ENDED', 'accepted_at' => '2026-10-01 09:00:00', 'ended_at' => '2026-10-05 09:00:00'], 2, $now);
check('Đã kết thúc sau 4 ngày: không đánh giá được (tính tới ngày kết thúc, không tới hôm nay)', !$e['ok']);
check('Đã kết thúc sau 20 ngày, có tập: được', coachReviewEligibility(['status' => 'ENDED', 'accepted_at' => '2026-10-01 09:00:00', 'ended_at' => '2026-10-21 09:00:00'], 4, $now)['ok']);
check('Bị từ chối: không đánh giá được', !coachReviewEligibility(['status' => 'REJECTED', 'accepted_at' => null, 'ended_at' => '2026-10-01'], 0, $now)['ok']);
check('Số sao 0 hoặc 6 bị chặn', coachReviewValidate(0, '') !== null && coachReviewValidate(6, '') !== null && coachReviewValidate('abc', '') !== null);
check('Nhận xét 501 ký tự bị chặn', coachReviewValidate(5, str_repeat('a', 501)) !== null);
check('5 sao, không nhận xét: hợp lệ', coachReviewValidate('5', '') === null);
check('Tên công khai chỉ còn tên', coachReviewerName('Nguyễn Văn An') === 'Học viên An' && coachReviewerName('') === 'Học viên');

// ===== Luồng DB =====
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "[BỎ QUA] Không có pdo_sqlite nên không chạy phần DB\n";
} else {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT, role TEXT)");
    $pdo->exec("CREATE TABLE coach_clients (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_id INT, client_id INT, status TEXT, accepted_at TEXT, ended_at TEXT)");
    $pdo->exec("CREATE TABLE workout_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, log_date TEXT)");
    $pdo->exec("CREATE TABLE coach_reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_client_id INT UNIQUE, coach_id INT, client_id INT, rating INT, comment TEXT, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT, title TEXT, body TEXT DEFAULT '', link TEXT DEFAULT '', read_at TEXT, created_at TEXT)");
    foreach ([[2, 'HLV Một', 'COACH'], [4, 'Nguyễn Văn An', 'USER'], [5, 'Trần Bình', 'USER'], [6, 'Lê Chi', 'USER']] as $u) {
        $pdo->prepare('INSERT INTO users VALUES (?, ?, ?)')->execute($u);
    }
    $old = date('Y-m-d H:i:s', strtotime('-30 days'));
    $new = date('Y-m-d H:i:s', strtotime('-3 days'));
    $pdo->prepare("INSERT INTO coach_clients (id, coach_id, client_id, status, accepted_at) VALUES (1, 2, 4, 'ACTIVE', ?), (2, 2, 5, 'ACTIVE', ?), (3, 2, 6, 'ACTIVE', ?)")
        ->execute([$old, $new, $old]);
    $pdo->exec("INSERT INTO workout_logs (user_id, log_date) VALUES (4, '" . date('Y-m-d', strtotime('-10 days')) . "'), (5, '" . date('Y-m-d') . "')");

    check('Học viên đủ điều kiện viết được đánh giá', errOf(fn() => coachReviewSave($pdo, 4, 1, 5, '  HLV nhiệt tình, lịch rõ ràng  ')) === null);
    check('Lưu đúng: 5 sao, nhận xét đã bỏ khoảng trắng thừa', $pdo->query('SELECT rating || "|" || comment FROM coach_reviews WHERE coach_client_id = 1')->fetchColumn() === '5|HLV nhiệt tình, lịch rõ ràng');
    check('HLV nhận thông báo đánh giá mới, chỉ thấy tên rút gọn', str_contains((string)$pdo->query("SELECT body FROM notifications WHERE user_id = 2 AND type = 'coach.review'")->fetchColumn(), 'Học viên An'));
    check('Sửa đánh giá: vẫn 1 dòng, điểm mới 4', errOf(fn() => coachReviewSave($pdo, 4, 1, 4, 'Ổn')) === null
        && (int)$pdo->query('SELECT COUNT(*) FROM coach_reviews')->fetchColumn() === 1 && (int)$pdo->query('SELECT rating FROM coach_reviews')->fetchColumn() === 4);
    check('Mới tập 3 ngày: bị chặn', errOf(fn() => coachReviewSave($pdo, 5, 2, 5, '')) !== null);
    check('Đủ ngày nhưng chưa ghi buổi nào: bị chặn', errOf(fn() => coachReviewSave($pdo, 6, 3, 5, '')) !== null);
    check('Học viên khác không đánh giá thay quan hệ của người khác', errOf(fn() => coachReviewSave($pdo, 5, 1, 1, 'Tệ')) !== null);
    check('Số sao sai bị chặn trước khi đụng DB', errOf(fn() => coachReviewSave($pdo, 4, 1, 9, '')) === 'Hãy chọn từ 1 đến 5 sao.');
    $sum = coachRatingSummary($pdo, [2, 99]);
    check('Tổng hợp: HLV 2 có 1 đánh giá trung bình 4,0; HLV chưa có thì không có dòng', $sum === [2 => ['avg' => 4.0, 'count' => 1]]);
    check('Danh sách công khai không có họ tên đầy đủ', coachReviewsForCoach($pdo, 2)[0]['name'] === 'Học viên An');
    check('Học viên khác không xóa được đánh giá', errOf(fn() => coachReviewDelete($pdo, 5, 1)) !== null);
    check('Học viên xóa đánh giá của mình', errOf(fn() => coachReviewDelete($pdo, 4, 1)) === null && coachRatingSummary($pdo, [2]) === []);
    coachReviewSave($pdo, 4, 1, 2, 'Nội dung vi phạm');
    $rid = (int)$pdo->query('SELECT id FROM coach_reviews')->fetchColumn();
    check('ADMIN xóa đánh giá vi phạm', str_contains(coachReviewAdminDelete($pdo, $rid), '2 sao') && (int)$pdo->query('SELECT COUNT(*) FROM coach_reviews')->fetchColumn() === 0);
}

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
