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
- [x] A-8 Chặn dò mật khẩu ở trang đăng nhập (`includes/login-throttle.php`, bảng `login_attempts` trong `sql/login_attempts.sql`, đã gộp vào `schema.sql` và `ERD.md`)
  - **Ghi chú:** trước 29/10/2026 mục này bị tick nhầm khi chưa có code; làm thật ở nhánh `fix-audit-2026-10`. Quy tắc: sai 5 lần cùng email hoặc 20 lần cùng IP trong 15 phút thì khóa; đăng nhập đúng xóa các lần sai của email đó
- [x] A-9 Đổi mật khẩu trong hồ sơ (cần nhập mật khẩu hiện tại), trang báo lỗi 403/404/hết hạn mã bảo vệ (`includes/error-page.php`), form bài tập admin bố cục 2 cột
- [x] A-10 Quên mật khẩu: `forgot-password.php`, `reset-password.php`, bảng `password_resets` trong `sql/password_resets.sql` (báo D gộp vào `schema.sql` và `ERD.md`)
  - **Cách hoạt động:** mã ngẫu nhiên 32 byte, DB chỉ lưu bản băm SHA-256, hết hạn 30 phút, dùng một lần; trên localhost trang hiện link để demo, trên máy chủ thật gửi bằng `mail()` (cần `APP_URL` trong `config.php`)

- [x] A-11 Thiết kế lại trang Dinh dưỡng (`nutrition.php`, `css/nutrition.css`, `js/nutrition.js`) và thêm `includes/meal-plan.php`: đổi mục tiêu tính lại macro, vòng tròn macro, kiểu ăn (cân bằng, giàu protein, ít tinh bột, tùy chỉnh %), gợi ý lịch ăn 3/4/5 bữa, bảng so sánh mục tiêu. Không sửa `tdee.php`
- [x] A-12 Protein theo trình độ (người mới 1,6 g/kg, có kinh nghiệm 1,8 g/kg; `calculateTargets()` nhận thêm tham số `$experience`) và mục **Bài viết** trên navbar: `articles.php`, `article.php`, `includes/articles.php`, mỗi bài là một file trong `content/articles/` (thêm bài mới chỉ cần tạo thêm file, hướng dẫn ở đầu `includes/articles.php`)
  - **Báo C:** nếu sau này có API tính TDEE riêng thì cũng cần truyền `experience_level` vào `calculateTargets()` để cùng kết quả với trang Dinh dưỡng

- [x] A-13 Sửa và nâng cấp chatbot (làm lại phần của E-5): `api/chatbot.php` + `includes/ai.php` (gọi Gemini, báo lỗi có mã rõ ràng, không còn Fatal khi thiếu `AI_MODEL`), `includes/chatbot-widget.php` + `css/chatbot.css` + `js/chatbot.js` (logo, hiệu ứng, lịch sử hội thoại, gợi ý câu hỏi), `tools/chatbot-check.php` (chẩn đoán)
  - **Hai trợ lý khác nhau:** trang người dùng = *GymSmart AI* (huấn luyện viên ảo, biết bài tập trong thư viện, công tắc "Dùng hồ sơ của tôi"); trong `/admin/` = *Trợ lý quản trị* (số liệu tổng hợp, bài tập thiếu video/ảnh, hướng dẫn trang quản trị, thẻ "Soạn bài tập" tạo bản nháp rồi điền vào form). Server tự quyết định theo vai trò đọc lại từ DB, không tin client
  - **Cấu hình trong `config.php` (không commit):** `AI_API_KEY` (bắt buộc), `AI_MODEL` (tùy chọn), `AI_CA_BUNDLE` (chỉ khi XAMPP báo lỗi SSL)
  - **Báo E:** chatbot do E làm đã được viết lại; xóa chỗ include/script/CSS cũ của E (nếu có) để khỏi hiện hai nút chat
