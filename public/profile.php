<?php
/**
 * profile.php — hồ sơ cá nhân (A-15: giao diện mới + ảnh đại diện).
 * Người dùng nhập tuổi, chiều cao, cân nặng, giới tính, mức vận động, trình độ, mục tiêu.
 * Đây là dữ liệu đầu vào cho TDEE/Macro (C) và gợi ý lịch tập (B).
 *
 * Trang có 4 form, trường ẩn "form" cho biết form nào vừa được gửi:
 *   profile        → lưu thông tin hồ sơ
 *   avatar         → tải ảnh đại diện mới (JPG/PNG/WebP, tối đa 2 MB)
 *   avatar_remove  → xóa ảnh đại diện (quay về chữ cái đầu)
 *   password       → đổi mật khẩu
 *
 * Ảnh đại diện: server KHÔNG giữ file người dùng gửi lên. Ảnh được đọc lại bằng GD, cắt vuông giữa,
 * thu về 400x400 và lưu thành JPEG mới với tên ngẫu nhiên (xóa luôn EXIF/mã lạ giấu trong file).
 * Cột users.avatar chỉ lưu đường dẫn tương đối, ví dụ "uploads/avatars/u2-1a2b3c4d5e6f.jpg".
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';

requireLogin();

// Khóa trùng ENUM trong schema.sql, giá trị là chữ hiển thị
$genderOptions = ['MALE' => 'Nam', 'FEMALE' => 'Nữ', 'OTHER' => 'Khác'];
$activityOptions = [
    'SEDENTARY'    => 'Ít vận động (ngồi nhiều, hầu như không tập)',
    'LIGHT'        => 'Vận động nhẹ (1–3 buổi/tuần)',
    'MODERATE'     => 'Vận động vừa (3–5 buổi/tuần)',
    'ACTIVE'       => 'Vận động nhiều (6–7 buổi/tuần)',
    'EXTRA_ACTIVE' => 'Rất nhiều (tập nặng hằng ngày hoặc lao động nặng)',
];
$goalOptions = ['LOSE_FAT' => 'Giảm mỡ', 'MAINTAIN' => 'Duy trì', 'GAIN_MUSCLE' => 'Tăng cơ'];
// Trình độ tập luyện: dùng để gợi ý lịch tập (B). Giá trị 1 = tên ngắn, giá trị 2 = mô tả
$experienceOptions = [
    'BEGINNER'     => ['Mới tập', 'dưới 6 tháng'],
    'INTERMEDIATE' => ['Trung bình', '6 tháng – 2 năm'],
    'ADVANCED'     => ['Nâng cao', 'trên 2 năm'],
];

// Lấy id từ SESSION, KHÔNG lấy từ form/URL (nếu lấy từ form, người dùng có thể sửa số id để sửa hồ sơ người khác)
$userId = (int)$_SESSION['user_id'];

// ---------- Cấu hình ảnh đại diện ----------
const AVATAR_DIR_REL  = 'uploads/avatars';
const AVATAR_MAX_BYTES = 2 * 1024 * 1024; // 2 MB
const AVATAR_SIZE      = 400;             // ảnh lưu ra 400x400 px

/** Đường dẫn ảnh chỉ hợp lệ khi đúng dạng uploads/avatars/<tên an toàn>.jpg (chống đường dẫn lạ trong DB). */
function avatarPathOk($path): bool
{
    return is_string($path) && preg_match('#^uploads/avatars/[A-Za-z0-9._-]+\.jpg$#', $path) === 1;
}

