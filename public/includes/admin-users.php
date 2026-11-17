<?php
/**
 * admin-users.php — xử lý hành động của admin lên tài khoản người dùng:
 *   - set_role:     đổi vai trò USER <-> ADMIN
 *   - revoke_coach: thu hồi vai trò HLV (COACH -> USER), kết thúc mọi quan hệ với học viên
 *   - delete:       xóa tài khoản
 * Dùng chung cho admin/users.php và admin/user-detail.php (viết một chỗ, tránh lặp code).
 *
 * Quy tắc an toàn (kiểm tra ở PHP, không chỉ ẩn nút):
 *   1. Phải là POST, có mã CSRF đúng.
 *   2. Không được tự đổi vai trò hoặc tự xóa chính mình (tránh admin tự khóa mình ngoài hệ thống).
 *   3. Không xóa trực tiếp tài khoản ADMIN: phải hạ xuống USER trước (hai bước, khó xóa nhầm).
 *   4. Vai trò mới chỉ nhận đúng 'USER' hoặc 'ADMIN' (whitelist). COACH chỉ có qua duyệt đơn đăng ký HLV
 *      (admin/coach-applications.php), không cấp tay ở đây.
 *   5. Tài khoản COACH không đổi thẳng sang ADMIN: phải thu hồi HLV trước (để học viên của họ được báo kết thúc đàng hoàng).
 *   6. Người đang có HLV (đang tập hoặc đang chờ) không được lên ADMIN: một người không vừa là học viên vừa là quản trị.
 * Xóa user kéo theo xóa dữ liệu của họ (lịch tập, số đo...) nhờ ON DELETE CASCADE trong schema.
 */

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/audit-log.php';
require_once __DIR__ . '/coach-db.php';

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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($action, ['set_role', 'revoke_coach', 'delete'], true)) {
        return;
    }

    if (!verifyCsrf()) {
        showCsrfError();
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
            } elseif ($user['role'] === 'COACH') {
                $_SESSION['flash'] = ['error', 'Tài khoản này đang là huấn luyện viên. Hãy thu hồi vai trò HLV trước.'];
            } elseif ($newRole === 'ADMIN' && coachTablesReady($pdo) && coachOpenRelationForClient($pdo, $target) !== null) {
                $_SESSION['flash'] = ['error', 'Người này đang có (hoặc đang chờ) huấn luyện viên. Họ cần kết thúc trước khi lên ADMIN.'];
            } else {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $target]);
                auditLog($pdo, 'user.set_role', 'user', $target, 'Đổi vai trò "' . $user['full_name'] . '": ' . $user['role'] . ' -> ' . $newRole);
                $_SESSION['flash'] = ['ok', 'Đã đổi vai trò của "' . $user['full_name'] . '" thành ' . $newRole . '.'];
            }
        } elseif ($action === 'revoke_coach') {
            // Mọi kiểm tra (đúng là COACH, kết thúc quan hệ, tắt nhận học viên) nằm trong coachRevoke(), chạy trong 1 transaction
            try {
                $res = coachRevoke($pdo, $target, $self);
                auditLog($pdo, 'coach.revoke', 'user', $target, 'Thu hồi HLV "' . $res['name'] . '": kết thúc ' . $res['ended'] . ' học viên, từ chối ' . $res['rejected'] . ' yêu cầu');
                $_SESSION['flash'] = ['ok', 'Đã thu hồi vai trò HLV của "' . $res['name'] . '". Kết thúc ' . $res['ended'] . ' học viên, từ chối ' . $res['rejected'] . ' yêu cầu đang chờ.'];
            } catch (CoachException $e) {
                $_SESSION['flash'] = ['error', $e->getMessage()];
            }
        } else { // delete
            if ($user['role'] === 'ADMIN') {
                $_SESSION['flash'] = ['error', 'Không xóa trực tiếp tài khoản ADMIN. Hãy hạ xuống USER trước.'];
            } else {
                $avatar = null; // đọc trước khi xóa để dọn file ảnh đại diện trên đĩa
                try {
                    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                    $stmt->execute([$target]);
                    $avatar = $stmt->fetchColumn();
                } catch (PDOException $e) {
                    // chưa import cột avatar: không có ảnh để xóa
                }
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$target]);
                // Cùng quy tắc với avatarPathOk() trong profile.php: chỉ xóa đúng file trong uploads/avatars/
                if (is_string($avatar) && preg_match('#^uploads/avatars/[A-Za-z0-9._-]+\.jpg$#', $avatar) === 1) {
                    $file = __DIR__ . '/../' . $avatar;
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
                auditLog($pdo, 'user.delete', 'user', $target, 'Xóa tài khoản "' . $user['full_name'] . '"');
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
