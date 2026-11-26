<?php
/**
 * includes/coach-billing-rules.php — quy tắc giá tập với HLV, buổi thử miễn phí, lịch hẹn và gói (A-54).
 *
 * File này THUẦN: không đụng DB, không đọc $_POST/$_SESSION, thời gian "bây giờ" truyền vào bằng tham số.
 * Test ở test-coach-billing.php mà không cần MySQL. Phần truy vấn nằm ở includes/coach-billing.php.
 *
 * Cách tính quyền tập của một học viên (coachBillingSummary):
 *   - Mỗi buổi hẹn được gắn 1 nguồn lúc đặt: MONTH (ngày tập nằm trong gói tháng đã trả), TRIAL (buổi thử còn lại)
 *     hoặc PACK (gói buổi đã trả). Ưu tiên theo đúng thứ tự đó để học viên không phí buổi thử khi đang có gói tháng.
 *   - Buổi TRIAL/PACK "chiếm" 1 suất khi đang chờ / đã xác nhận (giữ chỗ), "dùng hết" suất khi Đã tập hoặc Vắng mặt.
 *     Hủy / từ chối / quá hạn thì trả suất lại.
 *   - Bị khóa khi: không có gói tháng còn hạn hôm nay, hết buổi thử, hết buổi trong gói, và không còn buổi nào đang giữ chỗ.
 *     Khi khóa, HLV không giao lịch / đặt dinh dưỡng / nhận xét được, tin nhắn vẫn dùng được.
 */

require_once __DIR__ . '/coach-rules.php';   // coachCleanText(), coachTextError()

const COACH_TRIAL_MIN = 1;                       // số buổi tập thử miễn phí HLV được chọn: 1 đến 3
const COACH_TRIAL_MAX = 3;
const COACH_PRICE_STEP = 1000;                    // giá làm tròn tới nghìn đồng
const COACH_SESSION_PRICE_MIN = 50000;            // giá 1 buổi: 50.000đ - 5.000.000đ
const COACH_SESSION_PRICE_MAX = 5000000;
const COACH_MONTH_PRICE_MIN = 200000;             // giá 1 tháng: 200.000đ - 50.000.000đ
const COACH_MONTH_PRICE_MAX = 50000000;
const COACH_SESSION_PACKS = [1, 4, 8, 12];        // học viên mua gói bao nhiêu buổi
const COACH_MONTH_PACKS = [1, 3];                 // hoặc bao nhiêu tháng
const COACH_MONTH_DAYS = 30;                      // 1 tháng = 30 ngày
const COACH_SESSION_MINUTES = 60;                 // mỗi buổi hẹn 60 phút (dùng để kiểm tra trùng giờ)
const COACH_BOOK_MIN_HOURS = 2;                   // đặt trước giờ tập ít nhất 2 giờ
const COACH_BOOK_MAX_DAYS = 30;                   // đặt trước tối đa 30 ngày
const COACH_BOOK_FIRST = '05:00';                 // giờ bắt đầu sớm nhất
const COACH_BOOK_LAST = '21:00';                  // giờ bắt đầu muộn nhất (kết thúc 22:00)
const COACH_BOOK_MAX_OPEN = 8;                    // tối đa 8 buổi sắp tới (chờ + đã xác nhận) cho mỗi học viên
const COACH_SESSION_NOTE_MAX = 300;
const COACH_PAYMENT_NOTE_MAX = 300;

const COACH_SESSION_LABELS = [
    'REQUESTED' => 'Chờ HLV xác nhận',
    'CONFIRMED' => 'Đã xác nhận',
    'DONE'      => 'Đã tập',
    'NO_SHOW'   => 'Vắng mặt',
    'CANCELLED' => 'Đã hủy',
    'DECLINED'  => 'HLV từ chối',
    'EXPIRED'   => 'Quá hạn',
];
const COACH_SESSION_TONES = [   // màu nhãn (class ch-badge--*)
    'REQUESTED' => 'warn', 'CONFIRMED' => 'info', 'DONE' => 'ok', 'NO_SHOW' => 'danger',
    'CANCELLED' => '', 'DECLINED' => '', 'EXPIRED' => '',
];
const COACH_BILLING_LABELS = ['TRIAL' => 'Buổi thử', 'PACK' => 'Gói buổi', 'MONTH' => 'Gói tháng'];
const COACH_ORDER_LABELS = ['PENDING' => 'Chờ HLV xác nhận tiền', 'PAID' => 'Đã thanh toán', 'CANCELLED' => 'Đã hủy'];

