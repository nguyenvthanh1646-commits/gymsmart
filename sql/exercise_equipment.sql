-- exercise_equipment.sql — dụng cụ cần cho từng bài tập (A-47: đổi bài tương đương khi thiếu dụng cụ). Chạy lại nhiều lần vẫn an toàn.
-- Cột exercises.equipment ĐÃ GỘP vào schema.sql. File này vừa thêm cột (DB cũ) vừa điền dụng cụ cho 40 bài của
-- seed_exercises_v2.sql theo id cố định. seed_exercises_v2.sql xóa và nạp lại bảng exercises, nên chạy lại file này SAU file đó
-- (tools/install.php đã xếp đúng thứ tự). Dụng cụ lấy theo phần "Cách thực hiện" của chính từng bài:
--   NONE     không cần dụng cụ (sàn, tường)      BENCH    chỉ cần ghế / bục
--   BARS     xà song song                         BAND     dây kháng lực          ROPE  dây nhảy
--   DUMBBELL tạ đơn (hoặc tạ ấm)                  BARBELL  tạ đòn
--   MACHINE  máy tập riêng (pec deck, đạp đùi...) CABLE    máy cáp
-- Bài admin thêm sau mặc định NONE; sửa lại trong form bài tập nếu cần.
ALTER TABLE exercises ADD COLUMN IF NOT EXISTS equipment VARCHAR(20) NOT NULL DEFAULT 'NONE';

UPDATE exercises SET equipment = 'NONE'     WHERE id IN (1, 12, 24, 32, 33, 34, 35, 37);
UPDATE exercises SET equipment = 'BENCH'    WHERE id IN (19, 23, 30);
UPDATE exercises SET equipment = 'BARS'     WHERE id IN (6);
UPDATE exercises SET equipment = 'BAND'     WHERE id IN (25);
UPDATE exercises SET equipment = 'ROPE'     WHERE id IN (38);
UPDATE exercises SET equipment = 'DUMBBELL' WHERE id IN (4, 5, 9, 11, 14, 15, 18, 27, 29, 31, 40);
UPDATE exercises SET equipment = 'BARBELL'  WHERE id IN (3, 13, 17, 26);
UPDATE exercises SET equipment = 'MACHINE'  WHERE id IN (2, 20, 21, 22, 39);
UPDATE exercises SET equipment = 'CABLE'    WHERE id IN (7, 8, 10, 16, 28, 36);
