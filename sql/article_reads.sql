-- article_reads.sql — ghi nhận người dùng đã đọc bài nền tảng nào, để trang chủ biết cần nhắc bài "Đọc trước khi tập" nào.
-- Cách dùng: phpMyAdmin → chọn database "gymsmart" → tab Import → chọn file này.
-- Chạy lại nhiều lần vẫn an toàn (IF NOT EXISTS).
-- ĐÃ GỘP vào schema.sql (máy mới không cần chạy file này). Chỉ chạy trên database đã tạo từ trước.
-- Chưa chạy file này thì website vẫn hoạt động, chỉ là trang chủ luôn nhắc đọc các bài nền tảng.

-- slug dùng chung cho bài file và bài DB nên không có khóa ngoại tới bảng articles.
CREATE TABLE IF NOT EXISTS article_reads (
    user_id INT NOT NULL,
    slug VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, slug),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
