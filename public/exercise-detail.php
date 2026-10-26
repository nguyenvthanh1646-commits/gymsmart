<?php
// public/exercise-detail.php - Trang chi tiết bài tập (D1)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/video.php';

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
$difficultyLabels = [
    'BEGINNER' => 'Người mới', 'INTERMEDIATE' => 'Trung cấp', 'ADVANCED' => 'Nâng cao',
];
$typeLabels = ['RESISTANCE' => 'Tập kháng lực', 'CARDIO' => 'Cardio'];


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
        'SELECT id, name, difficulty FROM exercises
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

<main class="container">
<?php if (!$exercise): ?>

    <div class="card">
        <h1>Không tìm thấy bài tập</h1>
        <p>Bài tập bạn tìm không tồn tại hoặc đường dẫn không hợp lệ.</p>
        <a class="btn" href="exercises.php">Quay lại thư viện bài tập</a>
    </div>

<?php else: ?>

    <div class="card">
        <h1><?= htmlspecialchars($exercise['name']) ?></h1>

        <p>
            <?php // Dùng ?? để nếu giá trị lạ thì hiện nguyên giá trị, vẫn qua htmlspecialchars ?>
            <strong>Nhóm cơ:</strong>
            <?= htmlspecialchars($muscleLabels[$exercise['muscle_group']] ?? $exercise['muscle_group']) ?>
            |
            <strong>Loại:</strong>
            <?= htmlspecialchars($typeLabels[$exercise['type']] ?? $exercise['type']) ?>
            |
            <strong>Độ khó:</strong>
            <?= htmlspecialchars($difficultyLabels[$exercise['difficulty']] ?? $exercise['difficulty']) ?>
        </p>

        <?php if (!empty($exercise['image_url'])): ?>
            <!-- basename() chỉ giữ tên file, tránh đường dẫn lạ như ../ -->
            <img src="img/exercises/<?= htmlspecialchars(basename($exercise['image_url'])) ?>"
                 alt="<?= htmlspecialchars($exercise['name']) ?>"
                 style="max-width:100%;">
        <?php endif; ?>

        <h2>Mô tả</h2>
        <!-- nl2br đổi xuống dòng thành <br>; phải escape trước rồi mới nl2br -->
        <p><?= nl2br(htmlspecialchars($exercise['description'] ?? '')) ?></p>

        <?php if ($steps): ?>
            <h2>Cách thực hiện</h2>
            <ol>
                <?php foreach ($steps as $step): ?>
                    <li><?= htmlspecialchars($step) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($embedUrl !== null): // chỉ hiện khung video khi có video hợp lệ ?>
            <h2>Video hướng dẫn</h2>
            <div style="position:relative; padding-top:56.25%; margin-bottom:24px;">
                <iframe src="<?= htmlspecialchars($embedUrl) ?>"
                        title="Video hướng dẫn: <?= htmlspecialchars($exercise['name']) ?>"
                        style="position:absolute; inset:0; width:100%; height:100%; border:0;"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen loading="lazy"></iframe>
            </div>
        <?php endif; ?>

        <?php if ($mistakes): ?>
            <h2>Lỗi thường gặp</h2>
            <ul>
                <?php foreach ($mistakes as $m): ?>
                    <li><?= htmlspecialchars($m) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($related): // chỉ hiện khi có bài gợi ý ?>
            <h2>Bài tập cùng nhóm cơ</h2>
            <ul>
                <?php foreach ($related as $r): ?>
                    <li>
                        <!-- (int) ép id thành số nguyên cho an toàn -->
                        <a href="exercise-detail.php?id=<?= (int)$r['id'] ?>">
                            <?= htmlspecialchars($r['name']) ?>
                        </a>
                        (<?= htmlspecialchars($difficultyLabels[$r['difficulty']] ?? $r['difficulty']) ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <a class="btn" href="exercises.php">Quay lại thư viện</a>
    </div>

<?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>