<?php
/**
 * forgot-password.php — "Quên mật khẩu": nhập email để nhận link đặt lại mật khẩu.
 *
 * Luồng chạy:
 *   1. Nhập email (POST)  → nếu email có trong DB, tạo mã ngẫu nhiên, lưu BẢN BĂM vào bảng password_resets
 *   2. Gửi link /reset-password.php?token=... cho người dùng
 *   3. Luôn hiện cùng một thông báo dù email có tồn tại hay không (để không ai dò được email nào đã đăng ký)
 *
 * Về việc gửi link:
 *   - XAMPP thường chưa cấu hình gửi email, nên khi chạy TRÊN CHÍNH MÁY NÀY (localhost) trang hiện luôn link để demo.
 *   - Khi chạy trên máy chủ thật, link không bao giờ hiện ra màn hình mà gửi bằng mail() (cần cấu hình email của máy chủ,
 *     và đặt APP_URL trong config.php, ví dụ define('APP_URL', 'https://gymsmart.example.com');).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (isLoggedIn()) {
    header('Location: /profile.php#doi-mat-khau'); // đã đăng nhập thì đổi mật khẩu ngay trong hồ sơ
    exit;
}

const RESET_MINUTES = 30; // mã có hiệu lực bao lâu

$email = '';
$error = '';
$sent = false;
$demoLink = ''; // chỉ có giá trị khi chạy trên localhost

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }

    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Vui lòng nhập một địa chỉ email hợp lệ.';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

        if ($user) {
            $now = date('Y-m-d H:i:s');

            // Chống bấm liên tục: nếu vừa yêu cầu trong 60 giây qua thì không tạo thêm mã mới
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > ?");
            $stmt->execute([$user['id'], date('Y-m-d H:i:s', time() - 60)]);
            $recent = !$isLocal && (int)$stmt->fetchColumn() > 0; // trên localhost bỏ qua giới hạn để demo cho tiện

            if (!$recent) {
                // Xóa các mã cũ chưa dùng của user này, mỗi lần chỉ có 1 mã còn hiệu lực
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL");
                $stmt->execute([$user['id']]);

                // Mã ngẫu nhiên khó đoán: 32 byte = 64 ký tự hex. KHÔNG dùng rand() hay time() vì đoán được.
                $token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare(
                    "INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $user['id'],
                    hash('sha256', $token), // DB chỉ giữ bản băm: lộ DB cũng không dùng được mã
                    date('Y-m-d H:i:s', time() + RESET_MINUTES * 60),
                    $now,
                ]);

                $path = '/reset-password.php?token=' . $token;
                if ($isLocal) {
                    $demoLink = $path; // demo: hiện link ngay vì XAMPP không gửi được email
                } elseif (defined('APP_URL')) {
                    $body = "Xin chào " . $user['full_name'] . ",\n\n"
                          . "Bạn (hoặc ai đó) vừa yêu cầu đặt lại mật khẩu GymSmart.\n"
                          . "Bấm vào link sau trong " . RESET_MINUTES . " phút để đặt mật khẩu mới:\n"
                          . rtrim(APP_URL, '/') . $path . "\n\n"
                          . "Nếu không phải bạn, hãy bỏ qua email này.";
                    @mail($email, 'Dat lai mat khau GymSmart', $body, "Content-Type: text/plain; charset=UTF-8");
                }
            }
        }
        // Dù có user hay không đều báo giống nhau
        $sent = true;
    }
}

$pageTitle = 'Quên mật khẩu';
include __DIR__ . '/includes/header.php';
?>

<div class="auth">
    <?php include __DIR__ . '/includes/auth-aside.php'; ?>

    <section class="auth__main">
        <div class="auth__box">
            <h1>Quên mật khẩu?</h1>
            <p class="pg-sub">Nhập email đã đăng ký, chúng tôi sẽ gửi link để bạn đặt mật khẩu mới.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($sent): ?>
                <div class="alert alert--ok" role="status">
                    Nếu email này đã đăng ký, link đặt lại mật khẩu đã được gửi (có hiệu lực <?= RESET_MINUTES ?> phút). Hãy kiểm tra hộp thư, cả mục thư rác.
                </div>
                <?php if ($demoLink !== ''): ?>
                    <div class="alert alert--note" role="note">
                        <div>
                            <b>Chế độ demo (localhost):</b> máy này chưa gửi được email nên link hiện tại đây.<br>
                            <a href="<?= htmlspecialchars($demoLink) ?>">Mở link đặt lại mật khẩu</a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <form method="post" action="/forgot-password.php">
                    <?= csrfField() ?>
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" autocomplete="email"
                               value="<?= htmlspecialchars($email) ?>" required>
                    </div>
                    <button type="submit" class="btn btn--block">Gửi link đặt lại mật khẩu</button>
                </form>
            <?php endif; ?>

            <p class="auth__alt"><a href="/login.php">&larr; Quay lại đăng nhập</a></p>
        </div>
    </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
