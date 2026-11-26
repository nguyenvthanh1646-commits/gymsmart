# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Tổng quan dự án

**Tên dự án:** GymSmart — Website hỗ trợ tập luyện cá nhân hóa
**Bối cảnh:** Bài tập lớn môn Phát triển ứng dụng trên nền Web
**Quy mô:** Nhóm 5 thành viên, thời gian làm trong 1 kỳ học
**Yêu cầu bắt buộc:** Tất cả thành viên phải **hiểu bản chất code và tự code tay** — không dùng framework che giấu logic (không Laravel, không ORM), không copy code mà không hiểu. **Không vibe code.** Cách làm việc với AI: tự viết code trước theo hiểu biết của mình, **chỉ hỏi AI khi bí ở một chỗ cụ thể** (lỗi không hiểu, không biết cú pháp, không chắc cách làm) — AI đóng vai trò giải thích/gỡ rối tại điểm nghẽn đó, không viết hộ nguyên tính năng từ đầu.

> Đã loại bỏ: chat tự do giữa user-user, community/forum. Lý do: chi phí xây dựng không tương xứng giá trị demo. Phần "học tập" chủ yếu là **ảnh + video hướng dẫn nằm ngay trong thư viện bài tập**.
>
> Lưu ý: vai trò **Huấn luyện viên (COACH)** từng bị loại nhưng **đã được làm lại** theo yêu cầu đổi phạm vi (11/2026), kèm nhắn tin chỉ giữa HLV và học viên của mình. Xem mục "Phân hệ HLV" bên dưới.
>
> Lưu ý: module **bài viết kiến thức** từng bị loại nhưng **đã được làm lại** (PR #37, #38, #40). Xem mục "Bài viết" bên dưới.

## Tech Stack — PHP thuần (LAMP, không framework)

- **Frontend:** HTML + CSS thuần + JavaScript thuần (vanilla JS, dùng `fetch` để gọi AJAX, KHÔNG dùng React/Vue)
- **Backend:** PHP thuần (không Laravel/Symfony) — xử lý logic trực tiếp trong các file `.php`
- **Database:** MySQL, truy vấn qua **PDO** (bắt buộc dùng prepared statements, không nối chuỗi SQL trực tiếp — tránh SQL Injection)
- **Quản trị DB:** phpMyAdmin — dùng để tạo/sửa bảng, xem dữ liệu, chạy thử query, import/export `.sql`. Toàn bộ nhóm thao tác DB qua phpMyAdmin thay vì dòng lệnh MySQL
- **Auth:** PHP Session (`$_SESSION`) — đơn giản, dễ hiểu bản chất hơn JWT cho người mới, không cần thư viện ngoài
- **AI Chatbot:** Gọi **Gemini** (`generateContent`) qua `curl` trong PHP (`public/includes/ai.php`), trả JSON cho frontend
- **Không dùng:** React, Vue, Laravel, Composer package quản lý nặng, ORM (Eloquent, Doctrine) — mọi thứ viết tay để hiểu rõ luồng chạy

> Lý do chọn PHP thuần: với yêu cầu "hiểu bản chất", framework càng ít càng tốt — mỗi dòng code sinh viên viết ra phải tự giải thích được nó làm gì, không có lớp trừu tượng ẩn giấu.

## Kiến trúc (Multi-Page Application, PHP thuần)

**Document root phải là `public/`.** Mọi link/redirect dùng đường dẫn tuyệt đối (`/login.php`, `/css/...`), nên mở thẳng `localhost/gymsmart-starter/...` sẽ hỏng. `config.php` nằm ở **gốc repo** (ngoài `public/`), được nạp qua `public/includes/db.php` bằng `__DIR__ . '/../../config.php'`.

- **Trang** (`public/*.php`, `public/admin/*.php`): mỗi file là 1 tính năng. Mở đầu bằng `require includes/auth.php` (tự bật session với cookie an toàn) rồi `requireLogin()` / `requireAdmin()` (riêng 4 trang chỉ đọc `exercises.php`, `exercise-detail.php`, `articles.php`, `article.php` cho khách xem, có dải mời đăng ký `includes/guest-cta.php`; danh sách trang công khai nằm trong `tools/audit.php`), nạp `includes/db.php` (tạo biến `$pdo`), gọi hàm từ `includes/*`, rồi include `header.php` → `navbar.php` → HTML → `footer.php`. Đặt `$pageTitle` trước khi include `header.php` (có thể đặt thêm `$pageCss` để nạp CSS riêng trong `<head>`). CSS/JS riêng từng trang nằm ở `public/css/<feature>.css`, `public/js/<feature>.js`. Trang chủ của khách nằm ở `includes/home-guest.php`; bảng điều khiển sau đăng nhập nằm ở `includes/home-user.php` và `includes/dash-*.php`, dữ liệu từ `includes/dashboard.php`.
- **API** (`public/api/*.php`): trả JSON cho `fetch()`. Có **2 kiểu đang tồn tại**:
  - Kiểu mới (GET, chỉ đọc): dùng `includes/api.php` → `apiBoot()` (chỉ GET, bắt buộc đăng nhập → 401 JSON, biến exception thành 500 JSON), trả `{success, data}` / `{success:false, error}`. Xem `body-metrics.php`, `nutrition-history.php`.
  - Kiểu cũ (POST): tự kiểm tra method/session và trả `{success, message}` (`log-workout.php`, `workout-plan-*.php`, `chatbot.php`). Khi sửa file nào thì giữ đúng kiểu của file đó, vì JS phía trang đang đọc đúng key đó.
  - API **không** dùng `requireLogin()` (hàm đó redirect sang HTML). `user_id` luôn lấy từ `$_SESSION`, không bao giờ lấy từ request.
- **Logic nghiệp vụ tách khỏi HTML, nằm trong `public/includes/`**, và phần thuật toán được giữ **thuần, không đụng DB** để test được:
  - `workout-plan.php`: thuật toán gợi ý lịch tập thuần (`generateWorkoutPlan()`, các hằng `WORKOUT_SPLITS`, `WORKOUT_GOALS`, `WORKOUT_LEVELS`, `WORKOUT_EXERCISE_ROLES` gắn **id bài tập cố định** theo `seed_exercises_v2.sql`). `workout-plan-db.php` là lớp truy vấn PDO đi kèm.
  - `tdee.php` (BMR Mifflin-St Jeor / TDEE / macro), `meal-plan.php`, `admin-users.php`, `audit-log.php` (ghi `admin_audit_log` cho thao tác admin).
  - `health.php` (G2): kiểm tra sức khỏe hệ thống cho `admin/health.php` (PHP, extension, bảng thiếu so với `schema.sql` kèm tên file bổ sung cần chạy, múi giờ PHP/MySQL, khóa Gemini chỉ báo đã/chưa cấu hình, thư mục ghi file) và sao lưu database `healthWriteBackup()` cho `admin/backup.php` (POST + CSRF + ô xác nhận, ghi nhật ký `system.backup`). Hàm thuần test ở `test-health.php`.
  - `equipment.php` (thuần, test ở `test-equipment.php`): nhãn dụng cụ, `equivalentExercises()` (bài cùng vai trò khác dụng cụ, dễ có dụng cụ trước), `personalRecords()` / `recordsImproved()` (kỷ lục cá nhân). `equipment-db.php` là phần truy vấn, tự bỏ qua khi DB cũ chưa có cột `equipment`.
  - `weekly-summary.php`: tổng kết tuần bằng AI (D2). `weeklyStats()` và `weeklyPrompt()` là hàm thuần (test ở `test-weekly.php`), AI chỉ viết lời từ đúng số liệu đó; lưu ở bảng `weekly_summaries`, hiển thị và tạo (POST + CSRF) ở `report.php`.
  - `achievements.php`: chuỗi tuần tập đều (`achWeeklyStreak()`), huy hiệu cột mốc (`achMilestones()`), tóm tắt tháng (`achMonthSummary()`); thuần, test ở `test-achievements.php`. Trang `report.php` (Báo cáo & thành tích, in / lưu PDF bằng hộp in của trình duyệt), `export-my-data.php` (người dùng tải CSV của chính mình), `includes/csv.php` (BOM UTF-8 + chống CSV injection, dùng chung với `admin/export.php`).
  - `meal-log.php`: nhật ký ăn (C1). Hàm thuần `mealPortion()`, `userFoodError()`, `mealDaySummary()` (test ở `test-meal-log.php`); trang `food-log.php`. Chỉ dùng số liệu có sẵn trong `foodCatalog()` hoặc người dùng tự nhập theo nhãn, không tự thêm số liệu món ăn không có nguồn.
  - `progression.php`: gợi ý mức tạ buổi sau từ nhật ký (`suggestProgression()`, tăng tải kép: đạt mức lặp trên 2 buổi liền cùng mức tạ thì tăng) và gộp set của chế độ Đang tập (`summarizeSessionSets()`). Thuần, test ở `test-progression.php`. Trang `workout-session.php` (chế độ Đang tập) lưu cả buổi qua `api/log-session.php`.
  - `ai.php`: gọi Gemini, đọc hằng tùy chọn qua `aiConst()` (`AI_API_KEY`, `AI_MODEL`, `AI_MODEL_FALLBACK`, `AI_CA_BUNDLE`, `AI_BASE_URL`, `AI_TIMEOUT`, `AI_TOTAL_TIMEOUT`), trả lỗi có mã; chi tiết kỹ thuật chỉ ghi log server, không gửi về trình duyệt. `api/chatbot.php` nhét dữ liệu user và bài tập liên quan (RAG theo từ khóa trên bảng `exercises`) vào prompt. Khi bật "Dùng hồ sơ của tôi", `includes/chat-context.php` thêm lịch tập, buổi hôm nay kèm mức tạ gợi ý, nhật ký 7 ngày, số đo 30 ngày (phần định dạng `formatTrainingContext()` là hàm thuần, test ở `test-chat.php`). Lịch sử chat lưu ở bảng `chatbot_*` qua `includes/chat-history.php` và `api/chatbot-history.php`.
- **Bảo mật dùng chung:** `csrf.php` (`csrfField()` trong form, `verifyCsrf()` khi nhận POST, lỗi thì `showCsrfError()`), `error-page.php` (`showError()`), `header.php` gửi các header bảo vệ. Quy trình rà soát nằm ở `SECURITY_CHECKLIST.md`.
- **Bài viết:** lấy từ 2 nguồn, gộp trong `includes/articles.php` → `loadArticles()`:
  1. File: mỗi bài là 1 file `public/content/articles/<slug>.php` trả về mảng (định dạng khối `content` ghi ở comment đầu `includes/articles.php`). Ảnh bìa và hình minh họa là SVG trong `public/img/articles/`. `sources` chỉ được ghi nghiên cứu có thật, đã kiểm tra PMID/DOI (xem `docs/nghien-cuu-da-xac-minh.md`).
  2. DB: bảng `articles` do admin soạn (`admin/articles.php`, `admin/article-form.php`).
  Hiển thị qua `includes/article-markup.php`.
- **Phân hệ HLV (vai trò `COACH`):** người dùng nộp đơn (`coach-apply.php`), ADMIN duyệt (`admin/coach-applications.php`) thì vai trò thành `COACH`; ADMIN thu hồi được (`admin/coaches.php`). Mỗi học viên chỉ có 1 HLV (`coaches.php`, `my-coach.php`). Khu `/coach/` có thanh điều hướng riêng (`includes/coach-nav.php`, cùng kiểu `/admin/`).
  - `includes/coach-rules.php`: quy tắc thuần (kiểm tra đơn, máy trạng thái quan hệ, giới hạn macro, nhịp gửi tin). `includes/coach-db.php`: truy vấn. `includes/coach.php`: `requireCoach()`, `requireCoachClient()`.
  - **Quy tắc quyền:** mọi trang/API của HLV gọi `requireCoach()` (đọc lại vai trò từ DB) và với dữ liệu một học viên thì `requireCoachClient()` / `canCoachAccessClient()` (quan hệ `ACTIVE` + vai trò vẫn là `COACH`). Không tin id trên URL. HLV không đọc email/mật khẩu học viên.
  - **Khóa tự sửa:** khi đang có HLV, học viên không tự sửa lịch tập/dinh dưỡng (`coachSelfEditState()`); muốn sửa phải gửi yêu cầu, HLV duyệt thì mở khóa 24 giờ.
  - Thao tác quan trọng của HLV ghi chung bảng `admin_audit_log` với mã `coach.*`.
  - **Đánh giá HLV (A-49):** `includes/coach-reviews.php` (quy tắc đủ điều kiện là hàm thuần, test ở `test-coach-reviews.php`), ô đánh giá `includes/coach-review-card.php` ở `my-coach.php`, điểm trung bình ở `coaches.php` và bảng điều khiển HLV, ADMIN xử lý vi phạm ở `admin/coach-reviews.php`.
  - **Giá tập, buổi thử, lịch hẹn, gói (A-54):** `includes/coach-billing-rules.php` (thuần: kiểm tra giá, báo giá gói, khung giờ đặt, máy trạng thái buổi, `coachBillingSummary()` tính buổi thử / gói buổi / gói tháng và khóa) + `includes/coach-billing.php` (truy vấn). Trang `coach/sessions.php`, tab `my-coach.php?tab=sessions`, form giá ở `coach/profile.php`. Không có cổng thanh toán: HLV bấm "Đã nhận tiền". Hết buổi thì `coachBillingAssert()` / `coachBillingLocked()` chặn giao lịch, đặt dinh dưỡng, nhận xét (tin nhắn vẫn được). Test ở `test-coach-billing.php`.
  - **Mẫu lịch (A-48):** `includes/coach-templates.php` + trang `coach/templates.php`; lưu mẫu ở `coach/client-plan.php` ("Lưu làm mẫu"). Giao mẫu kiểm tra lại quan hệ ACTIVE cho TỪNG học viên được chọn. Test ở `test-coach-templates.php`.
- **Tư vấn sức khỏe từ chuyên gia (vai trò `EXPERT`, A-55):** người dùng nộp đơn (`expert-apply.php`, bắt buộc số chứng chỉ hành nghề + file chứng chỉ), ADMIN duyệt / thu hồi (`admin/expert-applications.php`, `admin/experts.php`, file ở `admin/expert-certificate.php`). Người dùng hỏi riêng theo chủ đề (`consult.php`, `consult-ask.php`, `consult-question.php`); chuyên gia nhận và trả lời ở khu `/expert/` (`includes/expert-nav.php`). `includes/expert-rules.php` (thuần: kiểm tra đơn / câu hỏi, nhận dấu hiệu cấp cứu `healthRedFlags()`, máy trạng thái `healthNextStatus()`, số liệu chia sẻ), `includes/expert-db.php` (truy vấn), `includes/expert.php` (`requireExpert()`, khung nhắc gọi 115). Câu hỏi là dữ liệu riêng tư: chỉ người hỏi và chuyên gia đúng chủ đề / đã nhận xem được, ADMIN không đọc. Chuyên gia không chẩn đoán xác định, không kê đơn. Test ở `test-expert.php`; 3 chuyên gia mẫu bằng `tools/seed-experts.php`.
- **Thông báo (chuông, A-44):** `includes/notifications.php` (`notify()`, đọc/đánh dấu đã đọc), gọi ngay trong các hàm của `coach-db.php` khi có sự kiện (nhận xét, tin nhắn, yêu cầu, duyệt đơn...). Chuông là `includes/notif-bell.php` (gắn ở `navbar.php`, `coach-nav.php`, `admin-nav.php`), JS `js/notifications.js`, API `api/notifications.php`, trang `notifications.php`. Thêm sự kiện mới thì gọi `notify()` trong cùng transaction với thao tác chính.

### Database và file SQL

`sql/schema.sql` là bảng gốc. Các file khác trong `sql/` là **file bổ sung chạy riêng một lần**, chưa chắc đã được gộp vào `schema.sql` (`admin_features.sql`, `article_reads.sql`, `body_metrics.sql`, `nutrition_preferences.sql`, `password_resets.sql`, `split_types.sql`, `users_avatar.sql`, `coach_features.sql`, `coach_extras.sql`, `body_metrics_fix.sql`, `notifications.sql`, `weekly_summaries.sql`, `exercise_equipment.sql`, `coach_templates.sql`, `coach_reviews.sql`, `meal_logs.sql`, `coach_billing.sql`, `coach_bank.sql`, `health_experts.sql`). `exercise_equipment.sql` vừa thêm cột vừa điền dụng cụ cho 40 bài, nên phải chạy lại SAU `seed_exercises_v2.sql` (install.php đã xếp đúng). Mọi file bổ sung đều **chạy lại nhiều lần vẫn an toàn** (dùng cú pháp `IF NOT EXISTS` của MariaDB/XAMPP), nên database cũ chỉ cần `php tools/install.php --upgrade`. Thêm file bổ sung mới thì nhớ thêm tên vào hằng `MIGRATIONS` trong `tools/install.php`. Thêm bảng/cột thì tạo file `sql/<tên>.sql` riêng, dùng `IF NOT EXISTS` khi có thể, rồi cập nhật `docs/ERD.md`. Dữ liệu bài tập hiện tại là `seed_exercises_v2.sql` (40 bài, **xóa sạch và nạp lại** bảng `exercises`). Đổi id bài tập sẽ làm hỏng `WORKOUT_EXERCISE_ROLES` và các test.

## Phạm vi tính năng

### Bắt buộc (Core)
1. Auth: đăng ký/đăng nhập bằng session, phân quyền `USER` / `ADMIN`
2. Thư viện bài tập kèm ảnh + video hướng dẫn: xem/lọc bài tập kháng lực + cardio theo nhóm cơ, trang chi tiết có video YouTube nhúng, cách thực hiện, lỗi thường gặp (admin CRUD)
3. Lịch tập cá nhân hóa: gợi ý split theo mục tiêu/số ngày tập/kinh nghiệm, cho chỉnh sửa
4. Tính TDEE & Macro theo chỉ số cơ thể + mục tiêu

### Nên có
5. Theo dõi tiến trình: nhật ký tập luyện, biểu đồ cân nặng/số đo (dùng Chart.js qua CDN — thư viện JS thuần, không phải framework)
6. Hồ sơ cá nhân (profile): nhập tuổi, chiều cao, cân nặng, giới tính, mức vận động, mục tiêu — dữ liệu đầu vào cho TDEE và gợi ý lịch tập

### Tùy chọn (dồn vào Chatbot AI)
7. Chatbot AI cơ bản → 8. Cá nhân hóa theo dữ liệu user → 9. RAG đơn giản: tra bảng `exercises` (mô tả, nhóm cơ, cách thực hiện) theo từ khóa rồi dẫn tới trang bài tập

**Đã loại bỏ:** chat tự do user-user, community/forum.

**Đã làm thêm ngoài kế hoạch ban đầu:** bài viết kiến thức có trích nghiên cứu, khu admin (người dùng, bài tập, bài viết, nhật ký hoạt động, thống kê, xuất CSV), quên/đặt lại mật khẩu, ảnh đại diện, **phân hệ Huấn luyện viên**, **tư vấn sức khỏe từ chuyên gia** (hỏi đáp riêng, không phải diễn đàn). Trạng thái từng việc xem `TASKS.md`.

## Quy ước code (PHP/JS thuần)

- **PHP:**
  - Mọi truy vấn SQL dùng **PDO prepared statements** (`$stmt = $pdo->prepare(...); $stmt->execute([...]);`), tuyệt đối không nối chuỗi biến vào câu SQL
  - Tách logic DB ra hàm riêng trong `/includes/functions.php`, không viết SQL rải rác khắp file `.php` hiển thị HTML
  - Luôn `session_start()` đầu file cần auth, kiểm tra qua hàm dùng chung trong `auth.php`
  - File trong `/api` chỉ trả JSON (`header('Content-Type: application/json'); echo json_encode(...)`), không in HTML
  - Validate và sanitize mọi input từ `$_POST`/`$_GET` trước khi dùng
- **JavaScript:**
  - Không dùng framework, chỉ vanilla JS + `fetch()`
  - Tách file JS theo từng trang/tính năng, không gộp hết vào 1 file khổng lồ
  - Dùng `addEventListener`, tránh viết `onclick="..."` trực tiếp trong HTML khi logic phức tạp
- **CSS:**
  - 1 file `style.css` chứa biến dùng chung (màu, spacing) qua CSS variables (`:root { --color-accent: ...; }`)
  - Đặt tên class theo kiểu BEM hoặc đơn giản rõ nghĩa (`.exercise-card`, `.btn-primary`)
- **Đặt tên:** biến/hàm tiếng Anh rõ nghĩa; comment tiếng Việt giải thích nghiệp vụ được khuyến khích, đặc biệt ở phần thuật toán (gợi ý lịch tập, tính TDEE)

## Database — quy ước

- Tên bảng: số nhiều, snake_case (VD: `workout_plans`, `body_metrics`)
- Khóa ngoại: `<table_singular>_id`
- Luôn có `created_at`
- Trước khi sửa `/sql/schema.sql`, kiểm tra `/docs/ERD.md` để tránh trùng lặp

## Bảo mật (quan trọng hơn bình thường vì PHP thuần dễ mắc lỗi cơ bản)

- **Bắt buộc PDO prepared statements** — đây là lỗi phổ biến nhất của sinh viên mới học PHP (SQL Injection)
- Mật khẩu: `password_hash()` / `password_verify()` của PHP, không tự viết hàm hash
- Session: `session_regenerate_id()` sau khi đăng nhập thành công để tránh session fixation
- Validate input hai phía: JS (UX) **và** PHP (bắt buộc, vì JS có thể bị tắt/bypass)
- Không hardcode API key (Anthropic/OpenAI, DB credentials) — dùng file `config.php` không commit (thêm vào `.gitignore`), hoặc biến môi trường nếu hosting hỗ trợ
- Escape output ra HTML bằng `htmlspecialchars()` để tránh XSS khi hiển thị dữ liệu user nhập (VD: ghi chú trong workout log)

## Phân công nhóm 5 người (theo tính năng trọn vẹn, không tách frontend/backend riêng)

Mỗi người phụ trách **trọn 1 nhóm tính năng từ database → PHP backend → giao diện** để bắt buộc hiểu toàn bộ luồng (đúng yêu cầu "hiểu bản chất"), không chia kiểu 1 người chỉ làm HTML/CSS.

| Thành viên | Phụ trách trọn vẹn |
|---|---|
| **A** | Auth (đăng ký/đăng nhập/session) + hồ sơ cá nhân + layout dùng chung (header/footer/navbar) + bảo mật chung |
| **B** | Danh sách/lọc bài tập + Lịch tập cá nhân hóa (thuật toán gợi ý split) |
| **C** | TDEE/Macro calculator + Theo dõi tiến trình (biểu đồ) |
| **D** | Nhập liệu 40-50 bài tập kèm ảnh/video/hướng dẫn (`seed.sql`) + trang chi tiết bài tập + thiết kế & duy trì `ERD.md`/`schema.sql` chung cho cả nhóm |
| **E** | Chatbot AI + CSS tổng thể/responsive (vì cần hiểu giao diện toàn hệ thống để chatbot UI nhất quán) |

> D giữ vai trò duy trì database chung vì mọi người đều dùng chung bảng — cần 1 đầu mối tránh xung đột schema.

## Lệnh thường dùng

PHP có thể không có trong PATH. Trên máy này dùng `C:\xampp\php\php.exe` (PHP 8.2). Chạy các lệnh dưới đây ở gốc repo:

```bash
# Chạy local (MySQL của XAMPP phải đang chạy; config.php chép từ config.example.php)
php -S localhost:8000 -t public

# Test: script PHP tự viết, không dùng PHPUnit. In [OK]/[FAIL], cuối cùng in "TẤT CẢ ĐỀU PASS"
php test-workout-plan.php       # thuật toán lịch tập, dữ liệu từ test-exercises-fixture.php (không cần DB)
php test-workout-plan-db.php    # lớp workout-plan-db.php trên SQLite in-memory (cần pdo_sqlite)
php test-dashboard.php          # hàm thuần và lớp đọc DB của bảng điều khiển (phần DB cần pdo_sqlite)
php tools/test-coach-http.php   # kịch bản HLV + giá tập + tư vấn sức khỏe qua HTTP (200 bước): tự tạo DB gymsmart_test_hlv + máy chủ :8099 rồi xóa; cần MySQL đang chạy, không đụng DB thật
php test-coach.php              # phân hệ HLV: đơn đăng ký, quan hệ, quyền xem, khóa tự sửa, macro, tin nhắn (phần DB cần pdo_sqlite)
php test-coach-billing.php      # giá tập, buổi thử, lịch hẹn, gói, khóa khi hết buổi (phần DB cần pdo_sqlite)
php test-expert.php             # tư vấn sức khỏe: đơn chuyên gia, câu hỏi, dấu hiệu cấp cứu, quyền xem, nhận / trả lại / đóng (phần DB cần pdo_sqlite)
php test-health.php             # trang Sức khỏe hệ thống: đọc bảng từ schema.sql, trạng thái chung, câu INSERT sao lưu (không cần DB)
php test-nutrition.php          # nhịp mục tiêu, ngày tập/nghỉ, tiến độ cân nặng, loại trừ thực phẩm, lịch 7 ngày (phần lưu lựa chọn cần pdo_sqlite)

# Lint 1 file
php -l public/path/to/file.php

# Quét regex tìm chỗ bảo mật đáng nghi (thiếu prepare, thiếu escape, thiếu requireLogin...). Kết quả chỉ là gợi ý, phải tự đọc lại code
php tools/audit.php public

# Kiểm tra vì sao chatbot không trả lời (cURL, khóa API, model, SSL). Có gọi thật tới Gemini
php tools/chatbot-check.php

# Dựng database một lệnh (thay cho import từng file trong phpMyAdmin). DB đã có bảng thì tự dừng, không ghi đè
php tools/install.php                 # tạo DB_NAME: schema.sql + 40 bài tập + ảnh/video
php tools/install.php --upgrade       # DB cũ: chạy lại mọi file bổ sung trong sql/ (không mất dữ liệu)
php tools/install.php --db=gymsmart_demo --fresh --demo   # DB thử riêng kèm tài khoản thử @gymsmart.test
php tools/seed-coaches.php            # 5 HLV mẫu (3 nam, 2 nữ) kèm giá, mật khẩu Coach@12345; chạy lại chỉ cập nhật, không tạo trùng
php tools/seed-experts.php            # 3 chuyên gia sức khỏe MẪU (không có chứng chỉ, hiện nhãn "hồ sơ mẫu"), mật khẩu Expert@12345

# Chạy mọi test một lệnh, in bảng tổng kết (--http thêm kịch bản HLV qua HTTP, cần MySQL)
php tools/run-tests.php
```

Không có bước build, không có bundler, không dùng Composer/npm. Chart.js được lưu sẵn ở `public/js/lib/chart.umd.min.js` (không tải qua CDN).

Đổi `seed_exercises_v2.sql` hoặc bảng vai trò trong `workout-plan.php` thì phải cập nhật `test-exercises-fixture.php` và chạy lại cả 2 test.

**Database:** dùng XAMPP/MAMP (có sẵn phpMyAdmin), hoặc cài phpMyAdmin riêng nếu dùng MySQL độc lập.
- Tạo database `gymsmart` qua giao diện phpMyAdmin
- Tab **Import** → chọn `sql/schema.sql`, rồi `sql/seed_exercises_v2.sql`, rồi các file bổ sung trong `sql/` (xem mục "Database và file SQL"). Cách nhanh hơn: `php tools/install.php` (tạo mới) hoặc `php tools/install.php --upgrade` (DB cũ)
- Múi giờ: `includes/db.php` đặt PHP và phiên MySQL cùng `Asia/Ho_Chi_Minh` (đổi bằng hằng `APP_TIMEZONE` trong `config.php`). Công cụ dòng lệnh trỏ được sang DB khác bằng biến môi trường `GYMSMART_DB` (trang web luôn dùng `DB_NAME`)
- Khi sửa cấu trúc bảng trực tiếp trên phpMyAdmin, **nhớ export lại và cập nhật `sql/schema.sql`** để cả nhóm đồng bộ (tab Export → chọn format SQL)

## Quy trình Git của nhóm

- Repo GitHub để **Private**, mọi thành viên là Collaborator
- **Không code trực tiếp trên `main`.** Mỗi người một nhánh cho mỗi việc: `git checkout -b a-profile`, `b-exercises`, `c-tdee`...
- Trước khi bắt đầu mỗi buổi làm việc: `git checkout main` rồi `git pull`
- Làm xong thì `git push -u origin ten-nhanh`, tạo **Pull Request** trên GitHub; **một bạn khác đọc code** rồi mới bấm Merge
- Không commit `config.php` (đã có trong `.gitignore`)
- Chỉ **D** chỉnh cấu trúc bảng trên phpMyAdmin rồi export lại `schema.sql`; ai cần đổi bảng thì báo D. Sau khi `schema.sql` đổi, báo cả nhóm chạy lại lệnh SQL cập nhật trên máy mình

## Cách hỏi AI khi bí (không vibe code)

- **Mặc định là tự viết code trước.** Chỉ hỏi AI khi thực sự bí ở một điểm cụ thể — không hỏi "viết hộ tính năng X" ngay từ đầu
- Khi hỏi, nêu rõ: đã thử gì, kẹt ở đâu, hiểu sai hay không hiểu gì — để AI giải thích đúng chỗ bí thay vì viết lại từ đầu
- Nếu AI đưa code mẫu, **đọc hiểu hết rồi mới dùng**, không copy-paste thẳng khi chưa hiểu
- Với lỗi (bug): dán lỗi, hỏi "lỗi này nghĩa là gì" trước khi hỏi "sửa sao" — hiểu nguyên nhân rồi tự sửa nếu có thể
- Với thuật toán nghiệp vụ (gợi ý lịch tập, TDEE/macro): nên tự nghĩ logic trước, chỉ hỏi AI để kiểm tra lại hoặc gỡ chỗ chưa rõ công thức — đây là phần quan trọng nhất phải tự hiểu vì hay bị hỏi khi bảo vệ
- Có thể nhờ AI **review code đã tự viết xong** để tìm lỗi/góp ý — khác với nhờ viết mới

## Việc KHÔNG làm

- Không dùng framework/ORM (Laravel, Composer package nặng) — vi phạm yêu cầu hiểu bản chất
- Không nối chuỗi SQL trực tiếp với biến từ `$_POST`/`$_GET`
- Không tự sinh dữ liệu bài tập giả hoặc gắn nguồn không có thật
- Không dùng ảnh/video có bản quyền lấy từ mạng xã hội. Chỉ dùng: video YouTube nhúng bằng iframe, ảnh từ nguồn mở (như wger, ghi nguồn theo giấy phép), ảnh Unsplash theo Unsplash License (tải về lưu trong repo, ghi vào `public/assets/img/CREDITS.md`) hoặc ảnh nhóm tự chụp
- Không lưu API key trong code commit
- Không nhờ AI viết hộ nguyên tính năng/trang hoàn chỉnh ngay từ đầu — chỉ hỏi khi bí ở điểm cụ thể
- Không copy-paste code từ AI mà không đọc hiểu trước
- Không xây dựng chat tự do user-user, community/forum trừ khi được yêu cầu đổi phạm vi. Tin nhắn chỉ có giữa HLV và học viên đang ACTIVE