/** Trạng thái giữ chỗ và đã dùng (đếm vào suất của buổi thử / gói buổi). */
const COACH_SESSION_HOLDING = ['REQUESTED', 'CONFIRMED'];
const COACH_SESSION_USED = ['DONE', 'NO_SHOW'];

/** 300000 → "300.000đ" */
function coachMoney(int $vnd): string
{
    return number_format($vnd, 0, ',', '.') . 'đ';
}

/**
 * Đọc số tiền người dùng gõ: "300.000", "300000", "300 000đ", "1,500,000 VNĐ".
 * Trả về int, null nếu ô trống (= không bán kiểu này), false nếu không phải số tiền.
 */
function coachParseMoney($raw)
{
    if (!is_string($raw) && !is_int($raw)) {
        return false;
    }
    $s = trim((string)$raw);
    $s = preg_replace('/\s*(đ|vnđ|vnd)$/iu', '', $s) ?? '';
    $s = str_replace(['.', ',', ' '], '', $s);
    if ($s === '') {
        return null;
    }
    return preg_match('/^\d{1,9}$/', $s) ? (int)$s : false;
}

/**
 * Kiểm tra form "Giá và buổi tập thử" của HLV. Phải bán ít nhất 1 kiểu (theo buổi hoặc theo tháng).
 * Trả về ['data' => ['price_per_session' => ?int, 'price_per_month' => ?int, 'trial_sessions' => int, 'payment_note' => string],
 *         'errors' => [tên ô => lỗi]].
 */
function validateCoachPricing(array $in): array
{
    $errors = [];
    $data = [];
    $fields = [
        'price_per_session' => ['Giá 1 buổi', COACH_SESSION_PRICE_MIN, COACH_SESSION_PRICE_MAX],
        'price_per_month'   => ['Giá 1 tháng', COACH_MONTH_PRICE_MIN, COACH_MONTH_PRICE_MAX],
    ];
    foreach ($fields as $key => [$label, $min, $max]) {
        $v = coachParseMoney($in[$key] ?? '');
        if ($v === false) {
            $errors[$key] = $label . ' chỉ gồm chữ số, ví dụ 300.000.';
        } elseif ($v !== null && ($v < $min || $v > $max)) {
            $errors[$key] = $label . ' phải từ ' . coachMoney($min) . ' đến ' . coachMoney($max) . '.';
        } elseif ($v !== null && $v % COACH_PRICE_STEP !== 0) {
            $errors[$key] = $label . ' làm tròn tới nghìn đồng (ví dụ ' . coachMoney(intdiv($v, COACH_PRICE_STEP) * COACH_PRICE_STEP) . ').';
        }
        $data[$key] = is_int($v) ? $v : null;
    }
    if (!$errors && $data['price_per_session'] === null && $data['price_per_month'] === null) {
        $errors['price_per_session'] = 'Nhập ít nhất một mức giá: theo buổi hoặc theo tháng.';
    }

    $trial = filter_var($in['trial_sessions'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => COACH_TRIAL_MIN, 'max_range' => COACH_TRIAL_MAX]]);
    if ($trial === false) {
        $errors['trial_sessions'] = 'Số buổi tập thử miễn phí từ ' . COACH_TRIAL_MIN . ' đến ' . COACH_TRIAL_MAX . '.';
    }
    $data['trial_sessions'] = $trial === false ? COACH_TRIAL_MIN : $trial;

    $data['payment_note'] = coachCleanText($in['payment_note'] ?? '');
    if ($e = coachTextError($data['payment_note'], 'Hướng dẫn thanh toán', 0, COACH_PAYMENT_NOTE_MAX)) {
        $errors['payment_note'] = $e;
    }
    return ['data' => $data, 'errors' => $errors];
}

/** Tên gói để hiển thị: "Gói 8 buổi", "Gói 1 tháng". */
function coachOrderLabel(string $kind, int $quantity): string
{
    return 'Gói ' . $quantity . ($kind === 'MONTH' ? ' tháng' : ' buổi');
}

