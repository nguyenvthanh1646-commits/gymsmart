<?php
/**
 * includes/audit-log.php — ghi "nhật ký hoạt động" của admin vào bảng admin_audit_log.
 *
 * Dùng: auditLog($pdo, 'user.delete', 'user', $id, 'Xóa tài khoản "Nguyễn A"');
 * Gọi SAU khi hành động thành công (không ghi những lần bị từ chối).
 * Ghi log lỗi thì bỏ qua (chỉ ghi vào log máy chủ): chức năng chính của admin không được hỏng chỉ vì nhật ký.
 * Không bao giờ ghi mật khẩu hay dữ liệu nhạy cảm vào cột detail.
 */

/** Các loại hành động: khóa => nhãn hiển thị. Trang nhật ký chỉ nhận đúng các khóa này khi lọc. */
function auditActions(): array
{
    return [
        'user.set_role'      => 'Đổi vai trò người dùng',
        'user.delete'        => 'Xóa người dùng',
        'exercise.create'    => 'Thêm bài tập',
        'exercise.update'    => 'Sửa bài tập',
        'exercise.duplicate' => 'Nhân bản bài tập',
        'exercise.delete'    => 'Xóa bài tập',
        'article.create'     => 'Thêm bài viết',
        'article.update'     => 'Sửa bài viết',
        'article.publish'    => 'Đăng bài viết',
        'article.unpublish'  => 'Gỡ bài viết về nháp',
        'article.delete'     => 'Xóa bài viết',
        // Phân hệ HLV: 3 mã đầu do ADMIN làm, các mã sau do chính HLV làm (cột admin_id/admin_name lúc đó là HLV)
        'coach.approve'        => 'Duyệt đơn HLV',
        'coach.reject'         => 'Từ chối đơn HLV',
        'coach.revoke'         => 'Thu hồi vai trò HLV',
        'coach.accept'         => 'HLV nhận học viên',
        'coach.decline'        => 'HLV từ chối học viên',
        'coach.end'            => 'HLV kết thúc học viên',
        'coach.assign_plan'    => 'HLV giao lịch tập',
        'coach.set_macros'     => 'HLV đặt mục tiêu dinh dưỡng',
        'coach.change_request' => 'HLV trả lời yêu cầu sửa',
        'coach.pricing'        => 'HLV đặt giá tập',
        'coach.order_paid'     => 'HLV xác nhận đã nhận tiền',
        'coach.order_cancel'   => 'HLV hủy đơn mua gói',
        'coach.session_done'   => 'HLV xác nhận buổi đã tập',
        'coach.session_absent' => 'HLV ghi học viên vắng buổi',
        'expert.approve'       => 'Duyệt đơn chuyên gia sức khỏe',
        'expert.reject'        => 'Từ chối đơn chuyên gia sức khỏe',
        'expert.revoke'        => 'Thu hồi vai trò chuyên gia',
        'system.backup'        => 'Tải bản sao lưu database',
    ];
}

function auditLog(PDO $pdo, string $action, string $targetType, $targetId, string $detail = ''): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, admin_name, action, target_type, target_id, detail, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            mb_substr((string)($_SESSION['full_name'] ?? 'Không rõ'), 0, 100),
            mb_substr($action, 0, 40),
            mb_substr($targetType, 0, 30),
            $targetId === null ? null : mb_substr((string)$targetId, 0, 40),
            mb_substr($detail, 0, 255),
            mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), // chỉ tin REMOTE_ADDR, không tin X-Forwarded-For
            date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('auditLog: ' . $e->getMessage());
    }
}
