-- split_type_ap.sql — thêm kiểu chia Mặt trước / Mặt sau (ANTERIOR_POSTERIOR) cho lịch 4 ngày.
-- Chạy một lần trên phpMyAdmin (tab SQL). Không đụng dữ liệu cũ.
USE gymsmart;
ALTER TABLE workout_plans
    MODIFY split_type ENUM('FULL_BODY', 'UPPER_LOWER', 'ANTERIOR_POSTERIOR', 'PPL', 'BRO_SPLIT') NOT NULL;
