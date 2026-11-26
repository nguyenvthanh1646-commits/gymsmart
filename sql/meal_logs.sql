-- meal_logs.sql — nhật ký ăn trong ngày và thực phẩm tự thêm của người dùng (A-50). Chạy lại nhiều lần vẫn an toàn.
-- ĐÃ GỘP vào schema.sql. Database cũ: chạy file này trong phpMyAdmin hoặc `php tools/install.php --upgrade`.
--   user_foods  thực phẩm người dùng tự thêm theo nhãn bao bì: đạm, tinh bột, béo trên 100 g (calo tính bằng 4P + 4C + 9F).
--   meal_logs   mỗi dòng là một món đã ăn: ngày, bữa, khối lượng và SỐ LIỆU CHỤP LẠI lúc ghi (tên, kcal, P, C, F),
--               để sau này sửa bảng thực phẩm cũng không làm đổi lịch sử. food_key = mã món trong foodCatalog() (meal-plan.php),
--               user_food_id = thực phẩm tự thêm; xóa thực phẩm tự thêm thì các dòng đã ghi vẫn giữ (SET NULL).
CREATE TABLE IF NOT EXISTS user_foods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    protein_100g DECIMAL(5,1) NOT NULL,
    carbs_100g DECIMAL(5,1) NOT NULL,
    fat_100g DECIMAL(5,1) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_uf_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meal_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    log_date DATE NOT NULL,
    meal_kind VARCHAR(12) NOT NULL,           -- breakfast | lunch | dinner | snack
    food_key VARCHAR(20) NULL,
    user_food_id INT NULL,
    food_name VARCHAR(80) NOT NULL,
    grams DECIMAL(6,1) NOT NULL,
    kcal DECIMAL(7,1) NOT NULL,
    protein_g DECIMAL(6,1) NOT NULL,
    carbs_g DECIMAL(6,1) NOT NULL,
    fat_g DECIMAL(6,1) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_ml_user_date (user_id, log_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_food_id) REFERENCES user_foods(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
