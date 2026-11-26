-- users_avatar.sql — thêm cột ảnh đại diện cho bảng users (A-15, trang Hồ sơ).
-- ĐÃ GỘP vào schema.sql (máy mới không cần chạy file này). Chạy lại nhiều lần vẫn an toàn
-- (cú pháp ADD COLUMN IF NOT EXISTS của MariaDB/XAMPP: đã có cột thì bỏ qua, không báo lỗi).
-- Chạy trong phpMyAdmin: chọn database gymsmart > tab SQL > dán > Thực hiện.
-- Lưu đường dẫn tương đối của ảnh, ví dụ: uploads/avatars/u2-1a2b3c4d5e6f.jpg (NULL = chưa có ảnh, dùng chữ cái đầu).
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) NULL;
