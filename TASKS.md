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
- [x] A-40 Sửa lỗi giao diện (`a-40-sua-loi-giao-dien`): lịch sử số đo ở điện thoại gọn 2 dòng và ẩn ô trống (trang ngắn khoảng 1/4); biểu đồ mỡ/eo chỉ tạo trục có dữ liệu; nút Xóa số đo bị tô thành nút cam; biểu đồ calo của HLV chỉ 1 mốc thì hiện con số; chân trang không bị nút chatbot che; nhãn ô nội dung bài viết; `favicon.ico`
- [x] A-41 Chế độ "Đang tập" và gợi ý tăng tạ (`a-41-dang-tap`, đề xuất B1 + B2):
  - `includes/progression.php` (thuần): `suggestProgression()` tăng tải kép (đạt 8 lặp mọi set 2 buổi liền cùng mức tạ thì tăng 2,5 kg chân/lưng, 1 kg nhóm khác; 2 buổi liền dưới 6 lặp thì giảm ~10%; bài không tạ thì thêm lặp), `summarizeSessionSets()`; test `test-progression.php`
  - `workout-session.php` + `js/workout-session.js` + `css/workout-session.css`: tick từng set, đồng hồ nghỉ (±30 giây, bỏ qua, rung/kêu khi hết), nhớ tạm tiến độ khi lỡ tải lại, lưu cả buổi một lần qua `api/log-session.php` (transaction)
  - `workout-plan.php` dùng gợi ý mới (một truy vấn cho cả lịch thay cho mỗi bài một truy vấn), nút "Bắt đầu tập" từng ngày; trang chủ: nút chính và thẻ Hôm nay mở chế độ Đang tập
- [x] A-42 Chatbot dùng dữ liệu thật và lưu lịch sử (`a-42-chatbot-du-lieu`, đề xuất D1 + lỗi 13):
  - `includes/chat-context.php`: bật "Dùng hồ sơ của tôi" thì AI biết lịch tập, buổi hôm nay kèm mức tạ gợi ý (từ `progression.php`), nhật ký 7 ngày, số đo 30 ngày; trả lời kèm nút mở Đang tập / Tiến trình
  - Lịch sử lưu trên máy chủ (bảng `chatbot_*` có sẵn, trước đây chưa dùng): mở máy khác vẫn thấy; nút "Cuộc trò chuyện mới" và "Xóa lịch sử"; giữ 5 cuộc gần nhất
  - Giới hạn 30 câu/giờ theo tài khoản (trước chỉ theo session, đăng nhập lại là hết giới hạn)
  - Chip gợi ý theo trang (Lịch tập, Đang tập, Dinh dưỡng, Tiến trình, chi tiết bài tập); test `test-chat.php` (23 bước)
- [x] A-43 Thành tích, báo cáo, test TDEE (`a-43-thanh-tich-bao-cao`, đề xuất F1 + G3):
  - `includes/achievements.php` (thuần): chuỗi tuần tập đủ số buổi theo lịch, 11 huy hiệu cột mốc (buổi tập, chuỗi tuần, lần đo) kèm ngày đạt thật, tóm tắt tháng; test `test-achievements.php` (24 bước)
  - `report.php`: Báo cáo & thành tích theo tháng (so với tháng trước, khối lượng nâng, bài nhiều set nhất), huy hiệu, in / lưu PDF; `export-my-data.php` tải CSV nhật ký và số đo của chính mình; `includes/csv.php` dùng chung với `admin/export.php`
  - Trang chủ thêm ô "Chuỗi tuần tập đều", trang Tiến trình có nút sang Báo cáo
  - `test-tdee.php` (26 bước): BMR Mifflin-St Jeor, TDEE, chia macro bằng số tính tay và các trường hợp dữ liệu sai
- [x] A-44 Chuông thông báo (`a-44-thong-bao`, đề xuất A2, cần thêm `sql/notifications.sql`):
  - Bảng `notifications`; `includes/notifications.php`; 12 loại sự kiện gắn trong `coach-db.php` / `coach-extras.php` (đơn HLV mới cho ADMIN, đơn được duyệt / từ chối, yêu cầu học viên, HLV nhận / từ chối / kết thúc, giao lịch, đặt dinh dưỡng, xin tự sửa và trả lời, nhận xét, tin nhắn, thu hồi HLV)
  - Chuông trên thanh người dùng, khu HLV, khu admin; trang `notifications.php` chạy được khi tắt JS; mở khung chat hoặc trang HLV của tôi thì thông báo tương ứng tự thành đã đọc
  - `test-coach.php` thêm 12 bước kiểm tra thông báo (115 bước)
