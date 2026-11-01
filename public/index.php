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
require_once __DIR__ . '/includes/functions.php'; // exerciseImageSrc()

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
        'meal'     => '<path d="M7 3v7a2.5 2.5 0 005 0V3M9.5 3v18M17 3c-1.7 1-2.5 3.2-2.5 6 0 2 1 3.5 2.5 3.5V21"/>',
        'chat'     => '<path d="M4 5.5h16v11H10l-4.5 3.5V16.5H4z"/><path d="M8.5 11h.01M12 11h.01M15.5 11h.01"/>',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

// 7 tính năng ở trang khách: [biểu tượng, tiêu đề, mô tả]
$guestFeatures = [
    ['calendar', 'Lịch tập cá nhân hóa', 'Gợi ý số buổi, bài tập và số hiệp theo trình độ, mục tiêu và số ngày bạn có thể tập.'],
    ['play',     'Thư viện bài tập có ảnh, video', 'Xem kỹ thuật qua video, đọc hướng dẫn từng bước, lọc theo nhóm cơ, loại và độ khó.'],
    ['flame',    'Tính TDEE và macro', 'Tính nhu cầu năng lượng và lượng đạm, tinh bột, béo theo chỉ số và mục tiêu của bạn.'],
    ['meal',     'Lịch ăn gợi ý', 'Gợi ý chia bữa trong ngày theo mục tiêu dinh dưỡng và kiểu ăn bạn chọn.'],
    ['chart',    'Nhật ký và biểu đồ', 'Ghi lại buổi tập, cân nặng, số đo và xem biểu đồ thay đổi theo thời gian.'],
    ['book',     'Bài viết có dẫn nghiên cứu', 'Dinh dưỡng, sức khỏe và tập luyện, nêu rõ nguồn tham khảo.'],
    ['chat',     'Chatbot AI', 'Hỏi nhanh về bài tập và tập luyện, có thể dựa trên hồ sơ của bạn nếu bạn cho phép.'],
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

// Bài nền tảng (mustRead) dùng cho cả khách và người đã đăng nhập (mục "Đọc trước khi tập")
require_once __DIR__ . '/includes/articles.php';
$allArticles = loadArticles();
$mustReadArticles = array_filter($allArticles, fn($a) => $a['mustRead']);

// Số liệu thật và nội dung xem trước cho trang khách. Không có số viết tay: thiếu dữ liệu thì trang ẩn câu đó.
$guestStats = ['exercises' => 0, 'muscleGroups' => 0, 'sourcedArticles' => 0];
$previewExercises = [];
$articleTeasers = [];
if (!$loggedIn) {
    $guestStats = guestExerciseStats($pdo);
    $previewExercises = guestPreviewExercises($pdo);
    // Chỉ đếm bài có ghi nguồn thật (một số bài không có mục nguồn)
    $guestStats['sourcedArticles'] = count(array_filter($allArticles, fn($a) => !empty($a['sources'])));
    // Mục có tiêu đề "Đọc trước khi tập" nên đặt các bài nền tảng lên trước, còn chỗ mới thêm bài khác
    $articleTeasers = array_slice($mustReadArticles, 0, 3, true);
    foreach (['bao-nhieu-buoc-moi-ngay', 'protein-bao-nhieu-la-du', 'ta-nang-hay-ta-nhe'] as $s) {
        if (count($articleTeasers) >= 3) { break; }
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
$pageCss = $loggedIn ? [] : ['/css/home-guest.css'];
include __DIR__ . '/includes/header.php';
?>
<?php if (!$loggedIn): ?>

<!-- ================= KHÁCH CHƯA ĐĂNG NHẬP ================= -->
<?php include __DIR__ . '/includes/home-guest.php'; ?>

<?php else: ?>

<!-- ================= NGƯỜI DÙNG ĐÃ ĐĂNG NHẬP ================= -->
<link rel="stylesheet" href="/css/home.css">
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
            ['book',     'Bài viết', 'Kiến thức có nguồn', '/articles.php', is_file(__DIR__ . '/articles.php')],
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

<?php if (!empty($mustReadArticles)): ?>
<div class="hm-wrap">
    <!-- Nhắc đọc các bài nền tảng (gồng core, thở cơ hoành) trước khi bắt đầu tập -->
    <aside class="hm-must" aria-labelledby="hm-must-title">
        <div>
            <span class="hm-eyebrow">Đọc trước khi tập</span>
            <h2 id="hm-must-title">Học <?= count($mustReadArticles) ?> kỹ thuật nền tảng trước buổi tập đầu tiên</h2>
        </div>
        <ul>
            <?php foreach ($mustReadArticles as $s => $a): ?>
                <li><a href="/article.php?slug=<?= htmlspecialchars(rawurlencode($s)) ?>"><?= htmlspecialchars($a['title']) ?> <span aria-hidden="true">&rarr;</span></a></li>
            <?php endforeach; ?>
        </ul>
    </aside>
</div>
<?php endif; ?>

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
                    <?php if (($imgSrc = exerciseImageSrc($ex['image_url'])) !== null): ?>
                        <img class="hm-rec__img" src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($ex['name']) ?>" loading="lazy">
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
