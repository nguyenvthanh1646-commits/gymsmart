-- coach_billing.sql — giá tập với HLV, buổi tập thử miễn phí, lịch hẹn và gói buổi / gói tháng (A-54). Chạy lại nhiều lần vẫn an toàn.
-- ĐÃ GỘP vào schema.sql. Database cũ: chạy file này trong phpMyAdmin hoặc `php tools/install.php --upgrade` (chạy SAU coach_features.sql).
-- Cú pháp ADD COLUMN IF NOT EXISTS là của MariaDB (XAMPP).
--
-- Quy tắc nằm ở includes/coach-billing-rules.php (hàm thuần) và includes/coach-billing.php (truy vấn):
--   - HLV đặt giá 1 buổi và/hoặc giá 1 tháng (NULL = không bán kiểu đó), số buổi tập thử miễn phí 1-3.
--   - Học viên đặt lịch hẹn từng buổi; HLV xác nhận lịch, sau giờ tập thì đánh dấu Đã tập hoặc Vắng mặt.
--   - Mỗi buổi được tính vào: gói tháng còn hạn (MONTH), buổi thử còn lại (TRIAL) hoặc gói buổi đã trả (PACK).
--   - Không có cổng thanh toán: học viên chọn gói (đơn PENDING), trả tiền trực tiếp cho HLV, HLV bấm "Đã nhận tiền" (PAID).
--   - Hết buổi thử, hết gói, không có gói tháng còn hạn → HLV tạm không giao lịch / đặt dinh dưỡng / nhận xét được; tin nhắn vẫn dùng được.

SET NAMES utf8mb4;

-- 1. Giá và buổi thử trong hồ sơ HLV. payment_note: hướng dẫn thanh toán (số tài khoản...), CHỈ học viên đang tập với HLV mới thấy.
ALTER TABLE coach_profiles
    ADD COLUMN IF NOT EXISTS price_per_session INT UNSIGNED NULL AFTER availability,
    ADD COLUMN IF NOT EXISTS price_per_month INT UNSIGNED NULL AFTER price_per_session,
    ADD COLUMN IF NOT EXISTS trial_sessions TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER price_per_month,
    ADD COLUMN IF NOT EXISTS payment_note VARCHAR(300) NOT NULL DEFAULT '' AFTER trial_sessions;

-- 2. Số buổi thử CHỤP LẠI lúc học viên gửi yêu cầu (đúng con số học viên đã thấy). NULL = quan hệ có từ trước A-54,
--    khi đó dùng số buổi thử hiện tại trong hồ sơ HLV.
ALTER TABLE coach_clients
    ADD COLUMN IF NOT EXISTS trial_sessions TINYINT UNSIGNED NULL AFTER request_note;

-- 3. Đơn mua gói. Giá chụp lại lúc tạo đơn (HLV đổi giá sau đó không làm đổi đơn cũ).
--    kind = SESSIONS: quantity = số buổi; kind = MONTH: quantity = số tháng (mỗi tháng 30 ngày), starts_on/ends_on điền lúc HLV xác nhận.
--    open_rel: mỗi quan hệ chỉ có tối đa 1 đơn đang chờ thanh toán.
CREATE TABLE IF NOT EXISTS coach_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_client_id INT NOT NULL,
    kind ENUM('SESSIONS', 'MONTH') NOT NULL,
    quantity TINYINT UNSIGNED NOT NULL,
    unit_price INT UNSIGNED NOT NULL,              -- VNĐ cho 1 buổi hoặc 1 tháng
    amount INT UNSIGNED NOT NULL,                  -- VNĐ = unit_price × quantity
    status ENUM('PENDING', 'PAID', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    starts_on DATE NULL,
    ends_on DATE NULL,
    cancel_note VARCHAR(300) NOT NULL DEFAULT '',  -- lý do HLV hủy đơn (vd chưa nhận được tiền)
    cancelled_by INT NULL,                         -- không FK, chỉ để biết ai hủy
    created_at DATETIME NOT NULL,
    paid_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    open_rel INT AS (IF(status = 'PENDING', coach_client_id, NULL)) PERSISTENT,
    UNIQUE KEY uq_order_open (open_rel),
    INDEX idx_order_rel (coach_client_id, status),
    FOREIGN KEY (coach_client_id) REFERENCES coach_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Lịch hẹn từng buổi (mỗi buổi 60 phút). coach_id lặp lại từ quan hệ để kiểm tra trùng giờ của HLV nhanh.
--    REQUESTED (học viên đặt) → CONFIRMED (HLV nhận) → DONE (đã tập) / NO_SHOW (vắng, vẫn tính buổi);
--    REQUESTED → DECLINED (HLV từ chối) / EXPIRED (quá giờ mà HLV chưa nhận); REQUESTED, CONFIRMED → CANCELLED (hủy trước giờ tập).
CREATE TABLE IF NOT EXISTS coach_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coach_client_id INT NOT NULL,
    coach_id INT NOT NULL,
    starts_at DATETIME NOT NULL,
    billing ENUM('TRIAL', 'PACK', 'MONTH') NOT NULL,
    status ENUM('REQUESTED', 'CONFIRMED', 'DONE', 'NO_SHOW', 'CANCELLED', 'DECLINED', 'EXPIRED') NOT NULL DEFAULT 'REQUESTED',
    client_note VARCHAR(300) NOT NULL DEFAULT '',  -- lời nhắn khi đặt (địa điểm, muốn tập phần nào...)
    cancelled_by INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_cs_rel (coach_client_id, starts_at),
    INDEX idx_cs_coach (coach_id, status, starts_at),
    FOREIGN KEY (coach_client_id) REFERENCES coach_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