- [x] A-45 Khách xem thư viện bài tập và bài viết (`a-45-khach-xem-thu-vien`, đề xuất A3): bỏ `requireLogin()` ở 4 trang chỉ đọc, thanh menu khách có Bài tập & Video và Bài viết, dải mời đăng ký (`includes/guest-cta.php`), `tools/audit.php` thêm 4 trang vào danh sách công khai. Lịch tập, dinh dưỡng, tiến trình, báo cáo vẫn cần đăng nhập
- [x] A-46 Tổng kết tuần bằng AI (`a-46-ai-tong-ket-tuan`, đề xuất D2, cần thêm `sql/weekly_summaries.sql`): `includes/weekly-summary.php` tính số liệu tuần (so với lịch, so với tuần trước, bài tăng tạ so với 4 tuần trước, cân nặng) rồi Gemini viết 3 dòng Điểm tốt / Cần chú ý / Tuần tới, chỉ dùng đúng các con số đó; lưu bảng `weekly_summaries`, tạo lại tối đa 1 lần/ngày; ô mới trên trang Báo cáo kèm cảnh báo y tế cố định; test `test-weekly.php` (21 bước)
- [x] A-47 Kỷ lục cá nhân và đổi bài khi thiếu dụng cụ (`a-47-ky-luc-doi-bai`, đề xuất B3, cần thêm `sql/exercise_equipment.sql`):
  - Cột `exercises.equipment` điền cho 40 bài theo cách thực hiện; thư viện lọc theo dụng cụ, thẻ bài có nhãn dụng cụ; form admin có ô Dụng cụ
  - `includes/equipment.php`: bài tương đương cùng vai trò khác dụng cụ, kỷ lục cá nhân; hiện ở trang chi tiết bài tập, chế độ Đang tập ("Thiếu dụng cụ? Thay bằng"), ô đổi bài của Lịch tập (nhóm "Tương đương" lên đầu)
  - Lưu buổi tập báo "Kỷ lục mới"; trang Báo cáo có mục Kỷ lục cá nhân; trang chi tiết bài có kỷ lục của chính mình; test `test-equipment.php` (19 bước)
- [x] A-48 Mẫu lịch tập của HLV (`a-48-mau-lich-hlv`, đề xuất E1, cần thêm `sql/coach_templates.sql`): "Lưu làm mẫu" từ lịch hoặc bản nháp của một học viên, trang `coach/templates.php` xem trước theo ngày và giao cho nhiều học viên một lần (lịch cũ lưu trữ, học viên được báo qua chuông), xóa mẫu; mục "Mẫu lịch" trên menu HLV; test `test-coach-templates.php` (22 bước)
- [x] A-49 Đánh giá HLV (`a-49-danh-gia-hlv`, đề xuất E2, cần thêm `sql/coach_reviews.sql`): chỉ học viên đã tập với HLV đủ 14 ngày và có ghi buổi tập mới đánh giá được, mỗi quan hệ 1 đánh giá (sửa / xóa được, cả với HLV cũ); điểm trung bình + 3 đánh giá gần nhất ở danh sách HLV (chỉ hiện tên, không họ), ô đánh giá trên bảng điều khiển HLV, HLV nhận thông báo; ADMIN xóa đánh giá vi phạm ở `admin/coach-reviews.php`; test `test-coach-reviews.php` (24 bước)
- [x] A-50 Nhật ký ăn (`a-50-nhat-ky-an`, đề xuất C1, cần thêm `sql/meal_logs.sql`): trang `food-log.php` ghi món theo bữa và so tổng ngày với mục tiêu dinh dưỡng; "Thực phẩm của tôi" nhập theo nhãn bao bì; số liệu tính và chụp lại ở máy chủ (`includes/meal-log.php`); liên kết từ trang Dinh dưỡng và thẻ Dinh dưỡng ở trang chủ; test `test-meal-log.php` (25 bước)
  - **Còn thiếu so với đề xuất:** chưa thêm kho món ăn Việt Nam (phở, bún bò, cơm tấm...) vì chưa có nguồn số liệu đã kiểm chứng (ví dụ Bảng thành phần thực phẩm Việt Nam của Viện Dinh dưỡng). Khi có nguồn, thêm vào `foodCatalog()` kèm ghi nguồn
