<?php
/**
 * admin/article-form.php — soạn / sửa một bài viết (bảng articles).
 * Luồng: GET hiện form → POST kiểm tra dữ liệu ở server → INSERT/UPDATE → ghi nhật ký → về danh sách.
 * Nội dung gõ theo cú pháp đơn giản (không HTML), xem hướng dẫn ngay trong form và includes/article-markup.php.
 * Ảnh bìa chỉ chọn trong các file đã có sẵn ở img/articles/ (không cho tải file lên để tránh rủi ro file độc hại).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/articles.php';
require_once __DIR__ . '/../includes/article-markup.php';
require_once __DIR__ . '/../includes/audit-log.php';

requireAdmin();

$categories = articleCategories();

// Danh sách ảnh có sẵn (đường dẫn dạng /img/articles/ten.svg), chỉ nhận tên an toàn
$images = [];
foreach (glob(__DIR__ . '/../img/articles/*') ?: [] as $f) {
    $path = '/img/articles/' . basename($f);
    if (articleImageOk($path)) {
        $images[] = $path;
    }
}
sort($images);

$editId = null;
if (isset($_GET['id']) || isset($_POST['id'])) {
    $editId = filter_var($_GET['id'] ?? $_POST['id'], FILTER_VALIDATE_INT);
    if ($editId === false || $editId < 1) {
        showError(404, 'Không tìm thấy bài viết', 'Id bài viết không hợp lệ.', [['/admin/articles.php', 'Về danh sách bài viết']]);
    }
}

$form = [
    'slug' => '', 'title' => '', 'category' => 'general', 'tags' => '', 'summary' => '', 'quick' => '',
    'image' => '', 'image_alt' => '', 'body' => '', 'sources' => '', 'status' => 'DRAFT',
    'sort_order' => '100', 'published_date' => '',
];
$errors = [];
$existing = null;

if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$editId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        showError(404, 'Không tìm thấy bài viết', 'Bài viết này không tồn tại hoặc đã bị xóa.', [['/admin/articles.php', 'Về danh sách bài viết']]);
    }
    foreach ($form as $k => $_) {
        $form[$k] = (string)($existing[$k] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    // Lấy từng trường, ép về chuỗi (nếu ai đó gửi mảng thì thành chuỗi rỗng)
    foreach ($form as $k => $_) {
        if ($k === 'slug' && $editId !== null) {
            continue; // slug không đổi khi sửa (giữ nguyên link đã chia sẻ)
        }
        $v = $_POST[$k] ?? '';
        $form[$k] = is_string($v) ? trim($v) : '';
    }

    // ----- Kiểm tra dữ liệu -----
    if ($editId === null) {
        if (!preg_match('/^[a-z0-9-]{3,100}$/', $form['slug'])) {
            $errors[] = 'Slug chỉ gồm chữ thường không dấu, số và dấu gạch ngang, dài 3 đến 100 ký tự.';
        } elseif (isset(loadFileArticles()[$form['slug']])) {
            $errors[] = 'Slug này trùng với một bài hệ thống (file). Hãy chọn slug khác.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE slug = ?');
            $stmt->execute([$form['slug']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Slug này đã được dùng cho bài khác.';
            }
        }
    }
    if ($form['title'] === '' || mb_strlen($form['title']) > 200) {
        $errors[] = 'Tiêu đề bắt buộc, tối đa 200 ký tự.';
    }
    if (!isset($categories[$form['category']])) {
        $errors[] = 'Chuyên mục không hợp lệ.';
    }
    $tagList = array_values(array_filter(array_map('trim', explode(',', $form['tags'])), 'strlen'));
    if (count($tagList) > 5 || array_filter($tagList, fn($t) => mb_strlen($t) > 30)) {
        $errors[] = 'Tối đa 5 nhãn, mỗi nhãn không quá 30 ký tự, ngăn cách bằng dấu phẩy.';
    }
    $form['tags'] = implode(', ', $tagList);
    if ($form['summary'] === '' || mb_strlen($form['summary']) > 500) {
        $errors[] = 'Tóm tắt bắt buộc, tối đa 500 ký tự.';
    }
    if (mb_strlen($form['quick']) > 1000) {
        $errors[] = 'Khung “Trả lời nhanh” tối đa 1000 ký tự.';
    }
    if ($form['image'] !== '' && !in_array($form['image'], $images, true)) {
        $errors[] = 'Ảnh bìa phải chọn trong danh sách có sẵn.';
    }
    if ($form['image'] !== '' && $form['image_alt'] === '') {
        $errors[] = 'Có ảnh bìa thì cần mô tả ảnh (cho người đọc bằng trình đọc màn hình).';
    }
    if (mb_strlen($form['image_alt']) > 255) {
        $errors[] = 'Mô tả ảnh tối đa 255 ký tự.';
    }
    if ($form['published_date'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $form['published_date']);
        if (!$d || $d->format('Y-m-d') !== $form['published_date']) {
            $errors[] = 'Ngày đăng không hợp lệ.';
        }
    }
    if (!preg_match('/^\d{1,4}$/', $form['sort_order'])) {
        $errors[] = 'Thứ tự là số từ 0 đến 9999.';
    }
    if (!in_array($form['status'], ['DRAFT', 'PUBLISHED'], true)) {
        $errors[] = 'Trạng thái không hợp lệ.';
    }
    if (mb_strlen($form['body']) > 60000 || mb_strlen($form['sources']) > 8000) {
        $errors[] = 'Nội dung quá dài.';
    }
    if (count(articleParseMarkup($form['body'])) === 0) {
        $errors[] = 'Nội dung bài viết đang trống hoặc không có khối hợp lệ.';
    }

    // ----- Lưu -----
    if (empty($errors)) {
        $now = date('Y-m-d H:i:s');
        $date = $form['published_date'] !== '' ? $form['published_date']
              : ($form['status'] === 'PUBLISHED' ? date('Y-m-d') : null);
        $common = [
            $form['title'], $form['category'], $form['tags'], $form['summary'],
            $form['quick'] === '' ? null : $form['quick'],
            $form['image'] === '' ? null : $form['image'], $form['image_alt'],
            $form['body'], $form['sources'] === '' ? null : $form['sources'],
            $form['status'], (int)$form['sort_order'], $date,
        ];
        if ($editId === null) {
            $pdo->prepare(
                'INSERT INTO articles (title, category, tags, summary, quick, image, image_alt, body, sources, status, sort_order, published_date, slug, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute(array_merge($common, [$form['slug'], (int)$_SESSION['user_id'], $now, $now]));
            $newId = (int)$pdo->lastInsertId();
            auditLog($pdo, 'article.create', 'article', $newId, 'Soạn bài "' . $form['title'] . '" (' . $form['slug'] . ', ' . ($form['status'] === 'PUBLISHED' ? 'đã đăng' : 'nháp') . ')');
            $_SESSION['flash'] = ['ok', 'Đã tạo bài "' . $form['title'] . '".'];
        } else {
            $pdo->prepare(
                'UPDATE articles SET title = ?, category = ?, tags = ?, summary = ?, quick = ?, image = ?, image_alt = ?, body = ?,
                        sources = ?, status = ?, sort_order = ?, published_date = ?, updated_at = ? WHERE id = ?'
            )->execute(array_merge($common, [$now, $editId]));
            auditLog($pdo, 'article.update', 'article', $editId, 'Sửa bài "' . $form['title'] . '" (' . $existing['slug'] . ')');
            $_SESSION['flash'] = ['ok', 'Đã cập nhật bài "' . $form['title'] . '".'];
        }
        header('Location: /admin/articles.php');
        exit;
    }
}

$isEdit = $editId !== null;
$pageTitle = $isEdit ? 'Sửa bài viết' : 'Soạn bài viết';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
include __DIR__ . '/../includes/admin-style-extra.php';
$admNavActive = 'articles';
include __DIR__ . '/../includes/admin-nav.php';
?>

<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1><?= $isEdit ? 'Sửa bài viết' : 'Soạn bài viết' ?></h1>
            <p class="adm-sub"><a href="/admin/articles.php">&larr; Quay lại danh sách</a></p>
        </div>
        <?php if ($isEdit): ?>
            <a class="adm-btn adm-btn-ghost" href="/article.php?slug=<?= htmlspecialchars(rawurlencode($existing['slug'])) ?>&amp;preview=1" target="_blank" rel="noopener">Xem trước bản đã lưu</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
        <ul class="adm-errors" role="alert">
            <?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="/admin/article-form.php<?= $isEdit ? '?id=' . (int)$editId : '' ?>">
        <?= csrfField() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$editId ?>"><?php endif; ?>

        <div class="adm-panel">
        <div class="adm-form-section">
            <h2>Thông tin chung</h2>
            <div class="adm-form-grid">
                <div class="adm-field adm-field--full">
                    <label for="title">Tiêu đề</label>
                    <input class="adm-input" type="text" id="title" name="title" maxlength="200" required value="<?= htmlspecialchars($form['title']) ?>">
                </div>
                <div class="adm-field">
                    <label for="slug">Slug (đường dẫn)</label>
                    <input class="adm-input" type="text" id="slug" name="slug" maxlength="100" pattern="[a-z0-9\-]{3,100}"
                           value="<?= htmlspecialchars($form['slug']) ?>" <?= $isEdit ? 'readonly' : 'required' ?>>
                    <p class="adm-hint"><?= $isEdit ? 'Không đổi được sau khi tạo.' : 'Chữ thường không dấu, số, gạch ngang. Ví dụ: uong-nuoc-khi-tap' ?></p>
                </div>
                <div class="adm-field">
                    <label for="category">Chuyên mục</label>
                    <select class="adm-select" id="category" name="category">
                        <?php foreach ($categories as $key => $c): ?>
                            <option value="<?= htmlspecialchars($key) ?>"<?= $form['category'] === $key ? ' selected' : '' ?>><?= htmlspecialchars($c[0]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="adm-field">
                    <label for="tags">Nhãn (ngăn bằng dấu phẩy, tối đa 5)</label>
                    <input class="adm-input" type="text" id="tags" name="tags" value="<?= htmlspecialchars($form['tags']) ?>">
                </div>
                <div class="adm-field">
                    <label for="status">Trạng thái</label>
                    <select class="adm-select" id="status" name="status">
                        <option value="DRAFT"<?= $form['status'] === 'DRAFT' ? ' selected' : '' ?>>Nháp (chưa hiện với người dùng)</option>
                        <option value="PUBLISHED"<?= $form['status'] === 'PUBLISHED' ? ' selected' : '' ?>>Đăng</option>
                    </select>
                </div>
                <div class="adm-field">
                    <label for="published_date">Ngày đăng</label>
                    <input class="adm-input" type="date" id="published_date" name="published_date" value="<?= htmlspecialchars($form['published_date']) ?>">
                    <p class="adm-hint">Để trống thì lấy ngày đăng bài.</p>
                </div>
                <div class="adm-field">
                    <label for="sort_order">Thứ tự hiển thị</label>
                    <input class="adm-input" type="number" id="sort_order" name="sort_order" min="0" max="9999" value="<?= htmlspecialchars($form['sort_order']) ?>">
                    <p class="adm-hint">Số nhỏ hiện trước. Bài hệ thống đang dùng 1 đến 14.</p>
                </div>
                <div class="adm-field adm-field--full">
                    <label for="summary">Tóm tắt (hiện ở thẻ ngoài danh sách)</label>
                    <textarea class="adm-textarea" id="summary" name="summary" rows="2" maxlength="500" required><?= htmlspecialchars($form['summary']) ?></textarea>
                </div>
                <div class="adm-field adm-field--full">
                    <label for="quick">Trả lời nhanh (tùy chọn)</label>
                    <textarea class="adm-textarea" id="quick" name="quick" rows="3" maxlength="1000"><?= htmlspecialchars($form['quick']) ?></textarea>
                </div>
                <div class="adm-field">
                    <label for="image">Ảnh bìa</label>
                    <select class="adm-select" id="image" name="image">
                        <option value="">Không dùng ảnh</option>
                        <?php foreach ($images as $img): ?>
                            <option value="<?= htmlspecialchars($img) ?>"<?= $form['image'] === $img ? ' selected' : '' ?>><?= htmlspecialchars(basename($img)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="adm-hint">Chỉ chọn được ảnh đã có trong thư mục img/articles/. Muốn thêm ảnh mới, đưa file vào thư mục đó.</p>
                </div>
                <div class="adm-field">
                    <label for="image_alt">Mô tả ảnh</label>
                    <input class="adm-input" type="text" id="image_alt" name="image_alt" maxlength="255" value="<?= htmlspecialchars($form['image_alt']) ?>">
                </div>
            </div>
        </div>

        <div class="adm-form-section">
            <h2>Nội dung</h2>
            <div class="adm-field">
                <textarea class="adm-textarea adm-code" id="body" name="body" rows="22" required><?= htmlspecialchars($form['body']) ?></textarea>
                <details class="adm-data"><summary>Cú pháp soạn bài</summary>
<pre class="adm-code" style="white-space:pre-wrap;margin:8px 0 0">Đoạn văn thường (các dòng liền nhau là một đoạn, dòng trống để tách đoạn)
## Tiêu đề mục
- ý chấm            (các dòng "- " liền nhau)
1. ý đánh số
&gt; Lưu ý nổi bật
[hinh] /img/articles/ten.svg | Mô tả ảnh | Chú thích
[bang-chung] fair | Giải thích ngắn     (mức: strong, fair, thin, weak, basic, unverified)
[bang]
Cột 1 | Cột 2
a | b
(dòng trống kết thúc bảng)
? Câu hỏi | Câu trả lời
[tom-tat]
- Ý 1
- Ý 2
**chữ đậm** dùng được trong mọi đoạn. Không dùng HTML.</pre>
                </details>
            </div>
            <div class="adm-field">
                <label for="sources">Nguồn tham khảo (tùy chọn, mỗi dòng một nguồn)</label>
                <textarea class="adm-textarea adm-code" id="sources" name="sources" rows="5"><?= htmlspecialchars($form['sources']) ?></textarea>
                <p class="adm-hint">Dạng: Tác giả | Năm | Tiêu đề | Tạp chí | Tập, trang | PMID | DOI. Chỉ ghi nghiên cứu có thật và đã mở bản ghi để kiểm tra.</p>
            </div>
        </div>

        <div class="adm-bar">
            <a class="adm-btn adm-btn-ghost" href="/admin/articles.php">Hủy</a>
            <button type="submit" class="adm-btn"><?= $isEdit ? 'Lưu thay đổi' : 'Tạo bài' ?></button>
        </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
