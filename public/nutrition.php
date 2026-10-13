<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tdee.php';

requireLogin();

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT age, height_cm, weight_kg, gender, activity_level, goal
     FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);


$error = '';             // thông báo lỗi để hiển thị ở bước HTML sau
$missing = false;        // true nếu hồ sơ chưa đủ dữ liệu
$bmr = $tdee = $targets = null;

// Các cột bắt buộc phải có giá trị mới tính được
$required = ['age', 'height_cm', 'weight_kg', 'gender', 'activity_level', 'goal'];

if (!$user) {
    // Không tìm thấy user trong bảng users
    $missing = true;
} else {
    foreach ($required as $field) {
        // NULL hoặc rỗng = người dùng chưa nhập ở profile.php
        if ($user[$field] === null || $user[$field] === '') {
            $missing = true;
            break;   // thiếu một trường là đủ, khỏi kiểm tra tiếp
        }
    }
}

if (!$missing) {
    try {
        // Ép kiểu vì dữ liệu từ DB có thể là chuỗi
        $weight  = (float)$user['weight_kg'];
        $bmr     = calculateBMR($weight, (float)$user['height_cm'], (int)$user['age'], $user['gender']);
        $tdee    = calculateTDEE($bmr, $user['activity_level']);
        $targets = calculateTargets($tdee, $weight, $user['goal']);
    } catch (InvalidArgumentException $e) {
        // Dữ liệu sai (VD: tuổi = 0) thì lưu lỗi để hiện thân thiện, không để trang sập
        $error = $e->getMessage();
    }
}


// Chỉ lưu khi tính toán thành công
if ($targets !== null) {
    $today = date('Y-m-d');   // ngày hôm nay, đúng định dạng DATE của MySQL

    // 1. Hỏi DB: hôm nay user này đã có dòng chưa?
    $stmt = $pdo->prepare(
        'SELECT id FROM nutrition_targets WHERE user_id = ? AND calculated_date = ?'
    );
    $stmt->execute([$userId, $today]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);   // false nếu chưa có

    if ($existing) {
        // 2. Đã có -> cập nhật dòng đó (tránh bảng phình ra)
        $stmt = $pdo->prepare(
            'UPDATE nutrition_targets
             SET tdee_kcal = ?, target_kcal = ?, protein_g = ?, carbs_g = ?, fat_g = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $tdee,
            $targets['target_kcal'],
            $targets['protein_g'],
            $targets['carbs_g'],
            $targets['fat_g'],
            $existing['id'],
        ]);
    } else {
        // 3. Chưa có -> thêm dòng mới
        $stmt = $pdo->prepare(
            'INSERT INTO nutrition_targets
             (user_id, tdee_kcal, target_kcal, protein_g, carbs_g, fat_g, calculated_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $tdee,
            $targets['target_kcal'],
            $targets['protein_g'],
            $targets['carbs_g'],
            $targets['fat_g'],
            $today,
        ]);
    }
}
$pageTitle = 'Dinh dưỡng';
include __DIR__ . '/includes/header.php';
?>
<div class="container">
    <h1>Mục tiêu dinh dưỡng</h1>

    <?php if ($missing): ?>
        <!-- Hồ sơ chưa đủ dữ liệu: yêu cầu người dùng cập nhật ở profile.php -->
        <div class="card">
            <p>Vui lòng cập nhật hồ sơ để tính toán.</p>
            <a class="btn" href="profile.php">Cập nhật hồ sơ</a>
        </div>
    <?php elseif ($error !== ''): ?>
        <!-- Dữ liệu sai nên hàm tính đã chặn: hiện thông báo lỗi thân thiện -->
        <div class="card"><p><?= htmlspecialchars($error) ?></p></div>
    <?php else: ?>
        <!-- Tính thành công: hiện kết quả. Mọi giá trị in ra đều qua htmlspecialchars() -->
        <div class="card">
            <h2>Năng lượng</h2>
            <p>BMR (calo khi nghỉ): <strong><?= htmlspecialchars((string)round($bmr)) ?></strong> kcal</p>
            <p>TDEE (calo đốt cả ngày): <strong><?= htmlspecialchars((string)round($tdee)) ?></strong> kcal</p>
            <p>Calo mục tiêu: <strong><?= htmlspecialchars((string)$targets['target_kcal']) ?></strong> kcal</p>
        </div>

        <div class="card">
            <h2>Macro mỗi ngày</h2>
            <p>Protein: <strong><?= htmlspecialchars((string)$targets['protein_g']) ?></strong> g</p>
            <p>Carb: <strong><?= htmlspecialchars((string)$targets['carbs_g']) ?></strong> g</p>
            <p>Fat: <strong><?= htmlspecialchars((string)$targets['fat_g']) ?></strong> g</p>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
      