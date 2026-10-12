# CLAUDE.md

Tài liệu hướng dẫn cho Claude Code khi làm việc trên dự án này.

## Tổng quan dự án

**Tên dự án:** GymSmart — Website hỗ trợ tập luyện cá nhân hóa
**Bối cảnh:** Bài tập lớn môn Phát triển ứng dụng trên nền Web
**Quy mô:** Nhóm 5 thành viên, thời gian làm trong 1 kỳ học
**Yêu cầu bắt buộc:** Tất cả thành viên phải **hiểu bản chất code và tự code tay** — không dùng framework che giấu logic (không Laravel, không ORM), không copy code mà không hiểu. **Không vibe code.** Cách làm việc với AI: tự viết code trước theo hiểu biết của mình, **chỉ hỏi AI khi bí ở một chỗ cụ thể** (lỗi không hiểu, không biết cú pháp, không chắc cách làm) — AI đóng vai trò giải thích/gỡ rối tại điểm nghẽn đó, không viết hộ nguyên tính năng từ đầu.

> Đã loại bỏ: chat/nhắn tin giữa user-user, community/forum, role/tính năng Coach, **module bài viết nghiên cứu**. Lý do: chi phí xây dựng không tương xứng giá trị demo. Phần "học tập" của website được thay bằng **ảnh + video hướng dẫn nằm ngay trong thư viện bài tập**. Thời gian dư được dồn vào làm sâu Chatbot AI.

## Tech Stack — PHP thuần (LAMP, không framework)

- **Frontend:** HTML + CSS thuần + JavaScript thuần (vanilla JS, dùng `fetch` để gọi AJAX, KHÔNG dùng React/Vue)
- **Backend:** PHP thuần (không Laravel/Symfony) — xử lý logic trực tiếp trong các file `.php`
- **Database:** MySQL, truy vấn qua **PDO** (bắt buộc dùng prepared statements, không nối chuỗi SQL trực tiếp — tránh SQL Injection)
- **Quản trị DB:** phpMyAdmin — dùng để tạo/sửa bảng, xem dữ liệu, chạy thử query, import/export `.sql`. Toàn bộ nhóm thao tác DB qua phpMyAdmin thay vì dòng lệnh MySQL
- **Auth:** PHP Session (`$_SESSION`) — đơn giản, dễ hiểu bản chất hơn JWT cho người mới, không cần thư viện ngoài
- **AI Chatbot:** Gọi API model ngôn ngữ (Anthropic/OpenAI) qua `curl` trong PHP, trả JSON cho frontend
- **Không dùng:** React, Vue, Laravel, Composer package quản lý nặng, ORM (Eloquent, Doctrine) — mọi thứ viết tay để hiểu rõ luồng chạy

> Lý do chọn PHP thuần: với yêu cầu "hiểu bản chất", framework càng ít càng tốt — mỗi dòng code sinh viên viết ra phải tự giải thích được nó làm gì, không có lớp trừu tượng ẩn giấu.

## Cấu trúc thư mục dự kiến (mô hình Multi-Page Application)

```
/public (hoặc thư mục gốc web server trỏ tới)
  /css
    style.css          (style dùng chung)
    [feature].css       (style riêng từng trang nếu cần)
  /js
    main.js             (logic JS dùng chung)
    [feature].js         (JS riêng từng trang: workout-plan.js, nutrition.js, chatbot.js...)
  /includes
    db.php              (kết nối PDO, dùng chung toàn bộ)
    auth.php            (hàm kiểm tra session đăng nhập)
    header.php, footer.php, navbar.php   (include dùng chung mọi trang)
    functions.php        (hàm tiện ích dùng chung)
  /api
    logout.php
    calculate-tdee.php
    workout-plan-generate.php
    chatbot.php
    ... (mỗi file xử lý 1 request AJAX, trả JSON)
  index.php, login.php, register.php, profile.php
  exercises.php          (lưới bài tập + lọc theo nhóm cơ)
  exercise-detail.php    (video YouTube nhúng + cách thực hiện + lỗi thường gặp)
  workout-plan.php
  nutrition.php
  progress.php
/sql
  schema.sql            (câu lệnh tạo bảng)
  seed.sql              (dữ liệu mẫu: 40-50 bài tập kèm ảnh, video, hướng dẫn)
/docs
  ERD.md
  API_SPEC.md
  workflow.md
  UI_UX_PROMPT.md
```

**Nguyên tắc tổ chức:** Mỗi trang `.php` ở thư mục gốc chịu trách nhiệm cho 1 tính năng, tự include `header.php`/`footer.php`, tự gọi hàm cần thiết từ `/includes`. Phần nào cần cập nhật động không reload trang (tính TDEE, chat với AI, tick hoàn thành bài tập) thì gọi qua `/api/*.php` bằng `fetch()` trong JS, nhận về JSON.

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

**Đã loại bỏ:** chat user-user, community/forum, role Coach, bài viết nghiên cứu (thay bằng video trong thư viện bài tập).

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

```bash
# Chạy local bằng PHP built-in server (không cần cài Apache/Nginx)
php -S localhost:8000 -t public
```

**Database:** dùng XAMPP/MAMP (có sẵn phpMyAdmin), hoặc cài phpMyAdmin riêng nếu dùng MySQL độc lập.
- Tạo database `gymsmart` qua giao diện phpMyAdmin
- Tab **Import** → chọn `sql/schema.sql` để tạo bảng, rồi `sql/seed.sql` để nạp dữ liệu mẫu
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
- Không dùng ảnh/video có bản quyền lấy từ mạng xã hội. Chỉ dùng: video YouTube nhúng bằng iframe, ảnh từ nguồn mở (như wger, ghi nguồn theo giấy phép) hoặc ảnh nhóm tự chụp
- Không lưu API key trong code commit
- Không nhờ AI viết hộ nguyên tính năng/trang hoàn chỉnh ngay từ đầu — chỉ hỏi khi bí ở điểm cụ thể
- Không copy-paste code từ AI mà không đọc hiểu trước
- Không xây dựng lại chat user-user, community/forum, role Coach trừ khi được yêu cầu đổi phạm vi
