# TASKS — GymSmart

Bảng việc của nhóm. Mỗi việc là một nhánh Git và một PR riêng, có người khác review trước khi merge.

Quy ước dùng chung:
- Tên nhánh: `<chữ cái>-<số>-<mô-tả-ngắn>` (ví dụ `b-7-split-rule`).
- Làm xong thì tick `[x]` ngay trong file này và nêu trong PR.
- Mỗi việc ghi rõ: **File**, **Làm gì**, **Xong khi**. Chưa đạt "Xong khi" thì chưa mở PR.
- Tự code, chỉ hỏi AI khi bí ở một điểm cụ thể. Phải giải thích được từng dòng của mình.
- Không commit `config.php`. Đổi cấu trúc bảng thì báo D để cập nhật `schema.sql` và `ERD.md`.
- Trạng thái ở bảng dưới là theo hiểu biết của A, ai thấy sai thì sửa.

---

## A — Auth, layout, bảo mật chung

- [x] A-0 Đăng ký, đăng nhập, đăng xuất, `requireLogin()`
- [x] A-1 Hồ sơ cá nhân `profile.php`, có thêm trình độ tập luyện (`users.experience_level`)
- [x] A-2 Trang chủ `index.php`, nhắc hoàn thiện hồ sơ
- [ ] A-3 Tạo file này (`TASKS.md`) và tạo thẻ việc trên GitHub Projects
- [ ] A-4 Trang quản trị bài tập (thêm, sửa, xóa), chỉ `ADMIN` vào được
  - **File:** `admin/exercises.php`, `admin/exercise-form.php`, hàm `requireAdmin()` trong `includes/auth.php`
  - **Xong khi:** tài khoản USER mở `/admin/...` bị chặn (kiểm tra ở PHP, không chỉ ẩn nút); thêm, sửa, xóa được 1 bài; xóa phải hỏi xác nhận
- [ ] A-5 Rà soát bảo mật chéo trước khi demo (`prepare()`, `htmlspecialchars()`, thử `' OR '1'='1`)

---

## B — Thư viện bài tập + Lịch tập

- [x] B-1 → B-5 Trang danh sách bài tập: lưới thẻ, lọc nhóm cơ, loại, độ khó, ô tìm kiếm (A làm tạm, B chỉ cần đọc hiểu)
- [ ] B-6 Tạo bảng `workout_plans`, `workout_plan_exercises`
  - **File:** `sql/schema.sql` (báo D để gộp)
  - **Xong khi:** import được vào phpMyAdmin, khớp `ERD.md`
- [ ] B-7 Viết **bảng quy tắc chia lịch** (viết ra giấy hoặc `docs/` trước khi code)
  - **Làm gì:** với mỗi tổ hợp (trình độ × số buổi/tuần × mục tiêu) thì chọn split nào, mỗi buổi tập nhóm cơ nào, bao nhiêu bài, bao nhiêu set × rep
  - **Xong khi:** có bảng rõ ràng, mỗi dòng giải thích được "vì sao"; cả nhóm đọc hiểu được
- [ ] B-8 Hàm sinh lịch tập (thuần PHP, `if`/`switch`, không thư viện ngoài)
  - **File:** `includes/workout_plan.php`
  - **Làm gì:** nhận `experience_level`, `goal`, `days_per_week`; chọn bài từ bảng `exercises` theo `difficulty` và `muscle_group`
  - **Xong khi:** thử ít nhất 3 tổ hợp đầu vào ra lịch hợp lý
- [ ] B-9 Trang tạo lịch và xem lịch theo tuần
  - **File:** `workout-plan.php`
  - **Xong khi:** user nhập số buổi, bấm tạo, thấy lịch; mỗi user chỉ có 1 lịch `ACTIVE`

---

## C — TDEE/Macro + Tiến trình

- [ ] C-1 Hàm tính BMR, TDEE, macro (`includes/tdee.php`)
  - **Xong khi:** điền đủ 2 TODO (lý do dùng −78 cho giới tính OTHER; nguồn của hệ số kcal 0.8 / 1.0 / 1.1 và protein, fat g/kg); thử tay vài bộ số và khớp
- [ ] C-2 Form nhập chỉ số và hiển thị kết quả TDEE/Macro
  - **File:** `tdee.php`, `api/calculate-tdee.php`
  - **Xong khi:** tính không reload trang (AJAX, trả JSON); đọc sẵn dữ liệu từ hồ sơ; lưu vào `nutrition_targets`
- [ ] C-3 Bảng `body_metrics` và trang nhập số đo
- [ ] C-4 Trang biểu đồ tiến trình (Chart.js qua CDN, dữ liệu từ `/api/*.php`)
  - **Xong khi:** biểu đồ hiển thị đúng khi có và khi không có dữ liệu

> Thứ tự merge các PR xếp chồng của C: #4 → #5 → #6.

---

## D — Dữ liệu bài tập + Trang chi tiết

- [ ] D-1 Trang chi tiết bài tập
  - **File:** `exercise-detail.php` (tên file D chốt với nhóm, B sửa link cho khớp)
  - **Xong khi:** lấy bài theo id bằng prepared statement; id phải là số nguyên >= 1; id sai thì báo "không tìm thấy" (không lỗi PHP); hiện ảnh, mô tả, các bước, lỗi thường gặp, video; bài chưa có video thì ẩn khung video
  - **Cách test:** `?id=1`, `?id=99999`, `?id=abc`, `?id=0`, `?id=-5`, không có id, `?id=1 OR 1=1`, và thử khi chưa đăng nhập
- [ ] D-2 Nhập 40–50 bài tập vào `sql/seed.sql`
  - **Xong khi:** đủ 7 nhóm cơ, đủ 3 mức độ khó; `video_url` chỉ chứa ID YouTube (11 ký tự); không bài nào thiếu mô tả, các bước, lỗi thường gặp; ảnh và video có nguồn hợp pháp
- [ ] D-3 Gợi ý 3 bài cùng nhóm cơ ở cuối trang chi tiết
- [ ] D-4 Giữ `schema.sql` và `ERD.md` đồng bộ (D là đầu mối duy nhất sửa bảng)

---

## E — Giao diện + Chatbot

- [ ] E-1 `style.css` khung: biến màu, chữ, bố cục chung; dùng các class đang có (`card`, `btn`)
- [ ] E-2 Giao diện đăng ký, đăng nhập, hồ sơ, trang chủ theo wireframe
- [ ] E-3 Responsive (điện thoại, máy tính bảng) cho các trang đã có
- [ ] E-4 Giao diện trang lịch tập và trang TDEE/Macro (sau khi B và C có trang)
- [ ] E-5 (Tùy chọn) Chatbot mức 1: `api/chatbot.php` và widget chat nổi

---

## Mốc

| Mốc | Nội dung | Điều kiện đạt |
|---|---|---|
| M1 (giữa kỳ) | Đăng ký → nhập hồ sơ → xem bài tập và video → nhận lịch tập → xem TDEE/Macro | Cả luồng chạy trên máy 1 người lạ trong nhóm, không cần người làm ra nó chỉnh tay |
| M2 | Check-in buổi tập, biểu đồ tiến trình | B, C hoàn tất giai đoạn 2 |
| M3 | Báo cáo và demo | Mỗi người tự giải thích được phần của mình |
