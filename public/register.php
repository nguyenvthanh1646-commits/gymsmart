<?php
/**
 * register.php — trang đăng ký tài khoản.
 *
 * Luồng chạy:
 *   1. Người dùng mở trang (GET)  → hiện form trống
 *   2. Bấm "Đăng ký" (POST)       → PHP xử lý ở khối if bên dưới
 *   3. Có lỗi                      → hiện lại form kèm danh sách lỗi
 *   4. Không lỗi                   → lưu user vào DB, chuyển sang trang đăng nhập
 */

require_once __DIR__ . '/includes/auth.php'; // có session + isLoggedIn()
require_once __DIR__ . '/includes/db.php';   // có biến $pdo

// Đã đăng nhập rồi thì không cần đăng ký nữa
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$errors = [];
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // trim() bỏ khoảng trắng thừa đầu/cuối; "?? ''" tránh lỗi khi thiếu trường
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // ===== 1. Kiểm tra dữ liệu nhập (validate phía server, bắt buộc) =====
    if ($fullName === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    }

    if ($email === '') {
        $errors[] = 'Vui lòng nhập email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không đúng định dạng.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }

    // ===== 2. Kiểm tra email đã tồn tại chưa =====
    // Chỉ truy vấn khi dữ liệu nhập đã hợp lệ, đỡ tốn 1 lần gọi DB vô ích.
    // Dấu "?" là chỗ giữ chỗ: giá trị $email được gửi RIÊNG khỏi câu SQL
    // nên người dùng nhập gì cũng không phá được câu lệnh (chống SQL Injection).
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) { // fetch() trả về dòng dữ liệu nếu có, false nếu không có
            $errors[] = 'Email này đã được sử dụng.';
        }
    }

    // ===== 3. Mã hóa mật khẩu và lưu user mới =====
    if (empty($errors)) {
        // password_hash tạo chuỗi băm một chiều (không giải ngược lại được).
        // Không bao giờ lưu mật khẩu gốc vào DB.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)"
        );
        $stmt->execute([$fullName, $email, $passwordHash]);
        // Cột role tự nhận giá trị mặc định 'USER' theo schema.sql

        // ===== 4. Chuyển sang trang đăng nhập =====
        // exit bắt buộc: nếu thiếu, PHP vẫn chạy tiếp các dòng phía dưới.
        header('Location: /login.php');
        exit;
    }
}

$pageTitle = 'Đăng ký';
include __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 420px; margin: 2rem auto;">
    <h1>Đăng ký</h1>

    <?php if (!empty($errors)): ?>
        <ul style="color: #b91c1c;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="/register.php">
        <p>
            <label for="full_name">Họ và tên</label><br>
            <input type="text" id="full_name" name="full_name"
                   value="<?= htmlspecialchars($fullName) ?>" required>
        </p>
        <p>
            <label for="email">Email</label><br>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($email) ?>" required>
        </p>
        <p>
            <label for="password">Mật khẩu</label><br>
            <input type="password" id="password" name="password" required>
        </p>
        <button type="submit" class="btn">Đăng ký</button>
    </form>

    <p>Đã có tài khoản? <a href="/login.php">Đăng nhập</a></p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>