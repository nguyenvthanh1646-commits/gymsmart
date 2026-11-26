-- coach_extras.sql — phần mở rộng phân hệ HLV (chạy SAU sql/coach_features.sql). Chạy lại nhiều lần vẫn an toàn.
-- Cú pháp ADD COLUMN IF NOT EXISTS là của MariaDB (XAMPP).
--
--   1. workout_plans.status thêm 'DRAFT': HLV soạn lịch nháp, học viên không thấy cho tới khi HLV bấm "Giao lịch".
--      Mọi chỗ đọc lịch của học viên đều lọc status = 'ACTIVE' nên bản nháp không lộ ra.
--   2. coach_feedback.updated_at: HLV sửa nhận xét thì ghi lúc sửa (học viên thấy nhãn "đã sửa").
--   3. coach_applications.certificate_file: tên file ảnh/PDF chứng chỉ người nộp tải lên (không bắt buộc).
--      File lưu ở storage/certificates/ NGOÀI thư mục public/, chỉ ADMIN xem qua admin/certificate.php.

SET NAMES utf8mb4;

ALTER TABLE workout_plans
    MODIFY status ENUM('ACTIVE', 'COMPLETED', 'ARCHIVED', 'DRAFT') NOT NULL DEFAULT 'ACTIVE';

ALTER TABLE coach_feedback
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL AFTER created_at;

ALTER TABLE coach_applications
    ADD COLUMN IF NOT EXISTS certificate_file VARCHAR(80) NULL AFTER certifications;
