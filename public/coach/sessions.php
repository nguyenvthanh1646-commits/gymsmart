<?php
/**
 * coach/sessions.php — "Lịch hẹn và gói" của HLV (A-54).
 *   - Đơn mua gói chờ xác nhận: học viên đã chọn gói và trả tiền ngoài hệ thống, HLV bấm "Đã nhận tiền" hoặc hủy đơn.
 *   - Buổi học viên vừa đặt: xác nhận / từ chối. Buổi đã qua giờ: đánh dấu Đã tập hoặc Vắng mặt (vắng vẫn trừ buổi).
 *   - Buổi sắp tới: HLV hủy được (học viên không bị trừ buổi).
 *   - Quyền tập của từng học viên: buổi thử, gói buổi, gói tháng, có đang bị khóa không.
 * Mọi thao tác là form POST có CSRF; hàm DB kiểm tra buổi / đơn thuộc học viên ACTIVE của chính HLV này.
 * Thao tác tiền và xác nhận buổi ghi nhật ký (coach.order_*, coach.session_*).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';
require_once __DIR__ . '/../includes/audit-log.php';

requireCoach();
$coachId = (int)$_SESSION['user_id'];
$ready = coachBillingReady($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $action = (string)($_POST['action'] ?? '');
    try {
        if (in_array($action, ['confirm', 'decline', 'cancel', 'done', 'no_show'], true)) {
            $s = coachSessionAction($pdo, 'coach', $coachId, coachParseId($_POST['session_id'] ?? null), $action);
            $when = coachSessionWhen($s['starts_at']);
            if ($action === 'done' || $action === 'no_show') {
                auditLog($pdo, $action === 'done' ? 'coach.session_done' : 'coach.session_absent', 'client', $s['client_id'],
                    ($action === 'done' ? 'Đã tập' : 'Vắng') . ' buổi ' . $when . ' (' . (COACH_BILLING_LABELS[$s['billing']] ?? '') . ') của "' . $s['client_name'] . '"');
            }
            $texts = ['confirm' => 'Đã xác nhận buổi %s với "%s".', 'decline' => 'Đã từ chối buổi %s của "%s".', 'cancel' => 'Đã hủy buổi %s với "%s". Học viên không bị trừ buổi.',
                      'done' => 'Đã ghi nhận buổi %s của "%s".', 'no_show' => 'Đã ghi "%2$s" vắng buổi %1$s.'];
            $msg = sprintf($texts[$action], $when, $s['client_name']);
            if (!empty($s['now_locked'])) {
                $msg .= ' Học viên vừa dùng hết buổi, đã được nhắc mua gói.';
            }
            coachFlash('ok', $msg);
        } elseif ($action === 'order_paid' || $action === 'order_cancel') {
            $paid = $action === 'order_paid';
            $o = coachResolveOrder($pdo, $coachId, coachParseId($_POST['order_id'] ?? null), $paid, coachCleanText($_POST['note'] ?? ''));
            $label = coachOrderLabel($o['kind'], (int)$o['quantity']);
            auditLog($pdo, $paid ? 'coach.order_paid' : 'coach.order_cancel', 'client', $o['client_id'],
                ($paid ? 'Nhận ' : 'Hủy đơn ') . coachMoney((int)$o['amount']) . ' - ' . $label . ' của "' . $o['client_name'] . '"');
            coachFlash('ok', $paid ? 'Đã xác nhận nhận ' . coachMoney((int)$o['amount']) . ' từ "' . $o['client_name'] . '". ' . $label . ' đã được cộng.'
                : 'Đã hủy đơn ' . mb_strtolower($label) . ' của "' . $o['client_name'] . '".');
        } else {
            throw new CoachException('Thao tác không hợp lệ.');
        }
    } catch (CoachException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: /coach/sessions.php');
    exit;
}

$flash = coachTakeFlash();
$groups = $ready ? coachSessionsForCoach($pdo, $coachId) : ['requests' => [], 'to_mark' => [], 'upcoming' => [], 'recent' => []];
$orders = $ready ? coachPendingOrders($pdo, $coachId) : [];
$overview = $ready ? coachBillingOverview($pdo, $coachId) : [];
$pricing = $ready ? coachPricing($pdo, $coachId) : null;
$clients = coachActiveClients($pdo, $coachId);

/** Nút thao tác cho 1 buổi (form POST nhỏ). $confirm = câu hỏi xác nhận, rỗng = không hỏi. */
function sessionButton(int $id, string $action, string $label, string $class, string $confirm = ''): string
{
    return '<form method="post" action="/coach/sessions.php"' . ($confirm !== '' ? ' data-confirm="' . htmlspecialchars($confirm) . '"' : '') . '>'
        . csrfField() . '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '"><input type="hidden" name="session_id" value="' . $id . '">'
        . '<button type="submit" class="adm-btn adm-btn-sm ' . htmlspecialchars($class) . '" data-busy-text="Đang xử lý…">' . htmlspecialchars($label) . '</button></form>';
}

