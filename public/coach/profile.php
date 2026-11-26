<?php
/**
 * coach/profile.php — HLV tự sửa hồ sơ công khai (chức danh, chuyên môn, giới thiệu...) và bật/tắt nhận học viên.
 * Dùng chung hàm kiểm tra với mẫu đơn (validateCoachApplication, không cần tick cam kết).
 * Số học viên tối đa không được nhỏ hơn số học viên đang tập (không đuổi học viên bằng cách hạ con số).
 * Form riêng "Giá và buổi tập thử" (action=pricing, A-54): giá 1 buổi / 1 tháng, 1-3 buổi thử miễn phí, hướng dẫn thanh toán.
 * Cùng form đó (A-56): tài khoản nhận chuyển khoản (ngân hàng trong whitelist COACH_BANKS, số TK, chủ TK) để học viên
 * có đơn chờ thấy mã VietQR. Kiểm tra ở PHP bằng validateCoachBank(), lưu chung transaction với giá.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';

requireCoach();
$coachId = (int)$_SESSION['user_id'];
$profile = coachProfile($pdo, $coachId);
$errors = [];
$billingOn = coachBillingReady($pdo);
$pricing = $billingOn ? coachPricing($pdo, $coachId) : null;
$bankOn = $billingOn && coachBankReady($pdo);   // A-56: DB đã chạy sql/coach_bank.sql
$bank = $bankOn ? coachBankInfo($pdo, $coachId) : null;
$priceErrors = [];
$priceInput = null;   // giá trị vừa gửi khi có lỗi, để điền lại

// Form giá (A-54): xử lý riêng, không đụng các ô hồ sơ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pricing' && $billingOn && $profile !== null) {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    require_once __DIR__ . '/../includes/audit-log.php';
    $pv = validateCoachPricing($_POST);
    $priceErrors = $pv['errors'];
    // A-56: tài khoản nhận chuyển khoản nằm chung form giá; phần nào lỗi thì không lưu phần nào
    $bv = $bankOn ? validateCoachBank($_POST) : ['data' => null, 'errors' => []];
    $priceErrors += $bv['errors'];
    if (!$priceErrors) {
        try {
            coachTx($pdo, function () use ($pdo, $coachId, $pv, $bv) {
                coachSavePricing($pdo, $coachId, $pv['data']);
                if ($bv['data'] !== null) {
                    coachSaveBank($pdo, $coachId, $bv['data']);
                }
            });
            $bankLog = '';
            if ($bv['data'] !== null) {   // không ghi đủ số tài khoản vào nhật ký, chỉ 4 số cuối
                $bankLog = $bv['data']['bank_bin'] !== null
                    ? ', TK ' . COACH_BANKS[$bv['data']['bank_bin']] . ' ' . coachMaskAccount($bv['data']['bank_account'])
                    : ', không có TK ngân hàng';
            }
            auditLog($pdo, 'coach.pricing', 'coach', $coachId, 'Đặt giá: ' . (coachPriceLine($pv['data']['price_per_session'], $pv['data']['price_per_month']) ?: 'không bán')
                . ', ' . $pv['data']['trial_sessions'] . ' buổi thử' . $bankLog);
            coachFlash('ok', 'Đã lưu giá. Giá mới áp dụng cho đơn mua gói từ bây giờ; số buổi thử mới áp dụng cho học viên gửi yêu cầu sau này.');
        } catch (CoachException $e) {
            coachFlash('error', $e->getMessage());
        }
        header('Location: /coach/profile.php#gia');
        exit;
    }
    $priceInput = ['price_per_session' => is_string($_POST['price_per_session'] ?? null) ? $_POST['price_per_session'] : '',
                   'price_per_month' => is_string($_POST['price_per_month'] ?? null) ? $_POST['price_per_month'] : '',
                   'trial_sessions' => $pv['data']['trial_sessions'], 'payment_note' => $pv['data']['payment_note'],
                   // điền lại đúng chữ vừa gõ (đã chuẩn hóa nhẹ), kể cả khi sai, để HLV sửa tiếp
                   'bank_bin' => is_string($_POST['bank_bin'] ?? null) ? $_POST['bank_bin'] : '',
                   'bank_account' => is_string($_POST['bank_account'] ?? null) ? $_POST['bank_account'] : '',
                   'bank_holder' => coachNormalizeHolder($_POST['bank_holder'] ?? '')];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $v = validateCoachApplication($_POST, false);
    $errors = $v['errors'];
    $fullName = coachNormalizeName($_POST['full_name'] ?? '');
    if ($e = coachNameError($fullName)) {
        $errors['full_name'] = $e;
    }
    $active = (int)($profile['active_clients'] ?? 0);
    if (!isset($errors['max_clients']) && $v['data']['max_clients'] < $active) {
        $errors['max_clients'] = 'Bạn đang có ' . $active . ' học viên, số tối đa không thể nhỏ hơn.';
    }
    if ($profile === null) {
        $errors['_form'] = 'Không tìm thấy hồ sơ HLV. Hãy báo quản trị viên.';
    }
    if (!$errors) {
        coachSaveProfile($pdo, $coachId, $v['data'], ($_POST['accepting'] ?? '') === '1', $fullName);
        $_SESSION['full_name'] = $fullName;   // thanh menu và các trang khác hiện tên mới ngay
        coachFlash('ok', 'Đã lưu hồ sơ HLV.');
        header('Location: /coach/profile.php');
        exit;
    }
    // Có lỗi: điền lại đúng những gì vừa gửi
    $profile = array_merge($profile ?? [], $v['data'], ['accepting' => ($_POST['accepting'] ?? '') === '1' ? 1 : 0, 'full_name' => $fullName]);
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
include __DIR__ . '/../includes/admin-style-extra.php';   // .adm-hint-box và các khung nhắc
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
    <?php if ($priceErrors): ?>
        <div class="adm-errors" role="alert">Giá chưa lưu: vui lòng kiểm tra lại <?= count($priceErrors) ?> mục ở phần Giá và buổi tập thử.</div>
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
                <label for="full_name">Họ và tên hiển thị</label>
                <input class="adm-input" id="full_name" name="full_name" maxlength="100" autocomplete="name" required value="<?= htmlspecialchars((string)($profile['full_name'] ?? '')) ?>"<?= isset($errors['full_name']) ? ' aria-invalid="true" aria-describedby="err-full_name"' : '' ?>>
                <p class="adm-hint">Học viên thấy tên này ở trang Huấn luyện viên, trong tin nhắn và nhận xét.</p>
                <?= fe($errors, 'full_name') ?>
            </div>
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

    <?php if ($billingOn):
        $pv = $priceInput ?? [
            'price_per_session' => $pricing['price_per_session'] !== null ? number_format($pricing['price_per_session'], 0, ',', '.') : '',
            'price_per_month'   => $pricing['price_per_month'] !== null ? number_format($pricing['price_per_month'], 0, ',', '.') : '',
            'trial_sessions'    => $pricing['trial_sessions'],
            'payment_note'      => $pricing['payment_note'],
            'bank_bin'          => $bank['bank_bin'] ?? '',
            'bank_account'      => $bank['bank_account'] ?? '',
            'bank_holder'       => $bank['bank_holder'] ?? '',
        ];
    ?>
    <form class="adm-panel" id="gia" method="post" action="/coach/profile.php" style="margin-top:16px" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="pricing">
        <div class="adm-form-section">
            <h2>Giá và buổi tập thử</h2>
            <p class="adm-hint" style="margin-top:0">Học viên thấy giá ở trang Huấn luyện viên. Mỗi học viên mới được tập thử miễn phí vài buổi đầu; sau đó mua gói buổi hoặc gói tháng, trả tiền trực tiếp cho bạn rồi bạn bấm "Đã nhận tiền" ở trang Lịch hẹn. Hết buổi mà chưa mua gói thì bạn tạm không giao lịch, đặt dinh dưỡng, nhận xét được (vẫn nhắn tin được).</p>
            <?php if ($pricing['price_per_session'] === null && $pricing['price_per_month'] === null): ?>
                <div class="adm-hint-box">Bạn chưa đặt giá. Học viên vẫn tập thử được, nhưng hết buổi thử thì không mua gói được.</div>
            <?php endif; ?>
            <div class="adm-grid">
                <div class="adm-field">
                    <label for="price_per_session">Giá 1 buổi (đ)</label>
                    <input class="adm-input" id="price_per_session" name="price_per_session" inputmode="numeric" maxlength="13" placeholder="Ví dụ 300.000" value="<?= htmlspecialchars((string)$pv['price_per_session']) ?>"<?= isset($priceErrors['price_per_session']) ? ' aria-invalid="true" aria-describedby="err-price_per_session"' : '' ?>>
                    <p class="adm-hint">Để trống nếu không bán theo buổi. Học viên mua gói <?= htmlspecialchars(implode(', ', COACH_SESSION_PACKS)) ?> buổi.</p>
                    <?= fe($priceErrors, 'price_per_session') ?>
                </div>
                <div class="adm-field">
                    <label for="price_per_month">Giá 1 tháng (đ)</label>
                    <input class="adm-input" id="price_per_month" name="price_per_month" inputmode="numeric" maxlength="13" placeholder="Ví dụ 2.500.000" value="<?= htmlspecialchars((string)$pv['price_per_month']) ?>"<?= isset($priceErrors['price_per_month']) ? ' aria-invalid="true" aria-describedby="err-price_per_month"' : '' ?>>
                    <p class="adm-hint">Để trống nếu không bán theo tháng. Một tháng là <?= COACH_MONTH_DAYS ?> ngày, đặt bao nhiêu buổi cũng được.</p>
                    <?= fe($priceErrors, 'price_per_month') ?>
                </div>
                <div class="adm-field">
                    <label for="trial_sessions">Buổi tập thử miễn phí</label>
                    <select class="adm-select" id="trial_sessions" name="trial_sessions">
                        <?php for ($n = COACH_TRIAL_MIN; $n <= COACH_TRIAL_MAX; $n++): ?>
                            <option value="<?= (int)$n ?>"<?= (int)$pv['trial_sessions'] === $n ? ' selected' : '' ?>><?= (int)$n ?> buổi đầu</option>
                        <?php endfor; ?>
                    </select>
                    <p class="adm-hint">Áp dụng cho học viên gửi yêu cầu từ bây giờ.</p>
                    <?= fe($priceErrors, 'trial_sessions') ?>
                </div>
            </div>
            <div class="adm-field">
                <label for="payment_note">Hướng dẫn thanh toán (không bắt buộc)</label>
                <textarea class="adm-textarea" id="payment_note" name="payment_note" maxlength="<?= COACH_PAYMENT_NOTE_MAX ?>" rows="3" placeholder="Ví dụ: Chuyển khoản Vietcombank 0123456789, chủ TK Nguyễn Văn A, nội dung: tên + gói"><?= htmlspecialchars((string)$pv['payment_note']) ?></textarea>
                <p class="adm-hint">Chỉ học viên đang tập với bạn mới thấy, không hiện ở danh sách HLV công khai.</p>
                <?= fe($priceErrors, 'payment_note') ?>
            </div>
        </div>
        <?php if ($bankOn): ?>
        <div class="adm-form-section">
            <h2>Tài khoản nhận chuyển khoản (mã VietQR)</h2>
            <p class="adm-hint" style="margin-top:0">Khi có đủ 3 ô, học viên có đơn chờ thanh toán thấy mã VietQR đã điền sẵn số tiền của đơn và nội dung <b><?= htmlspecialchars(COACH_QR_PREFIX) ?>&lt;mã đơn&gt;</b>, quét bằng app ngân hàng là chuyển được. Tiền vào thẳng tài khoản của bạn, GymSmart không thu hộ; bạn vẫn bấm "Đã nhận tiền" như cũ. Chỉ học viên có đơn chờ với bạn mới thấy. Để trống cả 3 ô nếu không muốn dùng mã QR.</p>
            <div class="adm-grid">
                <div class="adm-field">
                    <label for="bank_bin">Ngân hàng</label>
                    <select class="adm-select" id="bank_bin" name="bank_bin"<?= isset($priceErrors['bank_bin']) ? ' aria-invalid="true" aria-describedby="err-bank_bin"' : '' ?>>
                        <option value="">Chưa chọn</option>
                        <?php foreach (COACH_BANKS as $bin => $bankName): ?>
                            <option value="<?= htmlspecialchars($bin) ?>"<?= (string)$pv['bank_bin'] === $bin ? ' selected' : '' ?>><?= htmlspecialchars($bankName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= fe($priceErrors, 'bank_bin') ?>
                </div>
                <div class="adm-field">
                    <label for="bank_account">Số tài khoản</label>
                    <input class="adm-input" id="bank_account" name="bank_account" inputmode="numeric" autocomplete="off"
                           minlength="<?= COACH_BANK_ACCOUNT_MIN ?>" maxlength="<?= COACH_BANK_ACCOUNT_MAX ?>" pattern="[0-9]{<?= COACH_BANK_ACCOUNT_MIN ?>,<?= COACH_BANK_ACCOUNT_MAX ?>}"
                           value="<?= htmlspecialchars((string)$pv['bank_account']) ?>"<?= isset($priceErrors['bank_account']) ? ' aria-invalid="true" aria-describedby="err-bank_account"' : '' ?>>
                    <p class="adm-hint">Chỉ chữ số, <?= COACH_BANK_ACCOUNT_MIN ?>–<?= COACH_BANK_ACCOUNT_MAX ?> ký tự.</p>
                    <?= fe($priceErrors, 'bank_account') ?>
                </div>
                <div class="adm-field">
                    <label for="bank_holder">Tên chủ tài khoản</label>
                    <input class="adm-input" id="bank_holder" name="bank_holder" autocomplete="off" maxlength="<?= COACH_BANK_HOLDER_MAX ?>"
                           placeholder="NGUYEN VAN A" style="text-transform:uppercase"
                           value="<?= htmlspecialchars((string)$pv['bank_holder']) ?>"<?= isset($priceErrors['bank_holder']) ? ' aria-invalid="true" aria-describedby="err-bank_holder"' : '' ?>>
                    <p class="adm-hint">In hoa không dấu, đúng như tên trên tài khoản ngân hàng.</p>
                    <?= fe($priceErrors, 'bank_holder') ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="adm-bar">
            <button type="submit" class="adm-btn" data-busy-text="Đang lưu…">Lưu giá</button>
        </div>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
