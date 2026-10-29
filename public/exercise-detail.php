<?php
// public/exercise-detail.php - Trang chi tiết bài tập (D1)
require_once __DIR__ . '/includes/auth.php';
requireLogin(); // giống exercises.php: xem thư viện bài tập cần đăng nhập
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/video.php';
require_once __DIR__ . '/includes/functions.php'; // exerciseImageSrc()

$exercise = false; // mặc định: chưa có bài tập nào

// Lấy id từ URL (?id=...) và kiểm tra: phải là số nguyên >= 1
$id = filter_var(
    $_GET['id'] ?? '',                         // nếu không có ?id thì dùng chuỗi rỗng
    FILTER_VALIDATE_INT,                       // yêu cầu giá trị là số nguyên
    ['options' => ['min_range' => 1]]          // và phải từ 1 trở lên
);
// $id là số nguyên nếu hợp lệ, hoặc false nếu không hợp lệ


// Nhãn hiển thị tiếng Việt cho các giá trị quy ước (cũng là whitelist)
$muscleLabels = [
    'CHEST' => 'Ngực', 'BACK' => 'Lưng', 'SHOULDERS' => 'Vai',
    'LEGS' => 'Chân', 'ARMS' => 'Tay', 'ABS' => 'Bụng', 'FULL_BODY' => 'Toàn thân',
];
// Cùng bộ nhãn với thư viện (exercises.php) để người dùng không thấy 2 cách gọi cho 1 mức độ khó
$difficultyLabels = [
    'BEGINNER' => 'Dễ', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Khó',
];
$typeLabels = ['RESISTANCE' => 'Kháng lực', 'CARDIO' => 'Cardio'];


if ($id !== false) {
    // Prepared statement: giá trị id được gửi tách riêng khỏi câu SQL
    $stmt = $pdo->prepare('SELECT * FROM exercises WHERE id = ?');
    $stmt->execute([$id]);
    $exercise = $stmt->fetch(PDO::FETCH_ASSOC); // false nếu không có dòng nào
}

// Link nhúng video: nhận link YouTube đầy đủ hoặc mã 11 ký tự (xem includes/video.php);
// sai định dạng thì coi như không có video
$embedUrl = $exercise ? youtubeEmbedUrl($exercise['video_url'] ?? null) : null;

// Tách văn bản nhiều dòng thành mảng các bước, bỏ dòng trống
function splitLines(?string $text): array {
    $lines = preg_split('/\r\n|\r|\n/', (string)$text);
    return array_values(array_filter(array_map('trim', $lines), 'strlen'));
}
$steps    = $exercise ? splitLines($exercise['instructions'] ?? '') : [];
$mistakes = $exercise ? splitLines($exercise['common_mistakes'] ?? '') : [];

// Gợi ý 3 bài cùng nhóm cơ (chỉ khi bài hiện tại tồn tại)
$related = [];
if ($exercise) {
    $stmt = $pdo->prepare(
        'SELECT id, name, type, difficulty, image_url FROM exercises
         WHERE muscle_group = ? AND id <> ?
         ORDER BY RAND() LIMIT 3'
    );
    $stmt->execute([$exercise['muscle_group'], $exercise['id']]);
    $related = $stmt->fetchAll(PDO::FETCH_ASSOC); // mảng rỗng nếu không có bài nào
}

if (!$exercise) {
    http_response_code(404); // báo cho trình duyệt là không tìm thấy
}

$pageTitle = $exercise ? $exercise['name'] : 'Không tìm thấy bài tập';
include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="/css/exercise-detail.css">

<main class="container">
<?php if (!$exercise): ?>

    <div class="card">
        <h1>Không tìm thấy bài tập</h1>
        <p>Bài tập bạn tìm không tồn tại hoặc đường dẫn không hợp lệ.</p>
        <a class="btn" href="exercises.php">Quay lại thư viện bài tập</a>
    </div>

