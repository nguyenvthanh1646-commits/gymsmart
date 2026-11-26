<?php
/**
 * test-coach-billing.php — giá tập với HLV, buổi thử miễn phí, lịch hẹn, gói buổi / gói tháng (A-54). Chạy: php test-coach-billing.php
 * Phần 1: quy tắc thuần (includes/coach-billing-rules.php), không cần DB.
 * Phần 2: lớp DB (includes/coach-billing.php) trên SQLite in-memory, cần pdo_sqlite.
 */
require __DIR__ . '/public/includes/coach-db.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $fails++;
}
function errOf(callable $fn): ?string
{
    try { $fn(); } catch (CoachException $e) { return $e->getMessage(); }
    return null;
}

// ===================== PHẦN 1: QUY TẮC THUẦN =====================
check('Tiền: "300.000" → 300000', coachParseMoney('300.000') === 300000);
check('Tiền: "1,500,000 VNĐ" và "250 000đ" đọc được', coachParseMoney('1,500,000 VNĐ') === 1500000 && coachParseMoney('250 000đ') === 250000);
check('Tiền: ô trống → null (không bán kiểu này)', coachParseMoney('  ') === null);
check('Tiền: chữ, số âm, mảng → false', coachParseMoney('ba trăm') === false && coachParseMoney('-5000') === false && coachParseMoney(['1']) === false);
check('coachMoney: 2500000 → "2.500.000đ"', coachMoney(2500000) === '2.500.000đ');

$okPricing = ['price_per_session' => '300.000', 'price_per_month' => '', 'trial_sessions' => '2', 'payment_note' => "  Vietcombank 0123\r\nChủ TK: Nguyễn An  "];
$v = validateCoachPricing($okPricing);
check('Giá hợp lệ: chỉ bán theo buổi', $v['errors'] === [] && $v['data']['price_per_session'] === 300000 && $v['data']['price_per_month'] === null && $v['data']['trial_sessions'] === 2);
check('Hướng dẫn thanh toán được làm sạch', $v['data']['payment_note'] === "Vietcombank 0123\nChủ TK: Nguyễn An");
check('Không nhập giá nào → lỗi', isset(validateCoachPricing(['price_per_session' => '', 'price_per_month' => ''] + $okPricing)['errors']['price_per_session']));
check('Giá 1 buổi 20.000đ (dưới mức tối thiểu) → lỗi', isset(validateCoachPricing(['price_per_session' => '20000'] + $okPricing)['errors']['price_per_session']));
check('Giá lẻ 300.500đ → lỗi làm tròn nghìn', str_contains((string)(validateCoachPricing(['price_per_session' => '300500'] + $okPricing)['errors']['price_per_session'] ?? ''), 'nghìn'));
check('Giá tháng 60 triệu (quá mức) → lỗi', isset(validateCoachPricing(['price_per_month' => '60.000.000'] + $okPricing)['errors']['price_per_month']));
check('Buổi thử 0 hoặc 4 → lỗi (chỉ 1-3)', isset(validateCoachPricing(['trial_sessions' => '0'] + $okPricing)['errors']['trial_sessions'])
    && isset(validateCoachPricing(['trial_sessions' => '4'] + $okPricing)['errors']['trial_sessions']));
check('Hướng dẫn thanh toán quá 300 ký tự → lỗi', isset(validateCoachPricing(['payment_note' => str_repeat('a', 301)] + $okPricing)['errors']['payment_note']));

