<?php
/**
 * register.php — trang đăng ký tài khoản (A-16: đăng ký 3 bước, thu thập đủ hồ sơ để tính TDEE ngay).
 *
 * Luồng chạy:
 *   1. Mở trang (GET)       → hiện form 3 bước (JS chia bước; tắt JS thì hiện cả 3 phần liền nhau)
 *        Bước 1: Tài khoản   (họ tên, email, mật khẩu + nhập lại)
 *        Bước 2: Cơ thể      (tuổi, giới tính, chiều cao, cân nặng)
 *        Bước 3: Mục tiêu    (mức vận động, trình độ, mục tiêu)
 *   2. Bấm "Tạo tài khoản" (POST) → kiểm tra TOÀN BỘ dữ liệu ở server (JS chỉ để tiện, không thay được bước này)
 *   3. Có lỗi               → hiện lại form ở đúng bước có lỗi, giữ lại những gì đã nhập (trừ mật khẩu)
 *   4. Không lỗi            → lưu user vào DB, chuyển sang trang đăng nhập
 */

require_once __DIR__ . '/includes/auth.php'; // có session + isLoggedIn()
require_once __DIR__ . '/includes/db.php';   // có biến $pdo
require_once __DIR__ . '/includes/csrf.php';

// Đã đăng nhập rồi thì không cần đăng ký nữa
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

// Các lựa chọn hợp lệ: khóa trùng ENUM trong schema.sql (giống profile.php)
$genderOptions = ['MALE' => 'Nam', 'FEMALE' => 'Nữ', 'OTHER' => 'Khác'];
$activityOptions = [
    'SEDENTARY'    => 'Ít vận động (ngồi nhiều, hầu như không tập)',
    'LIGHT'        => 'Vận động nhẹ (1–3 buổi/tuần)',
    'MODERATE'     => 'Vận động vừa (3–5 buổi/tuần)',
    'ACTIVE'       => 'Vận động nhiều (6–7 buổi/tuần)',
    'EXTRA_ACTIVE' => 'Rất nhiều (tập nặng hằng ngày hoặc lao động nặng)',
];
$experienceOptions = [
    'BEGINNER'     => ['Mới tập', 'dưới 6 tháng'],
    'INTERMEDIATE' => ['Trung bình', '6 tháng – 2 năm'],
    'ADVANCED'     => ['Nâng cao', 'trên 2 năm'],
];
$goalOptions = [
    'LOSE_FAT'    => ['Giảm mỡ', 'Ăn thâm hụt calo nhẹ'],
    'MAINTAIN'    => ['Duy trì', 'Giữ cân nặng hiện tại'],
    'GAIN_MUSCLE' => ['Tăng cơ', 'Ăn dư calo, nhiều protein'],
];

// Mỗi ô thuộc bước nào: để biết phải mở lại bước nào khi có lỗi
$stepOf = [
    'full_name' => 1, 'email' => 1, 'password' => 1, 'password2' => 1,
    'age' => 2, 'gender' => 2, 'height_cm' => 2, 'weight_kg' => 2,
    'activity_level' => 3, 'experience_level' => 3, 'goal' => 3,
];