/**
 * Báo giá một gói theo giá HIỆN TẠI của HLV. $pricing = ['price_per_session' => ?int, 'price_per_month' => ?int].
 * Trả về ['quote' => ['kind','quantity','unit_price','amount'] hoặc null, 'error' => câu lỗi hoặc null].
 */
function coachOrderQuote(array $pricing, $kind, $quantity): array
{
    if ($kind === 'SESSIONS') {
        $unit = $pricing['price_per_session'] ?? null;
        $allowed = COACH_SESSION_PACKS;
        $how = 'theo buổi';
    } elseif ($kind === 'MONTH') {
        $unit = $pricing['price_per_month'] ?? null;
        $allowed = COACH_MONTH_PACKS;
        $how = 'theo tháng';
    } else {
        return ['quote' => null, 'error' => 'Loại gói không hợp lệ.'];
    }
    if ($unit === null || (int)$unit <= 0) {
        return ['quote' => null, 'error' => 'HLV chưa bán gói ' . $how . '.'];
    }
    $q = filter_var($quantity, FILTER_VALIDATE_INT);
    if ($q === false || !in_array($q, $allowed, true)) {
        return ['quote' => null, 'error' => 'Số lượng không hợp lệ. Chọn một trong các gói có sẵn.'];
    }
    return ['quote' => ['kind' => $kind, 'quantity' => $q, 'unit_price' => (int)$unit, 'amount' => (int)$unit * $q], 'error' => null];
}

/**
 * Khoảng ngày của một gói tháng vừa được xác nhận: nối tiếp ngay sau gói tháng trước nếu gói đó còn hạn,
 * ngược lại bắt đầu từ hôm nay. Mỗi tháng 30 ngày, tính cả ngày đầu và ngày cuối.
 * Ví dụ: hôm nay 01/12, chưa có gói → 01/12 - 30/12. Gói cũ hết hạn 10/12 → 11/12 - 09/01.
 * @return array [starts_on, ends_on] dạng Y-m-d
 */
function coachMonthWindow(?string $lastEndsOn, string $today, int $months): array
{
    $start = $today;
    if ($lastEndsOn !== null && $lastEndsOn >= $today) {
        $start = date('Y-m-d', strtotime($lastEndsOn . ' +1 day'));
    }
    $end = date('Y-m-d', strtotime($start . ' +' . (COACH_MONTH_DAYS * max(1, $months) - 1) . ' days'));
    return [$start, $end];
}

/** Ngày $date (Y-m-d) có nằm trong một gói tháng đã trả không. $windows = [['starts_on','ends_on'], ...]. */
function coachDateCovered(array $windows, string $date): bool
{
    foreach ($windows as $w) {
        if (($w['starts_on'] ?? '') <= $date && $date <= ($w['ends_on'] ?? '')) {
            return true;
        }
    }
    return false;
}

/**
 * Gói tháng phủ liên tục từ $date tới ngày nào (gộp các gói nối tiếp nhau). null nếu $date không nằm trong gói nào.
 */
function coachMonthCoverEnd(array $windows, string $date): ?string
{
    usort($windows, fn($a, $b) => strcmp((string)$a['starts_on'], (string)$b['starts_on']));
    $end = null;
    foreach ($windows as $w) {
        $s = (string)$w['starts_on'];
        $e = (string)$w['ends_on'];
        if ($end === null) {
            if ($s <= $date && $date <= $e) {
                $end = $e;
            }
        } elseif ($s <= date('Y-m-d', strtotime($end . ' +1 day')) && $e > $end) {
            $end = $e;   // gói sau bắt đầu ngay khi (hoặc trước khi) gói trước hết hạn
        }
    }
    return $end;
}

/** Các giờ bắt đầu được chọn: 05:00, 05:30, ..., 21:00. */
function coachBookingTimes(): array
{
    $out = [];
    for ($t = strtotime('2000-01-01 ' . COACH_BOOK_FIRST); $t <= strtotime('2000-01-01 ' . COACH_BOOK_LAST); $t += 1800) {
        $out[] = date('H:i', $t);
    }
    return $out;
}

