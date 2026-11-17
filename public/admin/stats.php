<?php
/**
 * admin/stats.php — thống kê và biểu đồ tổng quan cho admin.
 * Chỉ ĐỌC dữ liệu. Câu SQL đều viết sẵn (không ghép dữ liệu người dùng); chỉ truyền mốc ngày qua dấu ?.
 * Gộp theo tuần được làm bằng PHP (không dùng hàm ngày riêng của MySQL) để dễ hiểu và chạy giống nhau ở mọi nơi.
 * Biểu đồ dùng Chart.js (public/js/lib/chart.umd.min.js), mỗi biểu đồ có bảng số liệu dự phòng bên dưới.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/articles.php';
require_once __DIR__ . '/../includes/coach-db.php';

requireAdmin();

const STATS_WEEKS = 12;
$missing = []; // tên bảng chưa có, để nhắc import

/** Chạy SELECT cố định + tham số; bảng chưa tồn tại thì trả về [] và ghi nhớ để nhắc */
function statRows(PDO $pdo, string $sql, array $params, string $table, array &$missing): array
{
    try {
        $stmt = $pdo->prepare($sql); // audit:ok $sql là hằng số viết sẵn trong file này
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $missing[$table] = true;
        return [];
    }
}

/** Thứ Hai của tuần chứa ngày $date (dạng Y-m-d) */
function weekStart(string $date): string
{
    $t = strtotime($date);
    return date('Y-m-d', strtotime('monday this week', $t));
}

// ----- 12 tuần gần nhất (tuần cuối là tuần này) -----
$weeks = [];
$thisMonday = strtotime('monday this week');
for ($i = STATS_WEEKS - 1; $i >= 0; $i--) {
    $weeks[date('Y-m-d', strtotime("-$i week", $thisMonday))] = 0;
}
$since = array_key_first($weeks);
$weekLabels = array_map(fn($d) => date('d/m', strtotime($d)), array_keys($weeks));

$newUsersByWeek = $weeks;
foreach (statRows($pdo, "SELECT created_at FROM users WHERE role = 'USER' AND created_at >= ?", [$since . ' 00:00:00'], 'users', $missing) as $r) {
    $k = weekStart(substr((string)$r['created_at'], 0, 10));
    if (isset($newUsersByWeek[$k])) {
        $newUsersByWeek[$k]++;
    }
}

// Buổi tập đã ghi log = số cặp (người dùng, ngày) khác nhau, không đếm từng bài trong buổi
$sessionsByWeek = $weeks;
foreach (statRows($pdo, "SELECT DISTINCT user_id, log_date FROM workout_logs WHERE log_date >= ?", [$since], 'workout_logs', $missing) as $r) {
    $k = weekStart((string)$r['log_date']);
    if (isset($sessionsByWeek[$k])) {
        $sessionsByWeek[$k]++;
    }
}

