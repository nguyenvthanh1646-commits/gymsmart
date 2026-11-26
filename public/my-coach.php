<?php
/**
 * my-coach.php — "HLV của tôi" phía học viên.
 *   ?tab=overview (mặc định): HLV hiện tại, trạng thái khóa lịch tập/dinh dưỡng, yêu cầu xin sửa, ngừng tập
 *   ?tab=feedback: nhận xét của HLV (mở tab thì đánh dấu đã đọc)
 *   ?tab=messages: nhắn tin với HLV (js/coach-chat.js ↔ api/coach-messages.php, api/coach-message-send.php)
 *   ?tab=sessions: buổi tập và gói (A-54): đặt lịch hẹn, hủy buổi, xem buổi thử còn lại, chọn mua gói, lịch sử thanh toán;
 *                  đơn đang chờ hiện mã VietQR nếu HLV đã ghi tài khoản ngân hàng (A-56), không thì hiện hướng dẫn chữ
 * Mọi dữ liệu lấy theo $_SESSION['user_id']; quan hệ lấy bằng coachOpenRelationForClient(), không nhận id từ URL.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/coach.php';
require_once __DIR__ . '/includes/coach-reviews.php';   // đánh giá HLV (A-49)

$userId = (int)$_SESSION['user_id'];
$ready  = coachTablesReady($pdo);
$reviewsOn = $ready && coachReviewsReady($pdo);

// Đánh giá HLV: xử lý riêng trước bộ xử lý chung của học viên. Post/Redirect/Get, chỉ quay về trang nội bộ my-coach.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reviewsOn && in_array($_POST['action'] ?? '', ['review_save', 'review_delete'], true)) {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $relId = coachParseId($_POST['relation_id'] ?? null);
    try {
        if ($_POST['action'] === 'review_save') {
            coachReviewSave($pdo, $userId, $relId, $_POST['rating'] ?? null, (string)($_POST['comment'] ?? ''));
            coachFlash('ok', 'Đã lưu đánh giá. Cảm ơn bạn!');
        } else {
            coachReviewDelete($pdo, $userId, $relId);
            coachFlash('ok', 'Đã xóa đánh giá.');
        }
    } catch (CoachException $e) {
        coachFlash('error', $e->getMessage());
    }
    $back = (string)($_POST['back'] ?? '');
    header('Location: ' . (preg_match('#^/my-coach\.php(\?history=\d+)?$#', $back) ? $back : '/my-coach.php'));
    exit;
}
// Buổi tập và gói (A-54): đặt / hủy buổi, chọn / hủy đơn mua gói. Mọi thao tác dùng user_id trong session.
$billingOn = $ready && coachBillingReady($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $billingOn && in_array($_POST['action'] ?? '', ['book', 'session_cancel', 'order_create', 'order_cancel'], true)) {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    try {
        switch ($_POST['action']) {
            case 'book':
                $b = coachBookSession($pdo, $userId, $_POST['date'] ?? '', $_POST['time'] ?? '', coachCleanText($_POST['note'] ?? ''));
                coachFlash('ok', 'Đã đặt buổi ' . coachSessionWhen($b['starts_at']) . ' (' . COACH_BILLING_LABELS[$b['billing']] . '). HLV sẽ xác nhận sớm.');
                break;
            case 'session_cancel':
                $c = coachSessionAction($pdo, 'client', $userId, coachParseId($_POST['session_id'] ?? null), 'cancel');
                coachFlash('ok', 'Đã hủy buổi ' . coachSessionWhen($c['starts_at']) . '. Buổi này không bị trừ.');
                break;
            case 'order_create':
                [$kind, $qty] = array_pad(explode(':', is_string($_POST['pack'] ?? null) ? $_POST['pack'] : '', 2), 2, '');
                coachCreateOrder($pdo, $userId, $kind, $qty);
                coachFlash('ok', 'Đã gửi đơn mua gói. Hãy thanh toán cho HLV theo hướng dẫn, HLV xác nhận xong thì gói được cộng.');
                break;
            case 'order_cancel':
                coachCancelOrderByClient($pdo, $userId, coachParseId($_POST['order_id'] ?? null));
                coachFlash('ok', 'Đã hủy đơn mua gói.');
                break;
        }
    } catch (CoachException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: /my-coach.php?tab=sessions');
    exit;
}
coachHandleClientAction($pdo, '/my-coach.php');

$flash  = coachTakeFlash();
$rel    = $ready ? coachOpenRelationForClient($pdo, $userId) : null;
$active = $rel !== null && $rel['status'] === 'ACTIVE';
$tabs   = ['overview' => 'Tổng quan', 'sessions' => 'Buổi tập & gói', 'feedback' => 'Nhận xét', 'messages' => 'Tin nhắn'];
if (!$billingOn) {
    unset($tabs['sessions']);   // DB chưa chạy sql/coach_billing.sql
}
$tab    = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'overview';

$coach = $notice = null;
$planState = $nutState = null;
$requests = $feedback = [];
$plan = $target = null;
$bill = null;
$sessions = ['upcoming' => [], 'past' => []];
$orders = [];
if ($active) {
    $relId   = (int)$rel['id'];
    $coachId = (int)$rel['coach_id'];
    $coach   = coachProfile($pdo, $coachId);
    $notice  = coachClientNotice($pdo, $userId);
    $bill    = $billingOn ? coachBillingState($pdo, $relId) : null;
    if ($tab === 'sessions' && $bill !== null) {
        $sessions = coachSessionsForRelation($pdo, $relId);
        $orders = coachOrders($pdo, $relId);
    }
    if ($tab === 'overview') {
        $planState = coachSelfEditState($pdo, $userId, 'PLAN');
        $nutState  = coachSelfEditState($pdo, $userId, 'NUTRITION');
        $requests  = coachChangeRequests($pdo, $relId, false);
        $st = $pdo->prepare("SELECT name, assigned_by FROM workout_plans WHERE user_id = ? AND status = 'ACTIVE' ORDER BY id DESC LIMIT 1");
        $st->execute([$userId]);
        $plan   = $st->fetch() ?: null;
        $target = coachAssignedNutrition($pdo, $userId, $coachId);
    } elseif ($tab === 'feedback') {
        $feedback = coachFeedbackList($pdo, $relId);
        coachMarkFeedbackRead($pdo, $relId);   // đọc xong ở đây; danh sách vẫn tô "mới" cho lần mở này nhờ read_at cũ
    }
}
// Lịch sử với HLV cũ: ?history=id chỉ nhận quan hệ ĐÃ KẾT THÚC của chính người đang đăng nhập (coachPastRelationForClient)
$past = $ready ? coachPastRelations($pdo, $userId) : [];
$hist = null;
$histMessages = $histFeedback = [];
if (isset($_GET['history'])) {
    $hist = $ready ? coachPastRelationForClient($pdo, $userId, coachParseId($_GET['history'])) : null;
    if ($hist === null) {
        showError(404, 'Không tìm thấy', 'Không có lịch sử huấn luyện này trong tài khoản của bạn.', [['/my-coach.php', 'HLV của tôi']]);
    }
    $histMessages = coachMessages($pdo, (int)$hist['id'], 0, 200);
    $histFeedback = coachFeedbackList($pdo, (int)$hist['id'], 100);
}
$reqLabels = ['PENDING' => ['Chờ HLV trả lời', 'warn'], 'APPROVED' => ['Được duyệt', 'ok'], 'REJECTED' => ['Không duyệt', 'danger'], 'CANCELLED' => ['Đã hủy', '']];

/** Một dòng trạng thái khóa (lịch tập / dinh dưỡng). */
function lockLine(array $s, string $label): string
{
    if (!$s['locked']) {
        return '<span class="ch-badge ch-badge--ok">Được tự sửa tới ' . htmlspecialchars(date('H:i d/m', strtotime($s['unlock_until']))) . '</span>';
    }
    return $s['pending']
        ? '<span class="ch-badge ch-badge--warn">Đã xin sửa, chờ HLV</span>'
        : '<span class="ch-badge ch-badge--accent">HLV quản lý ' . htmlspecialchars($label) . '</span>';
}