$pricing = ['price_per_session' => 300000, 'price_per_month' => 2400000];
$q = coachOrderQuote($pricing, 'SESSIONS', '8');
check('Báo giá gói 8 buổi = 2.400.000đ', $q['error'] === null && $q['quote']['amount'] === 2400000 && $q['quote']['unit_price'] === 300000);
check('Báo giá gói 3 tháng = 7.200.000đ', coachOrderQuote($pricing, 'MONTH', 3)['quote']['amount'] === 7200000);
check('Số buổi ngoài danh sách gói (5) → lỗi', coachOrderQuote($pricing, 'SESSIONS', 5)['error'] !== null);
check('HLV không bán theo tháng → lỗi', coachOrderQuote(['price_per_session' => 300000, 'price_per_month' => null], 'MONTH', 1)['error'] !== null);
check('Loại gói lạ → lỗi', coachOrderQuote($pricing, 'YEAR', 1)['error'] !== null);
check('Tên gói', coachOrderLabel('SESSIONS', 4) === 'Gói 4 buổi' && coachOrderLabel('MONTH', 1) === 'Gói 1 tháng');

check('Gói tháng đầu tiên: 01/12 → 30/12', coachMonthWindow(null, '2026-12-01', 1) === ['2026-12-01', '2026-12-30']);
check('Gói tháng nối tiếp gói còn hạn tới 10/12 → 11/12 - 09/01', coachMonthWindow('2026-12-10', '2026-12-01', 1) === ['2026-12-11', '2027-01-09']);
check('Gói cũ đã hết hạn → bắt đầu từ hôm nay', coachMonthWindow('2026-11-01', '2026-12-01', 1) === ['2026-12-01', '2026-12-30']);
check('Gói 3 tháng dài 90 ngày', coachMonthWindow(null, '2026-12-01', 3)[1] === '2027-02-28');
$w = [['starts_on' => '2026-12-11', 'ends_on' => '2027-01-09'], ['starts_on' => '2026-11-11', 'ends_on' => '2026-12-10']];
check('Hai gói nối tiếp được gộp: phủ tới 09/01', coachMonthCoverEnd($w, '2026-12-01') === '2027-01-09');
check('Ngày ngoài mọi gói → null', coachMonthCoverEnd($w, '2027-02-01') === null && !coachDateCovered($w, '2027-02-01') && coachDateCovered($w, '2026-12-11'));

check('Ngày không có thật (30/02) → null', coachBookingStart('2026-02-30', '18:00') === null);
check('Ghép ngày giờ đúng', coachBookingStart('2026-12-03', '18:30') === '2026-12-03 18:30:00');
$now = '2026-12-01 10:00:00';
check('Giờ 04:30 (trước 05:00) → lỗi', coachBookingTimeError('2026-12-03 04:30:00', $now) !== null);
check('Giờ 18:15 (không tròn 30 phút) → lỗi', coachBookingTimeError('2026-12-03 18:15:00', $now) !== null);
check('Đặt trước dưới 2 giờ → lỗi', coachBookingTimeError('2026-12-01 11:30:00', $now) !== null);
check('Đặt quá 30 ngày → lỗi', coachBookingTimeError('2027-01-05 18:00:00', $now) !== null);
check('Đặt hợp lệ', coachBookingTimeError('2026-12-03 21:00:00', $now) === null);
check('Danh sách giờ: 05:00 → 21:00, 33 mốc', ($t = coachBookingTimes())[0] === '05:00' && end($t) === '21:00' && count($t) === 33);
check('Cách nhau 59 phút là trùng, 60 phút không trùng', coachSessionsClash('2026-12-03 18:00:00', '2026-12-03 18:59:00') && !coachSessionsClash('2026-12-03 18:00:00', '2026-12-03 19:00:00'));
check('Giờ hẹn hiển thị', coachSessionWhen('2026-11-27 18:00:00') === '18:00 Thứ 6, 27/11/2026' && coachSessionWhen('2026-11-29 07:30:00') === '07:30 Chủ nhật, 29/11/2026');

$before = '2026-12-03 10:00:00';
$after = '2026-12-03 19:00:00';
$at = '2026-12-03 18:00:00';
check('HLV xác nhận buổi đang chờ', coachSessionNextStatus('REQUESTED', 'coach', 'confirm', $at, $before) === 'CONFIRMED');
check('HLV không xác nhận buổi đã qua giờ', coachSessionNextStatus('REQUESTED', 'coach', 'confirm', $at, $after) === null);
check('Học viên hủy trước giờ được, sau giờ không', coachSessionNextStatus('CONFIRMED', 'client', 'cancel', $at, $before) === 'CANCELLED'
    && coachSessionNextStatus('CONFIRMED', 'client', 'cancel', $at, $after) === null);
