<?php
/**
 * home-user.php — giao diện trang chủ sau đăng nhập (index.php include file này).
 * Dữ liệu nằm trong $dash, do loadDashboard() trong dashboard.php chuẩn bị. Mỗi thẻ là một file trong includes/dash-*.php.
 */

/** Khối báo trạng thái rỗng hoặc lỗi của một thẻ: biểu tượng, giải thích ngắn và nút cho bước tiếp theo. */
function duState(string $icon, string $title, string $text, string $href, string $label): void
{
    ?>
    <div class="du-state">
        <span class="du-state__icon"><?= hmIcon($icon, 26) ?></span>
        <p class="du-state__title"><?= htmlspecialchars($title) ?></p>
        <p class="du-state__text"><?= htmlspecialchars($text) ?></p>
        <a class="du-link du-link--button" href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
    </div>
    <?php
}

/** Trạng thái lỗi dùng chung: lỗi đọc dữ liệu thường hết khi tải lại. */
function duError(string $what): void
{
    duState('bolt', "Chưa tải được $what", 'Có lỗi khi đọc dữ liệu. Bạn thử tải lại trang.', '/index.php', 'Tải lại trang');
}

/** Tiêu đề thẻ: biểu tượng trong ô màu và chữ. */
function duCardTitle(string $icon, string $id, string $title): void
{
    ?>
    <h2 class="du-card__title" id="<?= htmlspecialchars($id) ?>"><span class="du-card__icon"><?= hmIcon($icon, 20) ?></span><?= htmlspecialchars($title) ?></h2>
    <?php
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

// Số liệu nhanh ở đầu trang: chỉ hiện số thật đã có, chưa có thì bỏ ô đó
$duStats = [];
if ($dash['nutrition']['status'] === 'ok') {
    $duStats[] = ['flame', 'Calo mục tiêu', dashNumber((float)$dash['nutrition']['target_kcal'], 0), 'kcal'];
}
if ($dash['progress']['status'] === 'ok') {
    $duStats[] = ['chart', 'Cân nặng', dashNumber((float)$dash['progress']['latest']['weight_kg']), 'kg'];
}
?>
<script src="/js/home-user.js" defer></script>

<div class="du">
    <div class="du-hero">
        <!-- Ảnh trang trí nên alt rỗng. Nguồn ảnh ghi ở assets/img/CREDITS.md -->
        <picture>
            <source media="(max-width: 720px)" type="image/webp" srcset="/assets/img/hero/hero-800.webp" width="800" height="1000">
            <source media="(max-width: 720px)" type="image/jpeg" srcset="/assets/img/hero/hero-800.jpg" width="800" height="1000">
            <source type="image/webp" srcset="/assets/img/hero/hero-1920.webp" width="1920" height="1080">
            <img class="du-hero__photo" src="/assets/img/hero/hero-1920.jpg" alt="" width="1920" height="1080" fetchpriority="high" decoding="async">
        </picture>
        <div class="du-wrap du-hero__in">
            <div class="du-hero__copy">
                <p class="du-hero__date"><?= htmlspecialchars($dash['dateLabel']) ?></p>
                <h1 class="du-hero__title">Chào <em><?= htmlspecialchars($firstName) ?></em></h1>
                <p class="du-hero__lead" data-dash-lead><?= htmlspecialchars($duLead) ?></p>
                <a class="du-btn" href="<?= htmlspecialchars($dash['action']['href']) ?>" data-dash-primary><?= htmlspecialchars($dash['action']['label']) ?></a>
            </div>
            <?php if ($duStats !== []): ?>
                <dl class="du-hero__stats">
                    <?php foreach ($duStats as [$statIcon, $statName, $statValue, $statUnit]): ?>
                        <div class="du-stat">
                            <span class="du-stat__icon"><?= hmIcon($statIcon, 20) ?></span>
                            <dt><?= htmlspecialchars($statName) ?></dt>
                            <dd><?= htmlspecialchars($statValue) ?> <small><?= htmlspecialchars($statUnit) ?></small></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
    </div>

    <div class="du-wrap du-body">
        <?php if (!empty($missingFields)): ?>
            <p class="du-notice">
                <b>Hồ sơ <?= (int)$doneCount ?>/<?= (int)$profileTotal ?> mục.</b>
                Còn thiếu: <?= htmlspecialchars(implode(', ', $missingFields)) ?>.
            </p>
        <?php endif; ?>

        <?php include __DIR__ . '/dash-week.php'; ?>

        <div class="du-grid">
            <?php include __DIR__ . '/dash-today.php'; ?>
            <?php include __DIR__ . '/dash-nutrition.php'; ?>
            <?php include __DIR__ . '/dash-progress.php'; ?>
            <?php include __DIR__ . '/dash-shortcuts.php'; ?>
        </div>
    </div>
</div>
