-- schema.sql — tạo TOÀN BỘ bảng cho GymSmart, theo đúng /docs/ERD.md
-- Đã gộp mọi file bổ sung: body_metrics.sql, users_avatar.sql, password_resets.sql, login_attempts.sql,
-- admin_features.sql (admin_audit_log, articles, article_views), split_types.sql.
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
    role ENUM('USER', 'ADMIN') NOT NULL DEFAULT 'USER',
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
    status ENUM('ACTIVE', 'COMPLETED', 'ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