<?php else: ?>
    <?php
    // Dùng ?? để nếu giá trị lạ thì hiện nguyên giá trị, vẫn qua htmlspecialchars khi in
    $muscle = $muscleLabels[$exercise['muscle_group']] ?? $exercise['muscle_group'];
    $imgSrc = exerciseImageSrc($exercise['image_url']); // chỉ nhận tên file hợp lệ, tránh đường dẫn lạ như ../
    ?>
    <nav class="exd-crumb" aria-label="Đường dẫn">
        <a href="exercises.php">Thư viện bài tập</a>
        <span aria-hidden="true">›</span>
        <!-- urlencode giữ giá trị nhóm cơ an toàn trong đường dẫn lọc -->
        <a href="exercises.php?muscle_group=<?= htmlspecialchars(urlencode($exercise['muscle_group'])) ?>"><?= htmlspecialchars($muscle) ?></a>
    </nav>

    <div class="pg-head exd-head">
        <div>
            <h1><?= htmlspecialchars($exercise['name']) ?></h1>
            <div class="ex-meta">
                <span class="tag"><?= htmlspecialchars($muscle) ?></span>
                <span class="tag"><?= htmlspecialchars($typeLabels[$exercise['type']] ?? $exercise['type']) ?></span>
                <span class="tag tag--<?= htmlspecialchars($exercise['difficulty']) ?>"><?= htmlspecialchars($difficultyLabels[$exercise['difficulty']] ?? $exercise['difficulty']) ?></span>
            </div>
        </div>
    </div>

    <div class="exd">
        <div class="exd__main">
            <?php if ($embedUrl !== null): // chỉ hiện khung video khi có video hợp lệ ?>
                <!-- Video đặt đầu trang (xem trước khi đọc chữ). Ảnh bài tập nằm phía sau làm ảnh chờ trong lúc YouTube tải -->
                <div class="exd__video">
                    <?php if ($imgSrc !== null): ?>
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" aria-hidden="true">
                    <?php endif; ?>
                    <iframe src="<?= htmlspecialchars($embedUrl) ?>"
                            title="Video hướng dẫn: <?= htmlspecialchars($exercise['name']) ?>"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen loading="lazy"></iframe>
                </div>
            <?php elseif ($imgSrc !== null): ?>
                <img class="exd__img" src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($exercise['name']) ?>">
            <?php endif; ?>

            <div class="card exd__body">
                <h2>Mô tả</h2>
                <!-- nl2br đổi xuống dòng thành <br>; phải escape trước rồi mới nl2br -->
                <p><?= nl2br(htmlspecialchars($exercise['description'] ?? '')) ?></p>

                <?php if ($steps): ?>
                    <h2>Cách thực hiện</h2>
                    <ol class="exd__steps">
                        <?php foreach ($steps as $step): ?>
                            <li><?= htmlspecialchars($step) ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <?php if ($mistakes): ?>
                    <h2>Lỗi thường gặp</h2>
                    <ul class="exd__mistakes">
                        <?php foreach ($mistakes as $m): ?>
                            <li><?= htmlspecialchars($m) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($embedUrl !== null && $imgSrc !== null): // có video thì ảnh minh họa nhóm cơ đặt ở cột phụ ?>
            <aside class="exd__side">
                <figure class="card exd__fig">
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($exercise['name']) ?>" loading="lazy">
                    <figcaption>Ảnh minh họa tư thế và nhóm cơ</figcaption>
                </figure>
            </aside>
        <?php endif; ?>
    </div>

    <?php if ($related): // chỉ hiện khi có bài gợi ý ?>
        <section class="exd-related" aria-labelledby="exd-related-title">
            <h2 id="exd-related-title">Bài tập cùng nhóm cơ</h2>
            <!-- Cùng kiểu thẻ với thư viện bài tập (class ex-card trong ui.css) -->
            <div class="ex-grid">
                <?php foreach ($related as $r): ?>
                    <!-- (int) ép id thành số nguyên cho an toàn -->
                    <a class="ex-card" href="exercise-detail.php?id=<?= (int)$r['id'] ?>">
                        <div class="ex-card__media">
                            <?php if (($relImg = exerciseImageSrc($r['image_url'])) !== null): ?>
                                <img src="<?= htmlspecialchars($relImg) ?>" alt="<?= htmlspecialchars($r['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="ex-card__ph">Chưa có ảnh</div>
                            <?php endif; ?>
                            <span class="tag tag--<?= htmlspecialchars($r['difficulty']) ?> ex-card__level"><?= htmlspecialchars($difficultyLabels[$r['difficulty']] ?? $r['difficulty']) ?></span>
                        </div>
                        <div class="ex-card__body">
                            <h3><?= htmlspecialchars($r['name']) ?></h3>
                            <div class="ex-meta">
                                <span class="tag"><?= htmlspecialchars($typeLabels[$r['type']] ?? $r['type']) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <a class="btn btn--ghost exd-back" href="exercises.php">&larr; Quay lại thư viện</a>

<?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>