/** Ghép ô ngày (Y-m-d) và ô giờ (H:i) thành 'Y-m-d H:i:00'. Sai định dạng hoặc ngày không có thật (30/02) → null. */
function coachBookingStart($date, $time): ?string
{
    if (!is_string($date) || !is_string($time)) {
        return null;
    }
    $d = DateTime::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);
    if ($d === false || $d->format('Y-m-d H:i') !== $date . ' ' . $time) {
        return null;
    }
    return $d->format('Y-m-d H:i:00');
}

/** Giờ hẹn có hợp lệ không (đúng khung giờ, đặt trước ít nhất 2 giờ, không quá 30 ngày). Trả câu lỗi hoặc null. */
function coachBookingTimeError(string $startsAt, string $now): ?string
{
    if (!in_array(substr($startsAt, 11, 5), coachBookingTimes(), true)) {
        return 'Giờ bắt đầu từ ' . COACH_BOOK_FIRST . ' đến ' . COACH_BOOK_LAST . ', cách nhau 30 phút.';
    }
    $t = strtotime($startsAt);
    $n = strtotime($now);
    if ($t < $n + COACH_BOOK_MIN_HOURS * 3600) {
        return 'Hãy đặt trước giờ tập ít nhất ' . COACH_BOOK_MIN_HOURS . ' giờ để HLV kịp xác nhận.';
    }
    if ($t > $n + COACH_BOOK_MAX_DAYS * 86400) {
        return 'Chỉ đặt được buổi trong ' . COACH_BOOK_MAX_DAYS . ' ngày tới.';
    }
    return null;
}

/** '2026-11-27 18:00:00' → "18:00 Thứ 6, 27/11/2026" (giờ hẹn để hiển thị và ghi trong thông báo). */
function coachSessionWhen(string $startsAt): string
{
    $t = strtotime($startsAt);
    $n = (int)date('N', $t);
    return date('H:i', $t) . ' ' . ($n === 7 ? 'Chủ nhật' : 'Thứ ' . ($n + 1)) . ', ' . date('d/m/Y', $t);
}

/** Hai buổi (mỗi buổi COACH_SESSION_MINUTES phút) có chồng giờ nhau không. */
function coachSessionsClash(string $a, string $b): bool
{
    return abs(strtotime($a) - strtotime($b)) < COACH_SESSION_MINUTES * 60;
}

/**
 * Máy trạng thái của một buổi hẹn. Trả về trạng thái mới, hoặc null nếu không được làm.
 *   client: cancel  (REQUESTED / CONFIRMED → CANCELLED), chỉ trước giờ tập
 *   coach : confirm (REQUESTED → CONFIRMED), chỉ trước giờ tập
 *           decline (REQUESTED → DECLINED)
 *           cancel  (CONFIRMED → CANCELLED), lúc nào cũng được: HLV vắng / có việc thì học viên không mất buổi
 *           done    (CONFIRMED → DONE), no_show (CONFIRMED → NO_SHOW): chỉ SAU giờ bắt đầu
 */
function coachSessionNextStatus(string $current, string $actor, string $action, string $startsAt, string $now): ?string
{
    $rules = [
        'client' => ['cancel' => ['REQUESTED' => 'CANCELLED', 'CONFIRMED' => 'CANCELLED']],
        'coach'  => ['confirm' => ['REQUESTED' => 'CONFIRMED'], 'decline' => ['REQUESTED' => 'DECLINED'],
                     'cancel' => ['CONFIRMED' => 'CANCELLED'], 'done' => ['CONFIRMED' => 'DONE'], 'no_show' => ['CONFIRMED' => 'NO_SHOW']],
    ];
    $next = $rules[$actor][$action][$current] ?? null;
    if ($next === null) {
        return null;
    }
    $started = strtotime($now) >= strtotime($startsAt);
    if (in_array($action, ['done', 'no_show'], true) && !$started) {
        return null;   // chưa tới giờ thì chưa biết đã tập hay vắng
    }
    if ($started && ($action === 'confirm' || ($actor === 'client' && $action === 'cancel'))) {
        return null;   // quá giờ thì học viên không tự hủy được nữa, HLV không xác nhận lịch đã qua
    }
    return $next;
}

/** Buổi đang chờ HLV mà đã tới giờ thì coi như quá hạn (trả suất lại cho học viên). */
function coachSessionExpired(string $status, string $startsAt, string $now): bool
{
    return $status === 'REQUESTED' && strtotime($startsAt) <= strtotime($now);
}