/** Xóa file ảnh cũ trên đĩa (chỉ khi đường dẫn hợp lệ). */
function avatarDeleteFile($path): void
{
    if (avatarPathOk($path)) {
        $file = __DIR__ . '/' . $path;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * Kiểm tra và xử lý ảnh tải lên. Trả về [đường dẫn mới, null] nếu được, hoặc [null, 'lời nhắn lỗi'].
 */
function avatarProcess(array $file, int $userId): array
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) {
        return [null, 'Bạn chưa chọn ảnh.'];
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'Ảnh quá lớn. Vui lòng chọn ảnh tối đa 2 MB.'];
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'Tải ảnh lên không thành công, vui lòng thử lại.'];
    }
    if ((int)$file['size'] > AVATAR_MAX_BYTES) {
        return [null, 'Ảnh quá lớn. Vui lòng chọn ảnh tối đa 2 MB.'];
    }
    if (!extension_loaded('gd')) {
        error_log('profile.php: thiếu extension GD (bật extension=gd trong php.ini).');
        return [null, 'Máy chủ chưa bật thư viện xử lý ảnh (GD). Báo người quản trị để bật.'];
    }

    // Kiểm tra theo NỘI DUNG file (không tin tên file hay loại file do trình duyệt khai báo)
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $loaders = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];
    if (!isset($loaders[$mime]) || !function_exists($loaders[$mime])) {
        return [null, 'Chỉ nhận ảnh JPG, PNG hoặc WebP.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || $info[0] < 1 || $info[1] < 1) {
        return [null, 'File này không phải ảnh hợp lệ.'];
    }
    if ($info[0] > 6000 || $info[1] > 6000) { // tránh ảnh khổng lồ làm tràn bộ nhớ khi giải nén
        return [null, 'Kích thước ảnh quá lớn (tối đa 6000 x 6000 px).'];
    }

    $src = @$loaders[$mime]($file['tmp_name']);
    if (!$src) {
        return [null, 'Không đọc được ảnh, hãy thử ảnh khác.'];
    }
    // JPEG có thể bị xoay do EXIF: xoay lại cho đúng chiều trước khi cắt
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rot !== 0) {
            $rotated = imagerotate($src, $rot, 0);
            if ($rotated) {
                $src = $rotated;
            }
        }
    }

    // Cắt hình vuông ở giữa rồi thu về AVATAR_SIZE
    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $dst = imagecreatetruecolor(AVATAR_SIZE, AVATAR_SIZE);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // nền trắng cho ảnh PNG trong suốt
    imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), AVATAR_SIZE, AVATAR_SIZE, $side, $side);

    $dir = __DIR__ . '/' . AVATAR_DIR_REL;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        error_log('profile.php: không tạo được thư mục ' . $dir);
        return [null, 'Máy chủ không lưu được ảnh (thư mục uploads/avatars không ghi được).'];
    }
    $rel = AVATAR_DIR_REL . '/u' . $userId . '-' . bin2hex(random_bytes(6)) . '.jpg'; // tên ngẫu nhiên, mỗi lần một tên mới
    if (!@imagejpeg($dst, __DIR__ . '/' . $rel, 86)) {
        error_log('profile.php: imagejpeg thất bại khi ghi ' . $rel);
        return [null, 'Máy chủ không lưu được ảnh (không ghi được vào uploads/avatars).'];
    }
    return [$rel, null];
}

// ---------- Đọc thông tin hiện tại ----------
$baseCols = 'full_name, email, age, height_cm, weight_kg, gender, activity_level, experience_level, goal';
$avatarReady = true; // false nếu bảng users chưa có cột avatar (chưa import sql/users_avatar.sql)
try {
    $stmt = $pdo->prepare("SELECT $baseCols, avatar FROM users WHERE id = ?");
    $stmt->execute([$userId]);
} catch (PDOException $e) {
    $avatarReady = false;
    $stmt = $pdo->prepare("SELECT $baseCols FROM users WHERE id = ?");
    $stmt->execute([$userId]);
}
$user = $stmt->fetch();

// Session còn nhưng user đã bị xóa khỏi DB → đăng xuất cho an toàn
if (!$user) {
    $_SESSION = [];
    session_destroy();
    header('Location: /login.php');
    exit;
}
$user['avatar'] = $avatarReady && avatarPathOk($user['avatar'] ?? null) ? $user['avatar'] : null;

