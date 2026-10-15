-- split_types.sql — thêm các kiểu chia mới cho bảng workout_plans:
--   ANTERIOR_POSTERIOR (Mặt trước / Mặt sau) và 3 lịch kết hợp PPL_UL, UL_FULL_BODY, AP_FULL_BODY.
-- Chạy một lần trên phpMyAdmin (tab SQL). Không đụng dữ liệu cũ.
USE gymsmart;
ALTER TABLE workout_plans
    MODIFY split_type ENUM('FULL_BODY', 'UPPER_LOWER', 'ANTERIOR_POSTERIOR', 'PPL', 'PPL_UL', 'UL_FULL_BODY', 'AP_FULL_BODY', 'BRO_SPLIT') NOT NULL;
