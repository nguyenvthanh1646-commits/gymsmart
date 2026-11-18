-- schema.sql — tạo TOÀN BỘ bảng cho GymSmart, theo đúng /docs/ERD.md
-- Đã gộp mọi file bổ sung: body_metrics.sql, users_avatar.sql, password_resets.sql, login_attempts.sql,
-- admin_features.sql (admin_audit_log, articles, article_views), split_types.sql, nutrition_preferences.sql,
-- coach_features.sql (phân hệ HLV: vai trò COACH, 6 bảng coach_*, cột assigned_by), coach_extras.sql.
--
-- Cách dùng trên máy MỚI (database trống):
--   phpMyAdmin → tab Import → chọn file này → rồi Import sql/seed_exercises_v2.sql → sql/seed_exercise_media.sql
--   Không cần chạy các file bổ sung kia nữa.
-- Máy ĐÃ có database cũ: không cần chạy lại file này, chỉ chạy những file bổ sung còn thiếu
-- (mọi lệnh CREATE đều có IF NOT EXISTS nên chạy lại cũng không mất dữ liệu, nhưng bảng đã có sẽ KHÔNG được sửa).

CREATE DATABASE IF NOT EXISTS gymsmart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gymsmart;
SET NAMES utf8mb4;

-- ====== USERS ======
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('USER', 'ADMIN', 'COACH') NOT NULL DEFAULT 'USER',   -- COACH: chỉ ADMIN cấp, qua đơn đăng ký HLV
    age INT NULL,
    height_cm FLOAT NULL,
    weight_kg FLOAT NULL,
    gender ENUM('MALE', 'FEMALE', 'OTHER') NULL,
    activity_level ENUM('SEDENTARY', 'LIGHT', 'MODERATE', 'ACTIVE', 'EXTRA_ACTIVE') NULL,
    experience_level ENUM('BEGINNER','INTERMEDIATE','ADVANCED') NULL,
    goal ENUM('LOSE_FAT', 'MAINTAIN', 'GAIN_MUSCLE') NULL,
    avatar VARCHAR(255) NULL,                 -- đường dẫn tương đối, ví dụ uploads/avatars/u2-1a2b3c.jpg; NULL = dùng chữ cái đầu
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====== EXERCISES ======
CREATE TABLE IF NOT EXISTS exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('RESISTANCE', 'CARDIO') NOT NULL,
    muscle_group VARCHAR(100) NOT NULL,       -- CHEST | BACK | SHOULDERS | LEGS | ARMS | ABS | FULL_BODY (PHP kiểm tra whitelist)
    description TEXT NULL,
    instructions TEXT NULL,                   -- mỗi dòng một bước
    common_mistakes TEXT NULL,                -- mỗi dòng một lỗi
    image_url VARCHAR(500) NULL,              -- chỉ TÊN FILE trong public/img/exercises/, ví dụ 12.jpg
    video_url VARCHAR(500) NULL,              -- chỉ ID YouTube 11 ký tự
    difficulty ENUM('BEGINNER', 'INTERMEDIATE', 'ADVANCED') NOT NULL DEFAULT 'BEGINNER',
    equipment VARCHAR(20) NOT NULL DEFAULT 'NONE',      -- dụng cụ cần (A-47, sql/exercise_equipment.sql): NONE | BENCH | BARS | BAND | ROPE | DUMBBELL | BARBELL | MACHINE | CABLE
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====== WORKOUT PLANS ======
CREATE TABLE IF NOT EXISTS workout_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    split_type ENUM('FULL_BODY', 'UPPER_LOWER', 'ANTERIOR_POSTERIOR', 'PPL', 'PPL_UL', 'UL_FULL_BODY', 'AP_FULL_BODY', 'BRO_SPLIT') NOT NULL,
    days_per_week INT NOT NULL,
    status ENUM('ACTIVE', 'COMPLETED', 'ARCHIVED', 'DRAFT') NOT NULL DEFAULT 'ACTIVE',   -- DRAFT: bản nháp HLV đang soạn, học viên không thấy
    assigned_by INT NULL,                     -- id HLV giao lịch; NULL = người dùng tự tạo
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wp_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workout_plan_exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workout_plan_id INT NOT NULL,
    exercise_id INT NOT NULL,
    day_of_week INT NOT NULL, -- 1-7
    sets INT NOT NULL,
    reps INT NOT NULL,
    weight_kg_suggested FLOAT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====== WORKOUT LOGS ======
CREATE TABLE IF NOT EXISTS workout_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    workout_plan_exercise_id INT NULL,        -- NULL = tập ngoài lịch
    exercise_id INT NOT NULL,
    log_date DATE NOT NULL,
    sets_completed INT NOT NULL,
    reps_completed INT NOT NULL,
    weight_kg_used FLOAT NULL,
    note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (workout_plan_exercise_id) REFERENCES workout_plan_exercises(id) ON DELETE SET NULL,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====== BODY METRICS (theo sql/body_metrics.sql) ======
CREATE TABLE IF NOT EXISTS body_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recorded_date DATE NOT NULL,              -- ngày đo (user chọn), khác created_at là lúc bấm lưu
    weight_kg DECIMAL(5,2) NOT NULL,          -- bắt buộc: biểu đồ chính là cân nặng
    body_fat_percent DECIMAL(4,1) NULL,
    chest_cm DECIMAL(5,1) NULL,
    waist_cm DECIMAL(5,1) NULL,
    arm_cm DECIMAL(4,1) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, recorded_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====== NUTRITION PREFERENCES ======
