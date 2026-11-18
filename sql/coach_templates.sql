-- coach_templates.sql — mẫu lịch tập của HLV để giao nhanh cho nhiều học viên (A-48). Chạy lại nhiều lần vẫn an toàn.
-- ĐÃ GỘP vào schema.sql. Database cũ: chạy file này trong phpMyAdmin hoặc `php tools/install.php --upgrade`.
--   coach_plan_templates        mỗi mẫu một dòng, thuộc về đúng 1 HLV (coach_id). Xóa tài khoản HLV thì xóa mẫu.
--   coach_plan_template_items   các bài của mẫu: bài nào, thứ mấy (1-7), số set, số lặp, thứ tự trong ngày.
--                               Xóa bài tập khỏi thư viện thì bài đó tự biến mất khỏi mẫu (ON DELETE CASCADE).
CREATE TABLE IF NOT EXISTS coach_plan_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_id INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    split_type VARCHAR(30) NOT NULL,
    days_per_week TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_tpl_coach (coach_id),
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coach_plan_template_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    exercise_id INT NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    sets TINYINT UNSIGNED NOT NULL,
    reps TINYINT UNSIGNED NOT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_tpl_items (template_id, day_of_week, order_index),
    FOREIGN KEY (template_id) REFERENCES coach_plan_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