/**
 * Tổng hợp quyền tập của một quan hệ HLV - học viên.
 * @param int    $trial     số buổi thử của quan hệ
 * @param int    $packUnits tổng số buổi của các gói buổi ĐÃ THANH TOÁN
 * @param array  $windows   các gói tháng đã thanh toán [['starts_on','ends_on'], ...]
 * @param array  $sessions  các buổi hẹn [['billing','status'], ...] (đã đánh dấu quá hạn trước khi gọi)
 * @param string $today     Y-m-d
 * @return array trial, trial_used, trial_left, pack_units, pack_used, pack_left, holding, used, month_until, month_active, locked
 */
function coachBillingSummary(int $trial, int $packUnits, array $windows, array $sessions, string $today): array
{
    $trialUsed = $packUsed = $holding = $used = 0;
    foreach ($sessions as $s) {
        $billing = $s['billing'] ?? '';
        $status = $s['status'] ?? '';
        if ($billing !== 'TRIAL' && $billing !== 'PACK') {
            continue;   // buổi trong gói tháng không trừ suất
        }
        $isHolding = in_array($status, COACH_SESSION_HOLDING, true);
        $isUsed = in_array($status, COACH_SESSION_USED, true);
        if (!$isHolding && !$isUsed) {
            continue;
        }
        $billing === 'TRIAL' ? $trialUsed++ : $packUsed++;
        $isHolding ? $holding++ : $used++;
    }
    $monthUntil = coachMonthCoverEnd($windows, $today);
    $trialLeft = max(0, $trial - $trialUsed);
    $packLeft = max(0, $packUnits - $packUsed);
    return [
        'trial'        => $trial,
        'trial_used'   => $trialUsed,
        'trial_left'   => $trialLeft,
        'pack_units'   => $packUnits,
        'pack_used'    => $packUsed,
        'pack_left'    => $packLeft,
        'holding'      => $holding,
        'used'         => $used,
        'month_until'  => $monthUntil,
        'month_active' => $monthUntil !== null,
        'locked'       => $monthUntil === null && $trialLeft === 0 && $packLeft === 0 && $holding === 0,
    ];
}

/**
 * Buổi hẹn vào ngày $date được tính vào đâu: 'MONTH' | 'TRIAL' | 'PACK', hoặc null nếu hết quyền (phải mua gói).
 * Gói tháng phủ ngày đó thì dùng gói tháng (không phí buổi thử), rồi tới buổi thử, cuối cùng là gói buổi.
 */
function coachPickBilling(array $summary, array $windows, string $date): ?string
{
    if (coachDateCovered($windows, $date)) {
        return 'MONTH';
    }
    if (($summary['trial_left'] ?? 0) > 0) {
        return 'TRIAL';
    }
    if (($summary['pack_left'] ?? 0) > 0) {
        return 'PACK';
    }
    return null;
}

/** Câu giải thích khi bị khóa. $forCoach = true: câu cho HLV, false: câu cho học viên. */
function coachBillingLockMessage(bool $forCoach): string
{
    return $forCoach
        ? 'Học viên đã dùng hết buổi tập thử và không còn gói nào. Khi học viên mua gói và bạn xác nhận đã nhận tiền, giao lịch, đặt dinh dưỡng và nhận xét sẽ mở lại. Tin nhắn vẫn dùng được.'
        : 'Bạn đã dùng hết buổi tập thử và không còn gói nào. HLV tạm thời không giao lịch, đặt dinh dưỡng hay nhận xét được cho tới khi bạn mua gói. Tin nhắn với HLV vẫn dùng được.';
}

/** Một dòng tóm tắt quyền tập: "Buổi thử còn 1/2 · Gói buổi còn 3 · Gói tháng tới 30/12". */
function coachBillingLine(array $summary): string
{
    $parts = ['Buổi thử còn ' . $summary['trial_left'] . '/' . $summary['trial']];
    if ($summary['pack_units'] > 0) {
        $parts[] = 'Gói buổi còn ' . $summary['pack_left'] . '/' . $summary['pack_units'];
    }
    if ($summary['month_until'] !== null) {
        $parts[] = 'Gói tháng tới ' . date('d/m/Y', strtotime($summary['month_until']));
    }
    return implode(' · ', $parts);
}

