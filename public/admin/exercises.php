<?php
/**
 * admin/exercises.php — quản trị bài tập: danh sách (có tìm kiếm, lọc) + xóa.
 * Chỉ ADMIN được vào. Thêm/sửa nằm ở admin/exercise-form.php.
 *
 * Xóa dùng POST (không dùng link GET), kèm mã CSRF, và hỏi xác nhận bằng hộp thoại.
 * Tìm kiếm/lọc chạy ngay trên trình duyệt (JS) vì trang quản trị chỉ có vài chục bài.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAdmin(); // kiểm tra ở PHP, không chỉ ẩn nút ở giao diện

$muscleLabels = [
    'CHEST' => 'Ngực', 'BACK' => 'Lưng', 'SHOULDERS' => 'Vai', 'LEGS' => 'Chân',
    'ARMS' => 'Tay', 'ABS' => 'Bụng', 'FULL_BODY' => 'Toàn thân',
];
$typeLabels = ['RESISTANCE' => 'Kháng lực', 'CARDIO' => 'Cardio'];
$difficultyLabels = [
    'BEGINNER' => 'Dễ', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Khó',
];

// ===== Xử lý xóa =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrf()) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ (sai mã bảo vệ). Hãy quay lại và thử lại.');
    }

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        $_SESSION['flash'] = ['error', 'Id bài tập không hợp lệ.'];
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM exercises WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['flash'] = $stmt->rowCount() > 0
                ? ['ok', 'Đã xóa bài tập.']
                : ['error', 'Không tìm thấy bài tập để xóa.'];
        } catch (PDOException $e) {
            // Mã 23000: vi phạm khóa ngoại, nghĩa là bài đang nằm trong lịch tập
            // hoặc nhật ký tập của ai đó nên không xóa được.
            if ($e->getCode() === '23000') {
                $_SESSION['flash'] = ['error', 'Không xóa được: bài tập này đang được dùng trong lịch tập hoặc nhật ký tập.'];
            } else {
                throw $e;
            }
        }
    }
    // PRG: chuyển hướng để F5 không gửi lại lệnh xóa
    header('Location: /admin/exercises.php');
    exit;
}

// Lấy thông báo một lần rồi xóa khỏi session
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$exercises = $pdo->query(
    "SELECT id, name, type, muscle_group, difficulty, video_url
     FROM exercises ORDER BY id DESC"
)->fetchAll();

$pageTitle = 'Quản trị bài tập';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$admNavActive = 'exercises';
include __DIR__ . '/../includes/admin-nav.php';
?>

<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Quản trị bài tập</h1>
            <p class="adm-sub">Thêm, sửa, xóa bài tập trong thư viện.</p>
        </div>
        <a class="adm-btn" href="/admin/exercise-form.php">+ Thêm bài tập</a>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status">
            <?= htmlspecialchars($flash[1]) ?>
        </div>
    <?php endif; ?>

    <div class="adm-panel">
        <div class="adm-tools">
            <input class="adm-input" type="search" id="adm-q" placeholder="Tìm theo tên bài tập..." aria-label="Tìm theo tên">
            <select class="adm-select" id="adm-muscle" aria-label="Lọc theo nhóm cơ">
                <option value="">Mọi nhóm cơ</option>
                <?php foreach ($muscleLabels as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="adm-select" id="adm-difficulty" aria-label="Lọc theo độ khó">
                <option value="">Mọi độ khó</option>
                <?php foreach ($difficultyLabels as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="adm-count" id="adm-count">Tổng: <?= count($exercises) ?> bài</div>

        <?php if (empty($exercises)): ?>
            <div class="adm-empty">Chưa có bài tập nào. Bấm "+ Thêm bài tập" để bắt đầu.</div>
        <?php else: ?>
        <table class="adm-table">
            <thead>
                <tr>
                    <th>ID</th><th>Tên bài tập</th><th>Nhóm cơ</th><th>Loại</th>
                    <th>Độ khó</th><th>Video</th><th></th>
                </tr>
            </thead>
            <tbody id="adm-rows">
            <?php foreach ($exercises as $ex): ?>
                <tr data-name="<?= htmlspecialchars(mb_strtolower($ex['name'])) ?>"
                    data-muscle="<?= htmlspecialchars($ex['muscle_group']) ?>"
                    data-difficulty="<?= htmlspecialchars($ex['difficulty']) ?>">
                    <td class="adm-cell-id adm-id">#<?= (int)$ex['id'] ?></td>
                    <td class="adm-cell-name adm-name"><?= htmlspecialchars($ex['name']) ?></td>
                    <td data-label="Nhóm cơ"><span class="adm-badge"><?= htmlspecialchars($muscleLabels[$ex['muscle_group']] ?? $ex['muscle_group']) ?></span></td>
                    <td data-label="Loại"><span class="adm-badge adm-b-<?= htmlspecialchars($ex['type']) ?>"><?= htmlspecialchars($typeLabels[$ex['type']] ?? $ex['type']) ?></span></td>
                    <td data-label="Độ khó"><span class="adm-badge adm-b-<?= htmlspecialchars($ex['difficulty']) ?>"><?= htmlspecialchars($difficultyLabels[$ex['difficulty']] ?? $ex['difficulty']) ?></span></td>
                    <td data-label="Video">
                        <?php if (!empty($ex['video_url'])): ?>
                            <span class="adm-video-yes">Có</span>
                        <?php else: ?>
                            <span class="adm-video-no">Chưa có</span>
                        <?php endif; ?>
                    </td>
                    <td class="adm-cell-actions">
                        <div class="adm-actions">
                            <a class="adm-btn adm-btn-ghost adm-btn-sm" href="/admin/exercise-form.php?id=<?= (int)$ex['id'] ?>">Sửa</a>
                            <button type="button" class="adm-btn adm-btn-danger adm-btn-sm adm-del"
                                    data-id="<?= (int)$ex['id'] ?>"
                                    data-name="<?= htmlspecialchars($ex['name']) ?>">Xóa</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="adm-empty" id="adm-none" hidden>Không có bài tập nào khớp bộ lọc.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Hộp xác nhận xóa: chỉ có MỘT form, JS điền id và tên bài vào trước khi mở -->
<dialog class="adm-dialog" id="adm-dialog">
    <h2>Xóa bài tập?</h2>
    <p>Bạn sắp xóa "<strong id="adm-dialog-name"></strong>". Không hoàn tác được.</p>
    <div class="adm-dialog-actions">
        <button type="button" class="adm-btn adm-btn-ghost" id="adm-cancel">Hủy</button>
        <form method="post" action="/admin/exercises.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="adm-dialog-id" value="">
            <button type="submit" class="adm-btn adm-btn-solid-danger">Xóa</button>
        </form>
    </div>
</dialog>

<script>
(function () {
    // Bỏ dấu tiếng Việt để tìm "day nguc" vẫn ra "Đẩy ngực"
    function plain(s) {
        return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
    }

    var q = document.getElementById('adm-q');
    var muscle = document.getElementById('adm-muscle');
    var diff = document.getElementById('adm-difficulty');
    var rows = document.querySelectorAll('#adm-rows tr');
    var count = document.getElementById('adm-count');
    var none = document.getElementById('adm-none');

    function applyFilter() {
        var text = plain(q.value.trim());
        var shown = 0;
        rows.forEach(function (tr) {
            var ok = plain(tr.dataset.name).indexOf(text) !== -1
                && (!muscle.value || tr.dataset.muscle === muscle.value)
                && (!diff.value || tr.dataset.difficulty === diff.value);
            tr.hidden = !ok;
            if (ok) shown++;
        });
        count.textContent = shown === rows.length
            ? 'Tổng: ' + rows.length + ' bài'
            : 'Hiển thị ' + shown + ' / ' + rows.length + ' bài';
        if (none) none.hidden = shown !== 0;
    }
    [q, muscle, diff].forEach(function (el) {
        el.addEventListener('input', applyFilter);
    });

    // Hộp xác nhận xóa
    var dialog = document.getElementById('adm-dialog');
    document.querySelectorAll('.adm-del').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('adm-dialog-id').value = btn.dataset.id;
            document.getElementById('adm-dialog-name').textContent = btn.dataset.name; // textContent: an toàn, không chạy HTML
            dialog.showModal();
        });
    });
    document.getElementById('adm-cancel').addEventListener('click', function () { dialog.close(); });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>