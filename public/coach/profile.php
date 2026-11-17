<?php
/**
 * coach/profile.php — HLV tự sửa hồ sơ công khai (chức danh, chuyên môn, giới thiệu...) và bật/tắt nhận học viên.
 * Dùng chung hàm kiểm tra với mẫu đơn (validateCoachApplication, không cần tick cam kết).
 * Số học viên tối đa không được nhỏ hơn số học viên đang tập (không đuổi học viên bằng cách hạ con số).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';

requireCoach();
$coachId = (int)$_SESSION['user_id'];
$profile = coachProfile($pdo, $coachId);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $v = validateCoachApplication($_POST, false);
    $errors = $v['errors'];
    $active = (int)($profile['active_clients'] ?? 0);
    if (!isset($errors['max_clients']) && $v['data']['max_clients'] < $active) {
        $errors['max_clients'] = 'Bạn đang có ' . $active . ' học viên, số tối đa không thể nhỏ hơn.';
    }
    if ($profile === null) {
        $errors['_form'] = 'Không tìm thấy hồ sơ HLV. Hãy báo quản trị viên.';
    }
    if (!$errors) {
        coachSaveProfile($pdo, $coachId, $v['data'], ($_POST['accepting'] ?? '') === '1');
        coachFlash('ok', 'Đã lưu hồ sơ HLV.');
        header('Location: /coach/profile.php');
        exit;
    }
    // Có lỗi: điền lại đúng những gì vừa gửi
    $profile = array_merge($profile ?? [], $v['data'], ['accepting' => ($_POST['accepting'] ?? '') === '1' ? 1 : 0]);
}
$flash = coachTakeFlash();
$picked = explode(',', (string)($profile['specialties'] ?? ''));

function fe(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<p class="ch-err" id="err-' . $k . '" role="alert">' . htmlspecialchars($errors[$k]) . '</p>' : '';
}

$pageTitle = 'Hồ sơ HLV';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$coachNavActive = 'profile';
include __DIR__ . '/../includes/coach-nav.php';
?>
<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Hồ sơ HLV</h1>
            <p class="adm-sub">Học viên thấy các thông tin này ở trang Huấn luyện viên. Email của bạn không hiện ra.</p>
        </div>
        <a class="adm-btn adm-btn-ghost" href="/coaches.php">Xem như học viên</a>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="adm-errors" role="alert"><?= isset($errors['_form']) ? htmlspecialchars($errors['_form']) : 'Vui lòng kiểm tra lại ' . count($errors) . ' mục được đánh dấu.' ?></div>
    <?php endif; ?>

    <?php if ($profile === null): ?>
        <div class="adm-panel"><div class="adm-empty">Chưa có hồ sơ HLV. Hãy báo quản trị viên.</div></div>
    <?php else: ?>
    <form class="adm-panel" method="post" action="/coach/profile.php" novalidate>
        <?= csrfField() ?>
        <div class="adm-form-section">
            <h2>Nhận học viên</h2>
            <label class="ch-check">
                <input type="checkbox" name="accepting" value="1"<?= (int)$profile['accepting'] === 1 ? ' checked' : '' ?>>
                <span><b>Đang nhận học viên mới</b><br><span class="adm-hint" style="margin:0">Tắt khi bận: học viên vẫn thấy bạn nhưng không gửi yêu cầu được. Học viên đang tập không bị ảnh hưởng.</span></span>
            </label>
            <div class="adm-grid">
                <div class="adm-field">
                    <label for="max_clients">Số học viên tối đa</label>
                    <input class="adm-input" id="max_clients" name="max_clients" type="number" inputmode="numeric" min="1" max="30" value="<?= (int)$profile['max_clients'] ?>">
                    <p class="adm-hint">Đang có <?= (int)($profile['active_clients'] ?? 0) ?> học viên.</p>
                    <?= fe($errors, 'max_clients') ?>
                </div>
                <div class="adm-field">
                    <label for="experience_years">Số năm kinh nghiệm</label>
                    <input class="adm-input" id="experience_years" name="experience_years" type="number" inputmode="numeric" min="0" max="50" value="<?= (int)$profile['experience_years'] ?>">
                    <?= fe($errors, 'experience_years') ?>
                </div>
                <div class="adm-field">
                    <label for="availability">Thời gian hỗ trợ</label>
                    <input class="adm-input" id="availability" name="availability" maxlength="255" value="<?= htmlspecialchars((string)$profile['availability']) ?>">
                    <?= fe($errors, 'availability') ?>
                </div>
            </div>
        </div>
        <div class="adm-form-section">
            <h2>Giới thiệu</h2>
            <div class="adm-field">
                <label for="headline">Chức danh</label>
                <input class="adm-input" id="headline" name="headline" maxlength="120" value="<?= htmlspecialchars((string)$profile['headline']) ?>">
                <?= fe($errors, 'headline') ?>
            </div>
            <div class="adm-field">
                <fieldset class="ch-picks">
                    <legend class="ch-legend">Chuyên môn (1–<?= COACH_SPECIALTIES_MAX ?>)</legend>
                    <?php foreach (COACH_SPECIALTIES as $code => $label): ?>
                        <label class="ch-pick"><input type="checkbox" name="specialties[]" value="<?= htmlspecialchars($code) ?>"<?= in_array($code, $picked, true) ? ' checked' : '' ?>><span><?= htmlspecialchars($label) ?></span></label>
                    <?php endforeach; ?>
                </fieldset>
                <?= fe($errors, 'specialties') ?>
            </div>
            <div class="adm-field">
                <label for="bio">Giới thiệu bản thân</label>
                <textarea class="adm-textarea" id="bio" name="bio" maxlength="1500" rows="6"><?= htmlspecialchars((string)$profile['bio']) ?></textarea>
                <span class="ch-count" data-count="bio"></span>
                <?= fe($errors, 'bio') ?>
            </div>
            <div class="adm-field">
                <label for="approach">Phương pháp huấn luyện</label>
                <textarea class="adm-textarea" id="approach" name="approach" maxlength="1000" rows="4"><?= htmlspecialchars((string)$profile['approach']) ?></textarea>
                <span class="ch-count" data-count="approach"></span>
                <?= fe($errors, 'approach') ?>
            </div>
            <div class="adm-field">
                <label for="certifications">Chứng chỉ (mỗi dòng một chứng chỉ)</label>
                <textarea class="adm-textarea" id="certifications" name="certifications" maxlength="1000" rows="3"><?= htmlspecialchars((string)$profile['certifications']) ?></textarea>
                <?= fe($errors, 'certifications') ?>
            </div>
        </div>
        <div class="adm-bar">
            <button type="submit" class="adm-btn" data-busy-text="Đang lưu…">Lưu hồ sơ</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
