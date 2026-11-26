<?php
/**
 * consult-ask.php — đặt câu hỏi tư vấn sức khỏe (A-55).
 * Máy chủ kiểm tra (validateHealthQuestion, healthAsk): chủ đề trong whitelist, độ dài, giới hạn 3 câu chưa đóng / 5 câu mỗi 24 giờ.
 * Câu hỏi có dấu hiệu cấp cứu (đau ngực, khó thở, ngất...) thì trang hiện cảnh báo gọi 115 và chỉ gửi được khi người hỏi
 * tick xác nhận không trong tình huống khẩn cấp.
 * "Chia sẻ số liệu hồ sơ": chỉ khi người hỏi tick, máy chủ mới chụp tuổi, giới tính, chiều cao, cân nặng, vòng eo, mức vận động, mục tiêu.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/expert.php';

$userId = (int)$_SESSION['user_id'];
refreshSessionRole($pdo);
$role = $_SESSION['role'] ?? 'USER';
if (!expertTablesReady($pdo) || ($role !== 'USER' && $role !== 'COACH')) {
    showError(403, 'Không gửi câu hỏi được', 'Tài khoản này không gửi câu hỏi tư vấn được, hoặc tính năng chưa được bật.', [['/consult.php', 'Tư vấn sức khỏe']]);
}

$errors = [];
// Chia sẻ số liệu mặc định TẮT: dữ liệu sức khỏe chỉ gửi khi người hỏi tự chọn
$old = ['topic' => is_string($_GET['topic'] ?? null) ? $_GET['topic'] : '', 'title' => '', 'body' => '', 'share_profile' => false, 'red_flags' => []];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $v = validateHealthQuestion($_POST);
    $errors = $v['errors'];
    $old = $v['data'];
    if (!$errors) {
        try {
            $id = healthAsk($pdo, $userId, $v['data']);
            coachFlash('ok', 'Đã gửi câu hỏi. Chuyên gia phù hợp sẽ nhận và trả lời; bạn được báo qua chuông thông báo.');
            header('Location: /consult-question.php?id=' . $id);
            exit;
        } catch (ExpertException $e) {
            $errors['_form'] = $e->getMessage();
        }
    }
}

function askErr(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<p class="ch-err" id="err-' . $k . '" role="alert">' . htmlspecialchars($errors[$k]) . '</p>' : '';
}
function askInvalid(array $errors, string $k): string
{
    return isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
}

$pageTitle = 'Đặt câu hỏi tư vấn';
$pageCss = ['/css/coach.css', '/css/consult.css'];
include __DIR__ . '/includes/header.php';
?>
<div class="ch">
    <header class="pg-head">
        <div>
            <h1>Đặt câu hỏi tư vấn</h1>
            <p class="pg-sub">Mô tả rõ vấn đề: xuất hiện từ khi nào, đau ở đâu, khi làm động tác gì, đã khám hay dùng thuốc gì chưa. Câu hỏi càng rõ, câu trả lời càng sát.</p>
        </div>
        <a class="btn btn--ghost" href="/consult.php">&larr; Tư vấn sức khỏe</a>
    </header>

    <?= healthSafetyBox() ?>
    <?php if (isset($errors['_form'])): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($errors['_form']) ?></div>
    <?php elseif (isset($errors['emergency_ack'])): ?>
        <div class="alert alert--error" role="alert"><div><b>Có thể cần cấp cứu.</b> <?= htmlspecialchars($errors['emergency_ack']) ?></div></div>
    <?php elseif ($errors): ?>
        <div class="alert alert--error" role="alert">Vui lòng kiểm tra lại <?= count($errors) ?> mục được đánh dấu.</div>
    <?php endif; ?>

    <form class="card" method="post" action="/consult-ask.php" novalidate>
        <?= csrfField() ?>
        <div class="field<?= isset($errors['topic']) ? ' is-invalid' : '' ?>">
            <label for="topic">Chủ đề</label>
            <select id="topic" name="topic" required<?= askInvalid($errors, 'topic') ?>>
                <option value="">Chọn chủ đề</option>
                <?php foreach (HEALTH_TOPICS as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code) ?>"<?= $old['topic'] === $code ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Câu hỏi được gửi tới các chuyên gia nhận chủ đề này.</small>
            <?= askErr($errors, 'topic') ?>
        </div>
        <div class="field<?= isset($errors['title']) ? ' is-invalid' : '' ?>">
            <label for="title">Tiêu đề</label>
            <input id="title" name="title" maxlength="150" required value="<?= htmlspecialchars((string)$old['title']) ?>" placeholder="Ví dụ: Đau mặt trước gối khi squat sâu"<?= askInvalid($errors, 'title') ?>>
            <?= askErr($errors, 'title') ?>
        </div>
        <div class="field<?= isset($errors['body']) ? ' is-invalid' : '' ?>">
            <label for="body">Nội dung</label>
            <textarea id="body" name="body" maxlength="2000" rows="8" required placeholder="Triệu chứng, thời gian, mức độ, bệnh nền, thuốc đang dùng, bạn muốn hỏi điều gì…"<?= askInvalid($errors, 'body') ?>><?= htmlspecialchars((string)$old['body']) ?></textarea>
            <small>30–2000 ký tự. Không ghi số điện thoại, địa chỉ hay giấy tờ tùy thân.</small>
            <span class="ch-count" data-count="body" aria-live="polite"></span>
            <?= askErr($errors, 'body') ?>
        </div>
        <label class="ch-check">
            <input type="checkbox" name="share_profile" value="1"<?= $old['share_profile'] ? ' checked' : '' ?>>
            <span><b>Gửi kèm số liệu hồ sơ</b><br><span class="ch-muted ch-small">Tuổi, giới tính, chiều cao, cân nặng và vòng eo gần nhất, mức vận động, mục tiêu tập. Không gửi email hay họ tên đầy đủ. Chuyên gia chỉ thấy tên của bạn.</span></span>
        </label>
        <?php if (!empty($old['red_flags']) || isset($errors['emergency_ack'])): ?>
            <label class="ch-check">
                <input type="checkbox" name="emergency_ack" value="1">
                <span><b>Tôi không trong tình huống khẩn cấp</b><br><span class="ch-muted ch-small">Nếu triệu chứng đang xảy ra hoặc nặng lên, hãy gọi 115 ngay thay vì gửi câu hỏi.</span></span>
            </label>
            <?= askErr($errors, 'emergency_ack') ?>
        <?php endif; ?>
        <p class="ch-muted ch-small">Câu hỏi chỉ bạn và chuyên gia nhận câu hỏi xem được. Chuyên gia trả lời để bạn tham khảo, không chẩn đoán xác định và không kê đơn thuốc.</p>
        <div class="form-actions">
            <button type="submit" class="btn" data-busy-text="Đang gửi…">Gửi câu hỏi</button>
        </div>
    </form>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