$errors = [];
$avatarError = '';
$form = $user;
$formName = $_POST['form'] ?? 'profile';

// Ảnh vượt giới hạn post_max_size của PHP thì cả $_POST lẫn $_FILES đều rỗng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $avatarError = 'Ảnh quá lớn so với giới hạn của máy chủ. Vui lòng chọn ảnh tối đa 2 MB.';
    $formName = 'none';
}

// ===== Ảnh đại diện: tải lên =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formName === 'avatar') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    if (!$avatarReady) {
        $avatarError = 'Chưa thể đổi ảnh: cần import sql/users_avatar.sql vào database trước.';
    } else {
        [$newPath, $avatarError] = avatarProcess($_FILES['avatar'] ?? [], $userId);
        if ($newPath !== null) {
            $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$newPath, $userId]);
            avatarDeleteFile($user['avatar']);
            $_SESSION['avatar'] = $newPath;
            header('Location: /profile.php?avatar=1');
            exit;
        }
    }
}

// ===== Ảnh đại diện: xóa =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formName === 'avatar_remove') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    if ($avatarReady) {
        $pdo->prepare('UPDATE users SET avatar = NULL WHERE id = ?')->execute([$userId]);
        avatarDeleteFile($user['avatar']);
        $_SESSION['avatar'] = null;
    }
    header('Location: /profile.php?avatar=0');
    exit;
}

// ===== Lưu hồ sơ =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formName === 'profile') {
    if (!verifyCsrf()) {
        showCsrfError();
    }

    $fullName   = trim($_POST['full_name'] ?? '');
    $age        = trim($_POST['age'] ?? '');
    $height     = str_replace(',', '.', trim($_POST['height_cm'] ?? '')); // cho phép gõ 1,75 kiểu Việt Nam
    $weight     = str_replace(',', '.', trim($_POST['weight_kg'] ?? ''));
    $gender     = $_POST['gender'] ?? '';
    $activity   = $_POST['activity_level'] ?? '';
    $experience = $_POST['experience_level'] ?? '';
    $goal       = $_POST['goal'] ?? '';

    $form = array_merge($user, [
        'full_name' => $fullName, 'age' => $age, 'height_cm' => $height, 'weight_kg' => $weight,
        'gender' => $gender, 'activity_level' => $activity, 'experience_level' => $experience, 'goal' => $goal,
    ]);

    // ===== Kiểm tra dữ liệu (validate phía server, bắt buộc) =====
    if ($fullName === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    }
    $ageValue = filter_var($age, FILTER_VALIDATE_INT);
    if ($ageValue === false || $ageValue < 10 || $ageValue > 100) {
        $errors[] = 'Tuổi phải là số nguyên từ 10 đến 100.';
    }
    $heightValue = filter_var($height, FILTER_VALIDATE_FLOAT);
    if ($heightValue === false || $heightValue < 100 || $heightValue > 250) {
        $errors[] = 'Chiều cao phải từ 100 đến 250 cm.';
    }
    $weightValue = filter_var($weight, FILTER_VALIDATE_FLOAT);
    if ($weightValue === false || $weightValue < 30 || $weightValue > 300) {
        $errors[] = 'Cân nặng phải từ 30 đến 300 kg.';
    }
    // Ô chọn vẫn có thể bị sửa HTML nên chỉ nhận giá trị nằm trong danh sách cho phép (whitelist)
    if (!array_key_exists($gender, $genderOptions)) {
        $errors[] = 'Vui lòng chọn giới tính.';
    }
    if (!array_key_exists($activity, $activityOptions)) {
        $errors[] = 'Vui lòng chọn mức độ vận động.';
    }
    if (!array_key_exists($experience, $experienceOptions)) {
        $errors[] = 'Vui lòng chọn trình độ tập luyện.';
    }
    if (!array_key_exists($goal, $goalOptions)) {
        $errors[] = 'Vui lòng chọn mục tiêu.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "UPDATE users
             SET full_name = ?, age = ?, height_cm = ?, weight_kg = ?,
                 gender = ?, activity_level = ?, experience_level = ?, goal = ?
             WHERE id = ?"
        );
        $stmt->execute([$fullName, $ageValue, $heightValue, $weightValue, $gender, $activity, $experience, $goal, $userId]);
        $_SESSION['full_name'] = $fullName; // menu/trang khác hiện tên mới
        header('Location: /profile.php?saved=1'); // Post/Redirect/Get: bấm F5 không gửi lại form
        exit;
    }
}

