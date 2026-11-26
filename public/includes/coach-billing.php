<?php
/**
 * includes/coach-billing.php — truy vấn DB cho giá tập với HLV, buổi thử, lịch hẹn và gói (A-54). Bảng: sql/coach_billing.sql.
 *
 * Quy ước giống coach-db.php:
 *   - $clientId / $coachId lấy từ $_SESSION ở nơi gọi. Mọi câu SQL tự kiểm tra lại buổi / đơn thuộc đúng quan hệ của người đó.
 *   - Lỗi nghiệp vụ ném CoachException (câu tiếng Việt hiện thẳng cho người dùng).
 *   - Thời gian lấy từ PHP (coachNow()) để test chạy được trên SQLite.
 *   - Database chưa chạy sql/coach_billing.sql thì coachBillingReady() = false: không khóa gì, các trang ẩn phần giá.
 * Quy tắc tính toán là hàm thuần trong coach-billing-rules.php.
 */
require_once __DIR__ . '/coach-db.php';
require_once __DIR__ . '/coach-billing-rules.php';

/** Database đã có bảng/cột của A-54 chưa (coach_sessions, coach_orders, cột giá trong coach_profiles). */
function coachBillingReady(PDO $pdo): bool
{
    static $ready = [];
    $key = spl_object_id($pdo);
    if (!isset($ready[$key])) {
        try {
            $pdo->query('SELECT price_per_session, trial_sessions FROM coach_profiles WHERE 1 = 0');
            $pdo->query('SELECT 1 FROM coach_sessions WHERE 1 = 0');
            $pdo->query('SELECT 1 FROM coach_orders WHERE 1 = 0');
            $ready[$key] = true;
        } catch (PDOException $e) {
            $ready[$key] = false;
        }
    }
    return $ready[$key];
}

// =====================================================================
// 1. GIÁ CỦA HLV
// =====================================================================

/** Giá và buổi thử hiện tại của một HLV. */
function coachPricing(PDO $pdo, int $coachId): array
{
    $st = $pdo->prepare('SELECT price_per_session, price_per_month, trial_sessions, payment_note FROM coach_profiles WHERE user_id = ?');
    $st->execute([$coachId]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'price_per_session' => isset($r['price_per_session']) ? (int)$r['price_per_session'] : null,
        'price_per_month'   => isset($r['price_per_month']) ? (int)$r['price_per_month'] : null,
        'trial_sessions'    => (int)($r['trial_sessions'] ?? COACH_TRIAL_MIN),
        'payment_note'      => (string)($r['payment_note'] ?? ''),
    ];
}

/** Giá công khai của nhiều HLV (cho trang danh sách HLV): [coach_id => ['price_per_session','price_per_month','trial_sessions']]. */
function coachPricingMap(PDO $pdo, array $coachIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $coachIds), fn($id) => $id > 0)));
    if (!$ids) {
        return [];
    }
    $st = $pdo->prepare('SELECT user_id, price_per_session, price_per_month, trial_sessions FROM coach_profiles WHERE user_id IN ('
        . coachPlaceholders($ids) . ')');   // audit:ok coachPlaceholders() chỉ ghép dấu ?
    $st->execute($ids);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int)$r['user_id']] = [
            'price_per_session' => $r['price_per_session'] !== null ? (int)$r['price_per_session'] : null,
            'price_per_month'   => $r['price_per_month'] !== null ? (int)$r['price_per_month'] : null,
            'trial_sessions'    => (int)$r['trial_sessions'],
        ];
    }
    return $out;
}

/** HLV lưu giá ($data đã qua validateCoachPricing). Chỉ áp dụng cho đơn mới; đơn đang chờ giữ giá cũ. */
function coachSavePricing(PDO $pdo, int $coachId, array $data): void
{
    $st = $pdo->prepare(
        "UPDATE coach_profiles SET price_per_session = ?, price_per_month = ?, trial_sessions = ?, payment_note = ?, updated_at = ?
         WHERE user_id = ? AND user_id IN (SELECT id FROM users WHERE role = 'COACH')"
    );
    $st->execute([$data['price_per_session'], $data['price_per_month'], $data['trial_sessions'], $data['payment_note'], coachNow(), $coachId]);
    if ($st->rowCount() === 0 && coachUserRole($pdo, $coachId) !== 'COACH') {
        throw new CoachException('Tài khoản này không còn là huấn luyện viên.');
    }
}