- [x] A-51 Sức khỏe hệ thống và sao lưu (`a-51-suc-khoe-he-thong`, đề xuất G2, không đổi database): trang `admin/health.php` (mục "Hệ thống" trên thanh admin) báo PHP/extension, bảng thiếu kèm file SQL cần chạy, múi giờ, khóa Gemini (không hiện giá trị), quyền ghi thư mục; nút tải bản sao lưu `.sql` (`admin/backup.php`, POST + CSRF + ô xác nhận, ghi nhật ký `system.backup`); đã thử sao lưu rồi nhập lại vào DB trống, khớp số dòng cả 29 bảng; test `test-health.php` (8 bước)
- [x] A-52 Thống nhất token CSS (`a-52-token-css`, đề xuất A1, chỉ sửa CSS): bộ token chuẩn ở `:root` của `ui.css` là nguồn duy nhất; `--color-*`, `--ui-*`, `--gs-*` chỉ còn là tên phụ trỏ về bộ chuẩn; bỏ các giá trị chết trong `style.css` (bị `ui.css` ghi đè từ trước); thêm token cặp chữ/nền trạng thái và chữ phụ đậm; thay 179 mã hex trùng đúng giá trị token bằng `var(...)`. Kiểm chứng: chụp 80 màn hình (4 vai trò, 1440 và 390px) trước/sau, so từng điểm ảnh: không có khác biệt nào ngoài các vùng nội dung động vốn đã khác giữa hai lần chụp
  - **Còn lại:** khoảng 600 mã màu viết cứng (tính cả các dòng định nghĩa token trong `ui.css`; khoảng 180 là `#fff`, 30 là `#000`, còn lại là các sắc độ trung gian của từng trang). Chuyển dần khi sửa từng trang; không gộp ép vào token gần giống vì sẽ đổi màu thật
- [x] A-53 HLV đổi tên và sửa lỗi Nhật ký ăn (`a-53-hlv-doi-ten-sua-bua-an`, không đổi database)
  - HLV đổi "Họ và tên hiển thị" ở `coach/profile.php`: chuẩn hóa bằng `coachNormalizeName()` (gộp khoảng trắng, bỏ ký tự điều khiển), kiểm tra `coachNameError()` (2 đến 100 ký tự); `coachSaveProfile()` lưu hồ sơ và tên trong cùng transaction, chỉ đổi khi tài khoản vẫn là COACH; cập nhật `$_SESSION['full_name']` để thanh menu đổi ngay
  - Lỗi: ghi món bữa trưa xong, trang tải lại thì ô "Bữa" nhảy về bữa sáng (form luôn đoán bữa theo giờ). Sửa: nhớ bữa vừa ghi cho đúng ngày đó trong session, `mealDefaultKind()` ưu tiên bữa đã nhớ rồi mới đoán theo giờ; ghi lỗi thì giữ lại thực phẩm và số gam vừa nhập
  - Test thêm: 6 bước ở `test-coach.php`, 4 bước ở `test-meal-log.php`
  - Lỗi: Trợ lý HLV (chatbot khu /coach/) câu nào cũng báo "GymSmart AI gặp sự cố". Nguyên nhân: `includes/coach-ai.php` dùng `COACH_GOAL_LABELS`, `COACH_LEVEL_LABELS`... định nghĩa trong `includes/coach.php`, mà `api/chatbot.php` không nạp file đó (lỗi có từ lúc thêm trợ lý HLV). Sửa: `coach-ai.php` tự nạp `coach.php`; test ở `test-chat.php` nạp riêng `coach-ai.php` trong tiến trình PHP khác và kiểm tra đủ hằng
