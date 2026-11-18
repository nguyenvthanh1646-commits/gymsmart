-- weekly_summaries.sql — bản tổng kết tuần do AI viết từ số liệu thật (A-46). Chạy lại nhiều lần vẫn an toàn.
-- ĐÃ GỘP vào schema.sql. Database cũ: chạy file này trong phpMyAdmin hoặc `php tools/install.php --upgrade`.
-- Mỗi người tối đa 1 bản cho mỗi tuần (UNIQUE user_id + week_start). Lưu lại để không gọi AI mỗi lần mở trang.
--   week_start  ngày thứ Hai của tuần được tổng kết
--   stats_json  số liệu đã gửi cho AI (để biết bản tổng kết dựa trên con số nào, kiểm tra lại được)
--   content     lời nhận xét AI viết (văn bản thuần, in ra bằng htmlspecialchars)
CREATE TABLE IF NOT EXISTS weekly_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    week_start DATE NOT NULL,
    stats_json TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_week_user (user_id, week_start),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
