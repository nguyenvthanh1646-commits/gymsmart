-- seed.sql — dữ liệu mẫu tối thiểu để test. B sẽ bổ sung đủ 40-50 bài tập
-- khi làm Sprint 1.1 (xem workflow.md), đây chỉ là vài dòng để chạy thử layout.

USE gymsmart;

INSERT INTO exercises (name, type, muscle_group, description, difficulty) VALUES
('Đẩy ngực tạ đòn', 'RESISTANCE', 'CHEST', 'Bài tập cơ bản cho ngực, dùng tạ đòn.', 'BEGINNER'),
('Gập bụng', 'RESISTANCE', 'ABS', 'Bài tập cơ bụng không cần dụng cụ.', 'BEGINNER'),
('Chạy bộ', 'CARDIO', 'FULL_BODY', 'Cardio cơ bản, có thể chạy ngoài trời hoặc máy chạy bộ.', 'BEGINNER');

INSERT INTO tags (name) VALUES ('Dinh dưỡng'), ('Kỹ thuật tập'), ('Phục hồi');
