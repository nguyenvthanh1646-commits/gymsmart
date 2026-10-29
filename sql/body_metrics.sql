-- body_metrics.sql — bảng số đo cơ thể theo từng ngày, dùng cho trang Tiến trình (việc C-3, A nhận làm).
-- Cách dùng: phpMyAdmin → chọn database "gymsmart" → tab Import → chọn file này.
-- ĐÃ GỘP vào schema.sql (máy mới không cần chạy file này). Chỉ chạy trên database cũ chưa có bảng này.
-- Chạy lại nhiều lần vẫn an toàn (IF NOT EXISTS).
--
-- Nếu báo lỗi #1215 "Cannot add foreign key constraint": cột users.id khác kiểu với user_id ở đây.
-- Mở bảng users → tab Structure xem id là INT hay BIGINT (có UNSIGNED không) rồi sửa user_id cho giống,
-- hoặc tạm xóa dòng FOREIGN KEY (nhớ dấu phẩy ở dòng trước nó).

CREATE TABLE IF NOT EXISTS body_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recorded_date DATE NOT NULL,              -- ngày đo (user chọn), khác created_at là lúc bấm lưu
    weight_kg DECIMAL(5,2) NOT NULL,          -- bắt buộc: biểu đồ chính là cân nặng
    body_fat_percent DECIMAL(4,1) NULL,       -- các số đo dưới đây không bắt buộc, để trống = NULL
    chest_cm DECIMAL(5,1) NULL,
    waist_cm DECIMAL(5,1) NULL,
    arm_cm DECIMAL(4,1) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, recorded_date),   -- truy vấn "số đo của user này theo ngày" chạy nhanh
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE   -- xóa user thì xóa luôn số đo của họ
);
