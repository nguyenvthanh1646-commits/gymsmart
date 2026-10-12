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

$pageTitle = 'Trang chủ';
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/home.css">

<?php if (!$loggedIn): ?>

<!-- ================= KHÁCH CHƯA ĐĂNG NHẬP ================= -->
<section class="hm-hero">
    <div class="hm-wrap hm-hero__in">
        <div>
            <span class="hm-chip"><i></i> Nền tảng tập luyện thông minh</span>
            <h1 class="hm-h1">Train Harder<br>&amp; <em>Smarter</em></h1>
            <p class="hm-lead">
                Thư viện bài tập có video chuẩn kỹ thuật, lịch tập cá nhân hóa theo trình độ,
                tính TDEE và macro khoa học, theo dõi tiến trình, tất cả trong một nơi.
            </p>
            <div class="hm-cta">
                <a class="hm-btn hm-btn--solid" href="/register.php">Bắt đầu miễn phí <?= hmIcon('bolt', 18) ?></a>
                <a class="hm-btn hm-btn--ghost" href="/login.php">Tôi đã có tài khoản</a>
            </div>
            <ul class="hm-points">
                <li><?= hmIcon('check', 18) ?> Video hướng dẫn từng bài</li>
                <li><?= hmIcon('check', 18) ?> Cá nhân hóa theo hồ sơ</li>
                <li><?= hmIcon('check', 18) ?> Công thức Mifflin-St Jeor</li>
            </ul>
        </div>

        <div class="hm-visual" aria-hidden="true">
            <span class="hm-note">Minh họa giao diện</span>
            <div class="hm-card hm-card--plan">
                <h3>Kế hoạch hôm nay · Ngực &amp; tay</h3>
                <div class="hm-row"><span class="hm-dot"><?= hmIcon('dumbbell', 18) ?></span><div><b>Đẩy ngực tạ đòn</b><small>Ngực · Kháng lực</small></div><span class="hm-tag">4 × 8</span></div>
                <div class="hm-row"><span class="hm-dot"><?= hmIcon('dumbbell', 18) ?></span><div><b>Đẩy tạ đôi ghế dốc</b><small>Ngực trên · Kháng lực</small></div><span class="hm-tag">3 × 10</span></div>
                <div class="hm-row"><span class="hm-dot"><?= hmIcon('dumbbell', 18) ?></span><div><b>Gập bụng</b><small>Bụng · Kháng lực</small></div><span class="hm-tag">3 × 15</span></div>
            </div>
            <div class="hm-card hm-card--macro">
                <h3>Mục tiêu dinh dưỡng</h3>
                <div class="hm-ringbox">
                    <div class="hm-ring"><span><div>2.650<small>KCAL</small></div></span></div>
                    <div class="hm-macros">
                        <div>Đạm 165 g<i style="--w:78%;--c:#ff5a1f"></i></div>
                        <div>Tinh bột 300 g<i style="--w:92%;--c:#ffa36b"></i></div>
                        <div>Béo 70 g<i style="--w:55%;--c:#7c8cff"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Dải số liệu nổi lên trên mép dưới của hero -->
<div class="hm-statsbar"><div class="hm-wrap">
    <div class="hm-stats">
        <div><b>7</b><span>nhóm cơ chính</span></div>
        <div><b>3</b><span>mức độ, từ mới tập đến nâng cao</span></div>
        <div><b>Video</b><span>hướng dẫn từng bài tập</span></div>
        <div><b>0đ</b><span>miễn phí hoàn toàn</span></div>
    </div>
</div></div>

<section class="hm-section hm-section--after-stats">
    <div class="hm-wrap">
        <div class="hm-head">
            <div class="hm-eyebrow">Tính năng</div>
            <h2 class="hm-title">Mọi thứ bạn cần để tập đúng cách</h2>
            <p class="hm-sub">Từ học kỹ thuật, lên lịch, ăn đủ chất đến theo dõi kết quả, một hành trình liền mạch.</p>
        </div>
        <div class="hm-grid4">
            <?php foreach ($features as $f): ?>
                <?php $tag = $f['ready'] ? 'a' : 'div'; ?>
                <<?= $tag ?> class="hm-feature" <?= $f['ready'] ? 'href="' . htmlspecialchars($f['href']) . '"' : '' ?>>
                    <span class="hm-icon"><?= hmIcon($f['icon'], 26) ?></span>
                    <h3><?= htmlspecialchars($f['title']) ?></h3>
                    <p><?= htmlspecialchars($f['text']) ?></p>
                    <span class="hm-badge <?= $f['ready'] ? 'hm-badge--ready' : 'hm-badge--soon' ?>">
                        <?= $f['ready'] ? 'Sẵn sàng' : 'Đang phát triển' ?>
                    </span>
                </<?= $tag /* audit:ok $tag chỉ là 'a' hoặc 'div' do mình đặt */ ?>>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="hm-section hm-section--soft">
    <div class="hm-wrap">
        <div class="hm-head">
            <div class="hm-eyebrow">Cách hoạt động</div>
            <h2 class="hm-title">Bắt đầu chỉ với 3 bước</h2>
        </div>
        <div class="hm-steps">
            <div class="hm-step"><h3>Tạo tài khoản và hồ sơ</h3><p>Nhập tuổi, chiều cao, cân nặng, trình độ và mục tiêu. Chỉ mất vài phút.</p></div>
            <div class="hm-step"><h3>Nhận kế hoạch của bạn</h3><p>Hệ thống gợi ý lịch tập và mục tiêu dinh dưỡng phù hợp với hồ sơ của bạn.</p></div>
            <div class="hm-step"><h3>Tập, ghi nhận, tiến bộ</h3><p>Làm theo video hướng dẫn, ghi lại buổi tập và xem mình tiến bộ qua từng tuần.</p></div>
        </div>
    </div>
</section>

<section class="hm-band">
    <?= gsLogo('light', true, 52) ?>
    <h2>Sẵn sàng tập nặng hơn, thông minh hơn?</h2>
    <p>Tạo tài khoản miễn phí và có kế hoạch của riêng bạn ngay hôm nay.</p>
    <a class="hm-btn hm-btn--solid" href="/register.php">Đăng ký miễn phí</a>
</section>

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