// ===== Đổi mật khẩu =====
$pwErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formName === 'password') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $currentPw = $_POST['current_password'] ?? '';
    $newPw     = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    // Phải nhập đúng mật khẩu hiện tại: mượn máy đang đăng nhập cũng không đổi được
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $hash = (string)$stmt->fetchColumn();

    if ($currentPw === '' || !password_verify($currentPw, $hash)) {
        $pwErrors[] = 'Mật khẩu hiện tại không đúng.';
    } elseif (strlen($newPw) < 8) {
        $pwErrors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    } elseif (strlen($newPw) > 72) {
        $pwErrors[] = 'Mật khẩu mới tối đa 72 ký tự.'; // bcrypt chỉ dùng 72 byte đầu
    } elseif ($newPw === $currentPw) {
        $pwErrors[] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    } elseif ($newPw !== $confirmPw) {
        $pwErrors[] = 'Nhập lại mật khẩu mới chưa khớp.';
    }

    if (empty($pwErrors)) {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);
        session_regenerate_id(true); // mã phiên cũ (nếu bị lộ) không còn dùng được
        header('Location: /profile.php?pw=1#bao-mat');
        exit;
    }
}

// ---------- Số liệu hiển thị ở cột bên trái ----------
$name = trim((string)$user['full_name']);
$parts = $name === '' ? [] : preg_split('/\s+/u', $name);
$initial = $parts ? mb_strtoupper(mb_substr(end($parts), 0, 1)) : '?';
$h = (float)$user['height_cm'];
$w = (float)$user['weight_kg'];
$bmi = ($h > 0 && $w > 0) ? $w / (($h / 100) ** 2) : null;
$bmiLabel = $bmi === null ? '' : ($bmi < 18.5 ? 'Thiếu cân' : ($bmi < 25 ? 'Bình thường' : ($bmi < 30 ? 'Thừa cân' : 'Béo phì')));
$bmiTone  = $bmi === null ? 'flat' : ($bmi < 18.5 ? 'low' : ($bmi < 25 ? 'ok' : ($bmi < 30 ? 'high' : 'bad')));
$bmiPos   = $bmi === null ? 0 : max(0, min(100, ($bmi - 15) / 20 * 100)); // thang 15 đến 35, kẹp trong khung

// Mục tiêu dinh dưỡng gần nhất (C-1: bảng nutrition_targets) và số đo gần nhất (A-14: body_metrics). Bảng chưa có thì bỏ qua.
$target = null;
$lastMetric = null;
$since = '';
try {
    $st = $pdo->prepare('SELECT target_kcal, protein_g FROM nutrition_targets WHERE user_id = ? ORDER BY calculated_date DESC, id DESC LIMIT 1');
    $st->execute([$userId]);
    $target = $st->fetch() ?: null;
} catch (PDOException $e) { error_log('profile.php: nutrition_targets: ' . $e->getMessage()); }
try {
    $st = $pdo->prepare('SELECT recorded_date, weight_kg FROM body_metrics WHERE user_id = ? ORDER BY recorded_date DESC, id DESC LIMIT 1');
    $st->execute([$userId]);
    $lastMetric = $st->fetch() ?: null;
} catch (PDOException $e) { error_log('profile.php: body_metrics: ' . $e->getMessage()); }
try {
    $st = $pdo->prepare('SELECT created_at FROM users WHERE id = ?');
    $st->execute([$userId]);
    $ts = strtotime((string)$st->fetchColumn());
    $since = $ts ? date('m/Y', $ts) : '';
} catch (PDOException $e) { /* bỏ qua */ }