- [x] A-15 Giao diện Hồ sơ mới + ảnh đại diện đổi được (`profile.php`, `css/profile.css`, `js/profile.js`, `sql/users_avatar.sql`; ảnh lưu ở `public/uploads/avatars/`, JPG/PNG/WebP tối đa 2 MB, vẽ lại thành JPG)
- [x] A-16 Đăng ký 3 bước (tài khoản, cơ thể, mục tiêu): thu đủ tuổi, giới tính, chiều cao, cân nặng, vận động, trình độ, mục tiêu ngay lúc đăng ký (`register.php`, `css/register.css`, `js/register.js`)
- [x] A-23 Nâng cấp trang chủ khi chưa đăng nhập (`includes/home-guest.php`, `css/home-guest.css`, `js/home-guest.js`, ảnh hero trong `assets/img/hero/`, nguồn ảnh ở `assets/img/CREDITS.md`): hero ảnh gym và lớp công nghệ, thẻ "Ví dụ", 7 tính năng, 3 bước, xem trước bài tập từ DB, mục kiến thức. Giao diện khi đã đăng nhập không đổi
- [x] A-38 Làm lại trang chủ khách theo hướng "Fresh energetic" (`a-37-landing-tre-trung`, mockup và ảnh trước/sau trong `docs/mockups/`, `docs/screens/landing-*`): hero sáng với tiêu đề lớn, một bảng điều khiển mô phỏng (vòng calo đếm lên, thanh macro, AI gõ chữ, dải 7 ngày, nghiêng 3D theo chuột), dải từ khóa, công cụ tính calo Mifflin–St Jeor chạy trong trình duyệt (khớp `tdee.php`), lưới bento, 3 bước có đường nối theo cuộn, chatbot mẫu, bài viết thật, nút dính đáy trên điện thoại. Token `--glow-cool` chỉ cho trang khách. Sau đó đổi hero sang hướng "Future sport" (hero tối công nghệ + thể thao, tiêu đề nghiêng, dải số liệu thật, ảnh cắt bình hành, bảng điều khiển kính tối); các khối dưới giữ nền sáng
- [x] A-24 Bảng điều khiển sau đăng nhập ở trang chủ (`includes/dashboard.php`, `includes/home-user.php`, `includes/dash-*.php`, `css/home-user.css`, `js/home-user.js`, test `test-dashboard.php`): hôm nay, tuần này, dinh dưỡng, tiến trình, lối tắt, ghi nhanh. Thêm bảng `article_reads` (`sql/article_reads.sql`, đã gộp vào `schema.sql` và `ERD.md`). DB cũ cần import file này
- [x] A-25 Sửa chatbot (`css/chatbot.css`, `js/chatbot.js`, `includes/chatbot-widget.php`, `includes/ai.php`, `api/chatbot.php`): khung bị mờ và bị thẻ trang đè khi đang chờ (trùng class `.is-busy` của `ui.css`), giao diện tối nền đặc, nút Dừng và Thử lại, markdown an toàn, không hiện lỗi kỹ thuật, chuyển sang model dự phòng khi model chính treo (mỗi lần gọi tối đa 15 giây, tổng 40 giây)
- [x] A-26 Nâng cấp Dinh dưỡng (`nutrition.php`, `includes/meal-plan.php`, `includes/tdee.php`, `includes/nutrition-db.php`, `test-nutrition.php`): đổi món từng bữa, loại trừ thực phẩm, lịch 7 ngày không lặp món liên tiếp, danh sách đi chợ, nhịp mục tiêu, calo theo ngày tập/nghỉ, tiến độ cân nặng so với kế hoạch. Lựa chọn lưu ở bảng `nutrition_preferences` (`sql/nutrition_preferences.sql`, đã gộp vào `schema.sql`). Nhóm C cần đọc hiểu phần mới của `tdee.php`

---

## Phân hệ Huấn luyện viên (HLV, vai trò COACH) — đổi phạm vi 11/2026, A làm

Mô tả bảng: `docs/ERD.md` mục "Phân hệ HLV". Chạy thêm `sql/coach_features.sql` (đã gộp vào `schema.sql`). Dữ liệu thử: `php tools/seed-coach-demo.php`. Test: `php test-coach.php`.
Các nhánh xếp chồng, merge theo thứ tự: `a-34-nang-cap-dinh-duong` → `a-30` → `a-31` → `a-32` → `a-33` → `a-35`.