check('HLV hủy buổi đã xác nhận cả sau giờ (HLV vắng thì không trừ buổi)', coachSessionNextStatus('CONFIRMED', 'coach', 'cancel', $at, $after) === 'CANCELLED');
check('Đánh dấu Đã tập chỉ sau giờ bắt đầu', coachSessionNextStatus('CONFIRMED', 'coach', 'done', $at, $before) === null
    && coachSessionNextStatus('CONFIRMED', 'coach', 'done', $at, $after) === 'DONE');
check('Vắng mặt chỉ cho buổi đã xác nhận', coachSessionNextStatus('REQUESTED', 'coach', 'no_show', $at, $after) === null
    && coachSessionNextStatus('CONFIRMED', 'coach', 'no_show', $at, $after) === 'NO_SHOW');
check('Học viên không tự đánh dấu Đã tập', coachSessionNextStatus('CONFIRMED', 'client', 'done', $at, $after) === null);
check('Không đổi buổi đã xong', coachSessionNextStatus('DONE', 'coach', 'cancel', $at, $after) === null);
check('Buổi chờ quá giờ là quá hạn', coachSessionExpired('REQUESTED', $at, $after) && !coachSessionExpired('CONFIRMED', $at, $after));

$s = coachBillingSummary(2, 0, [], [], '2026-12-01');
check('Mới bắt đầu: còn 2 buổi thử, không khóa', $s['trial_left'] === 2 && !$s['locked']);
$sess = [['billing' => 'TRIAL', 'status' => 'DONE'], ['billing' => 'TRIAL', 'status' => 'CONFIRMED']];
$s = coachBillingSummary(2, 0, [], $sess, '2026-12-01');
check('Hết suất thử nhưng còn 1 buổi đang hẹn: chưa khóa', $s['trial_left'] === 0 && $s['holding'] === 1 && !$s['locked']);
$sess[1]['status'] = 'NO_SHOW';
check('Buổi vắng cũng trừ: hết buổi thử, không gói → khóa', coachBillingSummary(2, 0, [], $sess, '2026-12-01')['locked']);
$sess[] = ['billing' => 'TRIAL', 'status' => 'CANCELLED'];
$sess[] = ['billing' => 'TRIAL', 'status' => 'EXPIRED'];
check('Buổi hủy / quá hạn không trừ suất', coachBillingSummary(2, 0, [], $sess, '2026-12-01')['trial_used'] === 2);
$s = coachBillingSummary(2, 4, [], $sess, '2026-12-01');
check('Mua gói 4 buổi: mở khóa, còn 4', !$s['locked'] && $s['pack_left'] === 4);
$s = coachBillingSummary(2, 0, [['starts_on' => '2026-11-20', 'ends_on' => '2026-12-19']], $sess, '2026-12-01');
check('Gói tháng còn hạn: mở khóa, ghi ngày hết hạn', !$s['locked'] && $s['month_until'] === '2026-12-19');
check('Gói tháng hết hạn thì khóa lại', coachBillingSummary(2, 0, [['starts_on' => '2026-10-01', 'ends_on' => '2026-10-30']], $sess, '2026-12-01')['locked']);
$s = coachBillingSummary(1, 2, [['starts_on' => '2026-12-01', 'ends_on' => '2026-12-30']], [], '2026-12-01');
check('Ngày trong gói tháng → dùng gói tháng, không phí buổi thử', coachPickBilling($s, [['starts_on' => '2026-12-01', 'ends_on' => '2026-12-30']], '2026-12-05') === 'MONTH');
check('Ngoài gói tháng → dùng buổi thử trước, rồi tới gói buổi', coachPickBilling($s, [], '2027-01-05') === 'TRIAL'
    && coachPickBilling(['trial_left' => 0, 'pack_left' => 2], [], '2027-01-05') === 'PACK');
