-- password_resets.sql — bảng mã đặt lại mật khẩu (chức năng "Quên mật khẩu", việc A-10).
-- Cách dùng: phpMyAdmin → chọn database "gymsmart" → tab Import → chọn file này.
-- Báo D gộp đoạn này vào schema.sql và thêm bảng vào ERD.md.
-- Chạy lại nhiều lần vẫn an toàn (IF NOT EXISTS).
-- Chỉ lưu bản BĂM (SHA-256) của mã; mã thật chỉ nằm trong đường link gửi cho người dùng.

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,         -- sha256 của mã trong link (64 ký tự hex)
    expires_at DATETIME NOT NULL,         -- hết hạn sau 30 phút
    used_at DATETIME NULL,                -- đã dùng thì ghi thời điểm, mã không dùng lại được
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_token (token_hash),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
