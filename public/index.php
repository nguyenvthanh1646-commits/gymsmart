<?php
/**
 * index.php — trang chủ.
 *
 * Ba trạng thái:
 *   - ADMIN:            chuyển sang /admin/dashboard.php (có trang chủ riêng).
 *   - Chưa đăng nhập:   trang giới thiệu (hero, tính năng, 3 bước, lời kêu gọi đăng ký).
 *   - Đã đăng nhập:     chào tên, thanh tiến độ hồ sơ, lối tắt đến các chức năng, gợi ý bài tập
 *                       theo trình độ của người dùng.
 *
 * Vì sao nhắc hoàn thiện hồ sơ? TDEE/Macro (C) và lịch tập (B) đều cần đủ 7 thông tin hồ sơ.
 */

require_once __DIR__ . '/includes/auth.php'; // có session + isLoggedIn()
require_once __DIR__ . '/includes/db.php';   // có biến $pdo
require_once __DIR__ . '/includes/logo.php';

$loggedIn = isLoggedIn();

// Quản trị viên có trang chủ riêng (tổng quan), không dùng trang chủ của người dùng
if ($loggedIn && ($_SESSION['role'] ?? '') === 'ADMIN') {
    header('Location: /admin/dashboard.php');
    exit;
}

/** Vẽ biểu tượng nét (SVG nhúng thẳng), màu theo chữ xung quanh (currentColor). */
function hmIcon(string $name, int $size = 24): string
{
    $paths = [
        'check'    => '<path d="M5 13l4 4L19 7"/>',
        'play'     => '<circle cx="12" cy="12" r="9"/><path d="M10 8.5l5.5 3.5-5.5 3.5z"/>',
        'dumbbell' => '<path d="M6.5 6.5v11M17.5 6.5v11M3.5 9v6M20.5 9v6M6.5 12h11"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'flame'    => '<path d="M12 3c1 3.5 5 5.5 5 10a5 5 0 01-10 0c0-2 1-3.5 2-4.5.3 1.2 1 2 2 2.3C10.5 8 11 5.5 12 3z"/>',
        'chart'    => '<path d="M4 19V5M4 19h16M8 15l3.5-4 3 2.5L19 8"/>',
        'user'     => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.6 3.7-5.5 7-5.5s6.2 1.9 7 5.5"/>',
        'bolt'     => '<path d="M13 3L5 13.5h6L10 21l8-10.5h-6z"/>',
        'book'     => '<path d="M5 4.5h10a3 3 0 013 3V20H8a3 3 0 01-3-3V4.5zM5 17a3 3 0 013-3h10"/>',
        'target'   => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

// Các chức năng của website. "ready" tự bật khi file trang tương ứng đã tồn tại,
// nên không phải sửa tay khi B, C hoàn thành trang của mình.
$features = [
    ['icon' => 'play',     'title' => 'Thư viện bài tập có video', 'href' => '/exercises.php',
     'text' => 'Hàng chục bài tập kèm ảnh, video và hướng dẫn từng bước, lọc theo nhóm cơ, loại và độ khó.',
     'ready' => is_file(__DIR__ . '/exercises.php')],
    ['icon' => 'calendar', 'title' => 'Lịch tập cá nhân hóa', 'href' => '/workout-plan.php',
     'text' => 'Gợi ý số buổi, bài tập và số hiệp theo trình độ, mục tiêu và số ngày bạn có thể tập.',
     'ready' => is_file(__DIR__ . '/workout-plan.php')],
    ['icon' => 'flame',    'title' => 'TDEE và Macro', 'href' => '/nutrition.php',
     'text' => 'Tính nhu cầu năng lượng và tỉ lệ đạm, tinh bột, béo theo công thức khoa học và mục tiêu của bạn.',
     'ready' => is_file(__DIR__ . '/nutrition.php')],
    ['icon' => 'chart',    'title' => 'Theo dõi tiến trình', 'href' => '/progress.php',
     'text' => 'Ghi lại cân nặng, số đo và buổi tập, xem biểu đồ thay đổi qua từng tuần.',
     'ready' => is_file(__DIR__ . '/progress.php')],
];

$muscleLabels = [
    'CHEST' => 'Ngực', 'BACK' => 'Lưng', 'SHOULDERS' => 'Vai', 'LEGS' => 'Chân',
    'ARMS' => 'Tay', 'ABS' => 'Bụng', 'FULL_BODY' => 'Toàn thân',
];
$typeLabels       = ['RESISTANCE' => 'Kháng lực', 'CARDIO' => 'Cardio'];
$levelLabels      = ['BEGINNER' => 'Dễ', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Khó'];
$experienceLabels = ['BEGINNER' => 'mới tập', 'INTERMEDIATE' => 'trung bình', 'ADVANCED' => 'nâng cao'];

$missingFields = [];  // tên các mục hồ sơ còn thiếu
$profileTotal  = 0;
$recommended   = [];  // bài tập gợi ý
$experience    = null;

if ($loggedIn) {
    // Tên cột trong DB => chữ hiển thị khi báo thiếu
    $profileFields = [
        'age'              => 'Tuổi',
        'height_cm'        => 'Chiều cao',
        'weight_kg'        => 'Cân nặng',
        'gender'           => 'Giới tính',
        'activity_level'   => 'Mức độ vận động',
        'experience_level' => 'Trình độ tập luyện',
        'goal'             => 'Mục tiêu',
    ];
    $profileTotal = count($profileFields);

    // Lấy id từ SESSION (không lấy từ URL/form) để không xem được hồ sơ người khác
    $stmt = $pdo->prepare(
        "SELECT age, height_cm, weight_kg, gender, activity_level, experience_level, goal
         FROM users WHERE id = ?"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        // Session còn nhưng user đã bị xóa khỏi DB → đăng xuất cho an toàn
        $_SESSION = [];
        session_destroy();
        header('Location: /login.php');
        exit;
    }

    foreach ($profileFields as $column => $label) {
        // NULL hoặc rỗng = chưa nhập. Dùng === null thay vì empty() vì giá trị 0 không phải "thiếu".
        if ($row[$column] === null || $row[$column] === '') {
            $missingFields[] = $label;
        }
    }

    $experience = isset($experienceLabels[$row['experience_level'] ?? '']) ? $row['experience_level'] : null;

    // Gợi ý 3 bài ngẫu nhiên, ưu tiên bài đúng độ khó với trình độ của người dùng.
    // ORDER BY (difficulty = ?) DESC đẩy bài cùng độ khó lên trước; thiếu thì lấy bài khác cho đủ 3.
    $stmt = $pdo->prepare(
        "SELECT id, name, type, muscle_group, difficulty, image_url
         FROM exercises
         ORDER BY (difficulty = ?) DESC, RAND()
         LIMIT 3"
    );
    $stmt->execute([$experience ?? '']);
    $recommended = $stmt->fetchAll();
}

$doneCount = $profileTotal - count($missingFields);
$percent   = $profileTotal > 0 ? (int)round($doneCount / $profileTotal * 100) : 0;
$firstName = '';
if ($loggedIn) {
    $parts = preg_split('/\s+/u', trim((string)($_SESSION['full_name'] ?? '')));
    $firstName = $parts ? end($parts) : '';
}

// Số liệu và bài viết nổi bật cho trang giới thiệu (khách). Lỗi database thì dùng giá trị mặc định, trang vẫn chạy.
$exerciseCount = 0;
$articleTeasers = [];
$articleCount = 0;
if (!$loggedIn) {
    try {
        $exerciseCount = (int)$pdo->query('SELECT COUNT(*) FROM exercises')->fetchColumn();
    } catch (Throwable $e) {
        $exerciseCount = 0;
    }
    require_once __DIR__ . '/includes/articles.php';
    $allArticles = loadArticles();
    $articleCount = count($allArticles);
    foreach (['bao-nhieu-buoc-moi-ngay', 'protein-bao-nhieu-la-du', 'ta-nang-hay-ta-nhe'] as $s) {
        if (isset($allArticles[$s])) {
            $articleTeasers[$s] = $allArticles[$s];
        }
    }
    if (count($articleTeasers) < 3) { // thiếu bài mong muốn thì lấy bài đầu danh sách cho đủ 3
        foreach ($allArticles as $s => $a) {
            if (count($articleTeasers) >= 3) { break; }
            $articleTeasers[$s] = $articleTeasers[$s] ?? $a;
        }
    }
}

$pageTitle = 'Trang chủ';
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/home.css">

<?php if (!$loggedIn): ?>

<!-- ================= KHÁCH CHƯA ĐĂNG NHẬP ================= -->
<section class="tp">
    <?php include __DIR__ . "/includes/hero-bg.php"; ?>
    <div class="hm-wrap">
        <div class="tp-copy">
            <h1 class="tp-h1">Train Harder &amp; Smarter</h1>
            <p class="tp-lead">
                Học đúng kỹ thuật qua video, nhận lịch tập chia theo trình độ và số buổi bạn có,
                tính calo và macro, rồi ghi lại từng buổi tập để thấy mình tiến bộ.
            </p>
            <div class="tp-cta">
                <a class="hm-btn hm-btn--solid" href="/register.php">Tạo tài khoản miễn phí</a>
                <a class="hm-btn hm-btn--line" href="/login.php">Đăng nhập</a>
            </div>
            <p class="tp-fine">Miễn phí, chỉ cần email để đăng ký.</p>
        </div>

    </div>

    <!-- Nền xám nhạt bắt đầu từ giữa cửa sổ ứng dụng, nên cửa sổ vắt ngang ranh giới trắng/xám -->
    <div class="tp-stage"><div class="hm-wrap">
        <!-- Cửa sổ ứng dụng minh họa: bấm vào từng hiệp ở ô "Hôm nay" để thử -->
        <div class="app" role="group" aria-label="Ví dụ giao diện GymSmart (minh họa)">
            <div class="app-bar">
                <span class="app-bar__name">Tuần này</span>
                <span class="app-bar__note">Minh họa giao diện, số liệu mẫu</span>
            </div>
            <div class="app-grid">

                <div class="pn pn--log">
                    <div class="pn-head"><h2>Hôm nay: buổi Trên</h2><span class="pn-meta"><b id="lg-done">0</b>/13 hiệp</span></div>
                    <?php
                    // [tên bài, nhóm cơ, số hiệp, số lần, kg, số hiệp đã tick sẵn]
                    $log = [
                        ['Đẩy ngực tạ đòn', 'Ngực', 4, 8, 50, 4],
                        ['Kéo cáp ngồi', 'Lưng', 3, 10, 45, 1],
                        ['Đẩy vai tạ đơn ngồi', 'Vai', 3, 10, 14, 0],
                        ['Cuốn tạ đòn bắp tay', 'Tay trước', 3, 10, 25, 0],
                    ];
                    foreach ($log as [$name, $muscle, $sets, $reps, $kg, $pre]): ?>
                        <div class="lg-ex">
                            <div class="lg-name"><b><?= htmlspecialchars($name) ?></b><small><?= htmlspecialchars($muscle) ?> · <?= $sets ?> × <?= $reps ?> · <?= $kg ?> kg</small></div>
                            <div class="lg-sets">
                                <?php for ($i = 1; $i <= $sets; $i++): $on = $i <= $pre; ?>
                                    <button type="button" class="lg-set<?= $on ? ' is-on' : '' ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"
                                            aria-label="<?= htmlspecialchars($name) ?>, hiệp <?= $i ?>" data-vol="<?= $reps * $kg ?>"><?= $i ?></button>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="lg-vol"><span>Khối lượng đã nâng</span><b><span id="lg-vol">0</span> kg</b></div>
                </div>

                <div class="pn pn--week">
                    <div class="pn-head"><h2>Lịch 4 buổi mỗi tuần</h2><span class="pn-meta">Trên / Dưới</span></div>
                    <?php
                    $days = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
                    $matrix = [
                        'Ngực' => [1, 0, 0, 1, 0, 0, 0], 'Lưng' => [1, 0, 0, 1, 0, 0, 0], 'Vai' => [1, 0, 0, 1, 0, 0, 0],
                        'Tay'  => [1, 0, 0, 1, 0, 0, 0], 'Chân' => [0, 1, 0, 0, 1, 0, 0], 'Bụng' => [0, 1, 0, 0, 1, 0, 0],
                    ];
                    ?>
                    <div class="mx" role="img" aria-label="Mỗi nhóm cơ được tập 2 lần mỗi tuần">
                        <div class="mx-row mx-row--head"><span></span><?php foreach ($days as $d): ?><span><?= $d ?></span><?php endforeach; ?><span></span></div>
                        <?php foreach ($matrix as $muscle => $cells): ?>
                            <div class="mx-row"><b><?= $muscle ?></b><?php foreach ($cells as $c): ?><span class="mx-cell<?= $c ? ' is-on' : '' ?>"></span><?php endforeach; ?><span class="mx-sum"><?= array_sum($cells) ?>×</span></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pn pn--nut">
                    <div class="pn-head"><h2>Dinh dưỡng và cân nặng</h2><span class="pn-meta">Hôm nay</span></div>
                    <div class="nut">
                        <div class="nut-ring"><div><b>2.650</b><small>kcal</small></div></div>
                        <ul class="nut-legend">
                            <li><i style="background:#ff5a1f"></i>Đạm <b>165 g</b></li>
                            <li><i style="background:#ffb27a"></i>Tinh bột <b>330 g</b></li>
                            <li><i style="background:#5b7cfa"></i>Béo <b>74 g</b></li>
                        </ul>
                    </div>
                    <div class="spark">
                        <div class="spark-top"><span>Cân nặng 8 tuần</span><b>68,0 → 65,4 kg</b></div>
                        <svg viewBox="0 0 240 56" preserveAspectRatio="none" aria-hidden="true">
                            <polyline points="0,8 34,12 68,20 102,22 136,30 170,36 204,42 240,48" fill="none" stroke="#ff5a1f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                        </svg>
                    </div>
                </div>

            </div>
        </div>

        <div class="facts" aria-label="Số liệu về GymSmart">
            <div><b><?= $exerciseCount > 0 ? (int)$exerciseCount : 40 ?></b><span>bài tập, mỗi bài một kiểu chuyển động</span></div>
            <div><b>7</b><span>nhóm cơ, từ ngực đến toàn thân</span></div>
            <div><b>2×</b><span>mỗi nhóm cơ chính tập ít nhất 2 lần mỗi tuần</span></div>
            <div><b><?= $articleCount > 0 ? (int)$articleCount : 14 ?></b><span>bài viết có dẫn nghiên cứu</span></div>
        </div>
    </div></div>
</section>

<section class="sec">
    <div class="hm-wrap">
        <div class="sec-head">
            <h2>Mọi thứ bạn cần để tập đúng cách</h2>
            <p>Hồ sơ của bạn quyết định lịch tập, lịch tập tạo ra buổi ghi log, log tạo ra biểu đồ tiến trình.</p>
        </div>
        <div class="bento">
            <?php
            $cards = $features;
            $cards[] = ['icon' => 'book', 'title' => 'Bài viết có nguồn nghiên cứu', 'href' => '/articles.php',
                'text' => 'Dinh dưỡng, sức khỏe và tập luyện, nêu rõ mức độ bằng chứng. Chỉ để tham khảo.', 'ready' => true];
            foreach ($cards as $i => $f):
                $tag = $f['ready'] ? 'a' : 'div';
                $wide = $i === 1 ? ' bento-card--wide' : '';
            ?>
                <<?= $tag ?> class="bento-card<?= $wide ?>" <?= $f['ready'] ? 'href="' . htmlspecialchars($f['href']) . '"' : '' ?>>
                    <span class="bento-icon"><?= hmIcon($f['icon'], 26) ?></span>
                    <h3><?= htmlspecialchars($f['title']) ?></h3>
                    <p><?= htmlspecialchars($f['text']) ?></p>
                    <span class="bento-foot"><?= $f['ready'] ? 'Xem chi tiết' : 'Sắp có' ?></span>
                </<?= $tag /* audit:ok $tag chỉ là 'a' hoặc 'div' do mình đặt */ ?>>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($articleTeasers)): ?>
<section class="sec sec--dark">
    <div class="hm-wrap">
        <div class="sec-head">
            <h2>Đọc trước khi tập</h2>
            <p>Mỗi bài nêu rõ mức độ bằng chứng và dẫn nguồn nghiên cứu. Chỉ để tham khảo, không thay tư vấn y tế.</p>
        </div>
        <div class="arts">
            <?php foreach ($articleTeasers as $s => $a): ?>
                <a class="art" href="/article.php?slug=<?= htmlspecialchars(rawurlencode($s)) ?>">
                    <span class="art-img"><?php if ($a['image'] !== ''): ?><img src="<?= htmlspecialchars($a['image']) ?>" alt="" loading="lazy"><?php endif; ?></span>
                    <span class="art-body">
                        <b><?= htmlspecialchars($a['title']) ?></b>
                        <small><?= htmlspecialchars($a['summary']) ?></small>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="sec-more"><a class="hm-btn hm-btn--ghost" href="/articles.php">Xem tất cả bài viết</a></div>
    </div>
</section>
<?php endif; ?>

<section class="sec">
    <div class="hm-wrap">
        <div class="sec-head"><h2>Bắt đầu chỉ với 3 bước</h2></div>
        <ol class="steps">
            <li><h3>Tạo tài khoản và hồ sơ</h3><p>Nhập tuổi, chiều cao, cân nặng, trình độ và mục tiêu. Chỉ mất vài phút.</p></li>
            <li><h3>Nhận kế hoạch của bạn</h3><p>Hệ thống gợi ý lịch tập và mục tiêu dinh dưỡng phù hợp với hồ sơ của bạn.</p></li>
            <li><h3>Tập, ghi lại, tiến bộ</h3><p>Làm theo video hướng dẫn, ghi lại buổi tập và xem mình tiến bộ qua từng tuần.</p></li>
        </ol>
    </div>
</section>

<section class="sec sec--tight">
    <div class="hm-wrap">
        <div class="final">
            <h2>Sẵn sàng tập nặng hơn, thông minh hơn?</h2>
            <p>Tạo tài khoản miễn phí và có kế hoạch của riêng bạn ngay hôm nay.</p>
            <a class="hm-btn hm-btn--solid" href="/register.php">Tạo tài khoản miễn phí</a>
        </div>
    </div>
</section>

<script>
// Ô "Hôm nay" minh họa: bấm một hiệp để đánh dấu xong hoặc bỏ đánh dấu, số hiệp và khối lượng tự cập nhật.
(function () {
    var sets = document.querySelectorAll('.lg-set');
    var doneEl = document.getElementById('lg-done');
    var volEl = document.getElementById('lg-vol');
    if (!sets.length || !doneEl || !volEl) { return; }
    function update() {
        var n = 0, vol = 0;
        sets.forEach(function (b) {
            if (b.classList.contains('is-on')) { n++; vol += Number(b.dataset.vol) || 0; }
        });
        doneEl.textContent = n;
        volEl.textContent = vol.toLocaleString('vi-VN');
    }
    sets.forEach(function (b) {
        b.addEventListener('click', function () {
            var on = b.classList.toggle('is-on');
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            update();
        });
    });
    update();
})();
</script>

<?php else: ?>

<!-- ================= NGƯỜI DÙNG ĐÃ ĐĂNG NHẬP ================= -->
<section class="hm-hero hm-hero--user">
    <div class="hm-wrap hm-hero__in">
        <div>
            <span class="hm-chip"><i></i> Train Harder &amp; Smarter</span>
            <h1 class="hm-hello">Chào <em><?= htmlspecialchars($firstName) ?></em>,<br>hôm nay tập gì nào?</h1>
            <p class="hm-lead">Chọn một mục bên dưới để tiếp tục, hoặc xem vài bài tập gợi ý dành cho bạn.</p>
            <div class="hm-cta">
                <a class="hm-btn hm-btn--solid" href="/exercises.php">Xem thư viện bài tập</a>
                <a class="hm-btn hm-btn--ghost" href="/profile.php">Cập nhật hồ sơ</a>
            </div>
        </div>

        <div class="hm-prog">
            <h3>Hồ sơ của bạn</h3>
            <div class="hm-prog__num"><?= (int)$doneCount ?><small> / <?= (int)$profileTotal ?> mục</small></div>
            <div class="hm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$percent ?>"><i style="width:<?= (int)$percent ?>%"></i></div>
            <?php if (!empty($missingFields)): ?>
                <p>Còn thiếu: <?= htmlspecialchars(implode(', ', $missingFields)) ?>. Cần đủ thông tin này để tính TDEE/Macro và gợi ý lịch tập.</p>
                <a class="hm-btn hm-btn--solid" href="/profile.php">Hoàn thiện hồ sơ</a>
            <?php else: ?>
                <p>Hồ sơ đã đầy đủ. Bạn sẵn sàng nhận lịch tập và mục tiêu dinh dưỡng cá nhân hóa.</p>
                <a class="hm-btn hm-btn--ghost" href="/profile.php">Xem hồ sơ</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="hm-wrap">
    <div class="hm-tiles">
        <?php
        $tiles = [
            ['dumbbell', 'Bài tập &amp; Video', 'Thư viện kỹ thuật', '/exercises.php', is_file(__DIR__ . '/exercises.php')],
            ['calendar', 'Lịch tập', 'Kế hoạch theo tuần', '/workout-plan.php', is_file(__DIR__ . '/workout-plan.php')],
            ['flame',    'TDEE &amp; Macro', 'Dinh dưỡng theo mục tiêu', '/nutrition.php', is_file(__DIR__ . '/nutrition.php')],
            ['chart',    'Tiến trình', 'Biểu đồ thay đổi', '/progress.php', is_file(__DIR__ . '/progress.php')],
            ['user',     'Hồ sơ', 'Chỉ số và mục tiêu', '/profile.php', true],
        ];
        foreach ($tiles as [$icon, $label, $hint, $href, $ready]):
            $tag = $ready ? 'a' : 'div';
        ?>
            <<?= $tag ?> class="hm-tile <?= $ready ? '' : 'is-soon' ?>" <?= $ready ? 'href="' . htmlspecialchars($href) . '"' : '' ?>>
                <span class="hm-icon"><?= hmIcon($icon, 22) ?></span>
                <span><b><?= $label ?></b><small><?= $ready ? htmlspecialchars($hint) : 'Sắp có' ?></small></span>
            </<?= $tag /* audit:ok $tag chỉ là 'a' hoặc 'div' do mình đặt */ ?>>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($recommended)): ?>
<section class="hm-section">
    <div class="hm-wrap">
        <div class="hm-head" style="margin-bottom: 28px;">
            <div class="hm-eyebrow">Gợi ý cho bạn</div>
            <h2 class="hm-title">
                <?= $experience !== null /* audit:ok các nhánh bên dưới đã htmlspecialchars */
                    ? 'Bài tập phù hợp trình độ ' . htmlspecialchars($experienceLabels[$experience])
                    : 'Vài bài tập để bắt đầu' ?>
            </h2>
        </div>
        <div class="hm-recs">
            <?php foreach ($recommended as $ex): ?>
                <a class="hm-rec" href="/exercise-detail.php?id=<?= (int)$ex['id'] ?>">
                    <?php if (!empty($ex['image_url'])): ?>
                        <img class="hm-rec__img" src="<?= htmlspecialchars($ex['image_url']) ?>" alt="<?= htmlspecialchars($ex['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="hm-rec__ph">Chưa có ảnh</div>
                    <?php endif; ?>
                    <div class="hm-rec__body">
                        <h3><?= htmlspecialchars($ex['name']) ?></h3>
                        <div class="hm-meta">
                            <span><?= htmlspecialchars($muscleLabels[$ex['muscle_group']] ?? $ex['muscle_group']) ?></span>
                            <span><?= htmlspecialchars($typeLabels[$ex['type']] ?? $ex['type']) ?></span>
                            <span class="lv-<?= htmlspecialchars($ex['difficulty']) ?>"><?= htmlspecialchars($levelLabels[$ex['difficulty']] ?? $ex['difficulty']) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <a class="hm-more" href="/exercises.php">Xem tất cả bài tập &rarr;</a>
    </div>
</section>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
