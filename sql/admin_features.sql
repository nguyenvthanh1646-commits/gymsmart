-- admin_features.sql — 3 bảng cho chức năng admin mới: nhật ký hoạt động, bài viết do admin soạn, lượt xem bài.
-- Cách dùng: phpMyAdmin → chọn database "gymsmart" → tab Import → chọn file này.
-- Chạy lại nhiều lần vẫn an toàn (IF NOT EXISTS).
-- ĐÃ GỘP vào schema.sql (máy mới không cần chạy file này). Chỉ chạy trên database cũ chưa có bảng này.

-- Ai làm gì và lúc nào. Không dùng khóa ngoại: xóa tài khoản admin thì nhật ký vẫn còn (đã lưu cả tên).
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    admin_name VARCHAR(100) NOT NULL,
    action VARCHAR(40) NOT NULL,          -- ví dụ user.delete, exercise.update, article.create
    target_type VARCHAR(30) NOT NULL,     -- user | exercise | article
    target_id VARCHAR(40) NULL,
    detail VARCHAR(255) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_created (created_at),
    INDEX idx_action_time (action, created_at),
    INDEX idx_admin_time (admin_id, created_at)
);

-- Bài viết do admin soạn trên giao diện (bài file trong content/articles/ vẫn dùng bình thường).
-- body và sources lưu dạng chữ theo cú pháp đơn giản (xem includes/article-markup.php).
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(20) NOT NULL DEFAULT 'general',
    tags VARCHAR(255) NOT NULL DEFAULT '',          -- các nhãn ngăn bằng dấu phẩy
    summary VARCHAR(500) NOT NULL DEFAULT '',
    quick TEXT NULL,
    image VARCHAR(255) NULL,                        -- dạng /img/articles/ten.svg
    image_alt VARCHAR(255) NOT NULL DEFAULT '',
    body MEDIUMTEXT NOT NULL,
    sources TEXT NULL,
    status ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
    sort_order INT NOT NULL DEFAULT 100,
    published_date DATE NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_status_order (status, sort_order)
);

-- Lượt xem bài, gộp theo ngày (mỗi bài mỗi ngày một dòng) để bảng không phình to.
CREATE TABLE IF NOT EXISTS article_views (
    slug VARCHAR(100) NOT NULL,
    view_date DATE NOT NULL,
    views INT NOT NULL DEFAULT 0,
    PRIMARY KEY (slug, view_date)
);
