<?php
/**
 * exercises.php — thư viện bài tập (việc B1 + B2).
 *
 * B1: lấy bài tập từ bảng exercises và hiển thị bằng vòng lặp foreach.
 * B2: mỗi bài là một thẻ (ảnh, tên, nhóm cơ, loại, độ khó), bấm vào để
 *     sang trang chi tiết exercise-detail.php?id=... (do D làm).
 *
 * Các việc B3 → B5 (lọc nhóm cơ, tìm theo tên, lọc loại/độ khó) sẽ được
 * thêm vào trang này sau, ngay phía trên câu SELECT.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin(); // theo API_SPEC: xem thư viện bài tập cần đăng nhập

// Bảng đổi giá trị trong DB (tiếng Anh, viết hoa) sang chữ hiển thị tiếng Việt.
// Khóa bên trái PHẢI trùng đúng giá trị lưu trong cột của bảng exercises.
$muscleLabels = [
    'CHEST'     => 'Ngực',
    'BACK'      => 'Lưng',
    'SHOULDERS' => 'Vai',
    'LEGS'      => 'Chân',
    'ARMS'      => 'Tay',
    'ABS'       => 'Bụng',
    'FULL_BODY' => 'Toàn thân',
];
$typeLabels = [
    'RESISTANCE' => 'Kháng lực',
    'CARDIO'     => 'Cardio',
];
$difficultyLabels = [
    'BEGINNER'     => 'Dễ',
    'INTERMEDIATE' => 'Trung bình',
    'ADVANCED'     => 'Khó',
];

// Câu SELECT này KHÔNG chứa dữ liệu từ người dùng nên dùng query() là đủ.
// Từ việc B3 trở đi, khi có $_GET (người dùng nhập) thì BẮT BUỘC chuyển sang
// prepare() + execute() để chống SQL Injection.
$stmt = $pdo->query(
    "SELECT id, name, type, muscle_group, difficulty, image_url
     FROM exercises
     ORDER BY name"
);
$exercises = $stmt->fetchAll(); // mảng chứa mọi dòng, mỗi dòng là một mảng

$pageTitle = 'Bài tập & Video';
include __DIR__ . '/includes/header.php';
?>

<h1>Thư viện bài tập</h1>
<p>Có <?= count($exercises) ?> bài tập.</p>

<?php if (empty($exercises)): ?>
    <p>Chưa có bài tập nào.</p>
<?php else: ?>
    <!-- Lưới tạm bằng style trực tiếp. E sẽ thay bằng class riêng ở việc E2. -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem;">
        <?php foreach ($exercises as $ex): ?>
            <?php
            // ?? '...' : nếu giá trị trong DB không có trong bảng đổi nhãn thì in luôn giá trị gốc
            $muscle     = $muscleLabels[$ex['muscle_group']] ?? $ex['muscle_group'];
            $type       = $typeLabels[$ex['type']] ?? $ex['type'];
            $difficulty = $difficultyLabels[$ex['difficulty']] ?? $ex['difficulty'];
            ?>
            <!-- (int) ép id thành số nguyên để không chèn được ký tự lạ vào đường dẫn -->
            <a href="/exercise-detail.php?id=<?= (int)$ex['id'] ?>"
               style="text-decoration: none; color: inherit;">
                <div class="card">
                    <?php if (!empty($ex['image_url'])): ?>
                        <img src="<?= htmlspecialchars($ex['image_url']) ?>"
                             alt="<?= htmlspecialchars($ex['name']) ?>"
                             loading="lazy"
                             style="width: 100%; height: 140px; object-fit: cover; border-radius: 6px;">
                    <?php else: ?>
                        <div style="height: 140px; border-radius: 6px; background: var(--color-border);
                                    display: flex; align-items: center; justify-content: center;
                                    color: var(--color-text-muted); font-size: 0.85rem;">
                            Chưa có ảnh
                        </div>
                    <?php endif; ?>

                    <h3 style="margin: 0.75rem 0 0.25rem;"><?= htmlspecialchars($ex['name']) ?></h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--color-text-muted);">
                        <?= htmlspecialchars($muscle) ?> · <?= htmlspecialchars($type) ?> · <?= htmlspecialchars($difficulty) ?>
                    </p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>