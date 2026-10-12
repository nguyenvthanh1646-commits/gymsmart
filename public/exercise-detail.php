<?php
// public/exercise-detail.php - Trang chi tiết bài tập (D1)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

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

$exercise = false;

// Kiểm tra id: phải là số nguyên >= 1, nếu không thì trả về false
$id = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id !== false) {
    // Prepared statement: giá trị id được gửi tách riêng khỏi câu SQL
    $stmt = $pdo->prepare('SELECT * FROM exercises WHERE id = ?');
    $stmt->execute([$id]);
    $exercise = $stmt->fetch(PDO::FETCH_ASSOC); // false nếu không có dòng nào
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
            <img src="images/exercises/<?= htmlspecialchars(basename($exercise['image_url'])) ?>"
                 alt="<?= htmlspecialchars($exercise['name']) ?>"
                 style="max-width:100%;">
        <?php endif; ?>

        <h2>Mô tả</h2>
        <!-- nl2br đổi xuống dòng thành <br>; phải escape trước rồi mới nl2br -->
        <p><?= nl2br(htmlspecialchars($exercise['description'] ?? '')) ?></p>

        <a class="btn" href="exercises.php">Quay lại thư viện</a>
    </div>

<?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>