<?php
/**
 * exercises.php — thư viện bài tập (B1 → B5).
 *
 * B1: lấy bài tập từ bảng exercises, hiển thị bằng foreach.
 * B2: mỗi bài là một thẻ, bấm vào sang exercise-detail.php?id=...
 * B3: lọc theo nhóm cơ        (?muscle_group=CHEST)
 * B4: tìm theo tên            (?q=ngực)
 * B5: lọc theo loại, độ khó   (?type=CARDIO&difficulty=BEGINNER)
 * Các bộ lọc kết hợp được với nhau, ví dụ:
 *   /exercises.php?muscle_group=CHEST&type=RESISTANCE&q=tạ
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin(); // theo API_SPEC: xem thư viện bài tập cần đăng nhập

// ===== Bảng nhãn: giá trị trong DB → chữ hiển thị tiếng Việt =====
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

// ===== Hàm phụ =====

/**
 * Đọc một tham số lựa chọn từ URL, chỉ chấp nhận giá trị nằm trong danh sách
 * cho phép (whitelist). Giá trị lạ hoặc kiểu lạ (ví dụ mảng ?type[]=x) → ''.
 * Dữ liệu trên URL do người dùng gõ được nên KHÔNG BAO GIỜ được tin trực tiếp.
 */
function readChoice(string $key, array $allowed): string
{
    $value = $_GET[$key] ?? '';
    if (!is_string($value) || !array_key_exists($value, $allowed)) {
        return '';
    }
    return $value;
}

/**
 * Tạo đường dẫn /exercises.php?... từ mảng tham số, bỏ các giá trị rỗng.
 * Dùng để các nút lọc giữ nguyên những bộ lọc khác đang bật.
 */
function exerciseUrl(array $params): string
{
    $params = array_filter($params, fn($v) => $v !== '');
    $query  = http_build_query($params);
    return '/exercises.php' . ($query !== '' ? '?' . $query : '');
}

/**
 * Trong câu LIKE, ký tự % và _ có ý nghĩa đặc biệt (khớp mọi chuỗi / mọi ký tự).
 * Nếu người dùng gõ "%" mà không xử lý thì sẽ khớp mọi bài tập.
 * Thêm dấu \ phía trước để chúng được hiểu là ký tự thường.
 */
function escapeLike(string $text): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
}

// ===== Đọc các bộ lọc từ URL =====
$selectedMuscle     = readChoice('muscle_group', $muscleLabels);
$selectedType       = readChoice('type', $typeLabels);
$selectedDifficulty = readChoice('difficulty', $difficultyLabels);

// Từ khóa tìm kiếm: phải là chuỗi, bỏ khoảng trắng thừa, giới hạn 100 ký tự
$keyword = $_GET['q'] ?? '';
$keyword = is_string($keyword) ? trim($keyword) : '';
$keyword = mb_substr($keyword, 0, 100);

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
if ($selectedType !== '') {
    $where[]  = 'type = ?';
    $params[] = $selectedType;
}
if ($selectedDifficulty !== '') {
    $where[]  = 'difficulty = ?';
    $params[] = $selectedDifficulty;
}
if ($keyword !== '') {
    $where[]  = 'name LIKE ?';
    // Dấu % ghép vào GIÁ TRỊ (không phải câu SQL): '%abc%' = chứa "abc" ở đâu cũng được
    $params[] = '%' . escapeLike($keyword) . '%';
}

$sql = "SELECT id, name, type, muscle_group, difficulty, image_url FROM exercises";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where); // nối các điều kiện bằng AND
}
$sql .= ' ORDER BY name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$exercises = $stmt->fetchAll();

// Các bộ lọc đang bật (dùng để dựng link và quyết định có hiện "Xóa bộ lọc")
$current = [
    'muscle_group' => $selectedMuscle,
    'type'         => $selectedType,
    'difficulty'   => $selectedDifficulty,
    'q'            => $keyword,
];
$hasFilter = ($selectedMuscle !== '' || $selectedType !== ''
           || $selectedDifficulty !== '' || $keyword !== '');

$pageTitle = 'Bài tập & Video';
include __DIR__ . '/includes/header.php';

// Style tạm (E sẽ thay bằng class riêng ở việc E2)
$pillStyle = 'display: inline-block; padding: 0.4rem 0.9rem; margin: 0 0.4rem 0.4rem 0; '
           . 'border: 1px solid var(--color-border); border-radius: 999px; '
           . 'text-decoration: none; color: var(--color-text); background: var(--color-surface);';
$pillActiveStyle = 'display: inline-block; padding: 0.4rem 0.9rem; margin: 0 0.4rem 0.4rem 0; '
                 . 'border: 1px solid var(--color-accent); border-radius: 999px; '
                 . 'text-decoration: none; color: #fff; background: var(--color-accent);';
?>

<h1>Thư viện bài tập</h1>