$pageTitle = 'HLV của tôi';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/includes/header.php';
?>
<div class="ch">
    <header class="pg-head">
        <div>
            <h1>HLV của tôi</h1>
            <p class="pg-sub">Lịch tập, mục tiêu dinh dưỡng, nhận xét và trao đổi với huấn luyện viên của bạn.</p>
        </div>
        <?php if ($active): ?><a class="btn btn--ghost" href="/coaches.php">Danh sách HLV</a><?php endif; ?>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert--<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <?php if ($hist !== null): ?>
        <a class="btn btn--ghost" href="/my-coach.php" style="margin-bottom:var(--s-4)">&larr; HLV của tôi</a>
        <div class="ch-layout">
            <section class="ch-chat" aria-label="Tin nhắn cũ với <?= htmlspecialchars($hist['coach_name']) ?>">
                <header class="ch-chat__head"><?= coachAvatar($hist['coach_avatar'], $hist['coach_name'], 40) ?>
                    <div><b><?= htmlspecialchars($hist['coach_name']) ?></b><span>Đã kết thúc <?= htmlspecialchars(date('d/m/Y', strtotime((string)$hist['ended_at']))) ?></span></div></header>
                <div class="ch-chat__log" tabindex="0" role="log" aria-label="Tin nhắn cũ">
                    <?php if (!$histMessages): ?><p class="ch-day">Không có tin nhắn nào.</p><?php endif; ?>
                    <?php foreach ($histMessages as $m): ?>
                        <div class="ch-msg<?= (int)$m['sender_id'] === $userId ? ' is-me' : '' ?>"><?= htmlspecialchars($m['content']) ?><time><?= htmlspecialchars(date('H:i d/m/Y', strtotime($m['created_at']))) ?></time></div>
                    <?php endforeach; ?>
                </div>
                <p class="ch-chat__closed">Quan hệ huấn luyện đã kết thúc nên không gửi tin được nữa.</p>
            </section>
            <aside class="ch-side">
                <section class="card"><h2 class="card__head">Nhận xét</h2>
                    <?php if (!$histFeedback): ?><p class="ch-muted" style="margin:0">Không có nhận xét nào.</p><?php endif; ?>
                    <ul class="ch-feed">
                        <?php foreach ($histFeedback as $f): ?>
                            <li><div class="ch-feed__meta"><b><?= $f['log_date'] ? 'Buổi ' . htmlspecialchars(date('d/m/Y', strtotime($f['log_date']))) : 'Nhận xét chung' ?></b></div><p><?= htmlspecialchars($f['content']) ?></p></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php if ($reviewsOn) { $reviewState = coachReviewState($pdo, $userId, (int)$hist['id']); $reviewRedirect = '/my-coach.php?history=' . (int)$hist['id']; include __DIR__ . '/includes/coach-review-card.php'; } ?>
            </aside>
        </div>

    <?php elseif (!$ready || $rel === null): ?>
        <div class="ch-empty">
            <div class="ch-empty__ico" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.6 3.7-5.5 7-5.5s6.2 1.9 7 5.5"/></svg>
            </div>
            <h2>Bạn chưa có huấn luyện viên</h2>
            <p>Chọn một HLV phù hợp với mục tiêu để được giao lịch tập và theo dõi sát hơn. Bạn vẫn tự dùng mọi tính năng khi không có HLV.</p>
            <a class="btn" href="/coaches.php">Xem danh sách HLV</a>
        </div>
        <?php if ($past): ?>
            <section class="card" style="margin-top:var(--s-5)" aria-labelledby="h-past">
                <h2 class="card__head" id="h-past">HLV trước đây</h2>
                <p class="card__sub">Xem lại nhận xét và tin nhắn cũ (chỉ đọc). HLV cũ không còn xem được dữ liệu của bạn.</p>
                <ul class="ch-feed">
                    <?php foreach ($past as $p): ?>
                        <li><div class="ch-feed__meta"><b><?= htmlspecialchars($p['coach_name']) ?></b><span><?= htmlspecialchars(date('d/m/Y', strtotime((string)$p['accepted_at']))) ?> – <?= htmlspecialchars(date('d/m/Y', strtotime((string)$p['ended_at']))) ?></span></div>
                            <a href="/my-coach.php?history=<?= (int)$p['id'] ?>"><?= (int)$p['feedback_count'] ?> nhận xét · <?= (int)$p['message_count'] ?> tin nhắn</a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

    <?php elseif (!$active): ?>
        <section class="card ch-status">
            <?= coachAvatar($rel['coach_avatar'], $rel['coach_name'], 56) ?>
            <div>
                <span class="ch-badge ch-badge--warn">Chờ HLV duyệt</span>
                <h2 style="margin-top:8px">Đang chờ <?= htmlspecialchars($rel['coach_name']) ?> duyệt</h2>
                <p>Bạn gửi yêu cầu <?= htmlspecialchars(mb_strtolower(coachTimeAgo($rel['created_at']))) ?>. Khi được duyệt, trang này hiện lịch tập, nhận xét và tin nhắn.</p>
                <?php if ($stale = coachPendingStaleDays($rel['created_at'], coachNow())): ?>
                            <div class="alert alert--note" role="note"><div>HLV chưa trả lời sau <?= (int)$stale ?> ngày. Bạn có thể hủy yêu cầu để chọn HLV khác, hoặc chờ thêm.</div></div>
                        <?php endif; ?>
                <?php if ($rel['request_note'] !== ''): ?><div class="ch-quote"><?= htmlspecialchars($rel['request_note']) ?></div><?php endif; ?>
                <form method="post" action="/my-coach.php" class="form-actions" data-confirm="Hủy yêu cầu gửi tới HLV này?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="relation_id" value="<?= (int)$rel['id'] ?>">
                    <button type="submit" class="btn btn--ghost" data-busy-text="Đang hủy…">Hủy yêu cầu</button>
                </form>
            </div>
        </section>

    <?php else: ?>
        <nav class="ch-tabs" aria-label="Mục">
            <?php foreach ($tabs as $key => $label):
                $count = $key === 'feedback' ? (int)($notice['feedback'] ?? 0) : ($key === 'messages' ? (int)($notice['messages'] ?? 0) : 0);
            ?>
                <a href="/my-coach.php?tab=<?= htmlspecialchars($key) ?>"<?= $tab === $key ? ' class="is-active" aria-current="page"' : '' ?>>
                    <?= htmlspecialchars($label) ?><?php if ($count > 0 && $tab !== $key): ?> <span class="ch-dot" aria-label="<?= $count ?> mới"><?= $count ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($bill !== null && $bill['locked'] && $tab !== 'messages'): ?>
            <div class="alert alert--error ch-bill-lock" role="alert"><div><b>Bạn đã dùng hết buổi tập.</b> <?= htmlspecialchars(coachBillingLockMessage(false)) ?>
                <?php if ($tab !== 'sessions'): ?><a href="/my-coach.php?tab=sessions">Mua gói</a><?php endif; ?></div></div>
        <?php endif; ?>

        <?php if ($tab === 'overview'): ?>
        <div class="ch-layout">
            <div class="ch-stack">
                <section class="card">
                    <h2 class="card__head">Lịch tập và dinh dưỡng</h2>
                    <p class="card__sub">Khi đang có HLV, bạn không tự sửa lịch tập và mục tiêu dinh dưỡng. Muốn đổi, hãy gửi yêu cầu; HLV duyệt thì bạn được tự sửa trong <?= COACH_UNLOCK_HOURS ?> giờ.</p>
                    <dl class="ch-kv">
                        <dt>Lịch tập</dt>
                        <dd><?= $plan ? htmlspecialchars($plan['name']) . ((int)($plan['assigned_by'] ?? 0) === (int)$rel['coach_id'] ? ' <span class="ch-badge ch-badge--accent ch-badge--plain">Do HLV giao</span>' : '') : '<span class="ch-muted">HLV chưa giao lịch</span>' ?>
                            <br><?= lockLine($planState, 'lịch tập') ?></dd>
                        <dt>Dinh dưỡng</dt>
                        <dd><?= $target ? number_format((float)$target['target_kcal'], 0, ',', '.') . ' kcal · P ' . (int)$target['protein_g'] . ' g · C ' . (int)$target['carbs_g'] . ' g · F ' . (int)$target['fat_g'] . ' g' : '<span class="ch-muted">HLV chưa đặt mục tiêu</span>' ?>
                            <br><?= lockLine($nutState, 'dinh dưỡng') ?></dd>
                    </dl>
                    <div class="form-actions" style="flex-wrap:wrap">
                        <a class="btn btn--ghost" href="/workout-plan.php">Mở lịch tập</a>
                        <a class="btn btn--ghost" href="/nutrition.php">Mở dinh dưỡng</a>
                        <?php if (($planState['locked'] && !$planState['pending']) || ($nutState['locked'] && !$nutState['pending'])): ?>
                            <button type="button" class="btn" data-toggle="change-form">Xin sửa</button>
                        <?php endif; ?>
                    </div>
                    <form id="change-form" class="ch-req" method="post" action="/my-coach.php" hidden>
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change">
                        <div class="field">
                            <label for="area">Muốn sửa phần nào?</label>
                            <select id="area" name="area">
                                <?php if ($planState['locked'] && !$planState['pending']): ?><option value="PLAN">Lịch tập</option><?php endif; ?>
                                <?php if ($nutState['locked'] && !$nutState['pending']): ?><option value="NUTRITION">Dinh dưỡng</option><?php endif; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="reason">Lý do</label>
                            <textarea id="reason" name="reason" maxlength="<?= COACH_NOTE_MAX ?>" required placeholder="Ví dụ: tuần này đau vai, muốn bỏ bài đẩy qua đầu"></textarea>
                            <span class="ch-count" data-count="reason"></span>
                        </div>
                        <div class="ch-req__row"><button type="submit" class="btn" data-busy-text="Đang gửi…">Gửi yêu cầu</button></div>
                    </form>
                </section>

                <section class="card">
                    <h2 class="card__head">Yêu cầu sửa đã gửi</h2>
                    <?php if (!$requests): ?>
                        <p class="ch-muted" style="margin:0">Bạn chưa gửi yêu cầu nào.</p>
                    <?php else: ?>
                        <ul class="ch-feed">
                            <?php foreach ($requests as $r): [$rl, $rc] = $reqLabels[$r['status']] ?? [$r['status'], '']; ?>
                                <li>
                                    <div class="ch-feed__meta">
                                        <b><?= htmlspecialchars(COACH_CHANGE_AREAS[$r['area']] ?? $r['area']) ?></b>
                                        <span class="ch-badge<?= $rc ? ' ch-badge--' . $rc : '' ?>"><?= htmlspecialchars($rl) ?></span>
                                        <span><?= htmlspecialchars(coachTimeAgo($r['created_at'])) ?></span>
                                    </div>
                                    <p><?= htmlspecialchars($r['reason']) ?></p>
                                    <?php if ($r['coach_reply'] !== ''): ?><div class="ch-quote"><b>HLV:</b> <?= htmlspecialchars($r['coach_reply']) ?></div><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="ch-side">
                <section class="card">
                    <div class="ch-coach__head">
                        <?= coachAvatar($coach['avatar'] ?? null, $rel['coach_name'], 56) ?>
                        <div>
                            <h2 style="margin:0;font-size:19px"><?= htmlspecialchars($rel['coach_name']) ?></h2>
                            <p class="ch-muted ch-small" style="margin:2px 0 0"><?= htmlspecialchars((string)($coach['headline'] ?? '')) ?></p>
                        </div>
                    </div>
                    <p class="ch-small ch-muted" style="margin:var(--s-3) 0 0">Tập cùng từ <?= htmlspecialchars(coachDateLabel((string)$rel['accepted_at'])) ?>.</p>
                    <?php if (!empty($coach['availability'])): ?><p class="ch-small" style="margin:6px 0 0"><b>Thời gian hỗ trợ:</b> <?= htmlspecialchars($coach['availability']) ?></p><?php endif; ?>
                    <?php if ($bill !== null): ?>
                        <p class="ch-small" style="margin:6px 0 0"><b>Buổi tập:</b> <?= htmlspecialchars(coachBillingLine($bill)) ?></p>
                        <a class="btn btn--ghost" style="margin-top:var(--s-3)" href="/my-coach.php?tab=sessions">Đặt buổi tập</a>
                    <?php endif; ?>
                </section>
                <?php if ($reviewsOn) { $reviewState = coachReviewState($pdo, $userId, (int)$rel['id']); $reviewRedirect = '/my-coach.php'; include __DIR__ . '/includes/coach-review-card.php'; } ?>
                <section class="card">
                    <h2 class="card__head">Ngừng tập với HLV</h2>
                    <p class="card__sub">HLV sẽ mất quyền xem dữ liệu của bạn ngay. Lịch tập và mục tiêu hiện tại vẫn được giữ và bạn tự sửa được.</p>
                    <form method="post" action="/my-coach.php" data-confirm="Ngừng tập với HLV <?= htmlspecialchars($rel['coach_name']) ?>? Không hoàn tác được, muốn tập lại phải gửi yêu cầu mới.">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="end">
                        <input type="hidden" name="relation_id" value="<?= (int)$rel['id'] ?>">
                        <button type="submit" class="btn btn--ghost" data-busy-text="Đang xử lý…">Ngừng tập</button>
                    </form>
                </section>
            </aside>
        </div>

        <?php elseif ($tab === 'sessions' && $bill !== null):
            $canBook = $bill['month_active'] || $bill['trial_left'] > 0 || $bill['pack_left'] > 0;
            $pendingOrder = null;
            foreach ($orders as $o) {
                if ($o['status'] === 'PENDING') { $pendingOrder = $o; break; }
            }
            $relPricing = $bill['relation'];
            $packs = [];
            if ($relPricing['price_per_session'] !== null) {
                foreach (COACH_SESSION_PACKS as $n) { $packs[] = ['SESSIONS', $n, $relPricing['price_per_session'] * $n]; }
            }
            if ($relPricing['price_per_month'] !== null) {
                foreach (COACH_MONTH_PACKS as $n) { $packs[] = ['MONTH', $n, $relPricing['price_per_month'] * $n]; }
            }
            $minDate = date('Y-m-d');
            $maxDate = date('Y-m-d', strtotime('+' . COACH_BOOK_MAX_DAYS . ' days'));
        ?>
        <div class="ch-layout">
            <div class="ch-stack">
                <section class="card" aria-labelledby="h-book">
                    <h2 class="card__head" id="h-book">Đặt buổi tập</h2>
                    <p class="card__sub">Mỗi buổi <?= COACH_SESSION_MINUTES ?> phút. Đặt trước ít nhất <?= COACH_BOOK_MIN_HOURS ?> giờ, HLV xác nhận thì buổi mới chắc chắn. Hủy trước giờ tập thì không bị trừ buổi; vắng mặt vẫn bị trừ.</p>
                    <?php if (!$canBook): ?>
                        <p class="ch-muted" style="margin:0">Bạn chưa còn buổi nào để đặt. Mua gói ở cột bên cạnh.</p>
                    <?php else: ?>
                        <form method="post" action="/my-coach.php?tab=sessions" class="ch-book">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="book">
                            <div class="ch-book__row">
                                <div class="field">
                                    <label for="bk-date">Ngày</label>
                                    <input type="date" id="bk-date" name="date" required min="<?= htmlspecialchars($minDate) ?>" max="<?= htmlspecialchars($maxDate) ?>" value="<?= htmlspecialchars(date('Y-m-d', strtotime('+1 day'))) ?>">
                                </div>
                                <div class="field">
                                    <label for="bk-time">Giờ bắt đầu</label>
                                    <select id="bk-time" name="time" required>
                                        <?php foreach (coachBookingTimes() as $t): ?>
                                            <option value="<?= htmlspecialchars($t) ?>"<?= $t === '18:00' ? ' selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="field">
                                <label for="bk-note">Lời nhắn (không bắt buộc)</label>
                                <textarea id="bk-note" name="note" maxlength="<?= COACH_SESSION_NOTE_MAX ?>" placeholder="Địa điểm, muốn tập phần nào, chấn thương cần lưu ý…"></textarea>
                                <span class="ch-count" data-count="bk-note"></span>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn" data-busy-text="Đang đặt…">Đặt buổi</button>
                                <span class="ch-muted ch-small"><?= $bill['trial_left'] > 0 ? 'Còn ' . (int)$bill['trial_left'] . ' buổi thử miễn phí.' : '' ?></span>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="card" aria-labelledby="h-upcoming">
                    <h2 class="card__head" id="h-upcoming">Buổi sắp tới</h2>
                    <?php if (!$sessions['upcoming']): ?>
                        <p class="ch-muted" style="margin:0">Chưa có buổi nào sắp tới.</p>
                    <?php else: ?>
                        <ul class="ch-sess-list">
                            <?php foreach ($sessions['upcoming'] as $s): $tone = COACH_SESSION_TONES[$s['status']] ?? ''; ?>
                                <li class="ch-sess">
                                    <div class="ch-sess__main">
                                        <b><?= htmlspecialchars(coachSessionWhen($s['starts_at'])) ?></b>
                                        <span><?= htmlspecialchars(COACH_BILLING_LABELS[$s['billing']] ?? $s['billing']) ?></span>
                                        <?php if ($s['client_note'] !== ''): ?><span class="ch-quote"><?= htmlspecialchars($s['client_note']) ?></span><?php endif; ?>
                                    </div>
                                    <div class="ch-sess__side">
                                        <span class="ch-badge<?= $tone !== '' ? ' ch-badge--' . $tone : '' ?>"><?= htmlspecialchars(COACH_SESSION_LABELS[$s['status']] ?? $s['status']) ?></span>
                                        <form method="post" action="/my-coach.php?tab=sessions" data-confirm="Hủy buổi <?= htmlspecialchars(coachSessionWhen($s['starts_at'])) ?>?">
                                            <?= csrfField() ?><input type="hidden" name="action" value="session_cancel"><input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                                            <button type="submit" class="btn btn--ghost ch-btn-sm" data-busy-text="Đang hủy…">Hủy buổi</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <?php if ($sessions['past']): ?>
                <section class="card" aria-labelledby="h-past-s">
                    <h2 class="card__head" id="h-past-s">Các buổi trước</h2>
                    <ul class="ch-sess-list">
                        <?php foreach ($sessions['past'] as $s): $tone = COACH_SESSION_TONES[$s['status']] ?? ''; ?>
                            <li class="ch-sess">
                                <div class="ch-sess__main"><b><?= htmlspecialchars(coachSessionWhen($s['starts_at'])) ?></b><span><?= htmlspecialchars(COACH_BILLING_LABELS[$s['billing']] ?? $s['billing']) ?></span></div>
                                <div class="ch-sess__side"><span class="ch-badge<?= $tone !== '' ? ' ch-badge--' . $tone : '' ?>"><?= htmlspecialchars(COACH_SESSION_LABELS[$s['status']] ?? $s['status']) ?></span></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>
            </div>

            <aside class="ch-side">
                <section class="card" aria-labelledby="h-bal">
                    <h2 class="card__head" id="h-bal">Quyền tập của bạn</h2>
                    <dl class="ch-kv">
                        <dt>Buổi thử miễn phí</dt><dd>còn <?= (int)$bill['trial_left'] ?>/<?= (int)$bill['trial'] ?></dd>
                        <dt>Gói buổi</dt><dd><?= $bill['pack_units'] > 0 ? 'còn ' . (int)$bill['pack_left'] . '/' . (int)$bill['pack_units'] . ' buổi' : '<span class="ch-muted">Chưa mua</span>' ?></dd>
                        <dt>Gói tháng</dt><dd><?= $bill['month_until'] !== null ? 'tới hết ' . htmlspecialchars(date('d/m/Y', strtotime($bill['month_until']))) : '<span class="ch-muted">Không có</span>' ?></dd>
                    </dl>
                </section>

                <section class="card" aria-labelledby="h-buy">
                    <h2 class="card__head" id="h-buy">Mua gói</h2>
                    <?php if ($pendingOrder !== null): ?>
                        <p class="ch-small" style="margin:0 0 6px"><span class="ch-badge ch-badge--warn">Chờ HLV xác nhận tiền</span></p>
                        <p style="margin:0"><b><?= htmlspecialchars(coachOrderLabel($pendingOrder['kind'], (int)$pendingOrder['quantity'])) ?></b> · <b><?= htmlspecialchars(coachMoney((int)$pendingOrder['amount'])) ?></b></p>
                        <p class="ch-small ch-muted">Thanh toán trực tiếp cho HLV <?= htmlspecialchars($rel['coach_name']) ?> rồi chờ HLV bấm xác nhận. GymSmart không thu tiền hộ.</p>
                        <?php
                        // A-56: mã VietQR. Đơn này lấy từ quan hệ ACTIVE của chính người đang đăng nhập ($relId từ $_SESSION),
                        // số tiền lấy từ DB; coachVietQrUrl() kiểm tra lại mọi giá trị, sai thì trả null và hiện hướng dẫn chữ như cũ.
                        $payBank = coachBankInfo($pdo, $coachId);
                        $qrUrl = $payBank !== null
                            ? coachVietQrUrl($payBank['bank_bin'], $payBank['bank_account'], $payBank['bank_holder'], (int)$pendingOrder['amount'], (int)$pendingOrder['id'])
                            : null;
                        ?>
                        <?php if ($qrUrl !== null): ?>
                            <figure class="ch-qr">
                                <img src="<?= htmlspecialchars($qrUrl) ?>" width="260" loading="lazy" referrerpolicy="no-referrer"
                                     alt="Mã VietQR chuyển <?= htmlspecialchars(coachMoney((int)$pendingOrder['amount'])) ?> tới <?= htmlspecialchars(COACH_BANKS[$payBank['bank_bin']]) ?>, nội dung <?= htmlspecialchars(coachTransferNote((int)$pendingOrder['id'])) ?>">
                                <figcaption class="ch-small ch-muted">Quét bằng app ngân hàng. Kiểm tra đúng tên chủ tài khoản trước khi chuyển.</figcaption>
                            </figure>
                            <dl class="ch-kv ch-qr__info">
                                <dt>Ngân hàng</dt><dd><?= htmlspecialchars(COACH_BANKS[$payBank['bank_bin']]) ?></dd>
                                <dt>Số tài khoản</dt><dd><?= htmlspecialchars($payBank['bank_account']) ?></dd>
                                <dt>Chủ tài khoản</dt><dd><?= htmlspecialchars($payBank['bank_holder']) ?></dd>
                                <dt>Số tiền</dt><dd><?= htmlspecialchars(coachMoney((int)$pendingOrder['amount'])) ?></dd>
                                <dt>Nội dung</dt><dd><b><?= htmlspecialchars(coachTransferNote((int)$pendingOrder['id'])) ?></b></dd>
                            </dl>
                        <?php endif; ?>
                        <?php if ($relPricing['payment_note'] !== '' && $relPricing['payment_note'] !== null): ?>
                            <div class="ch-quote ch-pay-note"><b>Hướng dẫn của HLV:</b><br><?= nl2br(htmlspecialchars((string)$relPricing['payment_note'])) ?></div>
                        <?php elseif ($qrUrl === null): ?>
                            <p class="ch-small ch-muted">HLV chưa ghi hướng dẫn thanh toán, hãy hỏi HLV qua tab Tin nhắn.</p>
                        <?php endif; ?>
                        <form method="post" action="/my-coach.php?tab=sessions" data-confirm="Hủy đơn mua gói này?" style="margin-top:var(--s-3)">
                            <?= csrfField() ?><input type="hidden" name="action" value="order_cancel"><input type="hidden" name="order_id" value="<?= (int)$pendingOrder['id'] ?>">
                            <button type="submit" class="btn btn--ghost ch-btn-sm" data-busy-text="Đang hủy…">Hủy đơn</button>
                        </form>
                    <?php elseif (!$packs): ?>
                        <p class="ch-muted" style="margin:0">HLV chưa đặt giá gói. Hãy nhắn tin hỏi HLV.</p>
                    <?php else: ?>
                        <form method="post" action="/my-coach.php?tab=sessions" class="ch-packs">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="order_create">
                            <fieldset>
                                <legend class="ch-small ch-muted">Chọn gói (giá hiện tại của HLV)</legend>
                                <?php foreach ($packs as $i => [$kind, $n, $amount]): ?>
                                    <label class="ch-pack">
                                        <input type="radio" name="pack" value="<?= htmlspecialchars($kind . ':' . $n) ?>" required<?= $i === 0 ? ' checked' : '' ?>>
                                        <span><b><?= htmlspecialchars(coachOrderLabel($kind, (int)$n)) ?></b>
                                            <small><?= $kind === 'MONTH' ? (COACH_MONTH_DAYS * (int)$n) . ' ngày, đặt buổi không giới hạn' : htmlspecialchars(coachMoney((int)$relPricing['price_per_session'])) . '/buổi' ?></small></span>
                                        <span class="ch-pack__price"><?= htmlspecialchars(coachMoney((int)$amount)) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="ch-small ch-muted">Bấm chọn gói xong, bạn thanh toán trực tiếp cho HLV (chuyển khoản hoặc tiền mặt). Gói được cộng khi HLV xác nhận đã nhận tiền.</p>
                            <button type="submit" class="btn" data-busy-text="Đang gửi…">Chọn gói này</button>
                        </form>
                    <?php endif; ?>
                </section>

                <?php if ($orders): ?>
                <section class="card" aria-labelledby="h-orders">
                    <h2 class="card__head" id="h-orders">Lịch sử mua gói</h2>
                    <ul class="ch-feed">
                        <?php foreach ($orders as $o): $otone = ['PENDING' => 'warn', 'PAID' => 'ok', 'CANCELLED' => ''][$o['status']] ?? ''; ?>
                            <li>
                                <div class="ch-feed__meta">
                                    <b><?= htmlspecialchars(coachOrderLabel($o['kind'], (int)$o['quantity'])) ?></b>
                                    <span><?= htmlspecialchars(coachMoney((int)$o['amount'])) ?></span>
                                    <span class="ch-badge<?= $otone !== '' ? ' ch-badge--' . $otone : '' ?>"><?= htmlspecialchars(COACH_ORDER_LABELS[$o['status']] ?? $o['status']) ?></span>
                                </div>
                                <p class="ch-small ch-muted" style="margin:4px 0 0">
                                    Chọn ngày <?= htmlspecialchars(date('d/m/Y', strtotime($o['created_at']))) ?>
                                    <?php if ($o['paid_at']): ?> · HLV xác nhận <?= htmlspecialchars(date('d/m/Y', strtotime($o['paid_at']))) ?><?php endif; ?>
                                    <?php if ($o['starts_on']): ?> · dùng từ <?= htmlspecialchars(date('d/m', strtotime($o['starts_on']))) ?> tới <?= htmlspecialchars(date('d/m/Y', strtotime($o['ends_on']))) ?><?php endif; ?>
                                </p>
                                <?php if ($o['cancel_note'] !== ''): ?><p class="ch-small" style="margin:4px 0 0">Lý do hủy: <?= htmlspecialchars($o['cancel_note']) ?></p><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>
            </aside>
        </div>

        <?php elseif ($tab === 'feedback'): ?>
            <?php if (!$feedback): ?>
                <div class="ch-empty">
                    <div class="ch-empty__ico" aria-hidden="true">✎</div>
                    <h2>Chưa có nhận xét</h2>
                    <p>Khi bạn ghi log buổi tập, HLV sẽ xem và nhận xét. Nhận xét mới hiện ở đây và trên trang chủ.</p>
                    <a class="btn" href="/workout-plan.php">Ghi log buổi tập</a>
                </div>
            <?php else: ?>
                <ul class="ch-feed">
                    <?php foreach ($feedback as $f): ?>
                        <li class="<?= $f['read_at'] === null ? 'is-new' : '' ?>">
                            <div class="ch-feed__meta">
                                <?php if ($f['read_at'] === null): ?><span class="ch-badge ch-badge--accent">Mới</span><?php endif; ?>
                                <b><?= $f['log_date'] ? 'Buổi tập ' . htmlspecialchars(coachDateLabel($f['log_date'])) : 'Nhận xét chung' ?></b>
                                <span><?= htmlspecialchars(coachTimeAgo($f['created_at'])) ?></span>
                                <?php if (!empty($f['updated_at'])): ?><span>(HLV đã sửa)</span><?php endif; ?>
                            </div>
                            <p><?= htmlspecialchars($f['content']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php elseif ($tab === 'messages'): ?>
            <?= coachChatBox((int)$rel['coach_id'], $rel['coach_name'], $coach['avatar'] ?? null,
                'Huấn luyện viên' . (!empty($coach['availability']) ? ' · ' . $coach['availability'] : '')) ?>
            <p class="ch-muted ch-small">Tin nhắn chỉ dùng để trao đổi về tập luyện. Vấn đề sức khỏe cấp tính, hãy liên hệ cơ sở y tế.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script src="/js/coach.js"></script>
<?php if ($active && $tab === 'messages'): ?><script src="/js/coach-chat.js"></script><?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