- [x] A-54 Giá tập với HLV, buổi tập thử miễn phí, lịch hẹn và gói (`a-54-gia-tap-hlv`, cần thêm `sql/coach_billing.sql`)
  - HLV đặt giá 1 buổi và/hoặc 1 tháng, 1-3 buổi tập thử miễn phí, hướng dẫn thanh toán (chỉ học viên đang tập thấy) ở `coach/profile.php`; danh sách HLV hiện giá và số buổi thử. Số buổi thử chụp lại lúc học viên gửi yêu cầu
  - Lịch hẹn từng buổi 60 phút: học viên đặt (05:00-21:00, trước ít nhất 2 giờ, tối đa 30 ngày), HLV xác nhận / từ chối, sau giờ tập đánh dấu Đã tập hoặc Vắng mặt (vắng vẫn trừ buổi); hủy trước giờ không trừ; buổi chờ quá giờ tự hết hạn; chặn trùng giờ của HLV (khóa dòng trong transaction)
  - Mỗi buổi tính vào gói tháng còn hạn → buổi thử → gói buổi. Gói buổi 1/4/8/12 buổi, gói tháng 1/3 tháng (nối tiếp gói cũ). Không có cổng thanh toán: học viên chọn gói, trả trực tiếp cho HLV, HLV bấm "Đã nhận tiền"
  - Hết buổi thử, hết gói, không có gói tháng → HLV tạm không giao lịch, đặt dinh dưỡng, nhận xét được (chặn ở máy chủ, giao mẫu lịch thì bỏ qua học viên đó); tin nhắn vẫn dùng được; học viên được báo mua gói
  - Trang mới `coach/sessions.php` (mục "Lịch hẹn"), tab "Buổi tập & gói" ở `my-coach.php`, thẻ quyền tập ở hồ sơ học viên phía HLV, nhắc việc ở bảng điều khiển HLV; thông báo `coach.session`, `coach.order`, `coach.billing`; nhật ký `coach.pricing`, `coach.order_paid`, `coach.order_cancel`, `coach.session_done`, `coach.session_absent`
  - Test: `test-coach-billing.php` (100 bước, quy tắc thuần + SQLite), thêm phần D vào kịch bản HTTP (37 bước, tổng 155). Đã thử DB cũ chưa chạy file SQL: mọi trang HLV vẫn mở được, sau `--upgrade` thì phần giá hiện ra
  - **Còn để ngỏ:** học viên không đặt buổi nào thì buổi thử không bao giờ hết, nên phần kèm online (giao lịch, nhận xét) vẫn miễn phí mãi. Nếu cần, thêm hạn dùng thử theo ngày. Hoàn tiền gói chưa dùng khi ngừng tập do hai bên tự thỏa thuận (ứng dụng chỉ hủy buổi sắp tới và đơn đang chờ)
- [x] A-55 Tư vấn sức khỏe từ chuyên gia (`a-54-gia-tap-hlv`, cần thêm `sql/health_experts.sql`)
  - Vai trò mới `EXPERT` (bác sĩ, chuyên gia dinh dưỡng, vật lý trị liệu): nộp đơn ở `expert-apply.php` (bắt buộc số chứng chỉ hành nghề + file chứng chỉ, cam kết không chẩn đoán xác định / không kê đơn), ADMIN duyệt ở `admin/expert-applications.php`, thu hồi ở `admin/experts.php` (câu đang trao đổi trả về hàng chờ)
  - Người dùng (USER, COACH) hỏi riêng theo 6 chủ đề ở `consult-ask.php`, miễn phí, tối đa 3 câu chưa đóng / 5 câu mỗi 24 giờ; tùy chọn gửi kèm số liệu hồ sơ (mặc định tắt). Câu có dấu hiệu cấp cứu (đau ngực, khó thở, ngất, dấu hiệu đột quỵ, chảy máu, ý nghĩ tự hại...) thì nhắc gọi 115 và phải xác nhận không khẩn cấp mới gửi
  - Chuyên gia đúng chủ đề thấy câu đang chờ ở `/expert/dashboard.php`, nhận (chỉ 1 người nhận được), trả lời, trả lại hàng chờ khi chưa trả lời, đóng câu hỏi; người hỏi đóng kèm đánh giá hữu ích. Chuyên gia chỉ thấy tên người hỏi, không thấy email; ADMIN không đọc câu hỏi
  - Thanh menu thêm mục "Tư vấn", nút "Khu chuyên gia"; khu quản trị thêm mục "Chuyên gia"; `tools/audit.php` thêm luật trang `/expert/` phải có `requireExpert()`
  - Test: `test-expert.php` (66 bước), thêm phần E vào kịch bản HTTP (30 bước, tổng 185). Đã thử DB cũ chưa chạy file SQL: các trang báo "chưa bật", không lỗi
  - 3 chuyên gia mẫu (`tools/seed-experts.php`): không có file chứng chỉ nên hiện nhãn "Hồ sơ mẫu, chưa kiểm tra chứng chỉ", nơi công tác và số chứng chỉ ghi rõ là mẫu

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
