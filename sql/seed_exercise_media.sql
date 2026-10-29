-- seed_exercise_media.sql — gắn ảnh và video cho 40 bài tập của seed_exercises_v2.sql (id 1-40).
-- Chạy SAU seed_exercises_v2.sql (file đó xóa sạch bảng exercises, kể cả ảnh/video).
-- Dữ liệu chép từ database của nhóm ngày 29/10/2026. image_url = tên file trong public/img/exercises/,
-- video_url = ID YouTube 11 ký tự (xem docs/ERD.md). Video là link nhúng của kênh gốc, không tải về đăng lại.
USE gymsmart;
SET NAMES utf8mb4;

UPDATE exercises SET image_url = '1.jpg', video_url = 'WDIpL0pjun0' WHERE id = 1;  -- Hít đất
UPDATE exercises SET image_url = '2.jpg', video_url = '3jYo5cMU3d4' WHERE id = 2;  -- Ép ngực máy Pec Deck
UPDATE exercises SET image_url = '3.jpg', video_url = 'lWFknlOTbyM' WHERE id = 3;  -- Đẩy ngực tạ đòn
UPDATE exercises SET image_url = '4.jpg', video_url = 'IP4oeKh1Sd4' WHERE id = 4;  -- Đẩy ngực tạ đơn ghế nghiêng
UPDATE exercises SET image_url = '5.jpg', video_url = 'Qc4L9I3pHnw' WHERE id = 5;  -- Pullover tạ đơn
UPDATE exercises SET image_url = '6.jpg', video_url = 'dX_nSOOJIsE' WHERE id = 6;  -- Dips ngực song song
UPDATE exercises SET image_url = '7.jpg', video_url = 'AOpi-p0cJkc' WHERE id = 7;  -- Kéo cáp xuống
UPDATE exercises SET image_url = '8.jpg', video_url = 'vwHG9Jfu4sw' WHERE id = 8;  -- Kéo cáp ngồi
UPDATE exercises SET image_url = '9.jpg', video_url = 'dFzUjzfih7k' WHERE id = 9;  -- Kéo tạ đơn một tay
UPDATE exercises SET image_url = '10.jpg', video_url = 'soX7zhZ7yfQ' WHERE id = 10;  -- Kéo tay thẳng cáp
UPDATE exercises SET image_url = '11.jpg', video_url = 'qvvJUKq7_sU' WHERE id = 11;  -- Nhún vai tạ đơn
UPDATE exercises SET image_url = '12.jpg', video_url = 'z6PJMT2y8GQ' WHERE id = 12;  -- Siêu nhân
UPDATE exercises SET image_url = '13.jpg', video_url = 'r4MzxtBKyNE' WHERE id = 13;  -- Deadlift tạ đòn
UPDATE exercises SET image_url = '14.jpg', video_url = 'rO_iEImwHyo' WHERE id = 14;  -- Đẩy vai tạ đơn ngồi
UPDATE exercises SET image_url = '15.jpg', video_url = 'PzsMitRdI_8' WHERE id = 15;  -- Nâng tạ đơn sang ngang
UPDATE exercises SET image_url = '16.jpg', video_url = 'x41LBbA6Fig' WHERE id = 16;  -- Face pull
UPDATE exercises SET image_url = '17.jpg', video_url = 'KP1sYz2VICk' WHERE id = 17;  -- Đẩy vai tạ đòn đứng
UPDATE exercises SET image_url = '18.jpg', video_url = 'k_EhLGvM8TQ' WHERE id = 18;  -- Squat tạ đơn kiểu goblet
UPDATE exercises SET image_url = '19.jpg', video_url = 't9183GZlvuo' WHERE id = 19;  -- Bulgarian split squat
UPDATE exercises SET image_url = '20.jpg', video_url = 'ZYDTJaAM-gE' WHERE id = 20;  -- Đạp đùi bằng máy
UPDATE exercises SET image_url = '21.jpg', video_url = 'swZQC689o9U' WHERE id = 21;  -- Duỗi đùi trước bằng máy
UPDATE exercises SET image_url = '22.jpg', video_url = 'i6m3Vp9H40Y' WHERE id = 22;  -- Gập đùi sau nằm bằng máy
UPDATE exercises SET image_url = '23.jpg', video_url = 'y2BloeX5Ebs' WHERE id = 23;  -- Cầu mông hip thrust
UPDATE exercises SET image_url = '24.jpg', video_url = 'k8ipHzKeAkQ' WHERE id = 24;  -- Nhón gót đứng
UPDATE exercises SET image_url = '25.jpg', video_url = 'vExUfXBDCSw' WHERE id = 25;  -- Bước ngang dây kháng lực
UPDATE exercises SET image_url = '26.jpg', video_url = 'JJB8XgKltA8' WHERE id = 26;  -- Cuốn tạ đòn bắp tay
UPDATE exercises SET image_url = '27.jpg', video_url = 'BRVDS6HVR9Q' WHERE id = 27;  -- Cuốn tạ đơn kiểu búa
UPDATE exercises SET image_url = '28.jpg', video_url = '-zLyUAo1gMw' WHERE id = 28;  -- Đẩy tay cáp xuống
UPDATE exercises SET image_url = '29.jpg', video_url = 'fYqswDVbJDg' WHERE id = 29;  -- Duỗi tay sau tạ đơn trên đầu
UPDATE exercises SET image_url = '30.jpg', video_url = 'c3ZGl4pAwZ4' WHERE id = 30;  -- Dips ghế tập tay sau
UPDATE exercises SET image_url = '31.jpg', video_url = '3VLTzIrnb5g' WHERE id = 31;  -- Cuốn cổ tay
UPDATE exercises SET image_url = '32.jpg', video_url = 'NGRKFMKhF8s' WHERE id = 32;  -- Gập bụng
UPDATE exercises SET image_url = '33.jpg', video_url = 'pSHjTRCQxIw' WHERE id = 33;  -- Plank
UPDATE exercises SET image_url = '34.jpg', video_url = 'JB2oyawG9KI' WHERE id = 34;  -- Nâng chân nằm
UPDATE exercises SET image_url = '35.jpg', video_url = 'wkD8rjkodUI' WHERE id = 35;  -- Xoay người kiểu Nga
UPDATE exercises SET image_url = '36.jpg', video_url = 'l-x3HPeHh90' WHERE id = 36;  -- Pallof press
UPDATE exercises SET image_url = '37.jpg', video_url = '_kGESn8ArrU' WHERE id = 37;  -- Chạy bộ
UPDATE exercises SET image_url = '38.jpg', video_url = 'IFgQfVQT_68' WHERE id = 38;  -- Nhảy dây
UPDATE exercises SET image_url = '39.jpg', video_url = 'ZN0J6qKCIrI' WHERE id = 39;  -- Máy chèo thuyền
UPDATE exercises SET image_url = '40.jpg', video_url = 'NH7Xv-7NQNQ' WHERE id = 40;  -- Đi bộ xách tạ
