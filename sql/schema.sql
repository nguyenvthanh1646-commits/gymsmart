-- schema.sql — tạo toàn bộ bảng cho GymSmart, theo đúng /docs/ERD.md
-- Cách dùng: mở phpMyAdmin → tạo database "gymsmart" → tab Import → chọn file này.

CREATE DATABASE IF NOT EXISTS gymsmart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gymsmart;

-- ====== USERS ======
CREATE TABLE users (
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ====== EXERCISES ======
CREATE TABLE exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('RESISTANCE', 'CARDIO') NOT NULL,
    muscle_group VARCHAR(100) NOT NULL,
    description TEXT NULL,
    instructions TEXT NULL,
    common_mistakes TEXT NULL,
    image_url VARCHAR(500) NULL,
    video_url VARCHAR(500) NULL,
    difficulty ENUM('BEGINNER', 'INTERMEDIATE', 'ADVANCED') NOT NULL DEFAULT 'BEGINNER',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ====== WORKOUT PLANS ======
CREATE TABLE workout_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    split_type ENUM('FULL_BODY', 'UPPER_LOWER', 'PPL', 'BRO_SPLIT') NOT NULL,
    days_per_week INT NOT NULL,
    status ENUM('ACTIVE', 'COMPLETED', 'ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE workout_plan_exercises (
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
);

-- ====== WORKOUT LOGS ======
CREATE TABLE workout_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    workout_plan_exercise_id INT NULL,
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
);

-- ====== BODY METRICS ======
CREATE TABLE body_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recorded_date DATE NOT NULL,
    weight_kg FLOAT NULL,
    body_fat_percent FLOAT NULL,
    chest_cm FLOAT NULL,
    waist_cm FLOAT NULL,
    arm_cm FLOAT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ====== NUTRITION TARGETS ======
CREATE TABLE nutrition_targets (
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
);


-- ====== CHATBOT ======
CREATE TABLE chatbot_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE chatbot_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    role ENUM('USER', 'ASSISTANT') NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES chatbot_sessions(id) ON DELETE CASCADE
);
