<?php
/**
 * admin/exercise-form.php — thêm bài tập mới hoặc sửa bài tập.
 *
 *   /admin/exercise-form.php        → chế độ THÊM (form trống)
 *   /admin/exercise-form.php?id=5   → chế độ SỬA  (form điền sẵn dữ liệu bài 5)
 *
 * Luồng: GET hiện form → POST kiểm tra dữ liệu → INSERT/UPDATE → chuyển về danh sách.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAdmin();

// Khóa trùng ENUM/quy ước trong DB; giá trị là chữ hiển thị
$typeOptions = ['RESISTANCE' => 'Kháng lực', 'CARDIO' => 'Cardio'];
$muscleOptions = [
    'CHEST' => 'Ngực', 'BACK' => 'Lưng', 'SHOULDERS' => 'Vai', 'LEGS' => 'Chân',
    'ARMS' => 'Tay', 'ABS' => 'Bụng', 'FULL_BODY' => 'Toàn thân',
];
$difficultyOptions = [
    'BEGINNER' => 'Dễ (mới tập)', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Khó (nâng cao)',
];

/**
 * Nhận link YouTube hoặc ID, trả về ID 11 ký tự; sai định dạng thì trả null.
 * Trong DB chỉ lưu ID (xem ERD.md), nên admin dán cả link cũng được.
 */
function extractYoutubeId(string $input): ?string
{
    $input = trim($input);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
        return $input; // đã là ID
    }
    // watch?v=ID, youtu.be/ID, embed/ID, shorts/ID
    if (preg_match('~(?:v=|youtu\.be/|embed/|shorts/)([A-Za-z0-9_-]{11})(?:[&?/]|$)~', $input, $m)) {
        return $m[1];
    }
    return null;
}

// ===== Xác định chế độ thêm hay sửa =====
$editId = null;
$exercise = null; // dữ liệu hiện có (chế độ sửa)

if (isset($_GET['id']) || isset($_POST['id'])) {
    $editId = filter_var($_GET['id'] ?? $_POST['id'], FILTER_VALIDATE_INT);
    if ($editId === false || $editId < 1) {
        http_response_code(404);
        exit('Id bài tập không hợp lệ.');
    }
    $stmt = $pdo->prepare("SELECT * FROM exercises WHERE id = ?");
    $stmt->execute([$editId]);
    $exercise = $stmt->fetch();
    if (!$exercise) {
        http_response_code(404);
        exit('Không tìm thấy bài tập.');
    }
}

$fields = ['name', 'type', 'muscle_group', 'difficulty', 'description',
           'instructions', 'common_mistakes', 'image_url', 'video_url'];
