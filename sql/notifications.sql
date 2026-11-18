-- notifications.sql — thông báo trong ứng dụng (chuông trên thanh menu, A-44). Chạy lại nhiều lần vẫn an toàn.
-- ĐÃ GỘP vào schema.sql. Database cũ: chạy file này trong phpMyAdmin hoặc `php tools/install.php --upgrade`.
-- Mỗi dòng là một thông báo cho MỘT người (user_id): HLV nhận xét, tin nhắn mới, đơn HLV được duyệt, yêu cầu học viên...
--   type      mã sự kiện viết sẵn trong code (vd coach.message), dùng để gộp thông báo trùng và lọc
--   link      đường dẫn nội bộ bắt đầu bằng "/" (code tự tạo, không nhận từ người dùng)
--   read_at   NULL = chưa đọc
-- Xóa tài khoản thì xóa luôn thông báo của người đó (ON DELETE CASCADE).
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(160) NOT NULL,
    body VARCHAR(300) NOT NULL DEFAULT '',
    link VARCHAR(255) NOT NULL DEFAULT '',
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_notif_user (user_id, read_at, id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