- [x] A-30 Cơ sở dữ liệu và phân quyền (`a-30-coach-db`): vai trò COACH, 6 bảng `coach_*`, cột `assigned_by`; `includes/coach-rules.php` (quy tắc thuần), `coach-db.php`, `coach.php` (`requireCoach()`, `canCoachAccessClient()`); `test-coach.php`; `tools/seed-coach-demo.php`; luật quét `/coach/` trong `tools/audit.php`
- [x] A-31 Đơn đăng ký HLV và quản trị (`a-31-coach-admin`): `coach-apply.php` (mẫu đơn 4 phần, rút đơn, nộp lại sau khi bị từ chối), `admin/coach-applications.php` (duyệt / từ chối có lý do), `admin/coaches.php` (danh sách, thu hồi), chặn COACH → ADMIN trực tiếp, nhật ký `coach.*`, số liệu HLV ở tổng quan và thống kê
- [x] A-32 Học viên chọn HLV và khu HLV (`a-32-coach-link`): `coaches.php`, `my-coach.php`, mục HLV trên menu; `/coach/dashboard.php` (4 số liệu, duyệt yêu cầu), `clients.php`, `profile.php`, thanh `coach-nav.php`
  - **Xong khi:** mỗi học viên chỉ có 1 HLV (đang chờ hoặc đang tập), DB cũng chặn bằng UNIQUE; HLV đủ chỗ hoặc tạm ngừng thì không gửi được
- [x] A-33 Hồ sơ học viên, giao lịch, đặt macro (`a-33-coach-panel`): `coach/client.php` (chỉ đọc, biểu đồ qua `api/body-metrics.php?client_id=`), `client-plan.php` (tạo từ thuật toán gợi ý rồi chỉnh), `client-nutrition.php` (kcal = 4P + 4C + 9F, giới hạn ở máy chủ); khóa tự sửa lịch tập và dinh dưỡng khi có HLV, xin sửa thì HLV duyệt mở 24 giờ
- [x] A-35 Nhận xét và nhắn tin (`a-35-coach-feedback-chat`): nhận xét từng buổi / nhận xét chung, `api/coach-messages.php` + `api/coach-message-send.php`, `coach/messages.php`, tab Tin nhắn ở `my-coach.php`, thông báo trên trang chủ (`includes/dash-coach.php`)
- [x] A-36 Mở rộng phân hệ HLV (`a-36-coach-mo-rong`, cần thêm `sql/coach_extras.sql`):
  - `tools/test-coach-http.php`: tự dựng DB thử + máy chủ thử, chạy 118 bước kịch bản qua HTTP rồi dọn sạch
  - Tỉ lệ tập đúng lịch tuần này (`coachWeekCompliance`) ở bảng điều khiển và danh sách học viên
  - Admin xem quan hệ HLV ở `admin/user-detail.php`
  - HLV sửa / xóa nhận xét (học viên thấy nhãn "HLV đã sửa"); "Đã xem" trong khung chat
  - Học viên xem lại tin nhắn, nhận xét với HLV cũ (`my-coach.php?history=`), chỉ đọc
  - Nhắc khi yêu cầu chờ HLV quá 7 ngày (học viên, trang chủ, bảng điều khiển HLV)
  - `api/nutrition-history.php?client_id=` + biểu đồ calo mục tiêu ở hồ sơ học viên
  - Trợ lý chatbot cho HLV (chế độ `coach`, `includes/coach-ai.php`): chỉ dữ liệu học viên ACTIVE, không gửi email, chỉ gửi tên gọi
  - Bản nháp lịch tập (`workout_plans.status = DRAFT`): tạo từ gợi ý / chép lịch hiện tại, sửa, giao, bỏ
  - Tải file chứng chỉ trong mẫu đơn (JPG/PNG/WebP/PDF ≤ 3 MB, kiểm tra nội dung bằng finfo, lưu ở `storage/certificates/` ngoài `public/`, chỉ admin xem qua `admin/certificate.php`)