// Giá trị hiển thị trong form: dữ liệu DB (sửa) hoặc rỗng (thêm)
$form = [];
foreach ($fields as $f) {
    $form[$f] = $exercise[$f] ?? '';
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ (sai mã bảo vệ). Hãy quay lại và thử lại.');
    }

    foreach ($fields as $f) {
        $form[$f] = trim((string)($_POST[$f] ?? ''));
    }

    // ===== Kiểm tra dữ liệu =====
    if ($form['name'] === '') {
        $errors[] = 'Vui lòng nhập tên bài tập.';
    } elseif (mb_strlen($form['name']) > 100) {
        $errors[] = 'Tên bài tập tối đa 100 ký tự.';
    }
    if (!array_key_exists($form['type'], $typeOptions)) {
        $errors[] = 'Vui lòng chọn loại bài tập.';
    }
    if (!array_key_exists($form['muscle_group'], $muscleOptions)) {
        $errors[] = 'Vui lòng chọn nhóm cơ.';
    }
    if (!array_key_exists($form['difficulty'], $difficultyOptions)) {
        $errors[] = 'Vui lòng chọn độ khó.';
    }
    if ($form['description'] === '' || $form['instructions'] === '' || $form['common_mistakes'] === '') {
        $errors[] = 'Mô tả, các bước thực hiện và lỗi thường gặp không được để trống.';
    }

    // Ảnh: để trống, hoặc là link http(s), hoặc đường dẫn bắt đầu bằng "/".
    // Chặn kiểu "javascript:..." để không bị chèn mã vào thuộc tính src.
    if ($form['image_url'] !== '') {
        if (mb_strlen($form['image_url']) > 255
            || !preg_match('~^(https?://|/)~i', $form['image_url'])) {
            $errors[] = 'Ảnh phải là link http(s) hoặc đường dẫn bắt đầu bằng "/" (tối đa 255 ký tự).';
        }
    }

    // Video: để trống, hoặc link/ID YouTube hợp lệ. Lưu vào DB chỉ ID.
    $videoId = null;
    if ($form['video_url'] !== '') {
        $videoId = extractYoutubeId($form['video_url']);
        if ($videoId === null) {
            $errors[] = 'Video phải là link YouTube hoặc ID 11 ký tự.';
        }
    }

    // ===== Lưu vào DB =====
    if (empty($errors)) {
        $params = [
            $form['name'], $form['type'], $form['muscle_group'], $form['difficulty'],
            $form['description'], $form['instructions'], $form['common_mistakes'],
            $form['image_url'] === '' ? null : $form['image_url'],
            $videoId, // null nếu không có video
        ];

        if ($editId === null) {
            $stmt = $pdo->prepare(
                "INSERT INTO exercises
                 (name, type, muscle_group, difficulty, description, instructions,
                  common_mistakes, image_url, video_url)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute($params);
            $_SESSION['flash'] = ['ok', 'Đã thêm bài tập.'];
        } else {
            $stmt = $pdo->prepare(
                "UPDATE exercises
                 SET name = ?, type = ?, muscle_group = ?, difficulty = ?, description = ?,
                     instructions = ?, common_mistakes = ?, image_url = ?, video_url = ?
                 WHERE id = ?"
            );
            $params[] = $editId;
            $stmt->execute($params);
            $_SESSION['flash'] = ['ok', 'Đã cập nhật bài tập.'];
        }

        header('Location: /admin/exercises.php');
        exit;
    }
}

$isEdit = $editId !== null;
$pageTitle = $isEdit ? 'Sửa bài tập' : 'Thêm bài tập';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$admNavActive = 'exercises';
include __DIR__ . '/../includes/admin-nav.php';

/** In các thẻ <option>, chọn sẵn giá trị $selected */
function renderOptions(array $options, string $selected): void
{
    echo '<option value="">-- Chọn --</option>';
    foreach ($options as $value => $label) {
        $sel = $selected === $value ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $sel . '>'
            . htmlspecialchars($label) . '</option>';
    }
}
?>