/** Giá công khai của HLV, ví dụ "300.000đ/buổi · 2.500.000đ/tháng". Chưa đặt giá → "". */
function coachPriceLine(?int $perSession, ?int $perMonth): string
{
    $parts = [];
    if ($perSession) {
        $parts[] = coachMoney($perSession) . '/buổi';
    }
    if ($perMonth) {
        $parts[] = coachMoney($perMonth) . '/tháng';
    }
    return implode(' · ', $parts);
}

// =====================================================================
// TÀI KHOẢN NHẬN CHUYỂN KHOẢN VÀ MÃ VIETQR (A-56)
// =====================================================================
// HLV ghi ngân hàng + số TK + tên chủ TK; học viên có đơn đang chờ thấy ảnh mã VietQR (do img.vietqr.io vẽ)
// đã điền sẵn số tiền của đơn và nội dung "GS<mã đơn>". Không có cổng thanh toán: HLV vẫn tự bấm "Đã nhận tiền".

const COACH_BANK_ACCOUNT_MIN = 6;                 // số tài khoản: chỉ chữ số, 6-19 ký tự
const COACH_BANK_ACCOUNT_MAX = 19;
const COACH_BANK_HOLDER_MAX = 50;                 // tên chủ TK: chữ in hoa không dấu, tối đa 50 ký tự
const COACH_QR_PREFIX = 'GS';                     // nội dung chuyển khoản = GS + mã đơn, để HLV đối chiếu sao kê

/**
 * Danh sách ngân hàng được chọn (whitelist): mã BIN (mã ngân hàng của NAPAS) => tên ngắn.
 * Đối chiếu ngày 26/11/2026 với danh sách công khai https://api.vietqr.io/v2/banks (các dòng có transferSupported = 1).
 * Thêm ngân hàng mới thì phải kiểm tra lại mã ở đúng nguồn đó, không tự đoán.
 */
const COACH_BANKS = [
    '970436' => 'Vietcombank',
    '970415' => 'VietinBank',
    '970418' => 'BIDV',
    '970405' => 'Agribank',
    '970407' => 'Techcombank',
    '970422' => 'MB Bank',
    '970416' => 'ACB',
    '970432' => 'VPBank',
    '970423' => 'TPBank',
    '970403' => 'Sacombank',
    '970441' => 'VIB',
    '970443' => 'SHB',
    '970437' => 'HDBank',
    '970448' => 'OCB',
    '970426' => 'MSB',
    '970440' => 'SeABank',
    '970431' => 'Eximbank',
    '970449' => 'LPBank',
    '970428' => 'Nam A Bank',
    '970409' => 'Bac A Bank',
    '970425' => 'ABBANK',
    '970412' => 'PVcomBank',
    '970427' => 'VietABank',
    '970452' => 'Kienlongbank',
    '970419' => 'NCB',
    '970438' => 'BaoViet Bank',
    '970433' => 'VietBank',
    '970454' => 'Viet Capital Bank (BVBank)',
    '970400' => 'Saigonbank',
    '970430' => 'PGBank',
    '546034' => 'CAKE by VPBank',
    '546035' => 'Ubank by VPBank',
    '963388' => 'Timo',
];

/** Số tài khoản hợp lệ: chỉ chữ số 0-9, dài 6-19. */
function coachBankAccountValid($acc): bool
{
    return is_string($acc) && preg_match('/^[0-9]{' . COACH_BANK_ACCOUNT_MIN . ',' . COACH_BANK_ACCOUNT_MAX . '}$/', $acc) === 1;
}

/** Tên chủ TK hợp lệ: các từ chữ A-Z in hoa, cách nhau đúng 1 khoảng trắng, tối đa 50 ký tự. */
function coachBankHolderValid($holder): bool
{
    return is_string($holder) && strlen($holder) <= COACH_BANK_HOLDER_MAX && preg_match('/^[A-Z]+( [A-Z]+)*$/', $holder) === 1;
}

/**
 * Chuẩn hóa tên chủ TK người dùng gõ: bỏ khoảng trắng 2 đầu, gộp khoảng trắng liền nhau, đổi a-z thành A-Z.
 * KHÔNG tự bỏ dấu tiếng Việt (tự bỏ có thể ra tên khác tên trên tài khoản): còn dấu thì validateCoachBank() báo lỗi.
 * strtoupper() từ PHP 8.2 chỉ đổi chữ ASCII, nên "ễ" vẫn giữ nguyên và bị bắt lỗi.
 */
