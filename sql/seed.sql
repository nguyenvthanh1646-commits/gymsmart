-- seed.sql — dữ liệu mẫu tối thiểu để test. B sẽ bổ sung đủ 40-50 bài tập
-- khi làm Sprint 1.1 (xem workflow.md), đây chỉ là vài dòng để chạy thử layout.

USE gymsmart;

INSERT INTO exercises (name, type, muscle_group, description, instructions, common_mistakes, difficulty) VALUES
('Đẩy ngực tạ đòn', 'RESISTANCE', 'CHEST', 'Bài tập cơ bản cho ngực, dùng tạ đòn.',
 'Nằm ngửa trên ghế, hai chân đặt vững trên sàn\nNắm thanh tạ rộng hơn vai một chút\nHạ tạ chậm xuống giữa ngực\nĐẩy tạ lên đến khi duỗi thẳng tay',
 'Nảy tạ trên ngực\nMông nhấc khỏi ghế\nKhuỷu tay mở quá rộng', 'BEGINNER'),
('Gập bụng', 'RESISTANCE', 'ABS', 'Bài tập cơ bụng không cần dụng cụ.',
 'Nằm ngửa, co gối, bàn chân đặt trên sàn\nĐặt tay nhẹ sau đầu hoặc bắt chéo trước ngực\nCuộn thân trên lên bằng cơ bụng\nHạ xuống chậm và có kiểm soát',
 'Kéo cổ bằng tay\nDùng đà để bật người lên\nNín thở', 'BEGINNER'),
('Chạy bộ', 'CARDIO', 'FULL_BODY', 'Cardio cơ bản, có thể chạy ngoài trời hoặc máy chạy bộ.',
 'Khởi động đi bộ nhanh 3-5 phút\nChạy với tốc độ vừa phải, vẫn nói chuyện được\nGiữ lưng thẳng, mắt nhìn phía trước\nKết thúc bằng đi bộ chậm và giãn cơ',
 'Bước sải chân quá dài\nBỏ qua khởi động\nTăng quãng đường quá nhanh', 'BEGINNER'),
('Hít đất', 'RESISTANCE', 'CHEST', 'Bài tập thân trên bằng trọng lượng cơ thể.',
 'Chống hai tay rộng bằng vai, thân thẳng từ đầu đến gót\nHạ ngực gần sát sàn\nĐẩy người lên đến khi duỗi thẳng tay',
 'Võng lưng hoặc nhô mông\nChỉ hạ xuống một nửa biên độ\nKhuỷu tay mở quá rộng', 'BEGINNER'),
('Kéo xà', 'RESISTANCE', 'BACK', 'Bài tập kéo thân trên bằng trọng lượng cơ thể, tập lưng xô và tay.',
 'Nắm xà rộng hơn vai, lòng bàn tay hướng ra trước\nBắt đầu từ vị trí treo thẳng tay\nKéo người lên đến khi cằm qua xà\nHạ xuống chậm về vị trí ban đầu',
 'Đung đưa người lấy đà\nKhông duỗi hết tay ở vị trí thấp\nNhún vai lên sát tai', 'ADVANCED'),
('Squat tạ đòn', 'RESISTANCE', 'LEGS', 'Bài tập nền tảng cho chân và mông.',
 'Đặt thanh tạ trên vai sau, hai chân rộng bằng vai\nGiữ ngực thẳng, siết cơ bụng\nHạ người xuống như ngồi ghế đến khi đùi song song sàn\nĐẩy qua gót chân để đứng lên',
 'Đầu gối chụm vào trong\nGót chân nhấc khỏi sàn\nCong lưng khi xuống', 'INTERMEDIATE'),
('Đẩy vai tạ đơn', 'RESISTANCE', 'SHOULDERS', 'Bài tập phát triển cơ vai bằng tạ đơn.',
 'Ngồi thẳng lưng, hai tạ đơn ngang tai\nĐẩy tạ lên trên đầu đến khi gần duỗi thẳng tay\nHạ tạ chậm về ngang tai',
 'Ưỡn lưng quá mức\nDùng đà từ chân\nChọn tạ quá nặng', 'INTERMEDIATE'),
('Cuốn tạ đơn bắp tay', 'RESISTANCE', 'ARMS', 'Bài tập cô lập cơ nhị đầu cánh tay.',
 'Đứng thẳng, hai tay cầm tạ đơn, lòng bàn tay hướng ra trước\nGiữ khuỷu tay sát thân\nCuốn tạ lên đến vai\nHạ xuống chậm',
 'Đung đưa người lấy đà\nKhuỷu tay đưa ra trước\nHạ tạ quá nhanh', 'BEGINNER'),
('Plank', 'RESISTANCE', 'ABS', 'Bài tập giữ tư thế, tăng sức mạnh cơ bụng và thân.',
 'Chống hai khuỷu tay dưới vai, mũi chân chạm sàn\nGiữ thân thẳng từ đầu đến gót\nSiết cơ bụng và mông, giữ tư thế 20-60 giây',
 'Võng lưng\nNhô mông cao\nNín thở', 'BEGINNER'),
('Burpee', 'CARDIO', 'FULL_BODY', 'Bài cardio cường độ cao kết hợp cả người.',
 'Đứng thẳng, ngồi xổm và chống tay xuống sàn\nĐá chân ra sau về tư thế hít đất\nThu chân về, bật nhảy lên và vỗ tay trên đầu',
 'Võng lưng khi vào tư thế hít đất\nTiếp đất mạnh bằng đầu gối\nLàm quá nhanh làm sai kỹ thuật', 'INTERMEDIATE');