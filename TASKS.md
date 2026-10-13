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
- [x] A-3 Tạo file này (`TASKS.md`) và tạo thẻ việc trên GitHub Projects
- [x] A-4 Trang quản trị bài tập (thêm, sửa, xóa), chỉ `ADMIN` vào được
  - **File:** `admin/exercises.php`, `admin/exercise-form.php`, hàm `requireAdmin()` trong `includes/auth.php`
  - **Xong khi:** tài khoản USER mở `/admin/...` bị chặn (kiểm tra ở PHP, không chỉ ẩn nút); thêm, sửa, xóa được 1 bài; xóa phải hỏi xác nhận
- [x] A-5 Rà soát bảo mật chéo trước khi demo (`prepare()`, `htmlspecialchars()`, thử `' OR '1'='1`)
  - **Đã xong:** `tools/audit.php`, `SECURITY_CHECKLIST.md`, CSRF cho đăng nhập/đăng ký/hồ sơ, giới hạn mật khẩu 8–72 ký tự
  - **Đã xong thêm:** cookie phiên an toàn trong `includes/auth.php` (HttpOnly, SameSite=Lax, strict mode, secure khi chạy HTTPS)
  - **Cả nhóm còn làm:** đọc chéo code theo `SECURITY_CHECKLIST.md` bước 3 và ghi bảng kết quả ở cuối file đó
- [x] A-6 Giao diện chung: font Be Vietnam Pro, `ui.css` (nút, thẻ, form), logo, navbar, trang chủ, đăng nhập/đăng ký/hồ sơ, thư viện bài tập (PR `a-7-ui-polish`)
- [x] A-7 Admin: quản lý người dùng (xem chi tiết, đổi vai trò USER/ADMIN, xóa), xuất CSV người dùng và bài tập, nhân bản bài tập
- [x] A-8 Chặn dò mật khẩu ở trang đăng nhập (`includes/login-throttle.php`, bảng `login_attempts` trong `sql/login_attempts.sql`, báo D gộp vào `schema.sql` và `ERD.md`)
- [x] A-9 Đổi mật khẩu trong hồ sơ (cần nhập mật khẩu hiện tại), trang báo lỗi 403/404/hết hạn mã bảo vệ (`includes/error-page.php`), form bài tập admin bố cục 2 cột
- [x] A-10 Quên mật khẩu: `forgot-password.php`, `reset-password.php`, bảng `password_resets` trong `sql/password_resets.sql` (báo D gộp vào `schema.sql` và `ERD.md`)
  - **Cách hoạt động:** mã ngẫu nhiên 32 byte, DB chỉ lưu bản băm SHA-256, hết hạn 30 phút, dùng một lần; trên localhost trang hiện link để demo, trên máy chủ thật gửi bằng `mail()` (cần `APP_URL` trong `config.php`)

- [x] A-11 Thiết kế lại trang Dinh dưỡng (`nutrition.php`, `css/nutrition.css`, `js/nutrition.js`) và thêm `includes/meal-plan.php`: đổi mục tiêu tính lại macro, vòng tròn macro, kiểu ăn (cân bằng, giàu protein, ít tinh bột, tùy chỉnh %), gợi ý lịch ăn 3/4/5 bữa, bảng so sánh mục tiêu. Không sửa `tdee.php`
- [x] A-12 Protein theo trình độ (người mới 1,6 g/kg, có kinh nghiệm 1,8 g/kg; `calculateTargets()` nhận thêm tham số `$experience`) và mục **Bài viết** trên navbar: `articles.php`, `article.php`, `includes/articles.php`, mỗi bài là một file trong `content/articles/` (thêm bài mới chỉ cần tạo thêm file, hướng dẫn ở đầu `includes/articles.php`)
  - **Báo C:** `api/calculate-tdee.php` (nếu có) cũng cần truyền `experience_level` vào `calculateTargets()` để cùng kết quả với trang Dinh dưỡng

- [x] A-13 Sửa và nâng cấp chatbot (làm lại phần của E-5): `api/chatbot.php` + `includes/ai.php` (gọi Gemini, báo lỗi có mã rõ ràng, không còn Fatal khi thiếu `AI_MODEL`), `includes/chatbot-widget.php` + `css/chatbot.css` + `js/chatbot.js` (logo, hiệu ứng, lịch sử hội thoại, gợi ý câu hỏi), `tools/chatbot-check.php` (chẩn đoán)
  - **Hai trợ lý khác nhau:** trang người dùng = *GymSmart AI* (huấn luyện viên ảo, biết bài tập trong thư viện, công tắc "Dùng hồ sơ của tôi"); trong `/admin/` = *Trợ lý quản trị* (số liệu tổng hợp, bài tập thiếu video/ảnh, hướng dẫn trang quản trị, thẻ "Soạn bài tập" tạo bản nháp rồi điền vào form). Server tự quyết định theo `$_SESSION['role']`, không tin client
  - **Cấu hình trong `config.php` (không commit):** `AI_API_KEY` (bắt buộc), `AI_MODEL` (tùy chọn), `AI_CA_BUNDLE` (chỉ khi XAMPP báo lỗi SSL)
  - **Báo E:** chatbot do E làm đã được viết lại; xóa chỗ include/script/CSS cũ của E (nếu có) để khỏi hiện hai nút chat
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
