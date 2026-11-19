-- coach_reviews.sql — học viên đánh giá HLV (A-49). Chạy lại nhiều lần vẫn an toàn.
-- ĐÃ GỘP vào schema.sql. Database cũ: chạy file này trong phpMyAdmin hoặc `php tools/install.php --upgrade`.
-- Chỉ học viên ĐÃ THẬT SỰ TẬP với HLV mới đánh giá được (kiểm tra ở includes/coach-reviews.php): quan hệ đã được duyệt,
-- đủ 14 ngày từ lúc bắt đầu, có ít nhất 1 buổi tập ghi trong thời gian đó. Mỗi quan hệ tối đa 1 đánh giá (UNIQUE), sửa được.
-- ADMIN xóa được đánh giá vi phạm (ghi admin_audit_log). Xóa quan hệ / tài khoản thì xóa đánh giá theo.
CREATE TABLE IF NOT EXISTS coach_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_client_id INT NOT NULL,
    coach_id INT NOT NULL,
    client_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,          -- 1 đến 5 sao
    comment VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_review_relation (coach_client_id),
    INDEX idx_review_coach (coach_id, created_at),
    FOREIGN KEY (coach_client_id) REFERENCES coach_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
