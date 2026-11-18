<?php
/**
 * report.php — Báo cáo & thành tích (F1): chuỗi tuần tập đều, báo cáo theo tháng, huy hiệu cột mốc, tải CSV, in / lưu PDF.
 * Mọi số liệu tính từ nhật ký và số đo THẬT của chính người đăng nhập (includes/achievements.php, hàm thuần có test).
 * GET ?month=YYYY-MM (mặc định tháng này; không nhận tháng tương lai). "Lưu PDF" dùng hộp in của trình duyệt (CSS @media print),
 * không cần thư viện tạo PDF.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/workout-plan-db.php';
require_once __DIR__ . '/includes/report-db.php';
require_once __DIR__ . '/includes/achievements.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/weekly-summary.php';

$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');
$lastWeek = date('Y-m-d', strtotime(achWeekStart($today) . ' -7 days'));   // thứ Hai của tuần vừa xong
$goalLabels = ['LOSE_FAT' => 'giảm mỡ', 'MAINTAIN' => 'duy trì cân nặng', 'GAIN_MUSCLE' => 'tăng cơ'];

// ===== POST: AI viết tổng kết tuần vừa xong (D2). Post/Redirect/Get, kết quả báo bằng flash trong session =====
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $flash = ['error', 'Chưa tạo được bản tổng kết.'];
    try {
        $existing = weeklyGet($pdo, $userId, $lastWeek);
        if (!weeklyCanGenerate($existing, time())) {
            $flash = ['error', 'Mỗi tuần chỉ tạo lại tối đa 1 lần mỗi ngày. Bạn quay lại sau nhé.'];
        } else {
            $plan = fetchActivePlan($pdo, $userId);
            $stats = weeklyLoad($pdo, $userId, $lastWeek, $plan !== null ? (int)$plan['days_per_week'] : 0);
            if ($stats['sessions'] === 0 && $stats['weightEnd'] === null) {
                $flash = ['error', 'Tuần trước chưa có buổi tập hay lần cân nào nên chưa có gì để tổng kết.'];
            } else {
                require_once __DIR__ . '/includes/ai.php';
                $prompt = weeklyPrompt($stats, $goalLabels[fetchUserGoal($pdo, $userId) ?? ''] ?? 'chưa chọn');
                $res = aiGenerate($prompt['system'], [['role' => 'user', 'text' => $prompt['user']]], ['maxTokens' => 700, 'temperature' => 0.5]);
                if ($res['ok'] && trim($res['text']) !== '') {
                    weeklySave($pdo, $userId, $lastWeek, $stats, weeklyCleanReply($res['text']));
                    $flash = ['ok', 'Đã tạo bản tổng kết tuần.'];
                } else {
                    error_log('report weekly: ' . $res['code'] . ' ' . $res['detail']);
                    $flash = ['error', aiFriendlyError($res['code'])];
                }
            }
        }
    } catch (PDOException $e) {
        error_log('report weekly: ' . $e->getMessage());   // thường do DB cũ chưa có bảng weekly_summaries
        $flash = ['error', 'Chưa lưu được bản tổng kết. Quản trị viên cần chạy sql/weekly_summaries.sql.'];
    }
    $_SESSION['report_flash'] = $flash;
    header('Location: /report.php#tong-ket');
    exit;
}
$reportFlash = $_SESSION['report_flash'] ?? null;
unset($_SESSION['report_flash']);
$thisMonth = date('Y-m');
$month = (string)($_GET['month'] ?? $thisMonth);
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month > $thisMonth) {
    $month = $thisMonth;
}
$from = $month . '-01';
$to = date('Y-m-t', strtotime($from));
$prevMonth = date('Y-m', strtotime($from . ' -1 month'));
$nextMonth = date('Y-m', strtotime($from . ' +1 month'));

$loadError = false;
try {
    $plan = fetchActivePlan($pdo, $userId);
    $target = $plan !== null ? (int)$plan['days_per_week'] : ACH_DEFAULT_WEEK_TARGET;
    $trainingDates = reportTrainingDates($pdo, $userId);
    $metricDates = reportMetricDates($pdo, $userId);
    $streak = achWeeklyStreak($trainingDates, $today, $target);
    $badges = achMilestones($trainingDates, $metricDates, $streak);
    $sum = achMonthSummary(reportLogsBetween($pdo, $userId, $from, $to), reportMetricsBetween($pdo, $userId, $from, $to));
    $prevFrom = $prevMonth . '-01';
    $prev = achMonthSummary(reportLogsBetween($pdo, $userId, $prevFrom, date('Y-m-t', strtotime($prevFrom))), []);
    try {
        $weekly = weeklyGet($pdo, $userId, $lastWeek);
        $weeklyReady = true;
    } catch (PDOException $e) {
        $weekly = null;
        $weeklyReady = false;   // chưa import sql/weekly_summaries.sql: ẩn nút tạo
    }
} catch (PDOException $e) {
    error_log('report.php: ' . $e->getMessage());
    $loadError = true;
}
$firstMonth = !$loadError && ($trainingDates || $metricDates)
    ? substr(min(array_filter([$trainingDates[0] ?? null, $metricDates[0] ?? null])), 0, 7) : $thisMonth;
$monthNames = [1 => 'Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
$monthLabel = $monthNames[(int)substr($month, 5, 2)] . '/' . substr($month, 0, 4);
$num = fn(float $v, int $dec = 0): string => number_format($v, $dec, ',', '.');
$dmy = fn(?string $d): string => $d ? date('d/m/Y', strtotime($d)) : '';

$pageTitle = 'Báo cáo & thành tích';
$pageCss = ['/css/report.css'];
include __DIR__ . '/includes/header.php';
?>
<div class="rp">
    <div class="rp-head">
        <div>
            <h1>Báo cáo &amp; thành tích</h1>
            <p class="rp-sub">Tổng kết từ nhật ký tập và số đo của bạn. Huy hiệu chỉ có khi bạn thật sự đạt mốc.</p>
        </div>
        <div class="rp-actions">
            <button type="button" class="btn btn--ghost" data-print>In / lưu PDF</button>
            <a class="btn btn--ghost" href="/export-my-data.php?type=logs">Tải CSV nhật ký</a>
            <a class="btn btn--ghost" href="/export-my-data.php?type=metrics">Tải CSV số đo</a>
        </div>
    </div>

    <?php if ($loadError): ?>
        <div class="rp-empty"><p class="rp-empty__title">Chưa tải được báo cáo</p><p>Có lỗi khi đọc dữ liệu. Bạn thử tải lại trang.</p></div>
    <?php else: ?>

    <!-- ===== Chuỗi tuần ===== -->
    <section class="rp-streak" aria-labelledby="rp-streak-title">
        <h2 id="rp-streak-title" class="rp-sr">Chuỗi tuần tập đều</h2>
        <div class="rp-stat rp-stat--main">
            <span class="rp-stat__label">Chuỗi tuần hiện tại</span>
            <b class="rp-stat__value"><?= (int)$streak['current'] ?> <small>tuần</small></b>
            <span class="rp-stat__note">Tuần đạt = tập đủ <?= (int)$target ?> ngày<?= $plan === null ? ' (chưa có lịch nên tính 2 ngày)' : ' theo lịch của bạn' ?></span>
        </div>
        <div class="rp-stat">
            <span class="rp-stat__label">Tuần này</span>
            <b class="rp-stat__value"><?= (int)$streak['thisWeek'] ?>/<?= (int)$target ?> <small>ngày</small></b>
            <span class="rp-meter" role="img" aria-label="Đã tập <?= (int)$streak['thisWeek'] ?> trên <?= (int)$target ?> ngày"><i style="width: <?= (int)min(100, round($streak['thisWeek'] / max(1, $target) * 100)) ?>%"></i></span>
        </div>
        <div class="rp-stat">
            <span class="rp-stat__label">Chuỗi dài nhất</span>
            <b class="rp-stat__value"><?= (int)$streak['best'] ?> <small>tuần</small></b>
            <span class="rp-stat__note"><?= count($trainingDates) ?> ngày tập từ trước tới nay</span>
        </div>
    </section>

    <!-- ===== Tổng kết tuần bằng AI (D2) ===== -->
    <section class="rp-card rp-ai" id="tong-ket" aria-labelledby="rp-ai-title">
        <div class="rp-card__head">
            <h2 id="rp-ai-title">Tổng kết tuần trước bằng AI</h2>
            <span class="rp-count"><?= htmlspecialchars(date('d/m', strtotime($lastWeek)) . ' - ' . date('d/m/Y', strtotime($lastWeek . ' +6 days'))) ?></span>
        </div>
        <?php if ($reportFlash): ?>
            <p class="rp-flash rp-flash--<?= $reportFlash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($reportFlash[1]) ?></p>
        <?php endif; ?>
        <?php if ($weekly): ?>
            <div class="rp-ai__text">
                <?php foreach (explode("\n", (string)$weekly['content']) as $line): ?>
                    <p><?= htmlspecialchars($line) ?></p>
                <?php endforeach; ?>
            </div>
            <p class="rp-note">Viết lúc <?= htmlspecialchars(date('H:i d/m/Y', strtotime($weekly['created_at']))) ?> từ số liệu nhật ký và số đo của bạn.</p>
        <?php else: ?>
            <p class="rp-ai__intro">GymSmart AI đọc số buổi tập so với lịch, khối lượng nâng, bài đã tăng tạ và cân nặng của tuần trước, rồi viết 3 dòng: điểm tốt, điều cần chú ý, gợi ý cho tuần tới.</p>
        <?php endif; ?>
        <?php if ($weeklyReady && weeklyCanGenerate($weekly, time())): ?>
            <form method="post" action="/report.php" class="rp-ai__form" data-ai-form>
                <?= csrfField() ?>
                <button type="submit" class="btn<?= $weekly ? ' btn--ghost' : '' ?>"><?= $weekly ? 'Viết lại tổng kết' : 'Tạo tổng kết tuần' ?></button>
            </form>
        <?php endif; ?>
        <p class="rp-ai__warn">Nội dung do AI viết, chỉ để tham khảo, không thay thế tư vấn của bác sĩ hay chuyên gia dinh dưỡng. Có chấn thương hoặc bệnh nền thì hỏi chuyên gia trước khi đổi cách tập, cách ăn.</p>
    </section>

    <!-- ===== Báo cáo tháng ===== -->
    <section class="rp-card" aria-labelledby="rp-month-title">
        <div class="rp-card__head">
            <h2 id="rp-month-title">Báo cáo <?= htmlspecialchars($monthLabel) ?></h2>
            <nav class="rp-months" aria-label="Chọn tháng">
                <?php if ($prevMonth >= $firstMonth): ?>
                    <a href="/report.php?month=<?= htmlspecialchars($prevMonth) ?>" aria-label="Tháng trước">&larr; Tháng trước</a>
                <?php endif; ?>
                <?php if ($nextMonth <= $thisMonth): ?>
                    <a href="/report.php?month=<?= htmlspecialchars($nextMonth) ?>" aria-label="Tháng sau">Tháng sau &rarr;</a>
                <?php endif; ?>
            </nav>
        </div>
        <?php if ($sum['sessions'] === 0 && $sum['weightStart'] === null): ?>
            <div class="rp-empty">
                <p class="rp-empty__title">Chưa có dữ liệu trong <?= htmlspecialchars(mb_strtolower($monthLabel)) ?></p>
                <p>Ghi buổi tập ở chế độ Đang tập hoặc ghi cân nặng ở trang Tiến trình, báo cáo sẽ hiện ở đây.</p>
                <a class="btn" href="/workout-session.php">Mở chế độ Đang tập</a>
            </div>
        <?php else: ?>
            <dl class="rp-nums">
                <div><dt>Ngày tập</dt><dd><?= (int)$sum['sessions'] ?>
                    <?php $d = $sum['sessions'] - $prev['sessions']; if ($month !== $firstMonth): ?><small class="rp-delta"><?= $d === 0 ? 'bằng tháng trước' : ($d > 0 ? '+' : '') . (int)$d . ' so với tháng trước' ?></small><?php endif; ?></dd></div>
                <div><dt>Tổng set</dt><dd><?= (int)$sum['sets'] ?></dd></div>
                <div><dt>Tổng khối lượng nâng</dt><dd><?= htmlspecialchars($num((float)$sum['volume'])) ?> <small>kg</small></dd></div>
                <div><dt>Cân nặng</dt><dd>
                    <?php if ($sum['weightChange'] !== null): ?>
                        <?= htmlspecialchars(($sum['weightChange'] > 0 ? '+' : '') . $num($sum['weightChange'], 1)) ?> <small>kg (<?= htmlspecialchars($num($sum['weightStart'], 1)) ?> → <?= htmlspecialchars($num($sum['weightEnd'], 1)) ?>)</small>
                    <?php elseif ($sum['weightEnd'] !== null): ?>
                        <?= htmlspecialchars($num($sum['weightEnd'], 1)) ?> <small>kg, cần từ 2 lần đo để thấy thay đổi</small>
                    <?php else: ?>
                        <small>Chưa ghi trong tháng</small>
                    <?php endif; ?>
                </dd></div>
            </dl>
            <p class="rp-note">Khối lượng nâng = số set × số lặp × mức tạ của từng lần ghi; bài không dùng tạ tính 0.</p>
            <?php if ($sum['top']): ?>
                <h3 class="rp-h3">Bài tập nhiều set nhất</h3>
                <ol class="rp-top">
                    <?php foreach ($sum['top'] as $t): ?>
                        <li><span><?= htmlspecialchars($t['name']) ?></span><b><?= (int)$t['sets'] ?> set</b></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- ===== Huy hiệu ===== -->
    <section class="rp-card" aria-labelledby="rp-badge-title">
        <div class="rp-card__head">
            <h2 id="rp-badge-title">Huy hiệu cột mốc</h2>
            <span class="rp-count"><?= count(array_filter(array_column($badges, 'achieved'))) ?>/<?= count($badges) ?> đã đạt</span>
        </div>
        <ul class="rp-badges">
            <?php foreach ($badges as $b): ?>
                <li class="rp-badge<?= $b['achieved'] ? ' is-on' : '' ?>">
                    <span class="rp-badge__icon" aria-hidden="true"><?= $b['achieved'] ? '★' : '☆' ?></span>
                    <div>
                        <p class="rp-badge__title"><?= htmlspecialchars($b['title']) ?></p>
                        <p class="rp-badge__desc"><?= htmlspecialchars($b['desc']) ?></p>
                        <?php if ($b['achieved']): ?>
                            <p class="rp-badge__state">Đã đạt<?= $b['date'] ? ' ngày ' . htmlspecialchars($dmy($b['date'])) : '' ?></p>
                        <?php else: ?>
                            <p class="rp-badge__state">Chưa đạt · <?= (int)$b['have'] ?>/<?= (int)$b['need'] ?></p>
                            <span class="rp-meter" aria-hidden="true"><i style="width: <?= (int)round($b['have'] / max(1, $b['need']) * 100) ?>%"></i></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
    <p class="rp-print-note">In từ GymSmart ngày <?= htmlspecialchars($dmy($today)) ?>.</p>
</div>
<script>
// Nút "In / lưu PDF": mở hộp in của trình duyệt; chọn "Lưu dưới dạng PDF" để có file PDF
document.querySelector('[data-print]').addEventListener('click', function () { window.print(); });
// Nút tạo tổng kết: AI có thể mất 10-30 giây, khóa nút để khỏi bấm nhiều lần
var aiForm = document.querySelector('[data-ai-form]');
if (aiForm) {
    aiForm.addEventListener('submit', function () {
        var b = aiForm.querySelector('button');
        b.disabled = true;
        b.textContent = 'GymSmart AI đang viết… (10-30 giây)';
    });
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