-- Lựa chọn trên trang Dinh dưỡng, mỗi người một dòng. excluded_foods: các mã cách nhau bởi dấu phẩy.
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

-- ====== NUTRITION TARGETS ======
CREATE TABLE IF NOT EXISTS nutrition_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tdee_kcal FLOAT NOT NULL,
    target_kcal FLOAT NOT NULL,
    protein_g FLOAT NOT NULL,
    carbs_g FLOAT NOT NULL,
    fat_g FLOAT NOT NULL,
    calculated_date DATE NOT NULL,
    assigned_by INT NULL,                     -- id HLV đặt mục tiêu; NULL = trang Dinh dưỡng tự tính
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_nt_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====== CHATBOT ======
-- CHƯA DÙNG: lịch sử chat hiện lưu ở trình duyệt (js/chatbot.js). Giữ bảng cho bước "lưu lịch sử trên server" sau này.
CREATE TABLE IF NOT EXISTS chatbot_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chatbot_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    role ENUM('USER', 'ASSISTANT') NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES chatbot_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====== QUÊN MẬT KHẨU (theo sql/password_resets.sql) ======
-- Chỉ lưu bản BĂM SHA-256 của mã; mã thật chỉ nằm trong link gửi cho người dùng.
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,             -- hết hạn sau 30 phút
    used_at DATETIME NULL,                    -- đã dùng thì không dùng lại được
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_token (token_hash),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====== CHẶN DÒ MẬT KHẨU (theo sql/login_attempts.sql) ======
-- Không có khóa ngoại: email chưa đăng ký cũng phải được đếm.
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempted_at),
    INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====== ADMIN (theo sql/admin_features.sql) ======
-- Nhật ký thao tác admin. Không dùng khóa ngoại: xóa tài khoản admin thì nhật ký vẫn còn (đã lưu cả tên).
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    admin_name VARCHAR(100) NOT NULL,
    action VARCHAR(40) NOT NULL,              -- ví dụ user.delete, exercise.update, article.create
    target_type VARCHAR(30) NOT NULL,         -- user | exercise | article
    target_id VARCHAR(40) NULL,
    detail VARCHAR(255) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_created (created_at),
    INDEX idx_action_time (action, created_at),
    INDEX idx_admin_time (admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bài viết do admin soạn (bài dạng file trong public/content/articles/ không nằm trong bảng này).
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(20) NOT NULL DEFAULT 'general',
    tags VARCHAR(255) NOT NULL DEFAULT '',    -- các nhãn ngăn bằng dấu phẩy (không có bảng tags riêng)
    summary VARCHAR(500) NOT NULL DEFAULT '',
    quick TEXT NULL,
    image VARCHAR(255) NULL,                  -- dạng /img/articles/ten.svg
    image_alt VARCHAR(255) NOT NULL DEFAULT '',
    body MEDIUMTEXT NOT NULL,
    sources TEXT NULL,
    status ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
    sort_order INT NOT NULL DEFAULT 100,
    published_date DATE NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_status_order (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lượt xem bài, gộp theo ngày (mỗi bài mỗi ngày một dòng). slug dùng chung cho bài file và bài DB.
CREATE TABLE IF NOT EXISTS article_views (
    slug VARCHAR(100) NOT NULL,
    view_date DATE NOT NULL,
    views INT NOT NULL DEFAULT 0,
    PRIMARY KEY (slug, view_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Người dùng đã đọc bài nền tảng nào (trang chủ dựa vào đây để nhắc). slug dùng chung cho bài file và bài DB nên không có khóa ngoại tới articles.
CREATE TABLE IF NOT EXISTS article_reads (
    user_id INT NOT NULL,
    slug VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, slug),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====== PHÂN HỆ HUẤN LUYỆN VIÊN (theo sql/coach_features.sql) ======
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
    certificate_file VARCHAR(80) NULL,              -- file chứng chỉ trong storage/certificates/ (ngoài public/), chỉ ADMIN xem
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
    updated_at DATETIME NULL,                       -- HLV sửa nhận xét
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

-- Thông báo trong ứng dụng (A-44, sql/notifications.sql)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(40) NOT NULL,                -- mã sự kiện viết sẵn trong code, vd coach.message
    title VARCHAR(160) NOT NULL,
    body VARCHAR(300) NOT NULL DEFAULT '',
    link VARCHAR(255) NOT NULL DEFAULT '',     -- đường dẫn nội bộ bắt đầu bằng /
    read_at DATETIME NULL,                    -- NULL = chưa đọc
    created_at DATETIME NOT NULL,
    INDEX idx_notif_user (user_id, read_at, id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tổng kết tuần do AI viết (A-46, sql/weekly_summaries.sql)
CREATE TABLE IF NOT EXISTS weekly_summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    week_start DATE NOT NULL,                 -- thứ Hai của tuần được tổng kết
    stats_json TEXT NOT NULL,                 -- số liệu đã gửi cho AI
    content TEXT NOT NULL,                    -- lời nhận xét AI viết
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_week_user (user_id, week_start),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mẫu lịch tập của HLV (A-48, sql/coach_templates.sql)
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
