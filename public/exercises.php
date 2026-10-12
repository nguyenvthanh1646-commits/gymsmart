<?php
/**
 * exercises.php — thư viện bài tập (B1 → B5).
 *
 * B1: lấy bài tập từ bảng exercises, hiển thị bằng foreach.
 * B2: mỗi bài là một thẻ, bấm vào sang exercise-detail.php?id=...
 * B3: lọc theo nhóm cơ        (?muscle_group=CHEST)
 * B4: tìm theo tên, nhóm cơ, loại (?q=ngực)
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
    // Tìm theo TÊN bài tập. Thêm nữa: nếu từ khóa trùng một phần với tên nhóm cơ hoặc loại
    // (ví dụ gõ "ngực" khớp nhóm Ngực) thì lấy luôn các bài thuộc nhóm/loại đó.
    // Giá trị trong DB là CHEST, BACK... còn người dùng gõ tiếng Việt, nên phải đổi qua bảng nhãn.
    $orParts  = ['name LIKE ?'];
    // Dấu % ghép vào GIÁ TRỊ (không phải câu SQL): '%abc%' = chứa "abc" ở đâu cũng được
    $orParams = ['%' . escapeLike($keyword) . '%'];

    foreach ([['muscle_group', $muscleLabels], ['type', $typeLabels]] as [$column, $labels]) {
        $matchedKeys = [];
        foreach ($labels as $key => $label) {
            if (mb_stripos($label, $keyword) !== false) {
                $matchedKeys[] = $key; // $key lấy từ bảng nhãn viết sẵn trong code, không phải từ người dùng
            }
        }
        if (!empty($matchedKeys)) {
            // Tên cột ($column) là chữ cố định ở trên; các giá trị vẫn đi qua dấu ?
            $orParts[] = $column . ' IN (' . implode(',', array_fill(0, count($matchedKeys), '?')) . ')';
            $orParams  = array_merge($orParams, $matchedKeys);
        }
    }

    $where[] = '(' . implode(' OR ', $orParts) . ')';
    $params  = array_merge($params, $orParams);
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
?>

<div class="pg-head">
    <div>
        <h1>Thư viện bài tập</h1>
        <p class="pg-sub">Chọn nhóm cơ, lọc theo loại và độ khó, rồi bấm vào bài tập để xem video hướng dẫn.</p>
    </div>
</div>

<!-- Hàng nút lọc theo nhóm cơ: giữ nguyên các bộ lọc khác đang bật -->
<nav class="chips" aria-label="Lọc theo nhóm cơ">
    <a href="<?= htmlspecialchars(exerciseUrl(array_merge($current, ['muscle_group' => '']))) ?>"
       class="chip<?= $selectedMuscle === '' ? ' is-active' : '' /* audit:ok chuỗi class viết sẵn */ ?>">Tất cả</a>
    <?php foreach ($muscleLabels as $value => $label): ?>
        <a href="<?= htmlspecialchars(exerciseUrl(array_merge($current, ['muscle_group' => $value]))) ?>"
           class="chip<?= $selectedMuscle === $value ? ' is-active' : '' /* audit:ok chuỗi class viết sẵn */ ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
</nav>

<!-- Form tìm kiếm + lọc loại/độ khó. Dùng GET (không phải POST) vì đây là thao tác
     chỉ để xem: kết quả có địa chỉ riêng, gửi link hoặc bấm F5 đều ổn. -->
<form method="get" action="/exercises.php" class="filter-bar">
    <?php if ($selectedMuscle !== ''): ?>
        <!-- Giữ nhóm cơ đang chọn khi bấm "Lọc" -->
        <input type="hidden" name="muscle_group" value="<?= htmlspecialchars($selectedMuscle) ?>">
    <?php endif; ?>

    <input type="search" name="q" maxlength="100" placeholder="Tìm theo tên, nhóm cơ hoặc loại..." aria-label="Tìm bài tập"
           value="<?= htmlspecialchars($keyword) ?>">

    <select name="type" aria-label="Loại bài tập">
        <option value="">Tất cả loại</option>
        <?php foreach ($typeLabels as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= $selectedType === $value ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="difficulty" aria-label="Độ khó">
        <option value="">Mọi độ khó</option>
        <?php foreach ($difficultyLabels as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= $selectedDifficulty === $value ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn">Lọc</button>
</form>

<!-- Thanh kết quả: số bài tập tìm được + nút xóa bộ lọc -->
<div class="result-bar">
    <p><b><?= count($exercises) ?></b> bài tập<?= $hasFilter ? ' phù hợp bộ lọc' : ' trong thư viện' ?></p>
    <?php if ($hasFilter): ?>
        <a href="/exercises.php">&#10005; Xóa bộ lọc</a>
    <?php endif; ?>
</div>

<?php if (empty($exercises)): ?>
    <div class="empty">
        <?php if ($hasFilter): ?>
            <b>Không tìm thấy bài tập phù hợp</b>
            Thử đổi từ khóa hoặc <a href="/exercises.php">xóa bộ lọc</a>.
        <?php else: ?>
            <b>Chưa có bài tập nào</b>
            Quản trị viên sẽ sớm bổ sung thư viện.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="ex-grid">
        <?php foreach ($exercises as $ex): ?>
            <?php
            // ?? '...' : nếu giá trị trong DB không có trong bảng nhãn thì in luôn giá trị gốc
            $muscle     = $muscleLabels[$ex['muscle_group']] ?? $ex['muscle_group'];
            $type       = $typeLabels[$ex['type']] ?? $ex['type'];
            $difficulty = $difficultyLabels[$ex['difficulty']] ?? $ex['difficulty'];
            ?>
            <!-- (int) ép id thành số nguyên để không chèn được ký tự lạ vào đường dẫn -->
            <a class="ex-card" href="/exercise-detail.php?id=<?= (int)$ex['id'] ?>">
                <div class="ex-card__media">
                    <?php if (!empty($ex['image_url'])): ?>
                        <img src="<?= htmlspecialchars($ex['image_url']) ?>"
                             alt="<?= htmlspecialchars($ex['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="ex-card__ph">Chưa có ảnh</div>
                    <?php endif; ?>
                    <span class="tag tag--<?= htmlspecialchars($ex['difficulty']) ?> ex-card__level"><?= htmlspecialchars($difficulty) ?></span>
                </div>
                <div class="ex-card__body">
                    <!-- Tên dài tối đa 2 dòng (line-clamp), min-height giữ chỗ 2 dòng để các thẻ thẳng hàng -->
                    <h3><?= htmlspecialchars($ex['name']) ?></h3>
                    <div class="ex-meta">
                        <span class="tag"><?= htmlspecialchars($muscle) ?></span>
                        <span class="tag"><?= htmlspecialchars($type) ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>