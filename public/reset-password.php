<?php
/**
 * reset-password.php — đặt mật khẩu mới từ link trong email: /reset-password.php?token=...
 *
 * Luồng chạy:
 *   1. Mở link (GET)   → kiểm tra mã: đúng định dạng, có trong DB, chưa dùng, chưa hết hạn
 *   2. Gửi form (POST) → kiểm tra mật khẩu mới, cập nhật, đánh dấu mã đã dùng, chuyển về trang đăng nhập
 * Mã sai/hết hạn/đã dùng đều báo chung một câu, không nói rõ lý do.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';

// Mã nằm trong URL (GET) khi mở link, và trong ô ẩn (POST) khi gửi form
$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');

/** Tìm bản ghi mã còn hiệu lực; trả về mảng (có user_id) hoặc null */
function findValidReset(PDO $pdo, string $token): ?array
{
    // Chỉ chấp nhận đúng 64 ký tự hex, loại sớm mọi giá trị lạ
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT id, user_id, expires_at FROM password_resets WHERE token_hash = ? AND used_at IS NULL"
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    if (!$row || strtotime($row['expires_at']) < time()) {
        return null;
    }
    return $row;
}

$reset = findValidReset($pdo, $token);
$errors = [];

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }

    $newPw     = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (strlen($newPw) < 8) {
        $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    } elseif (strlen($newPw) > 72) {
        $errors[] = 'Mật khẩu mới tối đa 72 ký tự.';
    } elseif ($newPw !== $confirmPw) {
        $errors[] = 'Nhập lại mật khẩu mới chưa khớp.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction(); // đổi mật khẩu và đánh dấu mã đã dùng: làm cùng lúc hoặc không làm gì
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([password_hash($newPw, PASSWORD_DEFAULT), $reset['user_id']]);
        $stmt = $pdo->prepare("UPDATE password_resets SET used_at = ? WHERE user_id = ?");
        $stmt->execute([date('Y-m-d H:i:s'), $reset['user_id']]); // vô hiệu mọi mã của user này
        $pdo->commit();

        header('Location: /login.php?reset=1');
        exit;
    }
}

$pageTitle = 'Đặt lại mật khẩu';
include __DIR__ . '/includes/header.php';

$eyeSvg = '<svg class="pw__eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="pw__off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.9 10.9 0 0112 19c-6.4 0-10-7-10-7a18.5 18.5 0 015.1-5.9M9.9 5.2A10 10 0 0112 5c6.4 0 10 7 10 7a18.6 18.6 0 01-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 01-4.2-4.2"/></svg>';
?>

<div class="auth">
    <?php include __DIR__ . '/includes/auth-aside.php'; ?>

    <section class="auth__main">
        <div class="auth__box">
            <?php if (!$reset): ?>
                <h1>Link không còn hiệu lực</h1>
                <p class="pg-sub">Link đặt lại mật khẩu không đúng, đã được dùng hoặc đã hết hạn (30 phút). Hãy yêu cầu link mới.</p>
                <a class="btn btn--block" href="/forgot-password.php">Gửi lại link</a>
            <?php else: ?>
                <h1>Đặt mật khẩu mới</h1>
                <p class="pg-sub">Chọn mật khẩu mới cho tài khoản của bạn (8 đến 72 ký tự).</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert--error" role="alert">
                        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="/reset-password.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <?php foreach ([['new_password', 'Mật khẩu mới'], ['confirm_password', 'Nhập lại mật khẩu mới']] as [$id, $label]): ?>
                        <div class="field">
                            <label for="<?= $id /* audit:ok $id chỉ là 2 tên cố định do mình đặt */ ?>"><?= $label /* audit:ok chữ cố định */ ?></label>
                            <div class="pw">
                                <input type="password" id="<?= $id /* audit:ok */ ?>" name="<?= $id /* audit:ok */ ?>" autocomplete="new-password" minlength="8" maxlength="72" required>
                                <button type="button" class="pw__btn" data-pw-toggle="<?= $id /* audit:ok */ ?>" aria-label="Hiện mật khẩu" aria-pressed="false"><?= $eyeSvg /* audit:ok SVG cố định */ ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn--block">Đổi mật khẩu</button>
                </form>
            <?php endif; ?>

            <p class="auth__alt"><a href="/login.php">&larr; Quay lại đăng nhập</a></p>
        </div>
    </section>
</div>

<script src="/js/password-toggle.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