// ----- Top bài tập được ghi log -----
$topEx = statRows($pdo,
    "SELECT e.name AS label, COUNT(*) AS n
     FROM workout_logs l JOIN exercises e ON e.id = l.exercise_id
     GROUP BY e.id, e.name ORDER BY n DESC, e.name LIMIT 10", [], 'workout_logs', $missing);

// ----- Mục tiêu của người dùng -----
$goalLabels = ['LOSE_FAT' => 'Giảm mỡ', 'MAINTAIN' => 'Duy trì', 'GAIN_MUSCLE' => 'Tăng cơ'];
$goalCount = array_fill_keys(array_keys($goalLabels), 0);
$goalNone = 0;
foreach (statRows($pdo, "SELECT goal, COUNT(*) AS n FROM users WHERE role = 'USER' GROUP BY goal", [], 'users', $missing) as $r) {
    if (isset($goalCount[$r['goal']])) {
        $goalCount[$r['goal']] = (int)$r['n'];
    } else {
        $goalNone += (int)$r['n'];
    }
}
$goalData = [];
foreach ($goalLabels as $k => $label) {
    $goalData[] = ['label' => $label, 'n' => $goalCount[$k]];
}
$goalData[] = ['label' => 'Chưa chọn', 'n' => $goalNone];

// ----- Bài viết được xem nhiều nhất -----
$articles = loadArticles();
$topArticles = [];
foreach (statRows($pdo, "SELECT slug, SUM(views) AS n FROM article_views GROUP BY slug ORDER BY n DESC LIMIT 5", [], 'article_views', $missing) as $r) {
    $topArticles[] = ['label' => $articles[$r['slug']]['title'] ?? $r['slug'], 'n' => (int)$r['n']];
}

// ----- Thẻ số liệu -----
$totalUsers = (int)(statRows($pdo, "SELECT COUNT(*) AS n FROM users WHERE role = 'USER'", [], 'users', $missing)[0]['n'] ?? 0);
$weekAgo = date('Y-m-d', strtotime('-7 day'));
$users7 = (int)(statRows($pdo, "SELECT COUNT(*) AS n FROM users WHERE role = 'USER' AND created_at >= ?", [$weekAgo . ' 00:00:00'], 'users', $missing)[0]['n'] ?? 0);
$sessions7 = (int)(statRows($pdo, "SELECT COUNT(*) AS n FROM (SELECT DISTINCT user_id, log_date FROM workout_logs WHERE log_date >= ?) t", [$weekAgo], 'workout_logs', $missing)[0]['n'] ?? 0);
$coachCounts = coachAdminCounts($pdo);   // số HLV, cặp HLV-học viên đang tập (0 nếu chưa import bảng HLV)
$views7 = (int)(statRows($pdo, "SELECT COALESCE(SUM(views), 0) AS n FROM article_views WHERE view_date >= ?", [$weekAgo], 'article_views', $missing)[0]['n'] ?? 0);

/** Bảng số liệu dự phòng cho người đọc màn hình, và để xem số chính xác */
function dataTable(array $labels, array $values, string $col): void
{
    echo '<details class="adm-data"><summary>Xem dạng bảng</summary><table><thead><tr><th>Mục</th><th>' . htmlspecialchars($col) . '</th></tr></thead><tbody>';
    foreach ($labels as $i => $l) {
        echo '<tr><td>' . htmlspecialchars((string)$l) . '</td><td>' . (int)$values[$i] . '</td></tr>';
    }
    echo '</tbody></table></details>';
}

$cfg = [
    'weekLabels' => $weekLabels,
    'users'      => array_values($newUsersByWeek),
    'sessions'   => array_values($sessionsByWeek),
    'topEx'      => ['labels' => array_column($topEx, 'label'), 'values' => array_map('intval', array_column($topEx, 'n'))],
    'goals'      => ['labels' => array_column($goalData, 'label'), 'values' => array_column($goalData, 'n')],
    'articles'   => ['labels' => array_column($topArticles, 'label'), 'values' => array_column($topArticles, 'n')],
];

$pageTitle = 'Thống kê';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
include __DIR__ . '/../includes/admin-style-extra.php';
$admNavActive = 'stats';
include __DIR__ . '/../includes/admin-nav.php';
?>

<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Thống kê</h1>
            <p class="adm-sub">Hoạt động của người dùng trong <?= STATS_WEEKS ?> tuần gần nhất.</p>
        </div>
    </div>

    <?php if ($missing): ?>
        <div class="adm-hint-box">Thiếu bảng: <b><?= htmlspecialchars(implode(', ', array_keys($missing))) ?></b>. Hãy import <b>sql/admin_features.sql</b> (và các file seed còn thiếu) trong phpMyAdmin. Phần chưa có dữ liệu sẽ để trống.</div>
    <?php endif; ?>

    <div class="adm-stats">
        <div class="adm-stat"><div><div class="adm-stat-num"><?= $totalUsers ?></div><div class="adm-stat-label">Người dùng</div></div></div>
        <div class="adm-stat"><div><div class="adm-stat-num"><?= $users7 ?></div><div class="adm-stat-label">Đăng ký mới (7 ngày)</div></div></div>
        <div class="adm-stat"><div><div class="adm-stat-num"><?= $sessions7 ?></div><div class="adm-stat-label">Buổi tập đã ghi (7 ngày)</div></div></div>
        <div class="adm-stat"><div><div class="adm-stat-num"><?= $views7 ?></div><div class="adm-stat-label">Lượt xem bài (7 ngày)</div></div></div>
    </div>
    <div class="adm-stats" style="grid-template-columns:repeat(3,1fr)">
        <div class="adm-stat"><div><div class="adm-stat-num"><?= (int)$coachCounts['coaches'] ?></div><div class="adm-stat-label">Huấn luyện viên</div></div></div>
        <div class="adm-stat"><div><div class="adm-stat-num"><?= (int)$coachCounts['active_pairs'] ?></div><div class="adm-stat-label">Cặp HLV – học viên đang tập</div></div></div>
        <div class="adm-stat"><div><div class="adm-stat-num"><?= (int)$coachCounts['pending_apps'] ?></div><div class="adm-stat-label">Đơn HLV chờ duyệt</div></div></div>
    </div>

    <div class="adm-three">
        <div class="adm-panel adm-card-pad">
            <h2>Người dùng đăng ký mới mỗi tuần</h2>
            <div class="adm-chart"><canvas id="c-users" role="img" aria-label="Biểu đồ người dùng đăng ký mới theo tuần"></canvas></div>
            <?php dataTable($weekLabels, array_values($newUsersByWeek), 'Người dùng mới'); ?>
        </div>
        <div class="adm-panel adm-card-pad">
            <h2>Buổi tập được ghi log mỗi tuần</h2>
            <div class="adm-chart"><canvas id="c-sessions" role="img" aria-label="Biểu đồ số buổi tập ghi log theo tuần"></canvas></div>
            <?php dataTable($weekLabels, array_values($sessionsByWeek), 'Buổi tập'); ?>
        </div>
    </div>

    <div class="adm-three">
        <div class="adm-panel adm-card-pad">
            <h2>10 bài tập được ghi log nhiều nhất</h2>
            <?php if (empty($topEx)): ?><p class="adm-sub">Chưa có nhật ký tập nào.</p><?php else: ?>
                <div class="adm-chart adm-chart--tall"><canvas id="c-topex" role="img" aria-label="Biểu đồ 10 bài tập được ghi log nhiều nhất"></canvas></div>
                <?php dataTable($cfg['topEx']['labels'], $cfg['topEx']['values'], 'Lượt ghi'); ?>
            <?php endif; ?>
        </div>
        <div class="adm-panel adm-card-pad">
            <h2>Mục tiêu của người dùng</h2>
            <div class="adm-chart"><canvas id="c-goals" role="img" aria-label="Biểu đồ phân bố mục tiêu người dùng"></canvas></div>
            <?php dataTable($cfg['goals']['labels'], $cfg['goals']['values'], 'Người dùng'); ?>
            <h2 style="margin-top:22px;">Bài viết được xem nhiều nhất</h2>
            <?php if (empty($topArticles)): ?><p class="adm-sub">Chưa có lượt xem nào.</p><?php else: ?>
                <div class="adm-chart"><canvas id="c-articles" role="img" aria-label="Biểu đồ bài viết được xem nhiều nhất"></canvas></div>
                <?php dataTable($cfg['articles']['labels'], $cfg['articles']['values'], 'Lượt xem'); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="/js/lib/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') { return; } // không tải được thư viện thì còn bảng số liệu bên dưới
    var cfg = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var BLUE = '#2a78d6', ORANGE = '#eb6834', GRID = '#e6e5e0', INK = '#52514e';
    Chart.defaults.font.family = 'inherit';
    Chart.defaults.color = INK;

    function scales(horizontal) {
        var val = { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID }, border: { display: false } };
        var cat = { grid: { display: false }, ticks: { autoSkip: false } };
        return horizontal ? { x: val, y: cat } : { x: cat, y: val };
    }
    function bar(id, labels, values, color, horizontal, name) {
        var el = document.getElementById(id);
        if (!el) { return; }
        new Chart(el, {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: name, data: values, backgroundColor: color, borderRadius: 4, maxBarThickness: 28 }] },
            options: { indexAxis: horizontal ? 'y' : 'x', responsive: true, maintainAspectRatio: false,
                       plugins: { legend: { display: false } }, scales: scales(horizontal) }
        });
    }
    function line(id, labels, values, color, name) {
        new Chart(document.getElementById(id), {
            type: 'line',
            data: { labels: labels, datasets: [{ label: name, data: values, borderColor: color, backgroundColor: color, borderWidth: 2, pointRadius: 3, tension: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                       plugins: { legend: { display: false } }, scales: scales(false) }
        });
    }
    line('c-users', cfg.weekLabels, cfg.users, BLUE, 'Người dùng mới');
    line('c-sessions', cfg.weekLabels, cfg.sessions, ORANGE, 'Buổi tập');
    bar('c-topex', cfg.topEx.labels, cfg.topEx.values, BLUE, true, 'Lượt ghi');
    bar('c-goals', cfg.goals.labels, cfg.goals.values, BLUE, false, 'Người dùng');
    bar('c-articles', cfg.articles.labels, cfg.articles.values, ORANGE, true, 'Lượt xem');
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
