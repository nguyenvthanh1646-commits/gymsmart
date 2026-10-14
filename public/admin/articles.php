<?php
/**
 * admin/articles.php — quản lý bài viết.
 *  - Bài do admin soạn (bảng articles): thêm, sửa, đăng / gỡ về nháp, xóa.
 *  - Bài hệ thống (file trong content/articles/): chỉ xem, kèm lượt xem. Muốn sửa thì sửa file (xem includes/articles.php).
 * Mọi hành động là POST + mã CSRF; hành động thành công được ghi vào nhật ký (includes/audit-log.php).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/articles.php';
require_once __DIR__ . '/../includes/audit-log.php';

requireAdmin();

$categories = articleCategories();
$missing = false;

// ===== Hành động: đăng / gỡ / xóa =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['publish', 'unpublish', 'delete'], true)) {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $action = $_POST['action'];
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    try {
        $stmt = $pdo->prepare('SELECT id, slug, title FROM articles WHERE id = ?');
        $stmt->execute([$id === false ? 0 : $id]);
        $row = $stmt->fetch();
        if (!$row) {
            $_SESSION['flash'] = ['error', 'Không tìm thấy bài viết.'];
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM articles WHERE id = ?')->execute([$row['id']]);
            auditLog($pdo, 'article.delete', 'article', $row['id'], 'Xóa bài "' . $row['title'] . '" (' . $row['slug'] . ')');
            $_SESSION['flash'] = ['ok', 'Đã xóa bài "' . $row['title'] . '".'];
        } else {
            $status = $action === 'publish' ? 'PUBLISHED' : 'DRAFT';
            // Lần đầu đăng mà chưa có ngày đăng thì lấy ngày hôm nay
            $pdo->prepare("UPDATE articles SET status = ?, updated_at = ?, published_date = COALESCE(published_date, ?) WHERE id = ?")
                ->execute([$status, date('Y-m-d H:i:s'), date('Y-m-d'), $row['id']]);
            auditLog($pdo, 'article.' . $action, 'article', $row['id'], ($action === 'publish' ? 'Đăng' : 'Gỡ về nháp') . ' bài "' . $row['title'] . '"');
            $_SESSION['flash'] = ['ok', ($action === 'publish' ? 'Đã đăng' : 'Đã gỡ về nháp') . ' bài "' . $row['title'] . '".'];
        }
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['error', 'Chưa có bảng articles. Hãy import sql/admin_features.sql.'];
    }
    header('Location: /admin/articles.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ===== Dữ liệu hiển thị =====
$dbRows = [];
try {
    $dbRows = $pdo->query('SELECT id, slug, title, category, status, published_date, updated_at FROM articles ORDER BY id DESC')->fetchAll();
} catch (PDOException $e) {
    $missing = true;
}

$views = [];
try {
    foreach ($pdo->query('SELECT slug, SUM(views) AS n FROM article_views GROUP BY slug')->fetchAll() as $r) {
        $views[$r['slug']] = (int)$r['n'];
    }
} catch (PDOException $e) {
    $missing = true;
}

$fileArticles = loadFileArticles();
uasort($fileArticles, fn($a, $b) => $a['order'] <=> $b['order']);

$pageTitle = 'Quản lý bài viết';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
include __DIR__ . '/../includes/admin-style-extra.php';
$admNavActive = 'articles';
include __DIR__ . '/../includes/admin-nav.php';
?>

<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Bài viết</h1>
            <p class="adm-sub">Soạn bài mới, đăng hoặc gỡ về nháp. Bài hệ thống (file) chỉ xem.</p>
        </div>
        <a class="adm-btn" href="/admin/article-form.php">+ Soạn bài mới</a>
    </div>

    <?php if ($missing): ?>
        <div class="adm-hint-box">Chưa có bảng <b>articles</b> hoặc <b>article_views</b>. Hãy import <b>sql/admin_features.sql</b> trong phpMyAdmin.</div>
    <?php endif; ?>
    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <div class="adm-panel" style="margin-bottom:18px;">
        <div class="adm-count"><b>Bài do admin soạn</b> (<?= count($dbRows) ?>)</div>
        <?php if (empty($dbRows)): ?>
            <div class="adm-empty">Chưa có bài nào. Bấm “Soạn bài mới” để bắt đầu.</div>
        <?php else: ?>
        <table class="adm-table">
            <thead><tr><th>Tiêu đề</th><th>Chuyên mục</th><th>Trạng thái</th><th>Lượt xem</th><th>Cập nhật</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($dbRows as $r): $published = $r['status'] === 'PUBLISHED'; ?>
                <tr>
                    <td class="adm-cell-name adm-name"><a class="adm-link" href="/admin/article-form.php?id=<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['title']) ?></a><br><small><?= htmlspecialchars($r['slug']) ?></small></td>
                    <td data-label="Chuyên mục"><?= htmlspecialchars($categories[$r['category']][0] ?? $r['category']) ?></td>
                    <td data-label="Trạng thái"><span class="adm-badge adm-b-<?= $published ? 'PUBLISHED' : 'DRAFT' ?>"><?= $published ? 'Đã đăng' : 'Nháp' ?></span></td>
                    <td data-label="Lượt xem"><?= (int)($views[$r['slug']] ?? 0) ?></td>
                    <td class="adm-mono" data-label="Cập nhật"><?= htmlspecialchars(substr((string)$r['updated_at'], 0, 10)) ?></td>
                    <td class="adm-cell-actions">
                        <div class="adm-actions">
                            <a class="adm-btn adm-btn-ghost adm-btn-sm" href="/article.php?slug=<?= htmlspecialchars(rawurlencode($r['slug'])) ?>&amp;preview=1" target="_blank" rel="noopener">Xem trước</a>
                            <a class="adm-btn adm-btn-ghost adm-btn-sm" href="/admin/article-form.php?id=<?= (int)$r['id'] ?>">Sửa</a>
                            <button type="button" class="adm-btn adm-btn-ghost adm-btn-sm adm-act" data-action="<?= $published ? 'unpublish' : 'publish' ?>" data-id="<?= (int)$r['id'] ?>" data-name="<?= htmlspecialchars($r['title']) ?>"><?= $published ? 'Gỡ về nháp' : 'Đăng' ?></button>
                            <button type="button" class="adm-btn adm-btn-danger adm-btn-sm adm-act" data-action="delete" data-id="<?= (int)$r['id'] ?>" data-name="<?= htmlspecialchars($r['title']) ?>">Xóa</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="adm-panel">
        <div class="adm-count"><b>Bài hệ thống</b> (file trong content/articles/, <?= count($fileArticles) ?> bài)</div>
        <table class="adm-table">
            <thead><tr><th>Tiêu đề</th><th>Chuyên mục</th><th>Loại</th><th>Lượt xem</th><th>Ngày</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($fileArticles as $slug => $a): ?>
                <tr>
                    <td class="adm-cell-name adm-name"><?= htmlspecialchars($a['title']) ?><br><small><?= htmlspecialchars($slug) ?></small></td>
                    <td data-label="Chuyên mục"><?= htmlspecialchars($categories[$a['category']][0]) ?></td>
                    <td data-label="Loại"><span class="adm-badge adm-b-FILE">File</span></td>
                    <td data-label="Lượt xem"><?= (int)($views[$slug] ?? 0) ?></td>
                    <td class="adm-mono" data-label="Ngày"><?= htmlspecialchars($a['date']) ?></td>
                    <td class="adm-cell-actions"><a class="adm-btn adm-btn-ghost adm-btn-sm" href="/article.php?slug=<?= htmlspecialchars(rawurlencode($slug)) ?>" target="_blank" rel="noopener">Xem bài</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog class="adm-dialog" id="adm-dialog">
    <h2 id="adm-dialog-title"></h2>
    <p id="adm-dialog-text"></p>
    <div class="adm-dialog-actions">
        <button type="button" class="adm-btn adm-btn-ghost" id="adm-cancel">Hủy</button>
        <form method="post" action="/admin/articles.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="adm-dialog-action" value="">
            <input type="hidden" name="id" id="adm-dialog-id" value="">
            <button type="submit" class="adm-btn" id="adm-dialog-ok">Xác nhận</button>
        </form>
    </div>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('adm-dialog');
    var ok = document.getElementById('adm-dialog-ok');
    var text = {
        delete:    ['Xóa bài viết?', 'Xóa bài "%" vĩnh viễn. Không hoàn tác được.', 'Xóa'],
        publish:   ['Đăng bài viết?', 'Bài "%" sẽ hiện cho mọi người dùng.', 'Đăng'],
        unpublish: ['Gỡ bài về nháp?', 'Bài "%" sẽ không còn hiện với người dùng.', 'Gỡ về nháp']
    };
    document.querySelectorAll('.adm-act').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var t = text[btn.dataset.action];
            document.getElementById('adm-dialog-action').value = btn.dataset.action;
            document.getElementById('adm-dialog-id').value = btn.dataset.id;
            // textContent: tiêu đề bài không thể chèn mã HTML vào hộp thoại
            document.getElementById('adm-dialog-title').textContent = t[0];
            document.getElementById('adm-dialog-text').textContent = t[1].replace('%', btn.dataset.name);
            ok.className = 'adm-btn' + (btn.dataset.action === 'delete' ? ' adm-btn-solid-danger' : '');
            ok.textContent = t[2];
            dialog.showModal();
        });
    });
    document.getElementById('adm-cancel').addEventListener('click', function () { dialog.close(); });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
