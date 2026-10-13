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
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';   // có biến $pdo

// Đã đăng nhập rồi thì không cần vào trang này nữa
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Chống CSRF: form phải gửi kèm mã bí mật lưu trong session (xem includes/csrf.php)
    if (!verifyCsrf()) {
        showCsrfError();
    }

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

<div class="auth">
    <aside class="auth__aside">
        <?= gsLogo('light', false, 40) ?>
        <h2>Train Harder<br>&amp; <em>Smarter</em></h2>
        <p>Thư viện bài tập có video, lịch tập cá nhân hóa, TDEE/Macro và theo dõi tiến trình, tất cả trong một nơi.</p>
        <ul class="auth__points">
            <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>Video hướng dẫn chuẩn kỹ thuật</li>
            <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>Lịch tập theo trình độ và mục tiêu</li>
            <li><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>Tính TDEE và macro khoa học</li>
        </ul>
    </aside>

    <section class="auth__main">
        <div class="auth__box">
            <h1>Chào mừng trở lại</h1>
            <p class="pg-sub">Đăng nhập để tiếp tục hành trình tập luyện của bạn.</p>

            <?php if (isset($_GET['reset'])): ?>
                <div class="alert alert--ok" role="status">Đã đổi mật khẩu. Hãy đăng nhập bằng mật khẩu mới.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/login.php">
                <?= csrfField() ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" autocomplete="email"
                           value="<?= htmlspecialchars($email) ?>" required>
                </div>
                <div class="field">
                    <label for="password">Mật khẩu</label>
                    <div class="pw">
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                        <button type="button" class="pw__btn" data-pw-toggle="password" aria-label="Hiện mật khẩu" aria-pressed="false">
                            <svg class="pw__eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="pw__off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.9 10.9 0 0112 19c-6.4 0-10-7-10-7a18.5 18.5 0 015.1-5.9M9.9 5.2A10 10 0 0112 5c6.4 0 10 7 10 7a18.6 18.6 0 01-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 01-4.2-4.2"/></svg>
                        </button>
                    </div>
                    <a class="auth__forgot" href="/forgot-password.php">Quên mật khẩu?</a>
                </div>
                <button type="submit" class="btn btn--block">Đăng nhập</button>
            </form>

            <p class="auth__alt">Chưa có tài khoản? <a href="/register.php">Đăng ký miễn phí</a></p>
        </div>
    </section>
</div>

<script src="/js/password-toggle.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
