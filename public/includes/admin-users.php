<?php
/**
 * admin-users.php — xử lý hành động của admin lên tài khoản người dùng:
 *   - set_role: đổi vai trò USER <-> ADMIN
 *   - delete:   xóa tài khoản
 * Dùng chung cho admin/users.php và admin/user-detail.php (viết một chỗ, tránh lặp code).
 *
 * Quy tắc an toàn (kiểm tra ở PHP, không chỉ ẩn nút):
 *   1. Phải là POST, có mã CSRF đúng.
 *   2. Không được tự đổi vai trò hoặc tự xóa chính mình (tránh admin tự khóa mình ngoài hệ thống).
 *   3. Không xóa trực tiếp tài khoản ADMIN: phải hạ xuống USER trước (hai bước, khó xóa nhầm).
 *   4. Vai trò mới chỉ nhận đúng 'USER' hoặc 'ADMIN' (whitelist).
 * Xóa user kéo theo xóa dữ liệu của họ (lịch tập, số đo...) nhờ ON DELETE CASCADE trong schema.
 */

require_once __DIR__ . '/csrf.php';

/**
 * Gọi ở đầu trang, sau requireAdmin(). Nếu không phải POST của các hành động trên thì không làm gì.
 * Làm xong thì lưu thông báo vào session rồi chuyển hướng (mẫu Post/Redirect/Get).
 *
 * @param string $redirectOnDelete nơi chuyển đến sau khi xóa thành công (trang chi tiết user đó không còn nữa)
 * @param string $redirectOther    nơi chuyển đến trong các trường hợp còn lại
 */
function handleUserAction(PDO $pdo, string $redirectOther, string $redirectOnDelete): void
{
    $action = $_POST['action'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($action, ['set_role', 'delete'], true)) {
        return;
    }

    if (!verifyCsrf()) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ (sai mã bảo vệ). Hãy quay lại và thử lại.');
    }

    $target = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    $self   = (int)($_SESSION['user_id'] ?? 0);
    $go     = $redirectOther;

    if ($target === false || $target < 1) {
        $_SESSION['flash'] = ['error', 'Id người dùng không hợp lệ.'];
    } elseif ($target === $self) {
        $_SESSION['flash'] = ['error', 'Bạn không thể đổi vai trò hoặc xóa chính tài khoản của mình.'];
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE id = ?");
        $stmt->execute([$target]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['flash'] = ['error', 'Không tìm thấy tài khoản.'];
        } elseif ($action === 'set_role') {
            $newRole = $_POST['role'] ?? '';
            if (!in_array($newRole, ['USER', 'ADMIN'], true)) {
                $_SESSION['flash'] = ['error', 'Vai trò không hợp lệ.'];
            } elseif ($user['role'] === $newRole) {
                $_SESSION['flash'] = ['error', 'Tài khoản này đã có vai trò đó rồi.'];
            } else {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $target]);
                $_SESSION['flash'] = ['ok', 'Đã đổi vai trò của "' . $user['full_name'] . '" thành ' . $newRole . '.'];
            }
        } else { // delete
            if ($user['role'] === 'ADMIN') {
                $_SESSION['flash'] = ['error', 'Không xóa trực tiếp tài khoản ADMIN. Hãy hạ xuống USER trước.'];
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$target]);
                $_SESSION['flash'] = ['ok', 'Đã xóa tài khoản "' . $user['full_name'] . '" cùng dữ liệu của họ.'];
                $go = $redirectOnDelete;
            }
        }
    }

    header('Location: ' . $go); // $go chỉ nhận 2 giá trị viết sẵn trong code do trang gọi truyền vào
    exit;
}

/** Lấy thông báo một lần (flash) rồi xóa khỏi session. Trả về [loại, nội dung] hoặc null. */
function takeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
