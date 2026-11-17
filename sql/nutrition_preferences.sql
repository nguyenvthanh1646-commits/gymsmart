-- nutrition_preferences.sql — lưu lựa chọn trên trang Dinh dưỡng để giữ qua các lần đăng nhập.
-- Cách dùng: phpMyAdmin → chọn database "gymsmart" → tab Import → chọn file này.
-- Chạy lại nhiều lần vẫn an toàn (IF NOT EXISTS).
-- ĐÃ GỘP vào schema.sql (máy mới không cần chạy file này). Chỉ chạy trên database đã tạo từ trước.
-- Chưa chạy file này thì website vẫn hoạt động, lựa chọn chỉ được giữ trong phiên đăng nhập hiện tại.

-- Mỗi người một dòng nên user_id là khóa chính.
-- excluded_foods: các mã cách nhau bởi dấu phẩy (vegetarian, seafood, beef, pork, egg, dairy), xem foodExclusions() trong includes/meal-plan.php.
CREATE TABLE IF NOT EXISTS nutrition_preferences (
    user_id INT NOT NULL,
    diet_style VARCHAR(20) NOT NULL DEFAULT 'goal',
    custom_protein_pct TINYINT UNSIGNED NOT NULL DEFAULT 25,
    custom_carbs_pct TINYINT UNSIGNED NOT NULL DEFAULT 45,
    custom_fat_pct TINYINT UNSIGNED NOT NULL DEFAULT 30,
    meals_per_day TINYINT UNSIGNED NOT NULL DEFAULT 4,
    excluded_foods VARCHAR(100) NOT NULL DEFAULT '',
    weight_pace VARCHAR(10) NOT NULL DEFAULT 'standard',
    cycle_calories TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
