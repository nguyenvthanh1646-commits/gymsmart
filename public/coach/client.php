<?php
/**
 * coach/client.php?id=... — hồ sơ học viên (CHỈ ĐỌC) cho HLV.
 * Gồm: chỉ số cơ thể, TDEE/macro, lịch tập hiện tại, nhật ký 30 ngày (kèm nhận xét từng buổi), biểu đồ số đo,
 * yêu cầu xin tự sửa đang chờ (HLV duyệt/từ chối ở đây).
 *
 * Quyền: requireCoach() + requireCoachClient() — id trên URL phải là học viên ĐANG ACTIVE với HLV đang đăng nhập,
 * ngược lại 404. Không đọc email/mật khẩu (coachClientBasics chỉ lấy các cột cần dùng).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';
require_once __DIR__ . '/../includes/tdee.php';
require_once __DIR__ . '/../includes/workout-plan-db.php';

requireCoach();
[$clientId, $relationId] = requireCoachClient($pdo, $_GET['id'] ?? ($_POST['client_id'] ?? null));
$self = '/coach/client.php?id=' . $clientId;
coachHandleCoachAction($pdo, ($_POST['action'] ?? '') === 'end' ? '/coach/clients.php' : $self);

// Sửa / xóa nhận xét: coachOwnFeedback() kiểm tra nhận xét thuộc HLV này và quan hệ còn ACTIVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['feedback_edit', 'feedback_delete'], true)) {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $fbId = coachParseId($_POST['feedback_id'] ?? null);
    try {
        if (!coachExtrasReady($pdo)) {
            throw new CoachException('Cần import sql/coach_extras.sql để sửa nhận xét.');
        }
        if ($_POST['action'] === 'feedback_edit') {
            coachUpdateFeedback($pdo, (int)$_SESSION['user_id'], $fbId, coachCleanText($_POST['content'] ?? ''));
            coachFlash('ok', 'Đã sửa nhận xét. Học viên sẽ được báo lại.');
        } else {
            coachDeleteFeedback($pdo, (int)$_SESSION['user_id'], $fbId);
            coachFlash('ok', 'Đã xóa nhận xét.');
        }
    } catch (CoachException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: ' . $self . '#h-logs');
    exit;
}

// Nhận xét: log_date rỗng = nhận xét chung; có ngày thì phải là ngày học viên có ghi log (coachAddFeedback kiểm tra)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'feedback') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $logDate = (string)($_POST['log_date'] ?? '');
    try {
        coachAddFeedback($pdo, $relationId, $clientId, $logDate === '' ? null : $logDate, coachCleanText($_POST['content'] ?? ''));
        coachFlash('ok', 'Đã gửi nhận xét. Học viên sẽ thấy thông báo trên trang chủ.');
    } catch (CoachException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: ' . $self . ($logDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate) ? '#log-' . $logDate : '#h-logs'));
    exit;
}

$coachId  = (int)$_SESSION['user_id'];
$today    = date('Y-m-d');
$flash    = coachTakeFlash();
$client   = coachClientBasics($pdo, $clientId);
$plan     = fetchActivePlan($pdo, $clientId);
$planBy   = coachPlanAssigner($pdo, $clientId);
$logs     = coachClientLogs($pdo, $clientId, $today, 30);
$target   = coachLatestNutrition($pdo, $clientId);
$requests = coachChangeRequests($pdo, $relationId, true);
$canEditFb = coachExtrasReady($pdo);   // sửa/xóa nhận xét cần cột updated_at (sql/coach_extras.sql)
$feedbackByDate = [];
foreach (coachFeedbackList($pdo, $relationId, 100) as $f) {
    $feedbackByDate[$f['log_date'] ?? 'general'][] = $f;
}
$bill = coachBillingReady($pdo) ? coachBillingState($pdo, $relationId) : null;   // buổi thử, gói (A-54)
$st = $pdo->prepare('SELECT accepted_at FROM coach_clients WHERE id = ?');
$st->execute([$relationId]);
$acceptedAt = (string)$st->fetchColumn();

// TDEE tính từ hồ sơ (cùng công thức với trang Dinh dưỡng). Hồ sơ thiếu thì báo, không làm sập trang.
$bmr = $tdee = null;
$auto = null;
try {
    if ($client['weight_kg'] && $client['height_cm'] && $client['age'] && $client['gender'] && $client['activity_level']) {
        $bmr  = calculateBMR($client['weight_kg'], $client['height_cm'], $client['age'], $client['gender']);
        $tdee = calculateTDEE($bmr, $client['activity_level']);
        if ($client['goal']) {
            $auto = calculateTargets($tdee, $client['weight_kg'], $client['goal'], $client['experience_level']);
        }
    }
} catch (InvalidArgumentException $e) {
    $bmr = $tdee = $auto = null;
}
$bmi = ($client['weight_kg'] && $client['height_cm']) ? $client['weight_kg'] / (($client['height_cm'] / 100) ** 2) : null;
$dayNames = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];

$pageTitle = 'Hồ sơ học viên';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$coachNavActive = 'clients';
include __DIR__ . '/../includes/coach-nav.php';
?>
<div class="adm-wrap">
    <a class="adm-back" href="/coach/clients.php">&larr; Danh sách học viên</a>
    <div class="adm-head">
        <div class="ch-coach__head">
            <?= coachAvatar($client['avatar'], $client['full_name'], 56) ?>
            <div>
                <h1><?= htmlspecialchars($client['full_name']) ?></h1>
                <p class="adm-sub">
                    <?= htmlspecialchars(COACH_GOAL_LABELS[$client['goal']] ?? 'Chưa đặt mục tiêu') ?> ·
                    <?= htmlspecialchars(COACH_LEVEL_LABELS[$client['experience_level']] ?? 'Chưa chọn trình độ') ?> ·
                    tập cùng từ <?= htmlspecialchars(coachDateLabel($acceptedAt, false)) ?>
                </p>
            </div>
        </div>
        <div class="adm-actions" style="flex-wrap:wrap">
            <a class="adm-btn" href="/coach/client-plan.php?id=<?= (int)$clientId ?>">Giao lịch tập</a>
            <a class="adm-btn adm-btn-ghost" href="/coach/client-nutrition.php?id=<?= (int)$clientId ?>">Đặt dinh dưỡng</a>
            <a class="adm-btn adm-btn-ghost" href="/coach/messages.php?id=<?= (int)$clientId ?>">Nhắn tin</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <?= coachBillingBanner($pdo, $relationId) ?>

    <?php if ($requests): ?>
        <section class="adm-panel adm-card-pad" style="margin-bottom:16px;border-color:var(--accent)" aria-labelledby="h-req">
            <h2 id="h-req">Học viên xin tự sửa</h2>
            <ul class="ch-feed">
                <?php foreach ($requests as $r): ?>
                    <li>
                        <div class="ch-feed__meta"><b><?= htmlspecialchars(COACH_CHANGE_AREAS[$r['area']] ?? $r['area']) ?></b><span><?= htmlspecialchars(coachTimeAgo($r['created_at'])) ?></span></div>
                        <p><?= htmlspecialchars($r['reason']) ?></p>
                        <form method="post" action="<?= htmlspecialchars($self) ?>" style="margin-top:10px">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="resolve_change">
                            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                            <label class="ch-label" for="reply-<?= (int)$r['id'] ?>">Trả lời (không bắt buộc)</label>
                            <textarea class="adm-textarea" style="min-height:64px" id="reply-<?= (int)$r['id'] ?>" name="reply" maxlength="<?= COACH_NOTE_MAX ?>"></textarea>
                            <div class="adm-actions" style="margin-top:8px;justify-content:flex-start">
                                <button type="submit" name="decision" value="reject" class="adm-btn adm-btn-ghost adm-btn-sm">Không cho sửa</button>
                                <button type="submit" name="decision" value="approve" class="adm-btn adm-btn-sm">Cho tự sửa <?= COACH_UNLOCK_HOURS ?> giờ</button>
                            </div>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="adm-split">
        <div style="display:grid;gap:16px;min-width:0">
            <div class="cc-charts">
            <section class="adm-panel adm-card-pad" aria-labelledby="h-chart">
                <h2 id="h-chart">Cân nặng</h2>
                <div class="cc-chart" data-client="<?= (int)$clientId ?>" data-api="body-metrics" data-field="weightKg" data-unit="kg" data-label="Cân nặng" data-empty="Học viên chưa nhập số đo nào. Nhắc học viên cân và ghi ở trang Tiến trình mỗi tuần.">
                    <span class="ch-skel" style="height:220px" aria-hidden="true"></span>
                    <p class="adm-sub cc-chart__msg" role="status">Đang tải…</p>
                    <div class="adm-chart" hidden><canvas role="img" aria-label="Cân nặng"></canvas></div>
                </div>
            </section>
            <section class="adm-panel adm-card-pad" aria-labelledby="h-kcal">
                <h2 id="h-kcal">Calo mục tiêu</h2>
                <div class="cc-chart" data-client="<?= (int)$clientId ?>" data-api="nutrition-history" data-field="targetKcal" data-unit="kcal" data-label="Calo mục tiêu mỗi ngày" data-empty="Chưa có mục tiêu dinh dưỡng nào được lưu.">
                    <span class="ch-skel" style="height:220px" aria-hidden="true"></span>
                    <p class="adm-sub cc-chart__msg" role="status">Đang tải…</p>
                    <div class="adm-chart" hidden><canvas role="img" aria-label="Calo mục tiêu mỗi ngày"></canvas></div>
                </div>
            </section>
            </div>

            <section class="adm-panel adm-card-pad" aria-labelledby="h-logs">
                <h2 id="h-logs">Nhật ký 30 ngày</h2>
                <details class="cc-fb">
                    <summary>Viết nhận xét chung</summary>
                    <form method="post" action="<?= htmlspecialchars($self) ?>">
                        <?= csrfField() ?><input type="hidden" name="action" value="feedback"><input type="hidden" name="log_date" value="">
                        <label class="ch-sr" for="fb-general">Nhận xét chung</label>
                        <textarea class="adm-textarea" id="fb-general" name="content" maxlength="<?= COACH_FEEDBACK_MAX ?>" required placeholder="Nhận xét về tiến độ tuần này, điều cần chú ý…"></textarea>
                        <span class="ch-count" data-count="fb-general"></span>
                        <button type="submit" class="adm-btn adm-btn-sm" data-busy-text="Đang gửi…">Gửi nhận xét</button>
                    </form>
                </details>
                <?php foreach ($feedbackByDate['general'] ?? [] as $f): ?>
                    <?= coachFeedbackItem($f, $self, 'Nhận xét chung · ' . coachTimeAgo($f['created_at']), $canEditFb) ?>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                    <p class="adm-sub">Học viên chưa ghi buổi tập nào trong 30 ngày qua. Nhắn tin hỏi thăm để giữ nhịp tập.</p>
                <?php else: ?>
                    <?php foreach ($logs as $date => $rows): ?>
                        <article class="cc-day" id="log-<?= htmlspecialchars($date) ?>">
                            <header class="ch-feed__meta"><b><?= htmlspecialchars(coachDateLabel($date)) ?></b><span><?= count($rows) ?> bài</span></header>
                            <ul class="adm-list">
                                <?php foreach ($rows as $r): ?>
                                    <li><span><?= htmlspecialchars($r['name']) ?><?php if ($r['note'] !== null && $r['note'] !== ''): ?><br><small>“<?= htmlspecialchars($r['note']) ?>”</small><?php endif; ?></span>
                                        <span class="adm-mono"><?= (int)$r['sets_completed'] ?> × <?= (int)$r['reps_completed'] ?><?= $r['weight_kg_used'] !== null ? ' · ' . coachNum($r['weight_kg_used'], 1) . ' kg' : '' ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php foreach ($feedbackByDate[$date] ?? [] as $f): ?>
                                <?= coachFeedbackItem($f, $self, 'Nhận xét của bạn', $canEditFb) ?>
                            <?php endforeach; ?>
                            <details class="cc-fb">
                                <summary>Nhận xét buổi này</summary>
                                <form method="post" action="<?= htmlspecialchars($self) ?>">
                                    <?= csrfField() ?><input type="hidden" name="action" value="feedback"><input type="hidden" name="log_date" value="<?= htmlspecialchars($date) ?>">
                                    <label class="ch-sr" for="fb-<?= htmlspecialchars($date) ?>">Nhận xét buổi <?= htmlspecialchars(coachDateLabel($date)) ?></label>
                                    <textarea class="adm-textarea" id="fb-<?= htmlspecialchars($date) ?>" name="content" maxlength="<?= COACH_FEEDBACK_MAX ?>" required placeholder="Kỹ thuật, mức tạ, điều cần sửa ở buổi sau…"></textarea>
                                    <span class="ch-count" data-count="fb-<?= htmlspecialchars($date) ?>"></span>
                                    <button type="submit" class="adm-btn adm-btn-sm" data-busy-text="Đang gửi…">Gửi nhận xét</button>
                                </form>
                            </details>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>

        <div class="adm-side" style="display:grid;gap:16px">
            <?php if ($bill !== null): ?>
            <section class="adm-panel adm-card-pad" aria-labelledby="h-bill">
                <h2 id="h-bill">Buổi tập và gói</h2>
                <dl class="adm-kv">
                    <div><dt>Buổi thử</dt><dd>còn <?= (int)$bill['trial_left'] ?>/<?= (int)$bill['trial'] ?></dd></div>
                    <div><dt>Gói buổi</dt><dd><?= $bill['pack_units'] > 0 ? 'còn ' . (int)$bill['pack_left'] . '/' . (int)$bill['pack_units'] : 'Chưa mua' ?></dd></div>
                    <div><dt>Gói tháng</dt><dd><?= $bill['month_until'] !== null ? 'tới ' . htmlspecialchars(date('d/m/Y', strtotime($bill['month_until']))) : 'Không có' ?></dd></div>
                    <div><dt>Buổi đang hẹn</dt><dd><?= (int)$bill['holding'] ?></dd></div>
                </dl>
                <a class="adm-btn adm-btn-ghost adm-btn-sm" href="/coach/sessions.php">Lịch hẹn và gói</a>
            </section>
            <?php endif; ?>
            <section class="adm-panel adm-card-pad" aria-labelledby="h-body">
                <h2 id="h-body">Chỉ số cơ thể</h2>
                <dl class="adm-kv">
                    <div><dt>Tuổi · giới tính</dt><dd><?= $client['age'] ? (int)$client['age'] : '—' ?> · <?= htmlspecialchars(COACH_GENDER_LABELS[$client['gender']] ?? '—') ?></dd></div>
                    <div><dt>Chiều cao</dt><dd><?= $client['height_cm'] ? coachNum($client['height_cm']) . ' cm' : '—' ?></dd></div>
                    <div><dt>Cân nặng (hồ sơ)</dt><dd><?= $client['weight_kg'] ? coachNum($client['weight_kg'], 1) . ' kg' : '—' ?></dd></div>
                    <div><dt>BMI</dt><dd><?= $bmi ? coachNum($bmi, 1) : '—' ?></dd></div>
                    <div><dt>Vận động</dt><dd><?= htmlspecialchars(COACH_ACTIVITY_LABELS[$client['activity_level']] ?? '—') ?></dd></div>
                </dl>
            </section>

            <section class="adm-panel adm-card-pad" aria-labelledby="h-tdee">
                <h2 id="h-tdee">TDEE và macro</h2>
                <?php if ($tdee === null): ?>
                    <p class="adm-sub">Hồ sơ học viên chưa đủ thông tin để tính TDEE.</p>
                <?php else: ?>
                    <dl class="adm-kv">
                        <div><dt>BMR</dt><dd><?= coachNum($bmr) ?> kcal</dd></div>
                        <div><dt>TDEE</dt><dd><?= coachNum($tdee) ?> kcal</dd></div>
                        <?php if ($auto): ?><div><dt>Tự tính theo mục tiêu</dt><dd><?= coachNum($auto['target_kcal']) ?> kcal</dd></div><?php endif; ?>
                    </dl>
                <?php endif; ?>
                <h3 style="margin:16px 0 6px;font-size:1rem">Mục tiêu đang dùng</h3>
                <?php if ($target === null): ?>
                    <p class="adm-sub">Chưa có.</p>
                <?php else: ?>
                    <p style="margin:0"><b><?= coachNum($target['target_kcal']) ?> kcal</b> · P <?= coachNum($target['protein_g']) ?> g · C <?= coachNum($target['carbs_g']) ?> g · F <?= coachNum($target['fat_g']) ?> g</p>
                    <p class="adm-sub" style="margin-top:4px"><?= (int)$target['assigned_by'] === $coachId ? 'Do bạn đặt' : ($target['assigned_by'] ? 'Do HLV khác đặt' : 'Học viên tự tính') ?> ngày <?= htmlspecialchars(date('d/m/Y', strtotime($target['calculated_date']))) ?></p>
                <?php endif; ?>
                <a class="adm-btn adm-btn-ghost adm-btn-sm" style="margin-top:12px" href="/coach/client-nutrition.php?id=<?= (int)$clientId ?>">Đặt mục tiêu</a>
            </section>

            <section class="adm-panel adm-card-pad" aria-labelledby="h-plan">
                <h2 id="h-plan">Lịch tập hiện tại</h2>
                <?php if ($plan === null): ?>
                    <p class="adm-sub">Học viên chưa có lịch.</p>
                <?php else: ?>
                    <p style="margin:0 0 8px"><b><?= htmlspecialchars($plan['name']) ?></b><br>
                        <span class="ch-badge <?= ($planBy && (int)$planBy['id'] === $coachId) ? 'ch-badge--accent' : '' ?>"><?= ($planBy && (int)$planBy['id'] === $coachId) ? 'Do bạn giao' : 'Học viên tự tạo' ?></span></p>
                    <ul class="adm-list">
                        <?php foreach ($plan['days'] as $dow => $rows): ?>
                            <li><span><?= htmlspecialchars($dayNames[$dow] ?? '') ?></span><small><?= count($rows) ?> bài · <?= array_sum(array_column($rows, 'sets')) ?> set</small></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="adm-btn adm-btn-sm" style="margin-top:12px" href="/coach/client-plan.php?id=<?= (int)$clientId ?>"><?= $plan ? 'Sửa lịch' : 'Giao lịch' ?></a>
            </section>

            <section class="adm-panel adm-card-pad">
                <h2>Kết thúc huấn luyện</h2>
                <p class="adm-sub">Bạn sẽ mất quyền xem dữ liệu của học viên ngay. Lịch và mục tiêu đã giao vẫn ở lại với họ.</p>
                <form method="post" action="<?= htmlspecialchars($self) ?>" data-confirm="Kết thúc quan hệ với <?= htmlspecialchars($client['full_name']) ?>?">
                    <?= csrfField() ?><input type="hidden" name="action" value="end"><input type="hidden" name="relation_id" value="<?= $relationId ?>">
                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm" data-busy-text="Đang xử lý…">Kết thúc</button>
                </form>
            </section>
        </div>
    </div>
</div>
<script src="/js/lib/chart.umd.min.js"></script>
<script src="/js/coach.js"></script>
<script src="/js/coach-client.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