/** Số gọn: 78.0 -> 78, 78.6 -> 78.6 */
function nice($v, int $dec = 1): string
{
    return rtrim(rtrim(number_format((float)$v, $dec, '.', ''), '0'), '.');
}

$eyeSvg = '<svg class="pw__eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="pw__off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.9 10.9 0 0112 19c-6.4 0-10-7-10-7a18.5 18.5 0 015.1-5.9M9.9 5.2A10 10 0 0112 5c6.4 0 10 7 10 7a18.6 18.6 0 01-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 01-4.2-4.2"/></svg>';

/** In một ô mật khẩu có nút hiện/ẩn. $id vừa là id vừa là name. $col = số cột (trên lưới 12 cột) */
function pwField(string $id, string $label, string $autocomplete, string $eyeSvg, int $col, string $extra = ''): void
{
    echo '<div class="field pf-c' . $col . '"><label for="' . $id . '">' . htmlspecialchars($label) . '</label>'
       . '<div class="pw"><input type="password" id="' . $id . '" name="' . $id . '" autocomplete="' . $autocomplete . '" required ' . $extra . '>'
       . '<button type="button" class="pw__btn" data-pw-toggle="' . $id . '" aria-label="Hiện mật khẩu" aria-pressed="false">' . $eyeSvg . '</button>'
       . '</div></div>';
}

$pageTitle = 'Hồ sơ cá nhân';
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/profile.css">

