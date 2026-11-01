<?php
/**
 * home-user.php — giao diện trang chủ sau đăng nhập (index.php include file này).
 * Dữ liệu nằm trong $dash, do loadDashboard() trong dashboard.php chuẩn bị. Mỗi thẻ là một file trong includes/dash-*.php.
 */

/** Khối báo trạng thái rỗng hoặc lỗi của một thẻ: giải thích ngắn kèm nút cho bước tiếp theo. */
function duState(string $title, string $text, string $href, string $label): void
{
    ?>
    <div class="du-state">
        <p class="du-state__title"><?= htmlspecialchars($title) ?></p>
        <p class="du-state__text"><?= htmlspecialchars($text) ?></p>
        <a class="du-link du-link--button" href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
    </div>
    <?php
}

/** Trạng thái lỗi dùng chung: lỗi đọc dữ liệu thường hết khi tải lại. */
function duError(string $what): void
{
    duState("Chưa tải được $what", 'Có lỗi khi đọc dữ liệu. Bạn thử tải lại trang.', '/index.php', 'Tải lại trang');
}

$session = $dash['session'];
$planStatus = $dash['plan']['status'];

// Một câu tóm tắt hôm nay ở đầu trang
if (!empty($missingFields)) {
    $duLead = 'Hoàn thiện hồ sơ để nhận lịch tập và mục tiêu dinh dưỡng phù hợp với bạn.';
} elseif ($planStatus === 'none') {
    $duLead = 'Bạn chưa có lịch tập. Tạo lịch để biết mỗi ngày cần tập gì.';
} elseif ($planStatus === 'error') {
    $duLead = 'Chưa tải được lịch tập của bạn.';
} elseif ($session['summary'] === null) {
    $duLead = 'Hôm nay là ngày nghỉ.';
    if ($session['next'] !== null) {
        $duLead .= ' Buổi kế tiếp vào ' . DASH_DAY_NAMES[$session['next']['day']] . ($session['next']['nextWeek'] ? ' tuần sau.' : '.');
    }
} elseif ($session['logged'] >= $session['summary']['count']) {
    $duLead = 'Bạn đã ghi đủ buổi tập hôm nay.';
} else {
    $duLead = 'Hôm nay có ' . $session['summary']['count'] . ' bài, khoảng ' . $session['summary']['minutes'] . ' phút.';
}
?>
<script src="/js/home-user.js" defer></script>

<div class="du">
    <div class="du-wrap">
        <div class="du-top">
            <div>
                <p class="du-top__date"><?= htmlspecialchars($dash['dateLabel']) ?></p>
                <h1 class="du-top__title">Chào <em><?= htmlspecialchars($firstName) ?></em></h1>
                <p class="du-top__lead" data-dash-lead><?= htmlspecialchars($duLead) ?></p>
            </div>
            <a class="du-btn" href="<?= htmlspecialchars($dash['action']['href']) ?>" data-dash-primary><?= htmlspecialchars($dash['action']['label']) ?></a>
        </div>

        <?php if (!empty($missingFields)): ?>
            <p class="du-notice">
                <b>Hồ sơ <?= (int)$doneCount ?>/<?= (int)$profileTotal ?> mục.</b>
                Còn thiếu: <?= htmlspecialchars(implode(', ', $missingFields)) ?>.
            </p>
        <?php endif; ?>

        <div class="du-grid">
            <?php include __DIR__ . '/dash-today.php'; ?>
            <?php include __DIR__ . '/dash-week.php'; ?>
            <?php include __DIR__ . '/dash-nutrition.php'; ?>
        </div>
    </div>
</div>