<!-- Hàng nút lọc theo nhóm cơ: giữ nguyên các bộ lọc khác đang bật -->
<nav aria-label="Lọc theo nhóm cơ" style="margin-bottom: 1rem;">
    <a href="<?= htmlspecialchars(exerciseUrl(array_merge($current, ['muscle_group' => '']))) ?>"
       style="<?= $selectedMuscle === '' ? $pillActiveStyle : $pillStyle ?>">Tất cả</a>
    <?php foreach ($muscleLabels as $value => $label): ?>
        <a href="<?= htmlspecialchars(exerciseUrl(array_merge($current, ['muscle_group' => $value]))) ?>"
           style="<?= $selectedMuscle === $value ? $pillActiveStyle : $pillStyle ?>">
            <?= htmlspecialchars($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<!-- Form tìm kiếm + lọc loại/độ khó. Dùng GET (không phải POST) vì đây là thao tác
     chỉ để xem: kết quả có địa chỉ riêng, gửi link hoặc bấm F5 đều ổn. -->
<form method="get" action="/exercises.php"
      style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-bottom: 1rem;">
    <?php if ($selectedMuscle !== ''): ?>
        <!-- Giữ nhóm cơ đang chọn khi bấm "Lọc" -->
        <input type="hidden" name="muscle_group" value="<?= htmlspecialchars($selectedMuscle) ?>">
    <?php endif; ?>

    <input type="search" name="q" maxlength="100" placeholder="Tìm theo tên bài tập..."
           value="<?= htmlspecialchars($keyword) ?>">

    <select name="type" aria-label="Loại bài tập">
        <option value="">Tất cả loại</option>
        <?php foreach ($typeLabels as $value => $label): ?>
            <option value="<?= $value ?>" <?= $selectedType === $value ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="difficulty" aria-label="Độ khó">
        <option value="">Mọi độ khó</option>
        <?php foreach ($difficultyLabels as $value => $label): ?>
            <option value="<?= $value ?>" <?= $selectedDifficulty === $value ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn">Lọc</button>

    <?php if ($hasFilter): ?>
        <a href="/exercises.php">Xóa bộ lọc</a>
    <?php endif; ?>
</form>

<p>Có <?= count($exercises) ?> bài tập<?= $hasFilter ? ' phù hợp bộ lọc' : '' ?>.</p>

<?php if (empty($exercises)): ?>
    <?php if ($hasFilter): ?>
        <p>Không tìm thấy bài tập phù hợp. <a href="/exercises.php">Xóa bộ lọc</a></p>
    <?php else: ?>
        <p>Chưa có bài tập nào.</p>
    <?php endif; ?>
<?php else: ?>
    <!-- Lưới tạm bằng style trực tiếp. E sẽ thay bằng class riêng ở việc E2. -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem;">
        <?php foreach ($exercises as $ex): ?>
            <?php
            // ?? '...' : nếu giá trị trong DB không có trong bảng nhãn thì in luôn giá trị gốc
            $muscle     = $muscleLabels[$ex['muscle_group']] ?? $ex['muscle_group'];
            $type       = $typeLabels[$ex['type']] ?? $ex['type'];
            $difficulty = $difficultyLabels[$ex['difficulty']] ?? $ex['difficulty'];
            ?>
            <!-- (int) ép id thành số nguyên để không chèn được ký tự lạ vào đường dẫn -->
            <a href="/exercise-detail.php?id=<?= (int)$ex['id'] ?>"
               style="text-decoration: none; color: inherit; display: block;">
                <!-- height: 100% để mọi thẻ trong cùng hàng cao bằng nhau dù tên bài dài ngắn khác nhau -->
                <div class="card" style="height: 100%; box-sizing: border-box;">
                    <?php if (!empty($ex['image_url'])): ?>
                        <img src="<?= htmlspecialchars($ex['image_url']) ?>"
                             alt="<?= htmlspecialchars($ex['name']) ?>"
                             loading="lazy"
                             style="display: block; width: 100%; height: 140px; object-fit: cover; border-radius: 6px;">
                    <?php else: ?>
                        <div style="height: 140px; border-radius: 6px; background: var(--color-border);
                                    display: flex; align-items: center; justify-content: center;
                                    color: var(--color-text-muted); font-size: 0.85rem;">
                            Chưa có ảnh
                        </div>
                    <?php endif; ?>

                    <!-- Tên dài tối đa 2 dòng (-webkit-line-clamp), min-height giữ chỗ 2 dòng để các thẻ thẳng hàng -->
                    <h3 style="margin: 0.75rem 0 0.25rem; line-height: 1.3; min-height: 2.6em;
                               display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
                               overflow: hidden;"><?= htmlspecialchars($ex['name']) ?></h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--color-text-muted);">
                        <?= htmlspecialchars($muscle) ?> · <?= htmlspecialchars($type) ?> · <?= htmlspecialchars($difficulty) ?>
                    </p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>