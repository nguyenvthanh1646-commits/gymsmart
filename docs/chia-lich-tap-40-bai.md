# Chia lịch tập với bộ 40 bài mới (gợi ý cho B-7 / B-8)

Nạp `sql/seed_exercises_v2.sql` thì id bài tập cố định như bảng dưới.

| Nhóm (`muscle_group`) | id | Bài (mỗi bài một kiểu chuyển động riêng) |
|---|---|---|
| CHEST (6) | 1-6 | 1 Hít đất (đẩy ngang) · 2 Ép ngực máy (khép tay) · 3 Đẩy ngực tạ đòn · 4 Đẩy ngực ghế nghiêng (ngực trên) · 5 Pullover (vòng cung qua đầu) · 6 Dips ngực |
| BACK (7) | 7-13 | 7 Kéo cáp xuống (kéo dọc) · 8 Kéo cáp ngồi (kéo ngang) · 9 Kéo tạ đơn một tay · 10 Kéo tay thẳng cáp · 11 Nhún vai (cơ thang) · 12 Siêu nhân (lưng dưới) · 13 Deadlift |
| SHOULDERS (4) | 14-17 | 14 Đẩy vai tạ đơn ngồi · 15 Nâng sang ngang (vai giữa) · 16 Face pull (vai sau) · 17 Đẩy vai tạ đòn đứng |
| LEGS (8) | 18-25 | 18 Goblet squat · 19 Bulgarian split squat · 20 Đạp đùi máy · 21 Duỗi đùi trước · 22 Gập đùi sau · 23 Hip thrust (mông) · 24 Nhón gót (bắp chân) · 25 Bước ngang dây kháng lực |
| ARMS (6) | 26-31 | Tay trước: 26 Cuốn tạ đòn · 27 Cuốn kiểu búa · 31 Cuốn cổ tay. Tay sau: 28 Đẩy cáp xuống · 29 Duỗi tay trên đầu · 30 Dips ghế |
| ABS (5) | 32-36 | 32 Gập bụng (gập thân) · 33 Plank (giữ thẳng) · 34 Nâng chân (bụng dưới) · 35 Xoay kiểu Nga (xoay thân) · 36 Pallof press (chống xoay) |
| FULL_BODY (4) | 37-40 | Cardio: 37 Chạy bộ · 38 Nhảy dây · 39 Máy chèo. Kháng lực: 40 Đi bộ xách tạ |

Độ khó: 20 dễ, 16 trung bình, 4 khó (6, 13, 17, 19).

## Mỗi nhóm cơ xuất hiện bao nhiêu lần mỗi tuần

| Số buổi/tuần | Cách chia | Ngực | Lưng | Vai | Chân | Tay | Bụng |
|---|---|---|---|---|---|---|---|
| 2 | Toàn thân x2 | 2 | 2 | 2 | 2 | 2 | 2 |
| 3 | Toàn thân x3 | 3 | 3 | 3 | 3 | 3 | 3 |
| 4 | Trên / Dưới x2 | 2 | 2 | 2 | 2 | 2 | 2 |
| 5 | Trên, Dưới, Đẩy, Kéo, Chân | 2 | 2 | 2 | 2 | 3 | 3 |
| 6 | Đẩy / Kéo / Chân x2 | 2 | 2 | 2 | 2 | 4 | 4 |

(Buổi Đẩy có tay sau, buổi Kéo có tay trước và bụng, buổi Chân có bụng. Cardio 37-39 cộng thêm khi mục tiêu `LOSE_FAT`.)

## Ví dụ 3 buổi toàn thân (không lặp bài trong tuần)

| Nhóm | Buổi A | Buổi B | Buổi C |
|---|---|---|---|
| Ngực | 1 Hít đất | 4 Ghế nghiêng | 3 Tạ đòn |
| Lưng | 7 Kéo cáp xuống | 8 Kéo cáp ngồi | 9 Kéo một tay |
| Vai | 14 Đẩy vai ngồi | 15 Nâng sang ngang | 16 Face pull |
| Chân | 18 Goblet squat | 22 Gập đùi sau + 23 Hip thrust | 20 Đạp đùi |
| Tay | 27 Búa + 28 Đẩy cáp | 26 Cuốn tạ đòn | 29 Duỗi tay trên đầu |
| Bụng | 32 Gập bụng | 33 Plank | 34 Nâng chân |

## Lưu ý khi viết hàm sinh lịch
- Lọc theo `difficulty <= trình độ` (người nâng cao vẫn được lấy bài dễ), đừng lọc đúng bằng, vì chỉ có 4 bài khó.
- Mỗi tuần, đổi bài giữa các buổi cùng nhóm cơ (xoay vòng theo `id`) để không lặp cùng một bài.
- Người mới (BEGINNER) mỗi nhóm có ít nhất 2 bài dễ (vai 2, ngực 2, bụng 2, còn lại từ 3 trở lên).
