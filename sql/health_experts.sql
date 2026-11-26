-- health_experts.sql — tư vấn sức khỏe từ chuyên gia (A-55). Chạy SAU coach_features.sql. Chạy lại nhiều lần vẫn an toàn.
-- ĐÃ GỘP vào schema.sql. Database cũ: chạy file này trong phpMyAdmin hoặc `php tools/install.php --upgrade`.
--
-- Quy tắc nằm ở includes/expert-rules.php (hàm thuần) và includes/expert-db.php (truy vấn):
--   - Vai trò mới EXPERT (chuyên gia sức khỏe: bác sĩ, chuyên gia dinh dưỡng, vật lý trị liệu). Chỉ có được khi ADMIN duyệt đơn,
--     đơn bắt buộc có số chứng chỉ hành nghề và file chứng chỉ (lưu ở storage/certificates/, ngoài public/).
--   - Người dùng gửi câu hỏi RIÊNG TƯ theo chủ đề. Chuyên gia có chủ đề phù hợp thấy câu hỏi đang chờ và nhận câu hỏi;
--     từ lúc nhận, chỉ người hỏi và chuyên gia đó trao đổi được. Không có diễn đàn công khai, ADMIN không đọc câu hỏi.
--   - Miễn phí. Mỗi người tối đa 3 câu chưa đóng và 5 câu mới mỗi 24 giờ.

SET NAMES utf8mb4;

-- 1. Thêm vai trò EXPERT. MODIFY ghi lại toàn bộ danh sách nên chạy lại vẫn ra cùng kết quả.
ALTER TABLE users MODIFY role ENUM('USER', 'ADMIN', 'COACH', 'EXPERT') NOT NULL DEFAULT 'USER';

-- 2. Đơn đăng ký làm chuyên gia. open_user_id + UNIQUE: mỗi người tối đa 1 đơn đang chờ.
CREATE TABLE IF NOT EXISTS expert_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    profession ENUM('DOCTOR', 'NUTRITIONIST', 'PHYSIO') NOT NULL,
    title VARCHAR(120) NOT NULL,                    -- chức danh hiển thị, ví dụ "Bác sĩ Y học thể thao"
    workplace VARCHAR(150) NOT NULL,                -- nơi công tác
    license_no VARCHAR(50) NOT NULL,                -- số chứng chỉ hành nghề, CHỈ ADMIN thấy
    experience_years TINYINT UNSIGNED NOT NULL,
    topics VARCHAR(120) NOT NULL,                   -- mã chủ đề nhận tư vấn, cách nhau bởi dấu phẩy (whitelist ở PHP)
    bio VARCHAR(1500) NOT NULL,
    certificate_file VARCHAR(80) NULL,              -- tên file do máy chủ đặt, nằm trong storage/certificates/
    status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    admin_note VARCHAR(500) NOT NULL DEFAULT '',
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    open_user_id INT AS (IF(status = 'PENDING', user_id, NULL)) PERSISTENT,
    UNIQUE KEY uq_exp_app_open (open_user_id),
    INDEX idx_exp_app_status (status, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Hồ sơ công khai của chuyên gia (1-1 với users), tạo từ đơn khi được duyệt.
--    Chuyên gia chỉ tự sửa được giới thiệu, chủ đề và bật/tắt nhận câu hỏi; ngành, chức danh, nơi công tác, số chứng chỉ
--    là thông tin đã được ADMIN xác minh nên muốn đổi phải nộp đơn mới.
CREATE TABLE IF NOT EXISTS expert_profiles (
    user_id INT NOT NULL PRIMARY KEY,
    profession ENUM('DOCTOR', 'NUTRITIONIST', 'PHYSIO') NOT NULL,
    title VARCHAR(120) NOT NULL,
    workplace VARCHAR(150) NOT NULL,
    license_no VARCHAR(50) NOT NULL,
    experience_years TINYINT UNSIGNED NOT NULL,
    topics VARCHAR(120) NOT NULL,
    bio VARCHAR(1500) NOT NULL,
    accepting TINYINT(1) NOT NULL DEFAULT 1,        -- 1 = đang nhận câu hỏi mới
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Câu hỏi tư vấn. OPEN (chờ chuyên gia nhận) → CLAIMED (đã nhận, đang trao đổi) → CLOSED; OPEN → CANCELLED (người hỏi hủy);
--    CLAIMED → OPEN (chuyên gia trả lại khi chưa trả lời câu nào, hoặc bị thu hồi vai trò).
--    profile_snapshot: số liệu hồ sơ người hỏi ĐỒNG Ý chia sẻ, chụp lại lúc gửi (NULL = không chia sẻ).
--    helpful: người hỏi đánh giá lúc đóng (1 = hữu ích, 0 = chưa hữu ích, NULL = không đánh giá).
CREATE TABLE IF NOT EXISTS health_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    topic ENUM('INJURY', 'CONDITION', 'NUTRITION', 'RECOVERY', 'WOMEN', 'OTHER') NOT NULL,
    title VARCHAR(150) NOT NULL,
    body VARCHAR(2000) NOT NULL,
    profile_snapshot VARCHAR(600) NULL,
    red_flags VARCHAR(255) NOT NULL DEFAULT '',     -- dấu hiệu cần cấp cứu máy chủ nhận ra trong câu hỏi (người hỏi đã xác nhận không khẩn cấp)
    status ENUM('OPEN', 'CLAIMED', 'CLOSED', 'CANCELLED') NOT NULL DEFAULT 'OPEN',
    expert_id INT NULL,
    helpful TINYINT(1) NULL,
    created_at DATETIME NOT NULL,
    claimed_at DATETIME NULL,
    closed_at DATETIME NULL,
    closed_by INT NULL,                             -- không FK, chỉ để biết ai đóng
    INDEX idx_hq_user (user_id, status),
    INDEX idx_hq_pool (status, topic, created_at),
    INDEX idx_hq_expert (expert_id, status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (expert_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tin trao đổi trong một câu hỏi (của người hỏi hoặc chuyên gia đã nhận câu hỏi).
CREATE TABLE IF NOT EXISTS health_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    sender_id INT NOT NULL,
    body VARCHAR(2000) NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_hm_q (question_id, id),
    INDEX idx_hm_sender (sender_id, created_at),
    FOREIGN KEY (question_id) REFERENCES health_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
