-- users_avatar.sql — thêm cột ảnh đại diện cho bảng users (A-15, trang Hồ sơ).
-- ĐÃ GỘP vào schema.sql (máy mới không cần chạy file này). Chỉ chạy trên database cũ chưa có cột avatar.
-- Chạy trong phpMyAdmin: chọn database gymsmart > tab SQL > dán > Thực hiện.
-- Lưu đường dẫn tương đối của ảnh, ví dụ: uploads/avatars/u2-1a2b3c4d5e6f.jpg (NULL = chưa có ảnh, dùng chữ cái đầu).
-- Nếu báo "Duplicate column name 'avatar'" nghĩa là đã thêm rồi, bỏ qua.
ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL;