- [x] A-37 Chụp ảnh `docs/screens/coach-*.png` (34 ảnh, 1440px và 390px) và chạy kịch bản thử trên MySQL (111 bước, xem `SECURITY_CHECKLIST.md` mục "Phân hệ HLV")

---

## Nâng cấp 11/2026 (đề xuất đã duyệt, mỗi gói một nhánh xếp chồng từ `a-37-landing-tre-trung`)

Ảnh trước nâng cấp: `docs/screens/upgrade-before-*.png`, ảnh sau: `upgrade-after-*.png`.

- [x] A-39 Sửa lỗi nền (`a-39-sua-loi-nen`):
  - Múi giờ PHP và MySQL lệch 6 giờ (php.ini Europe/Berlin, MySQL theo giờ máy): `includes/db.php` đặt cả hai về `Asia/Ho_Chi_Minh` (đổi được bằng `APP_TIMEZONE`)
  - `sql/body_metrics_fix.sql`: DB cũ có cột số đo kiểu FLOAT, cân nặng cho NULL, thiếu index; `users_avatar.sql` chạy lại được
  - `tools/install.php` (tạo / nâng cấp DB một lệnh, `--demo` nạp tài khoản thử), `tools/run-tests.php` (chạy mọi test)
  - `admin/users.php`: tìm kiếm, lọc vai trò, phân trang ở máy chủ (20 dòng/trang)
  - Header `Content-Security-Policy` trong `includes/header.php`

---

## B — Thư viện bài tập + Lịch tập

- [x] B-1 → B-5 Trang danh sách bài tập: lưới thẻ, lọc nhóm cơ, loại, độ khó, ô tìm kiếm (A làm tạm, B chỉ cần đọc hiểu)
- [x] B-6 Tạo bảng `workout_plans`, `workout_plan_exercises`
  - **File:** `sql/schema.sql` (ENUM `split_type` đủ 8 kiểu; DB cũ chạy thêm `sql/split_types.sql`)
  - **Xong khi:** import được vào phpMyAdmin, khớp `ERD.md`
- [x] B-7 Viết **bảng quy tắc chia lịch** (`docs/chia-lich-tap-40-bai.md`) (viết ra giấy hoặc `docs/` trước khi code)
  - **Làm gì:** với mỗi tổ hợp (trình độ × số buổi/tuần × mục tiêu) thì chọn split nào, mỗi buổi tập nhóm cơ nào, bao nhiêu bài, bao nhiêu set × rep
  - **Xong khi:** có bảng rõ ràng, mỗi dòng giải thích được "vì sao"; cả nhóm đọc hiểu được
- [x] B-8 Hàm sinh lịch tập (thuần PHP, `if`/`switch`, không thư viện ngoài)
  - **File:** `includes/workout-plan.php` (`generateWorkoutPlan()`), lớp DB `includes/workout-plan-db.php`; test: `test-workout-plan.php`, `test-workout-plan-db.php`
  - **Làm gì:** nhận `experience_level`, `goal`, `days_per_week`; chọn bài từ bảng `exercises` theo `difficulty` và `muscle_group`
  - **Xong khi:** thử ít nhất 3 tổ hợp đầu vào ra lịch hợp lý
- [x] B-9 Trang tạo lịch và xem lịch theo tuần
  - **File:** `workout-plan.php`
  - **Xong khi:** user nhập số buổi, bấm tạo, thấy lịch; mỗi user chỉ có 1 lịch `ACTIVE`

---

## C — TDEE/Macro + Tiến trình

- [ ] C-1 Hàm tính BMR, TDEE, macro (`includes/tdee.php`)
  - **Xong khi:** điền đủ 2 TODO (lý do dùng −78 cho giới tính OTHER; nguồn của hệ số kcal 0.8 / 1.0 / 1.1 và protein, fat g/kg); thử tay vài bộ số và khớp
