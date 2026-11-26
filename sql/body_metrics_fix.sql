-- body_metrics_fix.sql — đưa bảng body_metrics của database CŨ về đúng như schema.sql. Chạy lại nhiều lần vẫn an toàn.
-- Vì sao cần: database tạo từ bản body_metrics.sql đời đầu có các cột số đo kiểu FLOAT (làm tròn sai, ví dụ 72.4 thành 72.40000153),
-- cân nặng cho phép NULL và thiếu index (user_id, recorded_date). File body_metrics.sql dùng CREATE TABLE IF NOT EXISTS
-- nên import lại không sửa được bảng đã có. File này dùng MODIFY (đổi kiểu cột) nên chạy trên bảng đã đúng cũng không sao.
-- Lưu ý: nếu còn dòng nào cân nặng NULL thì lệnh MODIFY ... NOT NULL sẽ báo lỗi. Khi đó xem các dòng đó bằng
--   SELECT * FROM body_metrics WHERE weight_kg IS NULL;   rồi tự quyết định sửa hay xóa (file này không tự xóa dữ liệu).
-- Chạy trong phpMyAdmin: chọn database gymsmart > tab SQL > dán > Thực hiện.

-- Dòng cũ nào chưa có created_at thì lấy ngày đo làm giờ lưu (không xóa dữ liệu), để đổi được sang NOT NULL
UPDATE body_metrics SET created_at = recorded_date WHERE created_at IS NULL;

ALTER TABLE body_metrics
    MODIFY weight_kg DECIMAL(5,2) NOT NULL,
    MODIFY body_fat_percent DECIMAL(4,1) NULL,
    MODIFY chest_cm DECIMAL(5,1) NULL,
    MODIFY waist_cm DECIMAL(5,1) NULL,
    MODIFY arm_cm DECIMAL(4,1) NULL,
    MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_user_date ON body_metrics (user_id, recorded_date);
