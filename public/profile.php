<?php
/**
 * profile.php — hồ sơ cá nhân.
 * Người dùng nhập tuổi, chiều cao, cân nặng, giới tính, mức vận động, mục tiêu.
 * Đây là dữ liệu đầu vào cho TDEE/Macro (C) và gợi ý lịch tập (B).
 *
 * Luồng chạy:
 *   1. Chưa đăng nhập        → chuyển về /login.php (requireLogin)
 *   2. Mở trang (GET)         → đọc dữ liệu hiện có trong DB, điền sẵn vào form
 *   3. Bấm "Lưu" (POST)       → kiểm tra dữ liệu, UPDATE vào DB
 *   4. Lưu xong               → chuyển lại chính trang này kèm ?saved=1 để báo thành công
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

// Các lựa chọn hợp lệ. Khóa (bên trái) trùng đúng giá trị ENUM trong schema.sql,
// giá trị (bên phải) là chữ hiển thị cho người dùng.
$genderOptions = [
    'MALE'   => 'Nam',
    'FEMALE' => 'Nữ',
    'OTHER'  => 'Khác',
];
$activityOptions = [
    'SEDENTARY'    => 'Ít vận động (ngồi nhiều, hầu như không tập)',
    'LIGHT'        => 'Vận động nhẹ (1–3 buổi/tuần)',
    'MODERATE'     => 'Vận động vừa (3–5 buổi/tuần)',
    'ACTIVE'       => 'Vận động nhiều (6–7 buổi/tuần)',
    'EXTRA_ACTIVE' => 'Rất nhiều (tập nặng hằng ngày hoặc lao động nặng)',
];
$goalOptions = [
    'LOSE_FAT'    => 'Giảm mỡ',
    'MAINTAIN'    => 'Duy trì',
    'GAIN_MUSCLE' => 'Tăng cơ',
];
// Trình độ tập luyện: dùng để gợi ý lịch tập (số buổi, số bài, số set phù hợp).
// Khóa trùng đúng giá trị ENUM của cột experience_level trong bảng users.
$experienceOptions = [
    'BEGINNER'     => 'Mới tập (dưới 6 tháng)',
    'INTERMEDIATE' => 'Trung bình (6 tháng đến 2 năm)',
    'ADVANCED'     => 'Nâng cao (trên 2 năm)',
];

// Lấy id từ SESSION, KHÔNG lấy từ form/URL. Nếu lấy từ form, người dùng có thể
// sửa số id để xem hoặc sửa hồ sơ của người khác.
$userId = $_SESSION['user_id'];

// Đọc thông tin hiện tại của user
$stmt = $pdo->prepare(
    "SELECT full_name, email, age, height_cm, weight_kg, gender, activity_level, experience_level, goal
     FROM users WHERE id = ?"
);
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Session còn nhưng user đã bị xóa khỏi DB → đăng xuất cho an toàn
if (!$user) {
    $_SESSION = [];
    session_destroy();
    header('Location: /login.php');
    exit;
}

$errors = [];
$form = $user; // dữ liệu hiển thị trong form (mặc định là dữ liệu trong DB)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $age      = trim($_POST['age'] ?? '');
    // Cho phép gõ dấu phẩy kiểu Việt Nam (1,75) bằng cách đổi thành dấu chấm
    $height   = str_replace(',', '.', trim($_POST['height_cm'] ?? ''));
    $weight   = str_replace(',', '.', trim($_POST['weight_kg'] ?? ''));
    $gender   = $_POST['gender'] ?? '';
    $activity = $_POST['activity_level'] ?? '';
    $experience = $_POST['experience_level'] ?? '';
    $goal     = $_POST['goal'] ?? '';

    // Giữ lại những gì người dùng vừa nhập để hiện lại form nếu có lỗi
    $form = array_merge($user, [
        'full_name'      => $fullName,
        'age'            => $age,
        'height_cm'      => $height,
        'weight_kg'      => $weight,
        'gender'         => $gender,
        'activity_level' => $activity,
        'experience_level' => $experience,
        'goal'           => $goal,
    ]);

    // ===== Kiểm tra dữ liệu (validate phía server, bắt buộc) =====
    if ($fullName === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    }

    // filter_var trả về số nếu hợp lệ, false nếu không phải số
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

    // Với các ô chọn (select), người dùng vẫn có thể sửa HTML để gửi giá trị lạ.
    // Chỉ chấp nhận giá trị nằm trong danh sách cho phép (whitelist).
    if (!array_key_exists($gender, $genderOptions)) {
        $errors[] = 'Vui lòng chọn giới tính.';
    }
    if (!array_key_exists($activity, $activityOptions)) {
        $errors[] = 'Vui lòng chọn mức độ vận động.';
    }
    // Trình độ tập luyện: thuật toán gợi ý lịch tập (B) dùng giá trị này
    if (!array_key_exists($experience, $experienceOptions)) {
        $errors[] = 'Vui lòng chọn trình độ tập luyện.';
    }
    if (!array_key_exists($goal, $goalOptions)) {
        $errors[] = 'Vui lòng chọn mục tiêu.';
    }

    // ===== Lưu vào DB =====
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "UPDATE users
             SET full_name = ?, age = ?, height_cm = ?, weight_kg = ?,
                 gender = ?, activity_level = ?, experience_level = ?, goal = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $fullName, $ageValue, $heightValue, $weightValue,
            $gender, $activity, $experience, $goal, $userId,
        ]);

        // Cập nhật tên trong session để menu/trang khác hiện tên mới
        $_SESSION['full_name'] = $fullName;

        // Chuyển hướng sau khi lưu (mẫu "Post/Redirect/Get"): nếu người dùng
        // bấm F5, trình duyệt chỉ tải lại trang, không gửi lại form lần nữa.
        header('Location: /profile.php?saved=1');
        exit;
    }
}

$pageTitle = 'Hồ sơ cá nhân';
include __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 520px; margin: 2rem auto;">
    <h1>Hồ sơ cá nhân</h1>
    <p>Thông tin này dùng để tính TDEE, macro và gợi ý lịch tập phù hợp với bạn.</p>

    <?php if (isset($_GET['saved'])): ?>
        <p style="color: #15803d;">Đã lưu hồ sơ.</p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <ul style="color: #b91c1c;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="/profile.php">
        <p>
            <label for="full_name">Họ và tên</label><br>
            <input type="text" id="full_name" name="full_name"
                   value="<?= htmlspecialchars($form['full_name'] ?? '') ?>" required>
        </p>

        <p>
            <label>Email (không đổi được)</label><br>
            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
        </p>

        <p>
            <label for="age">Tuổi</label><br>
            <input type="number" id="age" name="age" min="10" max="100"
                   value="<?= htmlspecialchars((string)($form['age'] ?? '')) ?>" required>
        </p>

        <p>
            <label for="height_cm">Chiều cao (cm)</label><br>
            <input type="number" id="height_cm" name="height_cm" min="100" max="250" step="0.1"
                   value="<?= htmlspecialchars((string)($form['height_cm'] ?? '')) ?>" required>
        </p>

        <p>
            <label for="weight_kg">Cân nặng (kg)</label><br>
            <input type="number" id="weight_kg" name="weight_kg" min="30" max="300" step="0.1"
                   value="<?= htmlspecialchars((string)($form['weight_kg'] ?? '')) ?>" required>
        </p>

        <p>
            <label for="gender">Giới tính</label><br>
            <select id="gender" name="gender" required>
                <option value="">-- Chọn --</option>
                <?php foreach ($genderOptions as $value => $label): ?>
                    <option value="<?= $value ?>"
                        <?= ($form['gender'] ?? '') === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="activity_level">Mức độ vận động</label><br>
            <select id="activity_level" name="activity_level" required>
                <option value="">-- Chọn --</option>
                <?php foreach ($activityOptions as $value => $label): ?>
                    <option value="<?= $value ?>"
                        <?= ($form['activity_level'] ?? '') === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="experience_level">Trình độ tập luyện</label><br>
            <select id="experience_level" name="experience_level" required>
                <option value="">-- Chọn --</option>
                <?php foreach ($experienceOptions as $value => $label): ?>
                    <option value="<?= $value ?>"
                        <?= ($form['experience_level'] ?? '') === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="goal">Mục tiêu</label><br>
            <select id="goal" name="goal" required>
                <option value="">-- Chọn --</option>
                <?php foreach ($goalOptions as $value => $label): ?>
                    <option value="<?= $value ?>"
                        <?= ($form['goal'] ?? '') === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <button type="submit" class="btn">Lưu hồ sơ</button>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>