check('Hết mọi quyền → null', coachPickBilling(['trial_left' => 0, 'pack_left' => 0], [], '2027-01-05') === null);
check('Dòng giá công khai', coachPriceLine(300000, 2400000) === '300.000đ/buổi · 2.400.000đ/tháng' && coachPriceLine(null, null) === '');

// ===================== PHẦN 2: LỚP DB TRÊN SQLITE =====================
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "[BỎ QUA] Không có pdo_sqlite nên không chạy phần DB\n";
} else {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT, role TEXT, avatar TEXT)");
    $pdo->exec("CREATE TABLE coach_profiles (user_id INTEGER PRIMARY KEY, headline TEXT DEFAULT '', max_clients INT DEFAULT 10, accepting INT DEFAULT 1,
        price_per_session INT, price_per_month INT, trial_sessions INT NOT NULL DEFAULT 1, payment_note TEXT NOT NULL DEFAULT '', updated_at TEXT)");
    $pdo->exec("CREATE TABLE coach_clients (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_id INT, client_id INT, status TEXT, request_note TEXT DEFAULT '',
        trial_sessions INT, created_at TEXT, accepted_at TEXT, ended_at TEXT, ended_by INT,
        open_client_id INT GENERATED ALWAYS AS (CASE WHEN status IN ('PENDING', 'ACTIVE') THEN client_id END) STORED UNIQUE)");
    $pdo->exec("CREATE TABLE coach_change_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_client_id INT, area TEXT, status TEXT, resolved_at TEXT)");
    $pdo->exec("CREATE TABLE coach_orders (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_client_id INT, kind TEXT, quantity INT, unit_price INT, amount INT,
        status TEXT DEFAULT 'PENDING', starts_on TEXT, ends_on TEXT, cancel_note TEXT NOT NULL DEFAULT '', cancelled_by INT, created_at TEXT, paid_at TEXT, cancelled_at TEXT,
        open_rel INT GENERATED ALWAYS AS (CASE WHEN status = 'PENDING' THEN coach_client_id END) STORED UNIQUE)");
    $pdo->exec("CREATE TABLE coach_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_client_id INT, coach_id INT, starts_at TEXT, billing TEXT,
        status TEXT DEFAULT 'REQUESTED', client_note TEXT NOT NULL DEFAULT '', cancelled_by INT, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE coach_feedback (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_client_id INT, log_date TEXT, content TEXT, read_at TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE workout_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, log_date TEXT)");
    $pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT, title TEXT, body TEXT DEFAULT '', link TEXT DEFAULT '', read_at TEXT, created_at TEXT)");
    foreach ([[2, 'HLV Hai', 'COACH'], [3, 'HLV Ba', 'COACH'], [4, 'Nguyễn Văn An', 'USER'], [5, 'Trần Bình', 'USER'], [6, 'Lê Chi', 'USER']] as $u) {
        $pdo->prepare('INSERT INTO users (id, full_name, role) VALUES (?, ?, ?)')->execute($u);
    }
    $pdo->exec("INSERT INTO coach_profiles (user_id, price_per_session, price_per_month, trial_sessions) VALUES (2, 300000, 2400000, 2), (3, NULL, NULL, 3)");
    $acc = date('Y-m-d H:i:s', strtotime('-20 days'));
    $pdo->prepare("INSERT INTO coach_clients (id, coach_id, client_id, status, trial_sessions, accepted_at) VALUES (1, 2, 4, 'ACTIVE', 2, ?), (2, 2, 5, 'ACTIVE', NULL, ?)")->execute([$acc, $acc]);

    check('DB đủ bảng: coachBillingReady() = true', coachBillingReady($pdo));
    check('Quan hệ cũ (chưa chụp số buổi thử) dùng số buổi thử trong hồ sơ HLV', coachBillingState($pdo, 2)['trial'] === 2);

    $d2 = date('Y-m-d', strtotime('+2 days'));
    $d3 = date('Y-m-d', strtotime('+3 days'));
    $d4 = date('Y-m-d', strtotime('+4 days'));
    $a = coachBookSession($pdo, 4, $d2, '18:00', 'Phòng tập Q1');
    check('Đặt buổi 1: tính vào buổi thử', $a['billing'] === 'TRIAL');
    check('HLV nhận thông báo có giờ hẹn', str_contains((string)$pdo->query("SELECT body FROM notifications WHERE user_id = 2 AND type = 'coach.session'")->fetchColumn(), '18:00'));
    $b = coachBookSession($pdo, 4, $d3, '18:00', '');
    check('Đặt buổi 2: vẫn là buổi thử', $b['billing'] === 'TRIAL');
    check('Buổi 3 khi đã giữ hết 2 suất thử → bị chặn, nhắc mua gói', str_contains((string)errOf(fn() => coachBookSession($pdo, 4, $d4, '18:00', '')), 'mua gói'));
    check('Học viên khác đặt 18:30 cùng ngày (trùng giờ HLV) → bị chặn', str_contains((string)errOf(fn() => coachBookSession($pdo, 5, $d2, '18:30', '')), 'HLV đã có lịch'));
    check('Học viên khác đặt 19:00 (cách 60 phút) → được', errOf(fn() => coachBookSession($pdo, 5, $d2, '19:00', '')) === null);
    check('Người không có HLV không đặt được', errOf(fn() => coachBookSession($pdo, 6, $d2, '08:00', '')) !== null);
    check('HLV khác không xác nhận được buổi không phải của mình', errOf(fn() => coachSessionAction($pdo, 'coach', 3, $a['id'], 'confirm')) !== null);
    check('Học viên khác không hủy được buổi của người khác', errOf(fn() => coachSessionAction($pdo, 'client', 5, $a['id'], 'cancel')) !== null);
    check('HLV xác nhận buổi 1', coachSessionAction($pdo, 'coach', 2, $a['id'], 'confirm')['status'] === 'CONFIRMED');
    check('Chưa tới giờ thì chưa đánh dấu Đã tập được', str_contains((string)errOf(fn() => coachSessionAction($pdo, 'coach', 2, $a['id'], 'done')), 'sau giờ bắt đầu'));

    // Giả lập thời gian trôi: dời buổi 1 về 2 giờ trước
    $pdo->prepare('UPDATE coach_sessions SET starts_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', time() - 7200), $a['id']]);
    $r = coachSessionAction($pdo, 'coach', 2, $a['id'], 'done');
    check('Sau giờ tập: HLV đánh dấu Đã tập, chưa hết buổi', $r['status'] === 'DONE' && !$r['now_locked']);
    check('Học viên hủy buổi 2 (trước giờ): trả lại 1 suất thử', coachSessionAction($pdo, 'client', 4, $b['id'], 'cancel')['status'] === 'CANCELLED'
        && coachBillingState($pdo, 1)['trial_left'] === 1);

    // Buổi đang chờ đã quá giờ → tự quá hạn khi đọc
    $pdo->prepare("INSERT INTO coach_sessions (coach_client_id, coach_id, starts_at, billing, status, created_at) VALUES (1, 2, ?, 'TRIAL', 'REQUESTED', ?)")
        ->execute([date('Y-m-d H:i:s', time() - 3600), coachNow()]);
    check('Buổi chờ quá giờ mà HLV chưa nhận → Quá hạn, trả suất', coachBillingState($pdo, 1)['trial_left'] === 1
        && (int)$pdo->query("SELECT COUNT(*) FROM coach_sessions WHERE status = 'EXPIRED'")->fetchColumn() === 1);

    $c = coachBookSession($pdo, 4, $d3, '07:00', '');
    coachSessionAction($pdo, 'coach', 2, $c['id'], 'confirm');
    $pdo->prepare('UPDATE coach_sessions SET starts_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', time() - 3600), $c['id']]);
    $r = coachSessionAction($pdo, 'coach', 2, $c['id'], 'no_show');
    check('Vắng buổi thử cuối → hết buổi (now_locked)', $r['status'] === 'NO_SHOW' && $r['now_locked']);
    check('Học viên được báo đã dùng hết buổi', (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 4 AND type = 'coach.billing'")->fetchColumn() === 1);
    check('Khóa: HLV không nhận xét được', str_contains((string)errOf(fn() => coachAddFeedback($pdo, 1, 4, null, 'Tốt lắm')), 'hết buổi'));
    check('Khóa: coachBillingAssert chặn giao lịch / dinh dưỡng', errOf(fn() => coachBillingAssert($pdo, 2, 4)) !== null);
    check('Học viên còn buổi thì không bị chặn', errOf(fn() => coachBillingAssert($pdo, 2, 5)) === null);
    check('Khóa thì không đặt thêm buổi được', errOf(fn() => coachBookSession($pdo, 4, $d4, '18:00', '')) !== null);

    // Mua gói buổi
    $oid = coachCreateOrder($pdo, 4, 'SESSIONS', '4');
    check('Học viên chọn gói 4 buổi: đơn chờ, giá chụp lại 1.200.000đ', $pdo->query("SELECT status || '|' || amount FROM coach_orders WHERE id = $oid")->fetchColumn() === 'PENDING|1200000');
    check('Đơn thứ hai khi đang có đơn chờ → bị chặn', str_contains((string)errOf(fn() => coachCreateOrder($pdo, 4, 'MONTH', 1)), 'đơn chờ'));
    check('Đơn chờ chưa mở khóa', coachBillingState($pdo, 1)['locked']);
    $pdo->exec("UPDATE coach_profiles SET price_per_session = 500000 WHERE user_id = 2");
    check('HLV khác không xác nhận được đơn', errOf(fn() => coachResolveOrder($pdo, 3, $oid, true, '')) !== null);
    coachResolveOrder($pdo, 2, $oid, true, '');
    $st = coachBillingState($pdo, 1);
    check('HLV xác nhận đã nhận tiền: mở khóa, còn 4 buổi; đổi giá sau không đổi đơn cũ', !$st['locked'] && $st['pack_left'] === 4
        && (int)$pdo->query("SELECT amount FROM coach_orders WHERE id = $oid")->fetchColumn() === 1200000);
    check('Học viên được báo đã xác nhận thanh toán', str_contains((string)$pdo->query("SELECT title FROM notifications WHERE user_id = 4 AND type = 'coach.order' ORDER BY id DESC")->fetchColumn(), 'xác nhận'));
    check('Đặt buổi sau khi mua gói: tính vào gói buổi', coachBookSession($pdo, 4, $d4, '18:00', '')['billing'] === 'PACK');
    check('Không xác nhận lại đơn đã xử lý', errOf(fn() => coachResolveOrder($pdo, 2, $oid, true, '')) !== null);

    // Gói tháng
    $m1 = coachCreateOrder($pdo, 4, 'MONTH', 1);
    $o = coachResolveOrder($pdo, 2, $m1, true, '');
    check('Gói tháng: từ hôm nay, 30 ngày', $o['starts_on'] === date('Y-m-d') && $o['ends_on'] === date('Y-m-d', strtotime('+29 days')));
    check('Có gói tháng: buổi mới tính vào gói tháng, không trừ gói buổi', coachBookSession($pdo, 4, $d4, '07:00', '')['billing'] === 'MONTH'
        && coachBillingState($pdo, 1)['pack_left'] === 3);
    $m2 = coachCreateOrder($pdo, 4, 'MONTH', 1);
    $o = coachResolveOrder($pdo, 2, $m2, true, '');
    check('Gói tháng thứ hai nối tiếp ngay sau gói đầu', $o['starts_on'] === date('Y-m-d', strtotime('+30 days')));
    check('Gói tháng phủ liên tục 60 ngày', coachBillingState($pdo, 1)['month_until'] === date('Y-m-d', strtotime('+59 days')));

    // Hủy đơn
    $x = coachCreateOrder($pdo, 4, 'SESSIONS', 1);
    check('Học viên khác không hủy được đơn', errOf(fn() => coachCancelOrderByClient($pdo, 5, $x)) !== null);
    coachResolveOrder($pdo, 2, $x, false, 'Chưa nhận được chuyển khoản');
    check('HLV hủy đơn kèm lý do, học viên được báo', $pdo->query("SELECT status FROM coach_orders WHERE id = $x")->fetchColumn() === 'CANCELLED'
        && str_contains((string)$pdo->query("SELECT body FROM notifications WHERE user_id = 4 ORDER BY id DESC")->fetchColumn(), 'Chưa nhận được'));
    check('HLV không bán theo buổi thì không mua gói buổi được', errOf(function () use ($pdo) {
        $pdo->exec("UPDATE coach_profiles SET price_per_session = NULL WHERE user_id = 2");
        coachCreateOrder($pdo, 4, 'SESSIONS', 4);
    }) !== null);

    // Trang Lịch hẹn của HLV
    $groups = coachSessionsForCoach($pdo, 2);
    check('Lịch hẹn của HLV: có buổi chờ xác nhận, đã bỏ buổi quá hạn', count($groups['requests']) >= 2
        && !in_array('EXPIRED', array_column($groups['requests'], 'status'), true));
    $cnt = coachBillingCounts($pdo, 2);
    check('Số việc cần làm của HLV', $cnt['requests'] === count($groups['requests']) && $cnt['orders'] === 0);

    // Kết thúc quan hệ: hủy buổi sắp tới và đơn chờ
    coachCreateOrder($pdo, 5, 'MONTH', 1);
    coachChangeRelation($pdo, 'client', 5, 2, 'end');
    check('Ngừng tập: buổi sắp tới và đơn chờ bị hủy', (int)$pdo->query("SELECT COUNT(*) FROM coach_sessions WHERE coach_client_id = 2 AND status IN ('REQUESTED', 'CONFIRMED')")->fetchColumn() === 0
        && (int)$pdo->query("SELECT COUNT(*) FROM coach_orders WHERE coach_client_id = 2 AND status = 'PENDING'")->fetchColumn() === 0);

    // Gửi yêu cầu mới: chụp lại số buổi thử
    coachRequest($pdo, 6, 3, '');
    check('Gửi yêu cầu tới HLV có 3 buổi thử: quan hệ ghi lại 3', (int)$pdo->query('SELECT trial_sessions FROM coach_clients WHERE client_id = 6')->fetchColumn() === 3);
    $pdo->exec("UPDATE coach_profiles SET trial_sessions = 1 WHERE user_id = 3");
    $rel6 = (int)$pdo->query('SELECT id FROM coach_clients WHERE client_id = 6')->fetchColumn();
    check('HLV giảm số buổi thử sau đó: học viên vẫn giữ 3', coachBillingState($pdo, $rel6)['trial'] === 3);

    // Lưu giá
    coachSavePricing($pdo, 3, validateCoachPricing(['price_per_session' => '', 'price_per_month' => '1.800.000', 'trial_sessions' => '3', 'payment_note' => ''])['data']);
    check('HLV lưu giá: chỉ bán theo tháng', coachPricing($pdo, 3) === ['price_per_session' => null, 'price_per_month' => 1800000, 'trial_sessions' => 3, 'payment_note' => '']);
    check('Tài khoản không phải HLV không lưu giá được', errOf(fn() => coachSavePricing($pdo, 4, ['price_per_session' => 1000, 'price_per_month' => null, 'trial_sessions' => 1, 'payment_note' => ''])) !== null);
    check('Bảng giá nhiều HLV', coachPricingMap($pdo, [2, 3, 99])[3]['price_per_month'] === 1800000 && !isset(coachPricingMap($pdo, [99])[99]));
}

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
