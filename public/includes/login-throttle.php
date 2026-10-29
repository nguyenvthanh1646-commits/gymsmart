<?php
/**
 * login-throttle.php — chặn dò mật khẩu (brute-force) ở trang đăng nhập.
 *
 * Quy tắc: trong 15 phút gần nhất, sai quá 5 lần với cùng một email HOẶC quá 20 lần từ cùng một IP
 * thì tạm khóa (từ chối kiểm tra mật khẩu) cho tới khi lần sai cũ nhất trôi ra khỏi cửa sổ 15 phút.
 *   - Theo email: chặn đoán mật khẩu của MỘT tài khoản.
 *   - Theo IP:    chặn một máy thử lần lượt NHIỀU email.
 * Đăng nhập đúng thì xóa các lần sai của email đó.
 *
 * Bảng login_attempts nằm trong sql/login_attempts.sql. Nếu chưa import thì việc chặn tự tắt
 * (ghi lỗi vào log máy chủ), trang đăng nhập vẫn chạy bình thường.
 */

const LOGIN_MAX_PER_EMAIL = 5;
const LOGIN_MAX_PER_IP    = 20;
const LOGIN_WINDOW_MIN    = 15;

function loginClientIp(): string
{
    // Chỉ tin REMOTE_ADDR: X-Forwarded-For do client tự gửi được nên giả mạo được
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function loginWindowStart(): string
{
    return date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MIN * 60);
}

/**
 * Còn bị khóa bao nhiêu phút (0 = được phép thử).
 * Số phút = lúc lần sai cũ nhất trong cửa sổ hết hạn, làm tròn lên.
 */
function loginLockedMinutes(PDO $pdo, string $email): int
{
    try {
        $since = loginWindowStart();
        $checks = [
            ['email', strtolower($email), LOGIN_MAX_PER_EMAIL],
            ['ip', loginClientIp(), LOGIN_MAX_PER_IP],
        ];
        $wait = 0;
        foreach ($checks as [$column, $value, $max]) {
            // Tên cột chỉ là 'email' hoặc 'ip' viết sẵn ở trên, giá trị đi qua dấu ?
            $stmt = $pdo->prepare("SELECT COUNT(*) AS n, MIN(attempted_at) AS oldest FROM login_attempts WHERE $column = ? AND attempted_at > ?"); // audit:ok tên cột cố định
            $stmt->execute([$value, $since]);
            $row = $stmt->fetch();
            if ((int)$row['n'] >= $max) {
                $unlockAt = strtotime($row['oldest']) + LOGIN_WINDOW_MIN * 60;
                $wait = max($wait, (int)ceil(($unlockAt - time()) / 60));
            }
        }
        return max(0, $wait);
    } catch (PDOException $e) {
        error_log('login-throttle: ' . $e->getMessage() . ' (đã import sql/login_attempts.sql chưa?)');
        return 0;
    }
}

/** Ghi một lần đăng nhập sai, đồng thời dọn các dòng quá cũ để bảng không phình to. */
function loginRecordFailure(PDO $pdo, string $email): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (email, ip, attempted_at) VALUES (?, ?, ?)');
        $stmt->execute([mb_substr(strtolower($email), 0, 255), loginClientIp(), date('Y-m-d H:i:s')]);
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
        $stmt->execute([date('Y-m-d H:i:s', time() - 86400)]); // giữ 1 ngày để admin xem nếu cần
    } catch (PDOException $e) {
        error_log('login-throttle: ' . $e->getMessage());
    }
}

/** Đăng nhập đúng: xóa các lần sai của email này (không xóa theo IP, tránh kẻ dò tự "xóa dấu" bằng tài khoản của mình). */
function loginClearFailures(PDO $pdo, string $email): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE email = ?');
        $stmt->execute([strtolower($email)]);
    } catch (PDOException $e) {
        error_log('login-throttle: ' . $e->getMessage());
    }
}