/** Một dòng buổi hẹn: giờ, học viên, nguồn buổi, lời nhắn, các nút. */
function sessionRow(array $s, string $buttons): string
{
    $tone = COACH_SESSION_TONES[$s['status']] ?? '';
    return '<li class="ch-sess"><div class="ch-sess__main">'
        . '<b>' . htmlspecialchars(coachSessionWhen($s['starts_at'])) . '</b>'
        . '<span><a class="adm-link" href="/coach/client.php?id=' . (int)$s['client_id'] . '">' . htmlspecialchars($s['client_name']) . '</a>'
        . ' · ' . htmlspecialchars(COACH_BILLING_LABELS[$s['billing']] ?? $s['billing']) . '</span>'
        . ($s['client_note'] !== '' ? '<span class="ch-quote">' . htmlspecialchars($s['client_note']) . '</span>' : '')
        . '</div><div class="ch-sess__side"><span class="ch-badge' . ($tone !== '' ? ' ch-badge--' . $tone : '') . '">' . htmlspecialchars(COACH_SESSION_LABELS[$s['status']] ?? $s['status']) . '</span>'
        . ($buttons !== '' ? '<div class="adm-actions">' . $buttons . '</div>' : '') . '</div></li>';
}

$pageTitle = 'Lịch hẹn và gói';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$coachNavActive = 'sessions';
include __DIR__ . '/../includes/coach-nav.php';
?>
<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Lịch hẹn và gói</h1>
            <p class="adm-sub">Học viên đặt buổi, bạn xác nhận; sau buổi tập đánh dấu Đã tập hoặc Vắng mặt. Học viên trả tiền gói trực tiếp cho bạn, bạn bấm "Đã nhận tiền" để cộng gói.</p>
        </div>
        <a class="adm-btn adm-btn-ghost" href="/coach/profile.php#gia">Sửa giá và buổi thử</a>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <?php if (!$ready): ?>
        <div class="adm-panel"><div class="adm-empty">Tính năng lịch hẹn và gói chưa bật: quản trị viên cần chạy <code>sql/coach_billing.sql</code> (hoặc <code>php tools/install.php --upgrade</code>).</div></div>
    <?php else: ?>

    <p class="adm-sub" style="margin:0 0 16px">
        Giá của bạn: <b><?= htmlspecialchars(coachPriceLine($pricing['price_per_session'], $pricing['price_per_month']) ?: 'chưa đặt') ?></b>
        · thử miễn phí <?= (int)$pricing['trial_sessions'] ?> buổi đầu.
    </p>

    <?php if ($orders): ?>
        <section class="adm-panel adm-card-pad" style="margin-bottom:16px;border-color:var(--accent)" aria-labelledby="h-orders">
            <h2 id="h-orders">Chờ xác nhận thanh toán</h2>
            <p class="adm-sub">Chỉ bấm "Đã nhận tiền" khi tiền đã vào tài khoản của bạn hoặc bạn đã nhận tiền mặt.</p>
            <ul class="ch-feed">
                <?php foreach ($orders as $o): ?>
                    <li>
                        <div class="ch-feed__meta">
                            <b><?= htmlspecialchars($o['client_name']) ?></b>
                            <span><?= htmlspecialchars(coachOrderLabel($o['kind'], (int)$o['quantity'])) ?> · <b><?= htmlspecialchars(coachMoney((int)$o['amount'])) ?></b></span>
                            <span>nội dung CK <b><?= htmlspecialchars(coachTransferNote((int)$o['id'])) ?></b></span>
                            <span>chọn <?= htmlspecialchars(mb_strtolower(coachTimeAgo($o['created_at']))) ?></span>
                        </div>
                        <div class="adm-actions" style="margin-top:10px;justify-content:flex-start;flex-wrap:wrap">
                            <form method="post" action="/coach/sessions.php" data-confirm="Xác nhận đã nhận <?= htmlspecialchars(coachMoney((int)$o['amount'])) ?> từ <?= htmlspecialchars($o['client_name']) ?>?">
                                <?= csrfField() ?><input type="hidden" name="action" value="order_paid"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                <button type="submit" class="adm-btn adm-btn-sm" data-busy-text="Đang lưu…">Đã nhận tiền</button>
                            </form>
                            <details class="ch-inline-form">
                                <summary class="adm-btn adm-btn-ghost adm-btn-sm">Hủy đơn</summary>
                                <form method="post" action="/coach/sessions.php">
                                    <?= csrfField() ?><input type="hidden" name="action" value="order_cancel"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                    <label class="ch-label" for="cn-<?= (int)$o['id'] ?>">Lý do (học viên thấy)</label>
                                    <input class="adm-input" id="cn-<?= (int)$o['id'] ?>" name="note" maxlength="300" placeholder="Ví dụ: chưa nhận được chuyển khoản">
                                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm" data-busy-text="Đang hủy…">Hủy đơn</button>
                                </form>
                            </details>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="adm-two">
        <section class="adm-panel adm-card-pad" aria-labelledby="h-req">
            <h2 id="h-req">Học viên đặt buổi <?php if ($groups['requests']): ?><span class="ch-badge ch-badge--warn"><?= count($groups['requests']) ?></span><?php endif; ?></h2>
            <?php if (!$groups['requests']): ?>
                <p class="adm-sub">Không có buổi nào chờ bạn xác nhận. Buổi chờ quá giờ mà chưa xác nhận sẽ tự hết hạn và trả buổi lại cho học viên.</p>
            <?php else: ?>
                <ul class="ch-sess-list">
                    <?php foreach ($groups['requests'] as $s): ?>
                        <?= sessionRow($s, sessionButton((int)$s['id'], 'decline', 'Từ chối', 'adm-btn-ghost', 'Từ chối buổi ' . coachSessionWhen($s['starts_at']) . '?')
                            . sessionButton((int)$s['id'], 'confirm', 'Xác nhận', '')) ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="adm-panel adm-card-pad" aria-labelledby="h-mark">
            <h2 id="h-mark">Cần đánh dấu <?php if ($groups['to_mark']): ?><span class="ch-badge ch-badge--danger"><?= count($groups['to_mark']) ?></span><?php endif; ?></h2>
            <?php if (!$groups['to_mark']): ?>
                <p class="adm-sub">Các buổi đã qua giờ đều đã được đánh dấu.</p>
            <?php else: ?>
                <p class="adm-sub">Buổi đã qua giờ bắt đầu. Vắng mặt vẫn trừ buổi; nếu bạn là người vắng, hãy chọn Hủy để học viên không bị trừ.</p>
                <ul class="ch-sess-list">
                    <?php foreach ($groups['to_mark'] as $s): ?>
                        <?= sessionRow($s, sessionButton((int)$s['id'], 'cancel', 'Hủy', 'adm-btn-ghost', 'Hủy buổi này? Học viên không bị trừ buổi.')
                            . sessionButton((int)$s['id'], 'no_show', 'Vắng mặt', 'adm-btn-ghost', 'Ghi học viên vắng buổi này? Buổi vắng vẫn bị trừ.')
                            . sessionButton((int)$s['id'], 'done', 'Đã tập', '')) ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="adm-panel adm-card-pad" style="margin-top:16px" aria-labelledby="h-up">
        <h2 id="h-up">Sắp tới</h2>
        <?php if (!$groups['upcoming']): ?>
            <p class="adm-sub">Chưa có buổi nào đã xác nhận sắp tới.</p>
        <?php else: ?>
            <ul class="ch-sess-list">
                <?php foreach ($groups['upcoming'] as $s): ?>
                    <?= sessionRow($s, sessionButton((int)$s['id'], 'cancel', 'Hủy buổi', 'adm-btn-ghost', 'Hủy buổi ' . coachSessionWhen($s['starts_at']) . '? Học viên được báo và không bị trừ buổi.')) ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="adm-panel" style="margin-top:16px" aria-labelledby="h-bal">
        <div class="adm-card-pad" style="padding-bottom:0"><h2 id="h-bal">Quyền tập của học viên</h2></div>
        <?php if (!$clients): ?>
            <div class="adm-empty">Bạn chưa có học viên nào.</div>
        <?php else: ?>
        <table class="adm-table">
            <thead><tr><th>Học viên</th><th>Buổi thử</th><th>Gói buổi</th><th>Gói tháng</th><th>Đang hẹn</th><th>Trạng thái</th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): $b = $overview[(int)$c['client_id']] ?? null; if ($b === null) continue; ?>
                <tr>
                    <td class="adm-cell-name"><a class="adm-link adm-name" href="/coach/client.php?id=<?= (int)$c['client_id'] ?>"><?= htmlspecialchars($c['full_name']) ?></a></td>
                    <td data-label="Buổi thử">còn <?= (int)$b['trial_left'] ?>/<?= (int)$b['trial'] ?></td>
                    <td data-label="Gói buổi"><?= $b['pack_units'] > 0 ? 'còn ' . (int)$b['pack_left'] . '/' . (int)$b['pack_units'] : '—' ?></td>
                    <td data-label="Gói tháng"><?= $b['month_until'] !== null ? 'tới ' . htmlspecialchars(date('d/m/Y', strtotime($b['month_until']))) : '—' ?></td>
                    <td data-label="Đang hẹn"><?= (int)$b['holding'] ?></td>
                    <td data-label="Trạng thái"><?= $b['locked'] ? '<span class="ch-badge ch-badge--danger">Hết buổi</span>' : '<span class="ch-badge ch-badge--ok">Còn buổi</span>' /* audit:ok chữ viết sẵn */ ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <?php if ($groups['recent']): ?>
        <section class="adm-panel adm-card-pad" style="margin-top:16px" aria-labelledby="h-recent">
            <h2 id="h-recent">14 ngày qua</h2>
            <ul class="ch-sess-list">
                <?php foreach ($groups['recent'] as $s): ?>
                    <?= sessionRow($s, '') ?>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
    <?php endif; ?>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