<div class="adm-wrap" style="max-width: 760px;">
    <div class="adm-head">
        <div>
            <h1><?= $isEdit ? 'Sửa bài tập' : 'Thêm bài tập' ?></h1>
            <p class="adm-sub"><a href="/admin/exercises.php">&larr; Quay lại danh sách</a></p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <ul class="adm-errors" role="alert">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form class="adm-panel" method="post" action="/admin/exercise-form.php<?= $isEdit ? '?id=' . (int)$editId : '' ?>">
        <?= csrfField() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$editId ?>">
        <?php endif; ?>

        <div class="adm-form-section">
            <h2>Thông tin chung</h2>
            <div class="adm-field">
                <label for="name">Tên bài tập</label>
                <input class="adm-input" type="text" id="name" name="name" maxlength="100" required
                       value="<?= htmlspecialchars($form['name']) ?>">
            </div>
            <div class="adm-grid">
                <div class="adm-field">
                    <label for="type">Loại</label>
                    <select class="adm-select" id="type" name="type" required><?php renderOptions($typeOptions, $form['type']); ?></select>
                </div>
                <div class="adm-field">
                    <label for="muscle_group">Nhóm cơ</label>
                    <select class="adm-select" id="muscle_group" name="muscle_group" required><?php renderOptions($muscleOptions, $form['muscle_group']); ?></select>
                </div>
                <div class="adm-field">
                    <label for="difficulty">Độ khó</label>
                    <select class="adm-select" id="difficulty" name="difficulty" required><?php renderOptions($difficultyOptions, $form['difficulty']); ?></select>
                </div>
            </div>
        </div>

        <div class="adm-form-section">
            <h2>Nội dung hướng dẫn</h2>
            <div class="adm-field">
                <label for="description">Mô tả</label>
                <textarea class="adm-textarea" id="description" name="description" rows="3" required><?= htmlspecialchars($form['description']) ?></textarea>
            </div>
            <div class="adm-field">
                <label for="instructions">Các bước thực hiện</label>
                <textarea class="adm-textarea" id="instructions" name="instructions" rows="6" required><?= htmlspecialchars($form['instructions']) ?></textarea>
                <p class="adm-hint">Mỗi bước một dòng.</p>
            </div>
            <div class="adm-field">
                <label for="common_mistakes">Lỗi thường gặp</label>
                <textarea class="adm-textarea" id="common_mistakes" name="common_mistakes" rows="4" required><?= htmlspecialchars($form['common_mistakes']) ?></textarea>
            </div>
        </div>

        <div class="adm-form-section">
            <h2>Ảnh và video</h2>
            <div class="adm-field">
                <label for="image_url">Ảnh minh họa</label>
                <input class="adm-input" type="text" id="image_url" name="image_url" maxlength="255"
                       value="<?= htmlspecialchars($form['image_url']) ?>" inputmode="url">
                <p class="adm-hint">Link http(s) hoặc đường dẫn bắt đầu bằng /. Để trống nếu chưa có.</p>
                <img class="adm-preview" id="image-preview" alt="Xem trước ảnh">
            </div>
            <div class="adm-field">
                <label for="video_url">Video YouTube</label>
                <input class="adm-input" type="text" id="video_url" name="video_url"
                       value="<?= htmlspecialchars($form['video_url']) ?>">
                <p class="adm-hint">Dán link hoặc ID video. Hệ thống chỉ lưu ID. Để trống nếu chưa có.</p>
                <img class="adm-preview" id="video-preview" alt="Xem trước video">
            </div>
        </div>

        <div class="adm-bar">
            <a class="adm-btn adm-btn-ghost" href="/admin/exercises.php">Hủy</a>
            <button type="submit" class="adm-btn"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm bài tập' ?></button>
        </div>
    </form>
</div>

<script>
// Xem trước ảnh và video ngay khi nhập (chỉ để admin kiểm tra nhanh, không ảnh hưởng dữ liệu gửi đi)
(function () {
    var img = document.getElementById('image_url');
    var imgPrev = document.getElementById('image-preview');
    var vid = document.getElementById('video_url');
    var vidPrev = document.getElementById('video-preview');

    function showImage() {
        var v = img.value.trim();
        // Chỉ xem trước link http(s) hoặc đường dẫn bắt đầu bằng "/", giống quy tắc kiểm tra ở server
        if (/^(https?:\/\/|\/)/i.test(v)) { imgPrev.src = v; imgPrev.classList.add('is-on'); }
        else { imgPrev.removeAttribute('src'); imgPrev.classList.remove('is-on'); }
    }
    function youtubeId(v) {
        v = v.trim();
        if (/^[A-Za-z0-9_-]{11}$/.test(v)) return v;
        var m = v.match(/(?:v=|youtu\.be\/|embed\/|shorts\/)([A-Za-z0-9_-]{11})(?:[&?\/]|$)/);
        return m ? m[1] : null;
    }
    function showVideo() {
        var id = youtubeId(vid.value);
        if (id) { vidPrev.src = 'https://img.youtube.com/vi/' + id + '/mqdefault.jpg'; vidPrev.classList.add('is-on'); }
        else { vidPrev.removeAttribute('src'); vidPrev.classList.remove('is-on'); }
    }
    imgPrev.addEventListener('error', function () { imgPrev.classList.remove('is-on'); });
    vidPrev.addEventListener('error', function () { vidPrev.classList.remove('is-on'); });
    img.addEventListener('input', showImage);
    vid.addEventListener('input', showVideo);
    showImage(); showVideo();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>