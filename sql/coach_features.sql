-- coach_features.sql — phân hệ Huấn luyện viên (HLV, vai trò COACH).
--
-- Chạy MỘT lần trên database đã có các bảng của schema.sql (phpMyAdmin → chọn database gymsmart → tab Import).
-- Chạy lại nhiều lần vẫn an toàn: bảng dùng CREATE TABLE IF NOT EXISTS, cột dùng ADD COLUMN IF NOT EXISTS,
-- khóa ngoại dùng ADD CONSTRAINT ... FOREIGN KEY IF NOT EXISTS.
-- Lưu ý: cú pháp "IF NOT EXISTS" cho cột và khóa ngoại là của MariaDB (XAMPP dùng MariaDB). MySQL 8 không có,
-- nếu dùng MySQL 8 thì bỏ chữ IF NOT EXISTS ở 2 lệnh ALTER cuối và chỉ chạy một lần.
--
-- Bảng mới: coach_applications, coach_profiles, coach_clients, coach_feedback, coach_messages, coach_change_requests.
-- Cột mới: workout_plans.assigned_by, nutrition_targets.assigned_by. Vai trò mới: users.role = 'COACH'.
-- Mô tả từng bảng: docs/ERD.md, mục "Phân hệ HLV".

SET NAMES utf8mb4;

-- 1. Thêm vai trò COACH. MODIFY ghi lại toàn bộ danh sách nên chạy lại vẫn ra cùng kết quả.
--    Có luôn 'EXPERT' (chuyên gia sức khỏe, sql/health_experts.sql) để chạy lại file này trên DB đã có chuyên gia không bị lỗi.
ALTER TABLE users MODIFY role ENUM('USER', 'ADMIN', 'COACH', 'EXPERT') NOT NULL DEFAULT 'USER';

-- 2. Đơn đăng ký làm HLV. Người dùng nộp, ADMIN duyệt (APPROVED → đổi vai trò thành COACH) hoặc từ chối.
--    open_user_id: chỉ có giá trị khi đơn đang PENDING, có UNIQUE nên mỗi người chỉ có tối đa 1 đơn đang chờ
--    (NULL không bị tính trùng, nên đơn cũ đã duyệt/từ chối không cản việc nộp lại).
CREATE TABLE IF NOT EXISTS coach_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    headline VARCHAR(120) NOT NULL,                 -- chức danh ngắn, ví dụ "HLV thể hình, chuyên giảm mỡ"
    specialties VARCHAR(255) NOT NULL,              -- các mã chuyên môn cách nhau bởi dấu phẩy (PHP kiểm tra whitelist)
    experience_years TINYINT UNSIGNED NOT NULL,     -- 0-50
    certifications VARCHAR(1000) NOT NULL DEFAULT '', -- mỗi dòng một chứng chỉ: tên - nơi cấp - năm
    bio VARCHAR(1500) NOT NULL,                     -- giới thiệu bản thân, hiện cho học viên
    approach VARCHAR(1000) NOT NULL,                -- phương pháp huấn luyện
    max_clients TINYINT UNSIGNED NOT NULL DEFAULT 10, -- 1-30
    availability VARCHAR(255) NOT NULL DEFAULT '',  -- thời gian có thể hỗ trợ, ví dụ "Tối thứ 2-6"
    status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    admin_note VARCHAR(500) NOT NULL DEFAULT '',    -- lý do từ chối / ghi chú của ADMIN, người nộp thấy được
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    open_user_id INT AS (IF(status = 'PENDING', user_id, NULL)) PERSISTENT,
    UNIQUE KEY uq_app_open (open_user_id),
    INDEX idx_app_status (status, created_at),
    INDEX idx_app_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Hồ sơ công khai của HLV (1-1 với users). Tạo từ đơn khi được duyệt, HLV tự sửa sau đó.
