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
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';   // có biến $pdo

// Đã đăng nhập rồi thì không cần đăng ký nữa
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$errors = [];
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Chống CSRF: form phải gửi kèm mã bí mật lưu trong session (xem includes/csrf.php)
    if (!verifyCsrf()) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ (sai mã bảo vệ). Hãy tải lại trang đăng nhập/đăng ký và thử lại.');
    }

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

    // Tối thiểu 8 ký tự cho mật khẩu đỡ bị đoán ra.
    // Tối đa 72 BYTE vì bcrypt (PASSWORD_DEFAULT) chỉ đọc 72 byte đầu, phần sau bị bỏ qua.
    if (strlen($password) < 8) {
        $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Mật khẩu tối đa 72 ký tự.';
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
        <div class="auth__box">
            <h1>Tạo tài khoản</h1>
            <p class="pg-sub">Miễn phí và chỉ mất chưa đến một phút.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert--error" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="/register.php">
                <?= csrfField() ?>
                <div class="field">
                    <label for="full_name">Họ và tên</label>
                    <input type="text" id="full_name" name="full_name" autocomplete="name"
                           value="<?= htmlspecialchars($fullName) ?>" required>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" autocomplete="email"
                           value="<?= htmlspecialchars($email) ?>" required>
                </div>
                <div class="field">
                    <label for="password">Mật khẩu</label>
                    <div class="pw">
                        <input type="password" id="password" name="password" autocomplete="new-password"
                           minlength="8" maxlength="72" required>
                        <button type="button" class="pw__btn" data-pw-toggle="password" aria-label="Hiện mật khẩu" aria-pressed="false">
                            <svg class="pw__eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="pw__off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.9 10.9 0 0112 19c-6.4 0-10-7-10-7a18.5 18.5 0 015.1-5.9M9.9 5.2A10 10 0 0112 5c6.4 0 10 7 10 7a18.6 18.6 0 01-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 01-4.2-4.2"/></svg>
                        </button>
                    </div>
                    <small>Từ 8 đến 72 ký tự.</small>
                </div>
                <button type="submit" class="btn btn--block">Đăng ký</button>
            </form>

            <p class="auth__alt">Đã có tài khoản? <a href="/login.php">Đăng nhập</a></p>
        </div>
    </section>
</div>

<script src="/js/password-toggle.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