- [ ] C-2 Form nhập chỉ số và hiển thị kết quả TDEE/Macro
  - **Hiện trạng:** A-11 đã làm trang `nutrition.php` (đọc từ hồ sơ, lưu `nutrition_targets`, tải lại trang chứ chưa dùng AJAX). Không có `tdee.php`/`api/calculate-tdee.php`; C xác nhận có cần làm thêm phần AJAX không
  - **Xong khi:** tính không reload trang (AJAX, trả JSON); đọc sẵn dữ liệu từ hồ sơ; lưu vào `nutrition_targets`
- [x] C-3 (A nhận) Bảng `body_metrics` và trang nhập số đo (`progress.php`)
- [x] C-4 (A nhận) Trang biểu đồ tiến trình (Chart.js (cân nặng, mỡ + vòng eo, khối lượng cơ bắp ước tính), file tự host ở `js/lib/`, không dùng CDN, dữ liệu từ `/api/body-metrics.php`, `/api/nutrition-history.php`)
  - **Xong khi:** biểu đồ hiển thị đúng khi có và khi không có dữ liệu

> Thứ tự merge các PR xếp chồng của C: #4 → #5 → #6.

---

## D — Dữ liệu bài tập + Trang chi tiết

- [x] D-1 Trang chi tiết bài tập
  - **File:** `exercise-detail.php` (tên file D chốt với nhóm, B sửa link cho khớp)
  - **Xong khi:** lấy bài theo id bằng prepared statement; id phải là số nguyên >= 1; id sai thì báo "không tìm thấy" (không lỗi PHP); hiện ảnh, mô tả, các bước, lỗi thường gặp, video; bài chưa có video thì ẩn khung video
  - **Cách test:** `?id=1`, `?id=99999`, `?id=abc`, `?id=0`, `?id=-5`, không có id, `?id=1 OR 1=1`, và thử khi chưa đăng nhập
- [x] D-2 Nhập 40–50 bài tập: `sql/seed_exercises_v2.sql` (40 bài) + `sql/seed_exercise_media.sql` (ảnh `img/exercises/<id>.jpg`, video ID YouTube)
  - **Xong khi:** đủ 7 nhóm cơ, đủ 3 mức độ khó; `video_url` chỉ chứa ID YouTube (11 ký tự); không bài nào thiếu mô tả, các bước, lỗi thường gặp; ảnh và video có nguồn hợp pháp
- [x] D-3 Gợi ý 3 bài cùng nhóm cơ ở cuối trang chi tiết
- [x] D-4 Giữ `schema.sql` và `ERD.md` đồng bộ (D là đầu mối duy nhất sửa bảng). 29/10/2026: `schema.sql` đã gộp mọi file bổ sung

---

## E — Giao diện + Chatbot

- [x] E-1 `style.css` khung: biến màu, chữ, bố cục chung; dùng các class đang có (`card`, `btn`) — A làm thay ở A-6 (`ui.css`), E cần đọc hiểu
- [x] E-2 Giao diện đăng ký, đăng nhập, hồ sơ, trang chủ theo wireframe — A làm thay ở A-6, A-15, A-16, A-38
- [x] E-3 Responsive (điện thoại, máy tính bảng) cho các trang đã có — A làm thay, kiểm tra ở 390/768/1440px (`docs/screens/`)
- [x] E-4 Giao diện trang lịch tập và trang TDEE/Macro (sau khi B và C có trang) — A làm thay ở A-6, A-11, A-26
- [x] E-5 (Tùy chọn) Chatbot mức 1: `api/chatbot.php` và widget chat nổi (A làm lại ở A-13)

---

## Mốc

| Mốc | Nội dung | Điều kiện đạt |
|---|---|---|
| M1 (giữa kỳ) | Đăng ký → nhập hồ sơ → xem bài tập và video → nhận lịch tập → xem TDEE/Macro | Cả luồng chạy trên máy 1 người lạ trong nhóm, không cần người làm ra nó chỉnh tay |
| M2 | Check-in buổi tập, biểu đồ tiến trình | B, C hoàn tất giai đoạn 2 |
| M3 | Báo cáo và demo | Mỗi người tự giải thích được phần của mình |