CREATE TABLE IF NOT EXISTS coach_profiles (
    user_id INT NOT NULL PRIMARY KEY,
    headline VARCHAR(120) NOT NULL,
    specialties VARCHAR(255) NOT NULL,
    experience_years TINYINT UNSIGNED NOT NULL,
    certifications VARCHAR(1000) NOT NULL DEFAULT '',
    bio VARCHAR(1500) NOT NULL,
    approach VARCHAR(1000) NOT NULL DEFAULT '',
    max_clients TINYINT UNSIGNED NOT NULL DEFAULT 10,
    availability VARCHAR(255) NOT NULL DEFAULT '',
    accepting TINYINT(1) NOT NULL DEFAULT 1,        -- 1 = đang nhận học viên mới
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Quan hệ HLV - học viên. PENDING (chờ HLV duyệt) → ACTIVE → ENDED; hoặc PENDING → REJECTED.
--    open_client_id: có giá trị khi PENDING hoặc ACTIVE, có UNIQUE → mỗi học viên chỉ có 1 HLV (đang chờ hoặc đang tập).
CREATE TABLE IF NOT EXISTS coach_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_id INT NOT NULL,
    client_id INT NOT NULL,
    status ENUM('PENDING', 'ACTIVE', 'ENDED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    request_note VARCHAR(500) NOT NULL DEFAULT '',  -- lời nhắn của học viên khi gửi yêu cầu
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME NULL,
    ended_at DATETIME NULL,                         -- lúc kết thúc / từ chối / hủy
    ended_by INT NULL,                              -- ai kết thúc (học viên, HLV hoặc ADMIN khi thu hồi); không FK
    open_client_id INT AS (IF(status IN ('PENDING', 'ACTIVE'), client_id, NULL)) PERSISTENT,
    UNIQUE KEY uq_cc_open_client (open_client_id),
    INDEX idx_cc_coach (coach_id, status),
    INDEX idx_cc_client (client_id, status),
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Nhận xét của HLV. log_date có giá trị = nhận xét buổi tập ngày đó; workout_log_id = nhận xét riêng 1 bài;
--    cả hai NULL = nhận xét chung. read_at: lúc học viên đã xem (để hiện "nhận xét mới").
CREATE TABLE IF NOT EXISTS coach_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_client_id INT NOT NULL,
    log_date DATE NULL,
    workout_log_id INT NULL,
    content VARCHAR(1000) NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fb_rel (coach_client_id, created_at),
    FOREIGN KEY (coach_client_id) REFERENCES coach_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (workout_log_id) REFERENCES workout_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tin nhắn giữa HLV và học viên, gắn với một quan hệ (không có chat tự do giữa người dùng với nhau).
CREATE TABLE IF NOT EXISTS coach_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_client_id INT NOT NULL,
    sender_id INT NOT NULL,
    content VARCHAR(1000) NOT NULL,
    read_at DATETIME NULL,                          -- lúc người nhận đã xem
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_msg_rel (coach_client_id, id),
    FOREIGN KEY (coach_client_id) REFERENCES coach_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Học viên xin tự sửa lịch tập (PLAN) hoặc dinh dưỡng (NUTRITION) khi đang có HLV.
--    HLV duyệt thì học viên được tự sửa phần đó tới unlock_until (24 giờ).
--    open_key: mỗi quan hệ chỉ có 1 yêu cầu đang chờ cho mỗi phần.
CREATE TABLE IF NOT EXISTS coach_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_client_id INT NOT NULL,
    area ENUM('PLAN', 'NUTRITION') NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    coach_reply VARCHAR(500) NOT NULL DEFAULT '',
    unlock_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    open_key VARCHAR(30) AS (IF(status = 'PENDING', CONCAT(coach_client_id, '-', area), NULL)) PERSISTENT,
    UNIQUE KEY uq_ccr_open (open_key),
    INDEX idx_ccr_rel (coach_client_id, area, status),
    FOREIGN KEY (coach_client_id) REFERENCES coach_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Đánh dấu lịch tập / mục tiêu dinh dưỡng do HLV giao (NULL = học viên tự tạo).
--    Xóa tài khoản HLV thì cột về NULL, lịch và mục tiêu của học viên vẫn còn.
ALTER TABLE workout_plans
    ADD COLUMN IF NOT EXISTS assigned_by INT NULL AFTER status,
    ADD CONSTRAINT fk_wp_assigned_by FOREIGN KEY IF NOT EXISTS (assigned_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE nutrition_targets
    ADD COLUMN IF NOT EXISTS assigned_by INT NULL AFTER calculated_date,
    ADD CONSTRAINT fk_nt_assigned_by FOREIGN KEY IF NOT EXISTS (assigned_by) REFERENCES users(id) ON DELETE SET NULL;