/** Database đã chạy sql/coach_bank.sql chưa (A-56: cột bank_bin, bank_account, bank_holder). Chưa thì ẩn phần VietQR. */
function coachBankReady(PDO $pdo): bool
{
    static $ready = [];
    $key = spl_object_id($pdo);
    if (!isset($ready[$key])) {
        try {
            $pdo->query('SELECT bank_bin, bank_account, bank_holder FROM coach_profiles WHERE 1 = 0');
            $ready[$key] = true;
        } catch (PDOException $e) {
            $ready[$key] = false;
        }
    }
    return $ready[$key];
}

/**
 * Tài khoản nhận chuyển khoản của HLV: ['bank_bin','bank_account','bank_holder'] hoặc null khi chưa có / DB chưa nâng cấp.
 * Thông tin riêng tư: chỉ gọi cho chính HLV (trang Hồ sơ) hoặc học viên đang có đơn chờ với HLV đó (my-coach.php).
 */
function coachBankInfo(PDO $pdo, int $coachId): ?array
{
    if (!coachBankReady($pdo)) {
        return null;
    }
    $st = $pdo->prepare('SELECT bank_bin, bank_account, bank_holder FROM coach_profiles WHERE user_id = ?');
    $st->execute([$coachId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r === false || $r['bank_bin'] === null || $r['bank_account'] === null || $r['bank_holder'] === null) {
        return null;
    }
    return ['bank_bin' => (string)$r['bank_bin'], 'bank_account' => (string)$r['bank_account'], 'bank_holder' => (string)$r['bank_holder']];
}

/** HLV lưu / xóa tài khoản nhận chuyển khoản ($data đã qua validateCoachBank; toàn null = xóa). */
function coachSaveBank(PDO $pdo, int $coachId, array $data): void
{
    $pdo->prepare("UPDATE coach_profiles SET bank_bin = ?, bank_account = ?, bank_holder = ?
                   WHERE user_id = ? AND user_id IN (SELECT id FROM users WHERE role = 'COACH')")
        ->execute([$data['bank_bin'], $data['bank_account'], $data['bank_holder'], $coachId]);
}

// =====================================================================
// 2. TRẠNG THÁI QUYỀN TẬP CỦA MỘT QUAN HỆ
// =====================================================================

/** Quan hệ kèm số buổi thử (chụp lại lúc gửi yêu cầu, quan hệ cũ thì lấy theo hồ sơ HLV) và giá hiện tại của HLV. */
function coachBillingRelation(PDO $pdo, int $relationId): ?array
{
    $st = $pdo->prepare(
        'SELECT cc.id, cc.coach_id, cc.client_id, cc.status, cc.accepted_at,
                COALESCE(cc.trial_sessions, p.trial_sessions, ' . COACH_TRIAL_MIN . ') AS trial,
                p.price_per_session, p.price_per_month, p.payment_note,
                uc.full_name AS client_name, uh.full_name AS coach_name
         FROM coach_clients cc
         JOIN users uc ON uc.id = cc.client_id
         JOIN users uh ON uh.id = cc.coach_id
         LEFT JOIN coach_profiles p ON p.user_id = cc.coach_id
         WHERE cc.id = ?'   // audit:ok COACH_TRIAL_MIN là hằng số nguyên viết sẵn
    );
    $st->execute([$relationId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r === false) {
        return null;
    }
    $r['trial'] = (int)$r['trial'];
    $r['price_per_session'] = $r['price_per_session'] !== null ? (int)$r['price_per_session'] : null;
    $r['price_per_month'] = $r['price_per_month'] !== null ? (int)$r['price_per_month'] : null;
    return $r;
}

/** Buổi đang chờ HLV mà đã tới giờ → EXPIRED (trả suất lại). Gọi trước mỗi lần đọc / ghi buổi hẹn. */
function coachExpireSessions(PDO $pdo, ?int $relationId = null, ?int $coachId = null): void
{
    $now = coachNow();
    if ($relationId !== null) {
        $pdo->prepare("UPDATE coach_sessions SET status = 'EXPIRED', updated_at = ? WHERE coach_client_id = ? AND status = 'REQUESTED' AND starts_at <= ?")
            ->execute([$now, $relationId, $now]);
    } elseif ($coachId !== null) {
        $pdo->prepare("UPDATE coach_sessions SET status = 'EXPIRED', updated_at = ? WHERE coach_id = ? AND status = 'REQUESTED' AND starts_at <= ?")
            ->execute([$now, $coachId, $now]);
    }
}

/** Các gói tháng đã thanh toán của quan hệ: [['starts_on','ends_on'], ...]. */
function coachMonthWindows(PDO $pdo, int $relationId): array
{
    $st = $pdo->prepare("SELECT starts_on, ends_on FROM coach_orders WHERE coach_client_id = ? AND kind = 'MONTH' AND status = 'PAID' AND starts_on IS NOT NULL ORDER BY starts_on");
    $st->execute([$relationId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Quyền tập hiện tại của một quan hệ: kết quả coachBillingSummary() + 'windows' (gói tháng) + 'relation'.
 * null nếu không có quan hệ này.
 */
function coachBillingState(PDO $pdo, int $relationId): ?array
{
    $rel = coachBillingRelation($pdo, $relationId);
    if ($rel === null) {
        return null;
    }
    coachExpireSessions($pdo, $relationId);
    $st = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM coach_orders WHERE coach_client_id = ? AND kind = 'SESSIONS' AND status = 'PAID'");
    $st->execute([$relationId]);
    $packUnits = (int)$st->fetchColumn();
    $windows = coachMonthWindows($pdo, $relationId);
    $st = $pdo->prepare('SELECT billing, status FROM coach_sessions WHERE coach_client_id = ?');
    $st->execute([$relationId]);
    $summary = coachBillingSummary($rel['trial'], $packUnits, $windows, $st->fetchAll(PDO::FETCH_ASSOC), date('Y-m-d'));
    return $summary + ['windows' => $windows, 'relation' => $rel];
}

/** Quan hệ đang bị khóa vì hết buổi không. DB chưa nâng cấp → không khóa. */
function coachBillingLocked(PDO $pdo, int $relationId): bool
{
    if (!coachBillingReady($pdo)) {
        return false;
    }
    $state = coachBillingState($pdo, $relationId);
    return $state !== null && $state['locked'];
}

/**
 * Chặn thao tác của HLV (giao lịch, đặt dinh dưỡng, nhận xét) khi học viên hết buổi.
 * Gọi ở máy chủ trước khi ghi (không chỉ ẩn nút). Không có quan hệ ACTIVE thì để các bước kiểm tra quyền khác xử lý.
 */
function coachBillingAssert(PDO $pdo, int $coachId, int $clientId): void
{
    if (!coachBillingReady($pdo)) {
        return;
    }
    $relationId = coachActiveRelationId($pdo, $coachId, $clientId);
    if ($relationId !== null && coachBillingLocked($pdo, $relationId)) {
        throw new CoachException(coachBillingLockMessage(true));
    }
}

/** Quyền tập của mọi học viên đang ACTIVE của HLV: [client_id => state]. Dùng cho trang Lịch hẹn và danh sách học viên. */
function coachBillingOverview(PDO $pdo, int $coachId): array
{
    $st = $pdo->prepare("SELECT id, client_id FROM coach_clients WHERE coach_id = ? AND status = 'ACTIVE'");
    $st->execute([$coachId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $state = coachBillingState($pdo, (int)$r['id']);
        if ($state !== null) {
            $out[(int)$r['client_id']] = $state;
        }
    }
    return $out;
}

// =====================================================================
// 3. LỊCH HẸN
// =====================================================================

/**
 * Học viên đặt một buổi tập với HLV hiện tại. Trả về ['id','starts_at','billing'].
 * Trong 1 transaction có khóa dòng hồ sơ HLV (FOR UPDATE), nên 2 học viên cùng đặt một giờ thì người sau bị báo trùng,
 * và một học viên bấm 2 lần cũng không vượt quá số buổi còn lại.
 */
function coachBookSession(PDO $pdo, int $clientId, $date, $time, string $note): array
{
    $startsAt = coachBookingStart($date, $time);
    if ($startsAt === null) {
        throw new CoachException('Ngày hoặc giờ không hợp lệ.');
    }
    if ($e = coachBookingTimeError($startsAt, coachNow())) {
        throw new CoachException($e);
    }
    if ($e = coachTextError($note, 'Lời nhắn', 0, COACH_SESSION_NOTE_MAX)) {
        throw new CoachException($e);
    }
    return coachTx($pdo, function () use ($pdo, $clientId, $startsAt, $note) {
        $rel = coachOpenRelationForClient($pdo, $clientId);
        if ($rel === null || $rel['status'] !== 'ACTIVE') {
            throw new CoachException('Bạn cần đang tập với một HLV mới đặt buổi được.');
        }
        $relationId = (int)$rel['id'];
        $coachId = (int)$rel['coach_id'];
        $pdo->prepare('SELECT user_id FROM coach_profiles WHERE user_id = ?' . coachForUpdate($pdo))->execute([$coachId]);   // audit:ok coachForUpdate() chỉ trả " FOR UPDATE" hoặc rỗng

        $now = coachNow();
        $state = coachBillingState($pdo, $relationId);
        $st = $pdo->prepare("SELECT COUNT(*) FROM coach_sessions WHERE coach_client_id = ? AND status IN ('REQUESTED', 'CONFIRMED') AND starts_at > ?");
        $st->execute([$relationId, $now]);
        if ((int)$st->fetchColumn() >= COACH_BOOK_MAX_OPEN) {
            throw new CoachException('Bạn đã có ' . COACH_BOOK_MAX_OPEN . ' buổi sắp tới. Tập xong bớt rồi đặt tiếp.');
        }
        // Trùng giờ: so với mọi buổi đang chờ / đã xác nhận của HLV (cả của học viên khác), cách nhau dưới 60 phút là trùng
        $gap = COACH_SESSION_MINUTES * 60 - 1;
        $st = $pdo->prepare("SELECT starts_at FROM coach_sessions WHERE coach_id = ? AND status IN ('REQUESTED', 'CONFIRMED') AND starts_at BETWEEN ? AND ?");
        $st->execute([$coachId, date('Y-m-d H:i:s', strtotime($startsAt) - $gap), date('Y-m-d H:i:s', strtotime($startsAt) + $gap)]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $other) {
            if (coachSessionsClash($startsAt, (string)$other)) {
                throw new CoachException('HLV đã có lịch gần giờ này (' . date('H:i', strtotime((string)$other)) . '). Hãy chọn giờ khác.');
            }
        }
        $billing = coachPickBilling($state, $state['windows'], substr($startsAt, 0, 10));
        if ($billing === null) {
            throw new CoachException('Bạn đã dùng hết buổi tập thử và không còn buổi nào trong gói. Hãy mua gói để đặt thêm buổi.');
        }
        $pdo->prepare("INSERT INTO coach_sessions (coach_client_id, coach_id, starts_at, billing, status, client_note, created_at) VALUES (?, ?, ?, ?, 'REQUESTED', ?, ?)")
            ->execute([$relationId, $coachId, $startsAt, $billing, $note, $now]);
        $id = (int)$pdo->lastInsertId();
        notify($pdo, $coachId, 'coach.session', 'Học viên đặt buổi tập',
            notifName($pdo, $clientId) . ' đặt buổi ' . coachSessionWhen($startsAt) . ' (' . COACH_BILLING_LABELS[$billing] . ')'
            . ($note !== '' ? ': ' . notifExcerpt($note) : '.'), '/coach/sessions.php');
        return ['id' => $id, 'starts_at' => $startsAt, 'billing' => $billing];
    });
}

/**
 * Đổi trạng thái một buổi theo coachSessionNextStatus().
 *   $actor = 'client': $actorId là học viên, chỉ đụng buổi của quan hệ có client_id = $actorId
 *   $actor = 'coach' : $actorId là HLV,     chỉ đụng buổi của quan hệ có coach_id  = $actorId
 * Quan hệ phải còn ACTIVE. Trả về dòng buổi (kèm client_id, client_name, status mới, 'now_locked' => học viên vừa hết buổi chưa).
 */
function coachSessionAction(PDO $pdo, string $actor, int $actorId, int $sessionId, string $action): array
{
    return coachTx($pdo, function () use ($pdo, $actor, $actorId, $sessionId, $action) {
        $col = $actor === 'coach' ? 'coach_id' : 'client_id';   // audit:ok chỉ 1 trong 2 tên cột viết sẵn
        $st = $pdo->prepare(
            "SELECT s.id, s.coach_client_id, s.starts_at, s.billing, s.status, cc.coach_id, cc.client_id, u.full_name AS client_name
             FROM coach_sessions s JOIN coach_clients cc ON cc.id = s.coach_client_id JOIN users u ON u.id = cc.client_id
             WHERE s.id = ? AND cc.$col = ? AND cc.status = 'ACTIVE'" . coachForUpdate($pdo)   // audit:ok $col viết sẵn, coachForUpdate() chỉ trả " FOR UPDATE" hoặc rỗng
        );
        $st->execute([$sessionId, $actorId]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        if ($s === false) {
            throw new CoachException('Không tìm thấy buổi tập này.');
        }
        $now = coachNow();
        if (coachSessionExpired($s['status'], $s['starts_at'], $now)) {
            coachExpireSessions($pdo, (int)$s['coach_client_id']);
            throw new CoachException('Buổi này đã quá giờ mà chưa được HLV xác nhận nên đã hết hạn.');
        }
        $next = coachSessionNextStatus($s['status'], $actor, $action, $s['starts_at'], $now);
        if ($next === null) {
            $why = in_array($action, ['done', 'no_show'], true) ? ' Chỉ đánh dấu được sau giờ bắt đầu buổi tập.' : '';
            throw new CoachException('Thao tác không còn phù hợp (buổi đang ở trạng thái: ' . (COACH_SESSION_LABELS[$s['status']] ?? $s['status']) . ').' . $why);
        }
        $cancelBy = in_array($next, ['CANCELLED', 'DECLINED'], true) ? $actorId : null;
        $pdo->prepare('UPDATE coach_sessions SET status = ?, cancelled_by = ?, updated_at = ? WHERE id = ?')->execute([$next, $cancelBy, $now, $sessionId]);
        $s['status'] = $next;

        $when = coachSessionWhen($s['starts_at']);
        $clientId = (int)$s['client_id'];
        $coachId = (int)$s['coach_id'];
        if ($actor === 'client') {
            notify($pdo, $coachId, 'coach.session', 'Học viên hủy buổi tập', $s['client_name'] . ' đã hủy buổi ' . $when . '.', '/coach/sessions.php');
        } else {
            $coachName = notifName($pdo, $coachId);
            $texts = [
                'CONFIRMED' => ['HLV đã xác nhận buổi tập', "HLV $coachName xác nhận buổi $when."],
                'DECLINED'  => ['HLV không nhận buổi tập', "HLV $coachName không nhận buổi $when. Bạn có thể chọn giờ khác hoặc nhắn tin với HLV."],
                'CANCELLED' => ['HLV hủy buổi tập', "HLV $coachName đã hủy buổi $when. Buổi này không bị trừ."],
                'DONE'      => ['Đã ghi nhận buổi tập', "HLV $coachName xác nhận bạn đã tập buổi $when."],
                'NO_SHOW'   => ['Bạn vắng buổi tập', "HLV $coachName ghi nhận bạn vắng buổi $when. Buổi vắng vẫn bị trừ."],
            ];
            notify($pdo, $clientId, 'coach.session', $texts[$next][0], $texts[$next][1], '/my-coach.php?tab=sessions');
        }
        // Buổi cuối cùng vừa dùng xong → báo học viên mua gói
        $s['now_locked'] = false;
        if (in_array($next, COACH_SESSION_USED, true)) {
            $state = coachBillingState($pdo, (int)$s['coach_client_id']);
            if ($state !== null && $state['locked']) {
                $s['now_locked'] = true;
                notify($pdo, $clientId, 'coach.billing', 'Bạn đã dùng hết buổi tập',
                    'Buổi tập thử và các gói đã dùng hết. Mua gói ở trang HLV của tôi để HLV tiếp tục giao lịch và nhận xét.', '/my-coach.php?tab=sessions');
            }
        }
        return $s;
    });
}

/** Các buổi của một quan hệ (sắp tới trước, rồi tới các buổi đã qua gần nhất). */
function coachSessionsForRelation(PDO $pdo, int $relationId, int $limit = 40): array
{
    coachExpireSessions($pdo, $relationId);
    $st = $pdo->prepare('SELECT id, starts_at, billing, status, client_note, created_at FROM coach_sessions WHERE coach_client_id = ? ORDER BY starts_at DESC LIMIT '
        . max(1, min(200, $limit)));   // audit:ok số nguyên đã giới hạn
    $st->execute([$relationId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $now = coachNow();
    $upcoming = array_reverse(array_values(array_filter($rows, fn($r) => in_array($r['status'], COACH_SESSION_HOLDING, true) && $r['starts_at'] > $now)));
    $past = array_values(array_filter($rows, fn($r) => !(in_array($r['status'], COACH_SESSION_HOLDING, true) && $r['starts_at'] > $now)));
    return ['upcoming' => $upcoming, 'past' => $past];
}

/**
 * Lịch hẹn cho trang Lịch hẹn của HLV, chỉ học viên còn ACTIVE:
 *   requests: học viên vừa đặt, chờ HLV xác nhận
 *   to_mark : đã xác nhận, đã qua giờ bắt đầu, chưa đánh dấu Đã tập / Vắng
 *   upcoming: đã xác nhận, sắp tới
 *   recent  : đã xong / hủy trong 14 ngày qua
 */
function coachSessionsForCoach(PDO $pdo, int $coachId): array
{
    coachExpireSessions($pdo, null, $coachId);
    $now = coachNow();
    $st = $pdo->prepare(
        "SELECT s.id, s.starts_at, s.billing, s.status, s.client_note, s.updated_at, cc.client_id, u.full_name AS client_name, u.avatar
         FROM coach_sessions s JOIN coach_clients cc ON cc.id = s.coach_client_id JOIN users u ON u.id = cc.client_id
         WHERE s.coach_id = ? AND cc.coach_id = ? AND cc.status = 'ACTIVE' AND (s.status IN ('REQUESTED', 'CONFIRMED') OR s.starts_at >= ?)
         ORDER BY s.starts_at"
    );
    $st->execute([$coachId, $coachId, date('Y-m-d H:i:s', time() - 14 * 86400)]);
    $out = ['requests' => [], 'to_mark' => [], 'upcoming' => [], 'recent' => []];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['status'] === 'REQUESTED') {
            $out['requests'][] = $r;
        } elseif ($r['status'] === 'CONFIRMED') {
            $out[$r['starts_at'] <= $now ? 'to_mark' : 'upcoming'][] = $r;
        } else {
            $out['recent'][] = $r;
        }
    }
    $out['recent'] = array_reverse($out['recent']);
    return $out;
}

// =====================================================================
// 4. ĐƠN MUA GÓI (thanh toán ngoài hệ thống, HLV xác nhận đã nhận tiền)
// =====================================================================

/** Học viên chọn gói. Giá lấy theo hồ sơ HLV lúc này và chụp lại vào đơn. Trả về id đơn. */
function coachCreateOrder(PDO $pdo, int $clientId, $kind, $quantity): int
{
    $rel = coachOpenRelationForClient($pdo, $clientId);
    if ($rel === null || $rel['status'] !== 'ACTIVE') {
        throw new CoachException('Bạn cần đang tập với một HLV mới mua gói được.');
    }
    $relation = coachBillingRelation($pdo, (int)$rel['id']);
    $q = coachOrderQuote($relation, $kind, $quantity);
    if ($q['error'] !== null) {
        throw new CoachException($q['error']);
    }
    $quote = $q['quote'];
    try {
        $pdo->prepare("INSERT INTO coach_orders (coach_client_id, kind, quantity, unit_price, amount, status, created_at) VALUES (?, ?, ?, ?, ?, 'PENDING', ?)")
            ->execute([(int)$rel['id'], $quote['kind'], $quote['quantity'], $quote['unit_price'], $quote['amount'], coachNow()]);
    } catch (PDOException $e) {
        if (coachIsDuplicate($e)) {   // UNIQUE(open_rel): mỗi quan hệ chỉ 1 đơn đang chờ
            throw new CoachException('Bạn đang có một đơn chờ HLV xác nhận thanh toán. Hủy đơn đó nếu muốn đổi gói.');
        }
        throw $e;
    }
    $id = (int)$pdo->lastInsertId();
    notify($pdo, (int)$rel['coach_id'], 'coach.order', 'Học viên chọn mua gói',
        notifName($pdo, $clientId) . ' chọn ' . mb_strtolower(coachOrderLabel($quote['kind'], $quote['quantity'])) . ', ' . coachMoney($quote['amount'])
        . '. Khi nhận được tiền, hãy bấm "Đã nhận tiền".', '/coach/sessions.php');
    return $id;
}

/** Học viên hủy đơn đang chờ của chính mình. */
function coachCancelOrderByClient(PDO $pdo, int $clientId, int $orderId): void
{
    $st = $pdo->prepare(
        "UPDATE coach_orders SET status = 'CANCELLED', cancelled_by = ?, cancelled_at = ?
         WHERE id = ? AND status = 'PENDING' AND coach_client_id IN (SELECT id FROM coach_clients WHERE client_id = ? AND status = 'ACTIVE')"
    );
    $st->execute([$clientId, coachNow(), $orderId, $clientId]);
    if ($st->rowCount() === 0) {
        throw new CoachException('Không có đơn đang chờ nào để hủy.');
    }
}

/**
 * HLV xác nhận đã nhận tiền ($paid = true) hoặc hủy đơn ($paid = false, kèm lý do không bắt buộc).
 * Gói tháng được điền ngày bắt đầu / kết thúc (nối tiếp gói tháng trước nếu còn hạn). Trả về dòng đơn kèm client_id, client_name.
 */
function coachResolveOrder(PDO $pdo, int $coachId, int $orderId, bool $paid, string $note): array
{
    if ($e = coachTextError($note, 'Lý do', 0, 300)) {
        throw new CoachException($e);
    }
    return coachTx($pdo, function () use ($pdo, $coachId, $orderId, $paid, $note) {
        $st = $pdo->prepare(
            "SELECT o.id, o.coach_client_id, o.kind, o.quantity, o.amount, cc.client_id, u.full_name AS client_name
             FROM coach_orders o JOIN coach_clients cc ON cc.id = o.coach_client_id JOIN users u ON u.id = cc.client_id
             WHERE o.id = ? AND cc.coach_id = ? AND cc.status = 'ACTIVE' AND o.status = 'PENDING'" . coachForUpdate($pdo)   // audit:ok coachForUpdate() chỉ trả " FOR UPDATE" hoặc rỗng
        );
        $st->execute([$orderId, $coachId]);
        $o = $st->fetch(PDO::FETCH_ASSOC);
        if ($o === false) {
            throw new CoachException('Đơn không tồn tại hoặc đã được xử lý.');
        }
        $now = coachNow();
        $label = coachOrderLabel($o['kind'], (int)$o['quantity']);
        $coachName = notifName($pdo, $coachId);
        if ($paid) {
            $start = $end = null;
            if ($o['kind'] === 'MONTH') {
                $last = $pdo->prepare("SELECT MAX(ends_on) FROM coach_orders WHERE coach_client_id = ? AND kind = 'MONTH' AND status = 'PAID'");
                $last->execute([$o['coach_client_id']]);
                $lastEnd = $last->fetchColumn();
                [$start, $end] = coachMonthWindow($lastEnd ? (string)$lastEnd : null, date('Y-m-d'), (int)$o['quantity']);
            }
            $pdo->prepare("UPDATE coach_orders SET status = 'PAID', paid_at = ?, starts_on = ?, ends_on = ? WHERE id = ?")->execute([$now, $start, $end, $orderId]);
            $o['starts_on'] = $start;
            $o['ends_on'] = $end;
            notify($pdo, (int)$o['client_id'], 'coach.order', 'HLV đã xác nhận thanh toán',
                "HLV $coachName đã nhận " . coachMoney((int)$o['amount']) . ' cho ' . mb_strtolower($label)
                . ($end !== null ? ' (từ ' . date('d/m', strtotime($start)) . ' tới ' . date('d/m/Y', strtotime($end)) . ').' : '. Bạn đặt buổi tập được rồi.'),
                '/my-coach.php?tab=sessions');
        } else {
            $pdo->prepare("UPDATE coach_orders SET status = 'CANCELLED', cancel_note = ?, cancelled_by = ?, cancelled_at = ? WHERE id = ?")
                ->execute([$note, $coachId, $now, $orderId]);
            notify($pdo, (int)$o['client_id'], 'coach.order', 'HLV đã hủy đơn mua gói',
                "HLV $coachName hủy đơn " . mb_strtolower($label) . ($note !== '' ? '. Lý do: ' . notifExcerpt($note) : '.'), '/my-coach.php?tab=sessions');
        }
        return $o;
    });
}

/** Các đơn của một quan hệ, mới nhất trước. */
function coachOrders(PDO $pdo, int $relationId, int $limit = 20): array
{
    $st = $pdo->prepare('SELECT id, kind, quantity, unit_price, amount, status, starts_on, ends_on, cancel_note, created_at, paid_at, cancelled_at
        FROM coach_orders WHERE coach_client_id = ? ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)));   // audit:ok số nguyên đã giới hạn
    $st->execute([$relationId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Đơn chờ HLV xác nhận tiền (của học viên còn ACTIVE). */
function coachPendingOrders(PDO $pdo, int $coachId): array
{
    $st = $pdo->prepare(
        "SELECT o.id, o.kind, o.quantity, o.unit_price, o.amount, o.created_at, cc.client_id, u.full_name AS client_name, u.avatar
         FROM coach_orders o JOIN coach_clients cc ON cc.id = o.coach_client_id JOIN users u ON u.id = cc.client_id
         WHERE cc.coach_id = ? AND cc.status = 'ACTIVE' AND o.status = 'PENDING' ORDER BY o.id"
    );
    $st->execute([$coachId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Số việc HLV cần làm ở trang Lịch hẹn: đơn chờ xác nhận tiền, buổi chờ xác nhận, buổi đã qua chưa đánh dấu. */
function coachBillingCounts(PDO $pdo, int $coachId): array
{
    $out = ['orders' => 0, 'requests' => 0, 'to_mark' => 0];
    if (!coachBillingReady($pdo)) {
        return $out;
    }
    coachExpireSessions($pdo, null, $coachId);
    $now = coachNow();
    $st = $pdo->prepare(
        "SELECT (SELECT COUNT(*) FROM coach_orders o JOIN coach_clients cc ON cc.id = o.coach_client_id WHERE cc.coach_id = ? AND cc.status = 'ACTIVE' AND o.status = 'PENDING'),
                (SELECT COUNT(*) FROM coach_sessions s JOIN coach_clients cc ON cc.id = s.coach_client_id WHERE s.coach_id = ? AND cc.status = 'ACTIVE' AND s.status = 'REQUESTED'),
                (SELECT COUNT(*) FROM coach_sessions s JOIN coach_clients cc ON cc.id = s.coach_client_id WHERE s.coach_id = ? AND cc.status = 'ACTIVE' AND s.status = 'CONFIRMED' AND s.starts_at <= ?)"
    );
    $st->execute([$coachId, $coachId, $coachId, $now]);
    [$out['orders'], $out['requests'], $out['to_mark']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));
    return $out;
}

/**
 * Quan hệ kết thúc (học viên ngừng, HLV kết thúc, ADMIN thu hồi HLV): hủy các buổi sắp tới và đơn đang chờ.
 * Gọi bên trong transaction của thao tác kết thúc. Gói đã trả mà chưa dùng hết: hai bên tự thỏa thuận hoàn tiền.
 */
function coachBillingCloseRelation(PDO $pdo, int $relationId, int $actorId): void
{
    if (!coachBillingReady($pdo)) {
        return;
    }
    $now = coachNow();
    $pdo->prepare("UPDATE coach_sessions SET status = 'CANCELLED', cancelled_by = ?, updated_at = ? WHERE coach_client_id = ? AND status IN ('REQUESTED', 'CONFIRMED')")
        ->execute([$actorId, $now, $relationId]);
    $pdo->prepare("UPDATE coach_orders SET status = 'CANCELLED', cancel_note = ?, cancelled_by = ?, cancelled_at = ? WHERE coach_client_id = ? AND status = 'PENDING'")
        ->execute(['Quan hệ huấn luyện đã kết thúc.', $actorId, $now, $relationId]);
}

// =====================================================================
// 5. HIỂN THỊ DÙNG CHUNG
// =====================================================================

/**
 * Dải cảnh báo "học viên đã hết buổi" ở đầu các trang HLV về một học viên (hồ sơ, giao lịch, dinh dưỡng).
 * Trả về chuỗi rỗng khi chưa khóa hoặc DB chưa nâng cấp.
 */
function coachBillingBanner(PDO $pdo, int $relationId): string
{
    if (!coachBillingLocked($pdo, $relationId)) {
        return '';
    }
    return '<div class="adm-errors ch-bill-lock" role="alert"><b>Học viên đã hết buổi tập.</b> '
        . htmlspecialchars(coachBillingLockMessage(true))
        . ' <a class="adm-link" href="/coach/sessions.php">Xem lịch hẹn và gói</a></div>';
}