function coachNormalizeHolder($raw): string
{
    if (!is_string($raw)) {
        return '';
    }
    return strtoupper(trim(preg_replace('/\s+/u', ' ', $raw) ?? ''));
}

/**
 * Kiểm tra phần "Tài khoản nhận chuyển khoản" trong form giá.
 * Để trống cả 3 ô = xóa tài khoản (data toàn null). Điền 1-2 ô = báo thiếu ô còn lại.
 * Trả về ['data' => ['bank_bin' => ?string, 'bank_account' => ?string, 'bank_holder' => ?string], 'errors' => [tên ô => lỗi]].
 */
function validateCoachBank(array $in): array
{
    $bin = is_string($in['bank_bin'] ?? null) ? trim($in['bank_bin']) : '';
    $acc = is_string($in['bank_account'] ?? null) ? str_replace([' ', '-', '.'], '', trim($in['bank_account'])) : '';
    $holder = coachNormalizeHolder($in['bank_holder'] ?? '');
    $data = ['bank_bin' => null, 'bank_account' => null, 'bank_holder' => null];

    if ($bin === '' && $acc === '' && $holder === '') {
        return ['data' => $data, 'errors' => []];
    }
    $errors = [];
    if ($bin === '') {
        $errors['bank_bin'] = 'Chọn ngân hàng.';
    } elseif (!array_key_exists($bin, COACH_BANKS)) {
        $errors['bank_bin'] = 'Ngân hàng không có trong danh sách.';
    }
    if ($acc === '') {
        $errors['bank_account'] = 'Nhập số tài khoản.';
    } elseif (!coachBankAccountValid($acc)) {
        $errors['bank_account'] = 'Số tài khoản chỉ gồm chữ số, dài ' . COACH_BANK_ACCOUNT_MIN . '–' . COACH_BANK_ACCOUNT_MAX . ' ký tự.';
    }
    if ($holder === '') {
        $errors['bank_holder'] = 'Nhập tên chủ tài khoản.';
    } elseif (mb_strlen($holder) > COACH_BANK_HOLDER_MAX) {
        $errors['bank_holder'] = 'Tên chủ tài khoản tối đa ' . COACH_BANK_HOLDER_MAX . ' ký tự.';
    } elseif (!coachBankHolderValid($holder)) {
        $errors['bank_holder'] = 'Tên chủ tài khoản viết in hoa không dấu, chỉ gồm chữ cái và khoảng trắng, ví dụ NGUYEN VAN A.';
    }
    if (!$errors) {
        $data = ['bank_bin' => $bin, 'bank_account' => $acc, 'bank_holder' => $holder];
    }
    return ['data' => $data, 'errors' => $errors];
}

/** Che số tài khoản khi ghi nhật ký / hiện tóm tắt: "0123456789" → "****6789". */
function coachMaskAccount(string $acc): string
{
    return '****' . substr($acc, -4);
}

/** Nội dung chuyển khoản của một đơn: "GS123". */
function coachTransferNote(int $orderId): string
{
    return COACH_QR_PREFIX . $orderId;
}

/**
 * Đường dẫn ảnh mã VietQR cho một đơn. Kiểm tra lại MỌI giá trị ngay trước khi ghép (không tin dữ liệu đã lưu),
 * mỗi phần đi qua rawurlencode() để ký tự lạ không chèn thêm tham số được. Khi in ra HTML vẫn phải htmlspecialchars().
 * Trả về null nếu có giá trị không hợp lệ → trang hiện hướng dẫn thanh toán dạng chữ như cũ.
 */
function coachVietQrUrl($bin, $acc, $holder, int $amount, int $orderId): ?string
{
    if (!is_string($bin) || !array_key_exists($bin, COACH_BANKS) || !coachBankAccountValid($acc)
        || !coachBankHolderValid($holder) || $amount <= 0 || $orderId <= 0) {
        return null;
    }
    return 'https://img.vietqr.io/image/' . rawurlencode($bin) . '-' . rawurlencode($acc) . '-compact2.png'
        . '?amount=' . rawurlencode((string)$amount)
        . '&addInfo=' . rawurlencode(coachTransferNote($orderId))
        . '&accountName=' . rawurlencode($holder);
}
