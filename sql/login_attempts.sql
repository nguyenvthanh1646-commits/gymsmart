-- login_attempts.sql — ghi các lần đăng nhập SAI để chặn dò mật khẩu (việc A-8).
-- Cách dùng: phpMyAdmin → chọn database "gymsmart" → tab Import → chọn file này.
-- Chạy lại nhiều lần vẫn an toàn (IF NOT EXISTS). Đã gộp vào schema.sql.
-- Không dùng khóa ngoại tới users: email sai (chưa đăng ký) cũng phải được đếm.

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,          -- email đã gõ (viết thường), có thể không tồn tại trong users
    ip VARCHAR(45) NOT NULL,              -- REMOTE_ADDR, đủ chỗ cho IPv6
    attempted_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempted_at),
    INDEX idx_ip_time (ip, attempted_at)
);
