<?php
/**
 * expert-apply.php — đơn đăng ký làm chuyên gia sức khỏe (A-55).
 *
 * Luồng giống đơn HLV: USER điền đơn (bắt buộc số chứng chỉ hành nghề + file chứng chỉ) → ADMIN xem ở admin/expert-applications.php
 * → duyệt (vai trò thành EXPERT, hồ sơ tạo từ đơn) hoặc từ chối kèm lý do → người dùng sửa và nộp lại.
 * Không nộp được khi: là ADMIN / COACH / đã là EXPERT, đang có hoặc đang chờ HLV, đã có đơn đang chờ.
 * File chứng chỉ dùng chung hàm của đơn HLV (coachStoreCertificate): kiểm tra nội dung file, tối đa 3 MB, lưu ngoài public/.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/expert.php';

$userId = (int)$_SESSION['user_id'];
refreshSessionRole($pdo);
$role = $_SESSION['role'] ?? 'USER';
$ready = expertTablesReady($pdo);
$errors = [];
$old = null;

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $action = $_POST['action'] ?? '';
    $v = null;
    try {
        if ($action === 'withdraw') {
            expertWithdrawApplication($pdo, $userId);
            coachFlash('ok', 'Đã rút đơn. Bạn có thể nộp lại bất cứ lúc nào.');
            header('Location: /expert-apply.php');
            exit;
        }
        if ($action === 'submit') {
            $v = validateExpertApplication($_POST);
            $errors = $v['errors'];
            $old = $v['data'] + ['topics_list' => explode(',', $v['data']['topics'])];
            if (!$errors) {
                // File chứng chỉ BẮT BUỘC; chỉ lưu khi các ô khác đã hợp lệ để không để lại file rác
                try {
                    $v['data']['certificate_file'] = coachStoreCertificate($_FILES['certificate_file'] ?? null, $userId);
                    if ($v['data']['certificate_file'] === null) {
                        $errors['certificate_file'] = 'Hãy tải lên ảnh hoặc file PDF chứng chỉ hành nghề.';
                    }
                } catch (CoachException $e) {
                    $errors['certificate_file'] = $e->getMessage();
                }
            }
            if (!$errors) {
                expertSubmitApplication($pdo, $userId, $v['data']);
                coachFlash('ok', 'Đã gửi đơn. Quản trị viên sẽ kiểm tra chứng chỉ và trả lời trên trang này.');
                header('Location: /expert-apply.php');
                exit;
            }
        }
    } catch (CoachException $e) {
        $errors['_form'] = $e->getMessage();
        if (isset($v['data']['certificate_file']) && coachCertNameOk($v['data']['certificate_file'])) {
            @unlink(coachCertDir() . '/' . $v['data']['certificate_file']);   // nộp thất bại thì xóa file vừa lưu
        }
    }
}

$flash = coachTakeFlash();
$latest = $ready ? expertLatestApplication($pdo, $userId) : null;
$openRel = ($ready && coachTablesReady($pdo)) ? coachOpenRelationForClient($pdo, $userId) : null;
$pending = $latest !== null && $latest['status'] === 'PENDING';

if (!$ready)                 $state = 'not_ready';
elseif ($role === 'EXPERT')  $state = 'expert';
elseif ($role !== 'USER')    $state = 'other_role';
elseif ($pending)            $state = 'pending';
elseif ($openRel !== null)   $state = 'has_coach';
else                         $state = 'form';

if ($old === null) {
    $src = ($latest !== null && $latest['status'] === 'REJECTED') ? $latest : [];
    $old = [
        'profession' => $src['profession'] ?? '', 'title' => $src['title'] ?? '', 'workplace' => $src['workplace'] ?? '',
        'license_no' => $src['license_no'] ?? '', 'experience_years' => $src['experience_years'] ?? '', 'bio' => $src['bio'] ?? '',
        'topics_list' => isset($src['topics']) ? explode(',', $src['topics']) : [],
    ];
}

function apErr(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<p class="ch-err" id="err-' . $k . '" role="alert">' . htmlspecialchars($errors[$k]) . '</p>' : '';
}
function apInvalid(array $errors, string $k): string
{
    return isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
}

$pageTitle = 'Đăng ký làm chuyên gia sức khỏe';
$pageCss = ['/css/coach.css', '/css/consult.css'];
include __DIR__ . '/includes/header.php';
?>
<div class="ch">
    <header class="pg-head">
        <div>
            <h1>Đăng ký làm chuyên gia sức khỏe</h1>
            <p class="pg-sub">Dành cho bác sĩ, chuyên gia dinh dưỡng, kỹ thuật viên vật lý trị liệu có chứng chỉ hành nghề. Quản trị viên kiểm tra chứng chỉ trước khi kích hoạt.</p>
        </div>
        <a class="btn btn--ghost" href="/consult.php">Tư vấn sức khỏe</a>
    </header>
    <?php if ($flash): ?>
        <div class="alert alert--<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <?php if ($state === 'not_ready'): ?>
        <div class="ch-empty"><div class="ch-empty__ico" aria-hidden="true">!</div><h2>Tính năng chưa được bật</h2><p>Quản trị viên cần chạy <code>sql/health_experts.sql</code>.</p></div>
    <?php elseif ($state === 'expert'): ?>
        <div class="card ch-status"><div class="ch-status__ico" aria-hidden="true">✓</div><div><h2>Bạn đã là chuyên gia</h2><p>Nhận và trả lời câu hỏi trong khu chuyên gia.</p><a class="btn" href="/expert/dashboard.php">Vào khu chuyên gia</a></div></div>
    <?php elseif ($state === 'other_role'): ?>
        <div class="card ch-status"><div class="ch-status__ico" aria-hidden="true">i</div><div><h2>Tài khoản này không đăng ký làm chuyên gia được</h2><p>Tài khoản quản trị hoặc huấn luyện viên không nộp đơn chuyên gia. Hãy dùng một tài khoản người dùng thường.</p></div></div>
    <?php elseif ($state === 'has_coach'): ?>
        <div class="card ch-status"><div class="ch-status__ico" aria-hidden="true">i</div><div><h2>Bạn đang là học viên của một HLV</h2><p>Hãy kết thúc với HLV <b><?= htmlspecialchars($openRel['coach_name']) ?></b> trước khi nộp đơn chuyên gia.</p><a class="btn btn--ghost" href="/my-coach.php">HLV của tôi</a></div></div>
    <?php elseif ($state === 'pending'): ?>
        <div class="card">
            <div class="ch-status"><div class="ch-status__ico" aria-hidden="true">⏳</div><div>
                <span class="ch-badge ch-badge--warn">Đang chờ duyệt</span>
                <h2 style="margin-top:8px">Đơn của bạn đã được gửi</h2>
                <p>Gửi lúc <?= htmlspecialchars(date('H:i d/m/Y', strtotime($latest['created_at']))) ?>. Kết quả sẽ hiện trên trang này và qua chuông thông báo.</p>
            </div></div>
            <dl class="ch-kv">
                <dt>Ngành</dt><dd><?= htmlspecialchars(EXPERT_PROFESSIONS[$latest['profession']] ?? '') ?></dd>
                <dt>Chức danh</dt><dd><?= htmlspecialchars($latest['title']) ?></dd>
                <dt>Nơi công tác</dt><dd><?= htmlspecialchars($latest['workplace']) ?></dd>
                <dt>Chủ đề</dt><dd><?= htmlspecialchars(implode(', ', healthTopicLabels($latest['topics']))) ?></dd>
                <dt>Chứng chỉ</dt><dd>Đã gửi số chứng chỉ và 1 file</dd>
            </dl>
            <form method="post" action="/expert-apply.php" class="form-actions" data-confirm="Rút đơn đăng ký chuyên gia?">
                <?= csrfField() ?><input type="hidden" name="action" value="withdraw">
                <button type="submit" class="btn btn--ghost" data-busy-text="Đang xử lý…">Rút đơn</button>
            </form>
        </div>
    <?php else: ?>
        <?php if ($latest !== null && $latest['status'] === 'REJECTED' && $latest['admin_note'] !== ''): ?>
            <div class="alert alert--note" role="note"><div><b>Đơn trước chưa được duyệt.</b> Lý do: <?= htmlspecialchars($latest['admin_note']) ?><br>Nội dung đơn cũ đã điền sẵn, hãy chọn lại file chứng chỉ.</div></div>
        <?php endif; ?>
        <?php if (isset($errors['_form'])): ?>
            <div class="alert alert--error" role="alert"><?= htmlspecialchars($errors['_form']) ?></div>
        <?php elseif ($errors): ?>
            <div class="alert alert--error" role="alert">Vui lòng kiểm tra lại <?= count($errors) ?> mục được đánh dấu.</div>
        <?php endif; ?>
        <form class="card" method="post" action="/expert-apply.php" enctype="multipart/form-data" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="submit">
            <fieldset class="form-sec">
                <h2><i>1</i>Hành nghề</h2>
                <div class="form-grid">
                    <div class="field<?= isset($errors['profession']) ? ' is-invalid' : '' ?>">
                        <label for="profession">Ngành</label>
                        <select id="profession" name="profession" required<?= apInvalid($errors, 'profession') ?>>
                            <option value="">Chọn ngành</option>
                            <?php foreach (EXPERT_PROFESSIONS as $code => $label): ?>
                                <option value="<?= htmlspecialchars($code) ?>"<?= $old['profession'] === $code ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= apErr($errors, 'profession') ?>
                    </div>
                    <div class="field<?= isset($errors['experience_years']) ? ' is-invalid' : '' ?>">
                        <label for="experience_years">Số năm hành nghề</label>
                        <input id="experience_years" name="experience_years" type="number" inputmode="numeric" min="0" max="60" required value="<?= htmlspecialchars((string)$old['experience_years']) ?>"<?= apInvalid($errors, 'experience_years') ?>>
                        <?= apErr($errors, 'experience_years') ?>
                    </div>
                </div>
                <div class="field<?= isset($errors['title']) ? ' is-invalid' : '' ?>">
                    <label for="title">Chức danh hiển thị</label>
                    <input id="title" name="title" maxlength="120" required value="<?= htmlspecialchars((string)$old['title']) ?>" placeholder="Ví dụ: Bác sĩ chuyên khoa Y học thể thao"<?= apInvalid($errors, 'title') ?>>
                    <?= apErr($errors, 'title') ?>
                </div>
                <div class="field<?= isset($errors['workplace']) ? ' is-invalid' : '' ?>">
                    <label for="workplace">Nơi công tác</label>
                    <input id="workplace" name="workplace" maxlength="150" required value="<?= htmlspecialchars((string)$old['workplace']) ?>"<?= apInvalid($errors, 'workplace') ?>>
                    <?= apErr($errors, 'workplace') ?>
                </div>
                <div class="field<?= isset($errors['license_no']) ? ' is-invalid' : '' ?>">
                    <label for="license_no">Số chứng chỉ hành nghề</label>
                    <input id="license_no" name="license_no" maxlength="50" required value="<?= htmlspecialchars((string)$old['license_no']) ?>"<?= apInvalid($errors, 'license_no') ?>>
                    <small>Chỉ quản trị viên thấy, dùng để đối chiếu. Người dùng chỉ thấy nhãn "đã kiểm tra chứng chỉ".</small>
                    <?= apErr($errors, 'license_no') ?>
                </div>
                <div class="field<?= isset($errors['certificate_file']) ? ' is-invalid' : '' ?>">
                    <label for="certificate_file">Ảnh hoặc file PDF chứng chỉ hành nghề</label>
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?= COACH_CERT_MAX_BYTES ?>">
                    <input id="certificate_file" name="certificate_file" type="file" required accept="image/jpeg,image/png,image/webp,application/pdf"<?= apInvalid($errors, 'certificate_file') ?>>
                    <small>Bắt buộc. JPG, PNG, WebP hoặc PDF, tối đa 3 MB. Chỉ quản trị viên xem được.<?= $errors ? ' Nếu đơn báo lỗi, hãy chọn lại file.' : '' ?></small>
                    <?= apErr($errors, 'certificate_file') ?>
                </div>
            </fieldset>
            <fieldset class="form-sec">
                <h2><i>2</i>Tư vấn</h2>
                <div class="field<?= isset($errors['topics']) ? ' is-invalid' : '' ?>">
                    <fieldset class="ch-picks">
                        <legend class="ch-legend">Chủ đề nhận tư vấn (chọn 1–<?= EXPERT_TOPICS_MAX ?>)</legend>
                        <?php foreach (HEALTH_TOPICS as $code => $label): if ($code === 'OTHER') continue; ?>
                            <label class="ch-pick"><input type="checkbox" name="topics[]" value="<?= htmlspecialchars($code) ?>"<?= in_array($code, $old['topics_list'], true) ? ' checked' : '' ?>><span><?= htmlspecialchars($label) ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <small>Câu hỏi chủ đề "Vấn đề khác" mọi chuyên gia đều thấy.</small>
                    <?= apErr($errors, 'topics') ?>
                </div>
                <div class="field<?= isset($errors['bio']) ? ' is-invalid' : '' ?>">
                    <label for="bio">Giới thiệu</label>
                    <textarea id="bio" name="bio" maxlength="1500" rows="6" required placeholder="Kinh nghiệm, lĩnh vực thế mạnh, đối tượng thường tư vấn…"<?= apInvalid($errors, 'bio') ?>><?= htmlspecialchars((string)$old['bio']) ?></textarea>
                    <small>Ít nhất 80 ký tự. Hiện cho người dùng ở trang Tư vấn sức khỏe.</small>
                    <span class="ch-count" data-count="bio" aria-live="polite"></span>
                    <?= apErr($errors, 'bio') ?>
                </div>
            </fieldset>
            <fieldset class="form-sec">
                <h2><i>3</i>Cam kết</h2>
                <label class="ch-check"><input type="checkbox" name="agree_truth" value="1"><span>Thông tin và chứng chỉ hành nghề tôi gửi là thật, còn hiệu lực.</span></label>
                <?= apErr($errors, 'agree_truth') ?>
                <label class="ch-check"><input type="checkbox" name="agree_rules" value="1"><span>Tôi chỉ tư vấn để người dùng tham khảo: không chẩn đoán xác định, không kê đơn thuốc qua web, khuyên đi khám hoặc gọi 115 khi có dấu hiệu nguy hiểm, giữ kín thông tin người hỏi.</span></label>
                <?= apErr($errors, 'agree_rules') ?>
            </fieldset>
            <div class="form-actions"><button type="submit" class="btn" data-busy-text="Đang gửi…">Gửi đơn đăng ký</button></div>
        </form>
    <?php endif; ?>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
