<?php
/**
 * login.php — trang đăng nhập.
 *
 * Luồng chạy:
 *   1. Mở trang (GET)        → hiện form
 *   2. Bấm "Đăng nhập" (POST) → tìm user theo email, so mật khẩu
 *   3. Đúng                   → lưu thông tin vào $_SESSION, chuyển về trang chủ
 *   4. Sai                    → hiện lại form kèm thông báo lỗi
 */

require_once __DIR__ . '/includes/auth.php'; // có session + isLoggedIn()
require_once __DIR__ . '/includes/db.php';   // có biến $pdo

// Đã đăng nhập rồi thì không cần vào trang này nữa
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập email và mật khẩu.';
    } else {
        // Tìm user theo email (prepared statement, chống SQL Injection)
        $stmt = $pdo->prepare(
            "SELECT id, full_name, password_hash, role FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(); // mảng thông tin user, hoặc false nếu không có email này

        // password_verify so mật khẩu người dùng nhập với chuỗi băm trong DB.
        // Ta KHÔNG tự so sánh bằng "==" vì mật khẩu trong DB đã bị băm một chiều.
        if ($user && password_verify($password, $user['password_hash'])) {
            // Tạo session ID mới sau khi đăng nhập thành công,
            // tránh tấn công "session fixation" (kẻ xấu cài sẵn session ID cho nạn nhân).
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            header('Location: /index.php');
            exit;
        }

        // Cố ý dùng 1 thông báo chung cho cả "sai email" và "sai mật khẩu",
        // để kẻ xấu không dò ra được email nào đã có tài khoản.
        $error = 'Email hoặc mật khẩu không đúng.';
    }
}

$pageTitle = 'Đăng nhập';
include __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 420px; margin: 2rem auto;">
    <h1>Đăng nhập</h1>

    <?php if ($error !== ''): ?>
        <p style="color: #b91c1c;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/login.php">
        <p>
            <label for="email">Email</label><br>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($email) ?>" required>
        </p>
        <p>
            <label for="password">Mật khẩu</label><br>
            <input type="password" id="password" name="password" required>
        </p>
        <button type="submit" class="btn">Đăng nhập</button>
    </form>

    <p>Chưa có tài khoản? <a href="/register.php">Đăng ký</a></p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>