<div class="pf">
    <div class="pf-top">
        <h1>Hồ sơ cá nhân</h1>
        <p>Thông tin này dùng để tính TDEE, macro và gợi ý lịch tập phù hợp với bạn.</p>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert--ok" role="status">Đã lưu hồ sơ.</div>
    <?php endif; ?>
    <?php if (isset($_GET['avatar'])): ?>
        <div class="alert alert--ok" role="status"><?= $_GET['avatar'] === '1' ? 'Đã cập nhật ảnh đại diện.' : 'Đã xóa ảnh đại diện.' ?></div>
    <?php endif; ?>
    <?php if ($avatarError !== ''): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($avatarError) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert--error" role="alert">
            <ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="pf-layout">
        <!-- ===== Cột trái: ảnh đại diện + chỉ số ===== -->
        <aside class="pf-side">
            <section class="pf-card pf-me" aria-label="Ảnh đại diện">
                <div class="pf-ava__pic" id="pf-ava-pic">
                    <?php if ($user['avatar']): ?>
                        <img src="/<?= htmlspecialchars($user['avatar']) ?>" alt="Ảnh đại diện của <?= htmlspecialchars($name) ?>" id="pf-ava-img" width="120" height="120">
                    <?php else: ?>
                        <span class="pf-ava__initial" id="pf-ava-initial"><?= htmlspecialchars($initial) ?></span>
                    <?php endif; ?>
                </div>
                <h2 class="pf-me__name"><?= htmlspecialchars($name !== '' ? $name : 'Chưa đặt tên') ?></h2>
                <p class="pf-me__mail"><?= htmlspecialchars($user['email']) ?></p>
                <?php if ($since !== ''): ?><p class="pf-me__since">Tham gia từ <?= htmlspecialchars($since) ?></p><?php endif; ?>

                <?php if ($avatarReady): ?>
                    <form method="post" action="/profile.php" enctype="multipart/form-data" class="pf-ava__form" id="pf-ava-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="form" value="avatar">
                        <input type="file" id="pf-file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only">
                        <label for="pf-file" class="btn btn--ghost pf-pick" id="pf-pick">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            <?= $user['avatar'] ? 'Đổi ảnh' : 'Tải ảnh lên' ?>
                        </label>
                        <div class="pf-ava__bar" id="pf-ava-bar" hidden>
                            <button type="submit" class="btn pf-btn-sm">Lưu ảnh</button>
                            <button type="button" class="btn btn--ghost pf-btn-sm" id="pf-ava-cancel">Hủy</button>
                        </div>
                        <p class="pf-ava__msg" id="pf-ava-msg" role="alert" hidden></p>
                    </form>
                    <p class="pf-me__hint" id="pf-ava-hint">JPG, PNG hoặc WebP · tối đa 2 MB</p>
                    <?php if ($user['avatar']): ?>
                        <form method="post" action="/profile.php" class="pf-ava__rm" data-confirm="Xóa ảnh đại diện hiện tại?">
                            <?= csrfField() ?>
                            <input type="hidden" name="form" value="avatar_remove">
                            <button type="submit" class="pf-link">Xóa ảnh</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="pf-me__hint pf-me__hint--warn">Cần import <code>sql/users_avatar.sql</code> để đổi ảnh.</p>
                <?php endif; ?>
            </section>

            <section class="pf-card pf-metrics" aria-label="Chỉ số của bạn">
                <h2>Chỉ số của bạn</h2>
                <dl>
                    <div class="pf-m">
                        <dt>BMI</dt>
                        <dd>
                            <?php if ($bmi !== null): ?>
                                <b><?= htmlspecialchars(number_format($bmi, 1)) ?></b> <span class="pf-tag pf-tag--<?= $bmiTone ?>"><?= htmlspecialchars($bmiLabel) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                        <?php if ($bmi !== null): ?>
                            <div class="pf-scale" aria-hidden="true"><i style="left: <?= htmlspecialchars(number_format($bmiPos, 1, '.', '')) ?>%"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="pf-m">
                        <dt>Calo mỗi ngày</dt>
                        <dd><?php if ($target): ?><b><?= htmlspecialchars(nice($target['target_kcal'], 0)) ?></b> kcal<?php else: ?><a href="/nutrition.php">Chưa tính</a><?php endif; ?></dd>
                    </div>
                    <div class="pf-m">
                        <dt>Protein mỗi ngày</dt>
                        <dd><?php if ($target): ?><b><?= htmlspecialchars(nice($target['protein_g'], 0)) ?></b> g<?php else: ?>—<?php endif; ?></dd>
                    </div>
                    <div class="pf-m">
                        <dt>Lần đo gần nhất</dt>
                        <dd><?php if ($lastMetric): ?><b><?= htmlspecialchars(nice($lastMetric['weight_kg'])) ?></b> kg <span class="pf-m__date"><?= htmlspecialchars(date('d/m', strtotime($lastMetric['recorded_date']))) ?></span><?php else: ?><a href="/progress.php">Chưa có</a><?php endif; ?></dd>
                    </div>
                </dl>
                <a class="pf-metrics__more" href="/progress.php">Xem tiến trình →</a>
            </section>
        </aside>

        <!-- ===== Cột phải: form hồ sơ + bảo mật ===== -->
        <div class="pf-main">
            <form method="post" action="/profile.php" class="pf-card pf-form">
                <?= csrfField() ?>
                <input type="hidden" name="form" value="profile">

                <h2 class="pf-h">Thông tin cá nhân</h2>
                <div class="pf-fields">
                    <div class="field pf-c6">
                        <label for="full_name">Họ và tên</label>
                        <input type="text" id="full_name" name="full_name" maxlength="100" autocomplete="name" value="<?= htmlspecialchars($form['full_name'] ?? '') ?>" required>
                    </div>
                    <div class="field pf-c6">
                        <label for="email">Email <em>không đổi được</em></label>
                        <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>
                    <div class="field pf-c3">
                        <label for="age">Tuổi</label>
                        <input type="number" id="age" name="age" min="10" max="100" inputmode="numeric" value="<?= htmlspecialchars((string)($form['age'] ?? '')) ?>" required>
                    </div>
                    <div class="field pf-c3">
                        <label for="gender">Giới tính</label>
                        <select id="gender" name="gender" required>
                            <option value="">-- Chọn --</option>
                            <?php foreach ($genderOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= ($form['gender'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field pf-c3">
                        <label for="height_cm">Chiều cao <em>cm</em></label>
                        <input type="number" id="height_cm" name="height_cm" min="100" max="250" step="0.1" inputmode="decimal" value="<?= htmlspecialchars((string)($form['height_cm'] ?? '')) ?>" required>
                    </div>
                    <div class="field pf-c3">
                        <label for="weight_kg">Cân nặng <em>kg</em></label>
                        <input type="number" id="weight_kg" name="weight_kg" min="30" max="300" step="0.1" inputmode="decimal" value="<?= htmlspecialchars((string)($form['weight_kg'] ?? '')) ?>" required>
                    </div>
                </div>

                <h2 class="pf-h">Tập luyện và mục tiêu</h2>
                <div class="pf-fields">
                    <div class="field pf-c12">
                        <label for="activity_level">Mức độ vận động</label>
                        <select id="activity_level" name="activity_level" required>
                            <option value="">-- Chọn --</option>
                            <?php foreach ($activityOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= ($form['activity_level'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <fieldset class="pf-seg pf-c6">
                        <legend>Trình độ tập luyện</legend>
                        <div class="pf-seg__row">
                            <?php foreach ($experienceOptions as $value => [$short, $sub]): ?>
                                <label class="pf-seg__opt" title="<?= htmlspecialchars($short . ' · ' . $sub) ?>">
                                    <input type="radio" name="experience_level" value="<?= htmlspecialchars($value) ?>" <?= ($form['experience_level'] ?? '') === $value ? 'checked' : '' ?> required>
                                    <span><b><?= htmlspecialchars($short) ?></b><small><?= htmlspecialchars($sub) ?></small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="pf-seg pf-c6">
                        <legend>Mục tiêu</legend>
                        <div class="pf-seg__row">
                            <?php foreach ($goalOptions as $value => $label): ?>
                                <label class="pf-seg__opt">
                                    <input type="radio" name="goal" value="<?= htmlspecialchars($value) ?>" <?= ($form['goal'] ?? '') === $value ? 'checked' : '' ?> required>
                                    <span><b><?= htmlspecialchars($label) ?></b></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>

                <div class="pf-actions">
                    <a class="btn btn--ghost" href="/index.php">Hủy</a>
                    <button type="submit" class="btn">Lưu hồ sơ</button>
                </div>
            </form>

            <section class="pf-card pf-form" id="bao-mat" aria-labelledby="pf-t3">
                <h2 class="pf-h" id="pf-t3">Đổi mật khẩu</h2>
                <?php if (isset($_GET['pw'])): ?>
                    <div class="alert alert--ok" role="status">Đã đổi mật khẩu.</div>
                <?php endif; ?>
                <?php if (!empty($pwErrors)): ?>
                    <div class="alert alert--error" role="alert">
                        <ul><?php foreach ($pwErrors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <form method="post" action="/profile.php#bao-mat">
                    <?= csrfField() ?>
                    <input type="hidden" name="form" value="password">
                    <div class="pf-fields">
                        <?php pwField('current_password', 'Mật khẩu hiện tại', 'current-password', $eyeSvg, 4); ?>
                        <?php pwField('new_password', 'Mật khẩu mới', 'new-password', $eyeSvg, 4, 'minlength="8" maxlength="72"'); ?>
                        <?php pwField('confirm_password', 'Nhập lại mật khẩu mới', 'new-password', $eyeSvg, 4, 'minlength="8" maxlength="72"'); ?>
                    </div>
                    <div class="pf-actions pf-actions--split">
                        <span>Mật khẩu từ 8 đến 72 ký tự.</span>
                        <button type="submit" class="btn btn--ghost">Đổi mật khẩu</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>

<script src="/js/password-toggle.js"></script>
<script src="/js/profile.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
