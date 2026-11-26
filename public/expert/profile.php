<?php
/**
 * expert/profile.php — chuyên gia sửa hồ sơ (A-55): giới thiệu, chủ đề nhận tư vấn, bật/tắt nhận câu hỏi.
 * Ngành, chức danh, nơi công tác, số chứng chỉ là thông tin ADMIN đã xác minh nên chỉ hiện để xem;
 * muốn đổi thì nộp đơn mới qua quản trị viên.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/expert.php';

requireExpert();
$expertId = (int)$_SESSION['user_id'];
$profile = expertProfile($pdo, $expertId);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profile !== null) {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $bio = coachCleanText($_POST['bio'] ?? '');
    if ($e = coachTextError($bio, 'Giới thiệu', 80, 1500)) {
        $errors['bio'] = $e;
    }
    $t = expertPickTopics($_POST['topics'] ?? []);
    if ($t['error'] !== null) {
        $errors['topics'] = $t['error'];
    }
    $accepting = ($_POST['accepting'] ?? '') === '1';
    if (!$errors) {
        try {
            expertSaveProfile($pdo, $expertId, $bio, implode(',', $t['codes']), $accepting);
            coachFlash('ok', 'Đã lưu hồ sơ chuyên gia.');
        } catch (ExpertException $e) {
            coachFlash('error', $e->getMessage());
        }
        header('Location: /expert/profile.php');
        exit;
    }
    $profile = array_merge($profile, ['bio' => $bio, 'topics' => implode(',', $t['codes']), 'accepting' => $accepting ? 1 : 0]);
}
$flash = coachTakeFlash();
$picked = explode(',', (string)($profile['topics'] ?? ''));

$pageTitle = 'Hồ sơ chuyên gia';
$pageCss = ['/css/coach.css', '/css/consult.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$expertNavActive = 'profile';
include __DIR__ . '/../includes/expert-nav.php';
?>
<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Hồ sơ chuyên gia</h1>
            <p class="adm-sub">Người dùng thấy hồ sơ ở trang Tư vấn sức khỏe. Số chứng chỉ hành nghề và email không hiện ra.</p>
        </div>
        <a class="adm-btn adm-btn-ghost" href="/consult.php">Xem như người dùng</a>
    </div>
    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?><div class="adm-errors" role="alert">Vui lòng kiểm tra lại <?= count($errors) ?> mục.</div><?php endif; ?>

    <?php if ($profile === null): ?>
        <div class="adm-panel"><div class="adm-empty">Chưa có hồ sơ chuyên gia. Hãy báo quản trị viên.</div></div>
    <?php else: ?>
    <div class="adm-panel adm-card-pad" style="margin-bottom:16px">
        <h2>Thông tin đã xác minh</h2>
        <dl class="adm-kv">
            <div><dt>Ngành</dt><dd><?= htmlspecialchars(EXPERT_PROFESSIONS[$profile['profession']] ?? '') ?></dd></div>
            <div><dt>Chức danh</dt><dd><?= htmlspecialchars($profile['title']) ?></dd></div>
            <div><dt>Nơi công tác</dt><dd><?= htmlspecialchars($profile['workplace']) ?></dd></div>
            <div><dt>Số năm hành nghề</dt><dd><?= (int)$profile['experience_years'] ?></dd></div>
            <div><dt>Số chứng chỉ</dt><dd><?= htmlspecialchars($profile['license_no']) ?> <span class="adm-sub">(chỉ bạn và quản trị viên thấy)</span></dd></div>
        </dl>
        <p class="adm-hint">Muốn đổi các thông tin này, hãy liên hệ quản trị viên để nộp lại đơn.</p>
    </div>
    <form class="adm-panel" method="post" action="/expert/profile.php" novalidate>
        <?= csrfField() ?>
        <div class="adm-form-section">
            <h2>Nhận câu hỏi</h2>
            <label class="ch-check">
                <input type="checkbox" name="accepting" value="1"<?= (int)$profile['accepting'] === 1 ? ' checked' : '' ?>>
                <span><b>Đang nhận câu hỏi mới</b><br><span class="adm-hint" style="margin:0">Tắt khi bận: không nhận thêm câu mới, các câu đang trao đổi vẫn tiếp tục.</span></span>
            </label>
            <div class="adm-field">
                <fieldset class="ch-picks">
                    <legend class="ch-legend">Chủ đề nhận tư vấn (1–<?= EXPERT_TOPICS_MAX ?>)</legend>
                    <?php foreach (HEALTH_TOPICS as $code => $label): if ($code === 'OTHER') continue; ?>
                        <label class="ch-pick"><input type="checkbox" name="topics[]" value="<?= htmlspecialchars($code) ?>"<?= in_array($code, $picked, true) ? ' checked' : '' ?>><span><?= htmlspecialchars($label) ?></span></label>
                    <?php endforeach; ?>
                </fieldset>
                <?php if (isset($errors['topics'])): ?><p class="ch-err" role="alert"><?= htmlspecialchars($errors['topics']) ?></p><?php endif; ?>
            </div>
            <div class="adm-field">
                <label for="bio">Giới thiệu</label>
                <textarea class="adm-textarea" id="bio" name="bio" maxlength="1500" rows="6"><?= htmlspecialchars((string)$profile['bio']) ?></textarea>
                <span class="ch-count" data-count="bio"></span>
                <?php if (isset($errors['bio'])): ?><p class="ch-err" role="alert"><?= htmlspecialchars($errors['bio']) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="adm-bar"><button type="submit" class="adm-btn" data-busy-text="Đang lưu…">Lưu hồ sơ</button></div>
    </form>
    <?php endif; ?>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
