-- coach_bank.sql — tài khoản nhận chuyển khoản của HLV để hiện mã VietQR cho đơn mua gói (A-56). Chạy lại nhiều lần vẫn an toàn.
-- ĐÃ GỘP vào schema.sql. Database cũ: chạy file này trong phpMyAdmin hoặc `php tools/install.php --upgrade` (chạy SAU coach_billing.sql).
-- Cú pháp ADD COLUMN IF NOT EXISTS là của MariaDB (XAMPP).
--
-- Quy tắc nằm ở includes/coach-billing-rules.php (validateCoachBank, coachVietQrUrl):
--   - bank_bin: mã BIN ngân hàng, chỉ nhận mã trong whitelist COACH_BANKS (viết sẵn trong PHP).
--   - bank_account: chỉ chữ số, 6-19 ký tự. bank_holder: chữ in hoa không dấu, tối đa 50 ký tự.
--   - 3 cột cùng có giá trị hoặc cùng NULL (NULL = HLV chưa ghi tài khoản → học viên thấy payment_note như cũ).
--   - CHỈ học viên đang có đơn chờ thanh toán với HLV đó mới thấy (kèm ảnh QR từ img.vietqr.io). Không đổi cách xác nhận tiền.

SET NAMES utf8mb4;

ALTER TABLE coach_profiles
    ADD COLUMN IF NOT EXISTS bank_bin CHAR(6) NULL AFTER payment_note,
    ADD COLUMN IF NOT EXISTS bank_account VARCHAR(19) NULL AFTER bank_bin,
    ADD COLUMN IF NOT EXISTS bank_holder VARCHAR(50) NULL AFTER bank_account;