$errors = [];                 // dạng [tên ô => lời nhắn]
$v = array_fill_keys(array_keys($stepOf), '');
unset($v['password'], $v['password2']); // không bao giờ gửi mật khẩu ngược lại trình duyệt

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Chống CSRF: form phải gửi kèm mã bí mật lưu trong session (xem includes/csrf.php)
    if (!verifyCsrf()) {
        showCsrfError();
    }

    // trim() bỏ khoảng trắng thừa; "?? ''" tránh lỗi khi thiếu trường.
    // Ép về chuỗi: gửi kiểu full_name[]=x thì mảng bị coi như trống thay vì gây lỗi PHP.
    $in = fn(string $k): string => is_string($_POST[$k] ?? null) ? $_POST[$k] : '';

    $v['full_name']        = trim($in('full_name'));
    $v['email']            = trim($in('email'));
    $password              = $in('password');
    $password2             = $in('password2');
    $v['age']              = trim($in('age'));
    $v['height_cm']        = str_replace(',', '.', trim($in('height_cm'))); // cho phép gõ 1,75 kiểu Việt Nam
    $v['weight_kg']        = str_replace(',', '.', trim($in('weight_kg')));
    $v['gender']           = $in('gender');
    $v['activity_level']   = $in('activity_level');
    $v['experience_level'] = $in('experience_level');
    $v['goal']             = $in('goal');

    // ===== Bước 1: tài khoản =====
    if ($v['full_name'] === '') {
        $errors['full_name'] = 'Vui lòng nhập họ và tên.';
    } elseif (mb_strlen($v['full_name']) > 100) {
        $errors['full_name'] = 'Họ và tên tối đa 100 ký tự.';
    }

    if ($v['email'] === '') {
        $errors['email'] = 'Vui lòng nhập email.';
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 255) {
        $errors['email'] = 'Email không đúng định dạng.';
    }

    // Tối thiểu 8 ký tự cho mật khẩu đỡ bị đoán ra. Tối đa 72 BYTE vì bcrypt chỉ đọc 72 byte đầu.
    if (strlen($password) < 8) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Mật khẩu tối đa 72 ký tự.';
    } elseif ($password !== $password2) {
        $errors['password2'] = 'Nhập lại mật khẩu chưa khớp.';
    }

    // ===== Bước 2: cơ thể =====
    $ageValue = filter_var($v['age'], FILTER_VALIDATE_INT);
    if ($ageValue === false || $ageValue < 10 || $ageValue > 100) {
        $errors['age'] = 'Tuổi phải là số nguyên từ 10 đến 100.';
    }
    if (!array_key_exists($v['gender'], $genderOptions)) {
        $errors['gender'] = 'Vui lòng chọn giới tính.';
    }
    $heightValue = filter_var($v['height_cm'], FILTER_VALIDATE_FLOAT);
    if ($heightValue === false || $heightValue < 100 || $heightValue > 250) {
        $errors['height_cm'] = 'Chiều cao phải từ 100 đến 250 cm.';
    }
    $weightValue = filter_var($v['weight_kg'], FILTER_VALIDATE_FLOAT);
    if ($weightValue === false || $weightValue < 30 || $weightValue > 300) {
        $errors['weight_kg'] = 'Cân nặng phải từ 30 đến 300 kg.';
    }

    // ===== Bước 3: mục tiêu (chỉ nhận giá trị nằm trong danh sách cho phép, vì HTML có thể bị sửa) =====
    if (!array_key_exists($v['activity_level'], $activityOptions)) {
        $errors['activity_level'] = 'Vui lòng chọn mức độ vận động.';
    }
    if (!array_key_exists($v['experience_level'], $experienceOptions)) {
        $errors['experience_level'] = 'Vui lòng chọn trình độ tập luyện.';
    }
    if (!array_key_exists($v['goal'], $goalOptions)) {
        $errors['goal'] = 'Vui lòng chọn mục tiêu.';
    }

    // ===== Kiểm tra email đã tồn tại chưa (chỉ khi dữ liệu đã hợp lệ, đỡ tốn 1 lần gọi DB) =====
    // Dấu "?" là chỗ giữ chỗ: $email gửi RIÊNG khỏi câu SQL nên nhập gì cũng không phá được (chống SQL Injection).
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$v['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email này đã được sử dụng.';
        }
    }

    // ===== Mã hóa mật khẩu và lưu user mới =====
    if (empty($errors)) {
        // password_hash tạo chuỗi băm một chiều. Không bao giờ lưu mật khẩu gốc vào DB.
        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash, age, height_cm, weight_kg, gender, activity_level, experience_level, goal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $v['full_name'], $v['email'], password_hash($password, PASSWORD_DEFAULT),
            $ageValue, $heightValue, $weightValue,
            $v['gender'], $v['activity_level'], $v['experience_level'], $v['goal'],
        ]);
        // Cột role tự nhận giá trị mặc định 'USER' theo schema.sql

        // exit bắt buộc: nếu thiếu, PHP vẫn chạy tiếp các dòng phía dưới.
        header('Location: /login.php');
        exit;
    }
}

// Bước cần mở khi trang hiện ra: bước nhỏ nhất có lỗi (hoặc bước 1 nếu chưa có lỗi)
$startStep = 1;
if ($errors) {
    $startStep = min(array_map(fn($k) => $stepOf[$k], array_keys($errors)));
}

/** Thông báo lỗi nằm ngay dưới ô nhập. */
function fieldError(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="rg-err" id="err-' . htmlspecialchars($key) . '" role="alert">' . htmlspecialchars($errors[$key]) . '</p>' : '';
}

