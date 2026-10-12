<?php
/**
 * exercises.php — thư viện bài tập (B1 + B2 + B3).
 *
 * B1: lấy bài tập từ bảng exercises và hiển thị bằng vòng lặp foreach.
 * B2: mỗi bài là một thẻ, bấm vào để sang exercise-detail.php?id=...
 * B3: lọc theo nhóm cơ qua tham số URL, ví dụ /exercises.php?muscle_group=CHEST
 *
 * B4 (tìm theo tên) và B5 (lọc loại/độ khó) sẽ thêm điều kiện vào mảng $where
 * ở phần "Xây dựng câu truy vấn" bên dưới, không cần viết lại cấu trúc.
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

// ===== B3: đọc nhóm cơ người dùng chọn từ URL =====
// $_GET là dữ liệu người dùng gõ vào được, KHÔNG được tin. Ta chỉ chấp nhận
// giá trị nằm trong danh sách $muscleLabels (whitelist). Giá trị lạ → bỏ qua,
// coi như không lọc.
// is_string() chặn trường hợp ?muscle_group[]=CHEST (PHP sẽ hiểu là mảng,
// đưa mảng vào array_key_exists sẽ gây lỗi).
$selectedMuscle = $_GET['muscle_group'] ?? '';
if (!is_string($selectedMuscle) || !array_key_exists($selectedMuscle, $muscleLabels)) {
    $selectedMuscle = '';
}

// ===== Xây dựng câu truy vấn =====
// $where chứa các điều kiện (mỗi điều kiện dùng dấu ? thay cho giá trị),
// $params chứa giá trị tương ứng theo đúng thứ tự.
// Chỉ nối vào câu SQL những đoạn chữ cố định do mình viết sẵn,
// còn giá trị người dùng luôn đi qua $params → không bị SQL Injection.
$where  = [];
$params = [];

if ($selectedMuscle !== '') {
    $where[]  = 'muscle_group = ?';
    $params[] = $selectedMuscle;
}

$sql = "SELECT id, name, type, muscle_group, difficulty, image_url FROM exercises";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where); // nối các điều kiện bằng AND
}
$sql .= ' ORDER BY name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$exercises = $stmt->fetchAll();

$pageTitle = 'Bài tập & Video';
include __DIR__ . '/includes/header.php';

// Style tạm cho nút lọc (E sẽ thay bằng class riêng ở việc E2)
$pillStyle = 'display: inline-block; padding: 0.4rem 0.9rem; margin: 0 0.4rem 0.4rem 0; '
           . 'border: 1px solid var(--color-border); border-radius: 999px; '
           . 'text-decoration: none; color: var(--color-text); background: var(--color-surface);';
$pillActiveStyle = 'display: inline-block; padding: 0.4rem 0.9rem; margin: 0 0.4rem 0.4rem 0; '
                 . 'border: 1px solid var(--color-accent); border-radius: 999px; '
                 . 'text-decoration: none; color: #fff; background: var(--color-accent);';
?>

<h1>Thư viện bài tập</h1>

<!-- Hàng nút lọc theo nhóm cơ -->
<nav aria-label="Lọc theo nhóm cơ" style="margin-bottom: 1rem;">
    <a href="/exercises.php" style="<?= $selectedMuscle === '' ? $pillActiveStyle : $pillStyle ?>">Tất cả</a>
    <?php foreach ($muscleLabels as $value => $label): ?>
        <a href="/exercises.php?muscle_group=<?= urlencode($value) ?>"
           style="<?= $selectedMuscle === $value ? $pillActiveStyle : $pillStyle ?>">
            <?= htmlspecialchars($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<p>
    Có <?= count($exercises) ?> bài tập<?php if ($selectedMuscle !== ''): ?>
        thuộc nhóm <?= htmlspecialchars($muscleLabels[$selectedMuscle]) ?><?php endif; ?>.
</p>

<?php if (empty($exercises)): ?>
    <p>Chưa có bài tập nào<?= $selectedMuscle !== '' ? ' trong nhóm này' : '' ?>.</p>
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