$eye = '<svg class="pw__eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="pw__off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.9 10.9 0 0112 19c-6.4 0-10-7-10-7a18.5 18.5 0 015.1-5.9M9.9 5.2A10 10 0 0112 5c6.4 0 10 7 10 7a18.6 18.6 0 01-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 01-4.2-4.2"/></svg>';

$pageTitle = 'Đăng ký';
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/register.css">

<div class="auth">
    <aside class="auth__aside">
        <?= gsLogo('light', false, 40) ?>
        <h2>Bắt đầu hành trình<br>của bạn <em>hôm nay</em></h2>
        <p>Thư viện bài tập có video, lịch tập cá nhân hóa, TDEE/Macro và theo dõi tiến trình, tất cả trong một nơi.</p>
        <ul class="auth__points">
            <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>Video hướng dẫn chuẩn kỹ thuật</li>
            <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>Lịch tập theo trình độ và mục tiêu</li>
            <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>Tính TDEE và macro khoa học</li>
        </ul>
    </aside>

    <section class="auth__main">
        <div class="auth__box rg">
            <h1>Tạo tài khoản</h1>
            <p class="pg-sub">Miễn phí. Điền đủ thông tin để GymSmart tính sẵn TDEE và gợi ý lịch tập cho bạn.</p>

            <!-- Thanh tiến độ 3 bước (js/register.js cập nhật) -->
            <ol class="rg-steps" id="rg-steps" aria-label="Các bước đăng ký">
                <li class="is-current" data-step="1"><i>1</i><span>Tài khoản</span></li>
                <li data-step="2"><i>2</i><span>Cơ thể</span></li>
                <li data-step="3"><i>3</i><span>Mục tiêu</span></li>
            </ol>

            <?php if (!empty($errors)): ?>
                <div class="alert alert--error" role="alert">Vui lòng kiểm tra lại <?= count($errors) ?> ô được đánh dấu bên dưới.</div>
            <?php endif; ?>

            <form method="post" action="/register.php" id="rg-form" data-start="<?= (int)$startStep ?>" novalidate>
                <?= csrfField() ?>

                <!-- ===== Bước 1 ===== -->
                <fieldset class="rg-step" data-step="1">
                    <legend class="sr-only">Bước 1: tài khoản</legend>
                    <div class="field<?= isset($errors['full_name']) ? ' has-err' : '' ?>">
                        <label for="full_name">Họ và tên</label>
                        <input type="text" id="full_name" name="full_name" autocomplete="name" maxlength="100" value="<?= htmlspecialchars($v['full_name']) ?>" required>
                        <?= fieldError($errors, 'full_name') ?>
                    </div>
                    <div class="field<?= isset($errors['email']) ? ' has-err' : '' ?>">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" autocomplete="email" maxlength="255" value="<?= htmlspecialchars($v['email']) ?>" required>
                        <?= fieldError($errors, 'email') ?>
                    </div>
                    <div class="field<?= isset($errors['password']) ? ' has-err' : '' ?>">
                        <label for="password">Mật khẩu</label>
                        <div class="pw">
                            <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>
                            <button type="button" class="pw__btn" data-pw-toggle="password" aria-label="Hiện mật khẩu" aria-pressed="false"><?= $eye /* audit:ok: biểu tượng SVG viết sẵn trong file, không có dữ liệu người dùng */ ?></button>
                        </div>
                        <div class="rg-meter" id="rg-meter" aria-hidden="true"><span></span><span></span><span></span><span></span><em id="rg-meter-text">Từ 8 đến 72 ký tự</em></div>
                        <?= fieldError($errors, 'password') ?>
                    </div>
                    <div class="field<?= isset($errors['password2']) ? ' has-err' : '' ?>">
                        <label for="password2">Nhập lại mật khẩu</label>
                        <div class="pw">
                            <input type="password" id="password2" name="password2" autocomplete="new-password" minlength="8" maxlength="72" required>
                            <button type="button" class="pw__btn" data-pw-toggle="password2" aria-label="Hiện mật khẩu" aria-pressed="false"><?= $eye /* audit:ok */ ?></button>
                        </div>
                        <?= fieldError($errors, 'password2') ?>
                    </div>
                </fieldset>

                <!-- ===== Bước 2 ===== -->
                <fieldset class="rg-step" data-step="2">
                    <legend class="sr-only">Bước 2: cơ thể</legend>
                    <div class="rg-row">
                        <div class="field<?= isset($errors['age']) ? ' has-err' : '' ?>">
                            <label for="age">Tuổi</label>
                            <input type="number" id="age" name="age" min="10" max="100" inputmode="numeric" value="<?= htmlspecialchars($v['age']) ?>" required>
                            <?= fieldError($errors, 'age') ?>
                        </div>
                        <fieldset class="rg-seg<?= isset($errors['gender']) ? ' has-err' : '' ?>">
                            <legend>Giới tính</legend>
                            <div class="rg-seg__row">
                                <?php foreach ($genderOptions as $val => $label): ?>
                                    <label class="rg-seg__opt"><input type="radio" name="gender" value="<?= htmlspecialchars($val) ?>" <?= $v['gender'] === $val ? 'checked' : '' ?> required><span><b><?= htmlspecialchars($label) ?></b></span></label>
                                <?php endforeach; ?>
                            </div>
                            <?= fieldError($errors, 'gender') ?>
                        </fieldset>
                    </div>
                    <div class="rg-row">
                        <div class="field<?= isset($errors['height_cm']) ? ' has-err' : '' ?>">
                            <label for="height_cm">Chiều cao <em>cm</em></label>
                            <input type="number" id="height_cm" name="height_cm" min="100" max="250" step="0.1" inputmode="decimal" value="<?= htmlspecialchars($v['height_cm']) ?>" required>
                            <?= fieldError($errors, 'height_cm') ?>
                        </div>
                        <div class="field<?= isset($errors['weight_kg']) ? ' has-err' : '' ?>">
                            <label for="weight_kg">Cân nặng <em>kg</em></label>
                            <input type="number" id="weight_kg" name="weight_kg" min="30" max="300" step="0.1" inputmode="decimal" value="<?= htmlspecialchars($v['weight_kg']) ?>" required>
                            <?= fieldError($errors, 'weight_kg') ?>
                        </div>
                    </div>
                    <p class="rg-bmi" id="rg-bmi" hidden></p>
                </fieldset>

                <!-- ===== Bước 3 ===== -->
                <fieldset class="rg-step" data-step="3">
                    <legend class="sr-only">Bước 3: mục tiêu</legend>
                    <div class="field<?= isset($errors['activity_level']) ? ' has-err' : '' ?>">
                        <label for="activity_level">Mức độ vận động hằng tuần</label>
                        <select id="activity_level" name="activity_level" required>
                            <option value="">-- Chọn --</option>
                            <?php foreach ($activityOptions as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>" <?= $v['activity_level'] === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= fieldError($errors, 'activity_level') ?>
                    </div>

                    <fieldset class="rg-seg<?= isset($errors['experience_level']) ? ' has-err' : '' ?>">
                        <legend>Trình độ tập luyện</legend>
                        <div class="rg-seg__row">
                            <?php foreach ($experienceOptions as $val => [$short, $sub]): ?>
                                <label class="rg-seg__opt"><input type="radio" name="experience_level" value="<?= htmlspecialchars($val) ?>" <?= $v['experience_level'] === $val ? 'checked' : '' ?> required><span><b><?= htmlspecialchars($short) ?></b><small><?= htmlspecialchars($sub) ?></small></span></label>
                            <?php endforeach; ?>
                        </div>
                        <?= fieldError($errors, 'experience_level') ?>
                    </fieldset>

                    <fieldset class="rg-seg<?= isset($errors['goal']) ? ' has-err' : '' ?>">
                        <legend>Mục tiêu của bạn</legend>
                        <div class="rg-seg__row">
                            <?php foreach ($goalOptions as $val => [$short, $sub]): ?>
                                <label class="rg-seg__opt"><input type="radio" name="goal" value="<?= htmlspecialchars($val) ?>" <?= $v['goal'] === $val ? 'checked' : '' ?> required><span><b><?= htmlspecialchars($short) ?></b><small><?= htmlspecialchars($sub) ?></small></span></label>
                            <?php endforeach; ?>
                        </div>
                        <?= fieldError($errors, 'goal') ?>
                    </fieldset>
                </fieldset>

                <div class="rg-nav">
                    <button type="button" class="btn btn--ghost" id="rg-back" hidden>Quay lại</button>
                    <button type="button" class="btn" id="rg-next" hidden>Tiếp tục</button>
                    <button type="submit" class="btn" id="rg-submit">Tạo tài khoản</button>
                </div>
            </form>

            <p class="auth__alt">Đã có tài khoản? <a href="/login.php">Đăng nhập</a></p>
        </div>
    </section>
</div>

<script src="/js/password-toggle.js"></script>
<script src="/js/register.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
