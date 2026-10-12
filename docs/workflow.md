# Workflow phát triển dự án — GymSmart (nhóm 5 người, PHP/HTML/CSS/JS thuần)

## Nguyên tắc chung

- **Code tay, không framework:** PHP thuần + PDO, HTML/CSS/JS thuần — không Laravel, không React. Mục tiêu là hiểu bản chất, không phải tốc độ
- Mỗi thành viên phụ trách **trọn 1 tính năng từ DB → backend → giao diện** (xem `CLAUDE.md`) — không chia kiểu "người chỉ làm CSS, người chỉ viết PHP" vì sẽ có người không hiểu toàn bộ luồng
- Sprint 2 tuần, mỗi sprint có deliverable demo được
- Thứ tự bắt buộc: **Core → Nên có → Tùy chọn (Chatbot AI)**
- **Bắt buộc hiểu + giải thích được code của chính mình** trước khi coi là xong — có cơ chế kiểm tra ở cuối mỗi sprint (xem mục "Cơ chế đảm bảo hiểu bản chất")

## Phân công cố định

| Thành viên | Phụ trách trọn vẹn |
|---|---|
| **A** | Auth (đăng ký/đăng nhập/session) + layout dùng chung + bảo mật chung |
| **B** | Thư viện bài tập + Lịch tập cá nhân hóa |
| **C** | TDEE/Macro calculator + Theo dõi tiến trình |
| **D** | Nhập liệu 40–50 bài tập kèm ảnh/video/hướng dẫn (`seed.sql`) + trang chi tiết bài tập (video nhúng) + chủ trì `ERD.md`/`schema.sql` chung |
| **E** | Chatbot AI + CSS tổng thể/responsive |

---

## Giai đoạn 0 — Chuẩn bị (tuần 1)

| Việc | Người phụ trách | Output |
|---|---|---|
| Cả nhóm học nhanh PHP cơ bản + PDO (nếu chưa biết) | Cả nhóm | — |
| Thiết kế ERD, thống nhất schema MySQL | D (chủ trì) + cả nhóm góp ý | `/docs/ERD.md`, `/sql/schema.sql` |
| Wireframe các trang chính | E (chủ trì) + cả nhóm | Wireframe |
| Setup môi trường: PHP, MySQL, XAMPP/MAMP (kèm phpMyAdmin) hoặc `php -S` | Cả nhóm | Mỗi người chạy được "Hello World" PHP trên máy mình, mở được phpMyAdmin |
| Tạo `header.php`/`footer.php`/`navbar.php`/`db.php` dùng chung | A | Layout khung sẵn cho cả nhóm dùng |
| Thống nhất cấu trúc JSON cho các API nội bộ (`/api/*.php`) | Cả nhóm | `/docs/API_SPEC.md` (bản nháp) |

**Lưu ý:** vì không dùng framework, giai đoạn 0 cần thêm thời gian để cả nhóm thật sự hiểu PDO, session, cấu trúc include — đừng rút ngắn giai đoạn này.

---

## Giai đoạn 1 — Core (tuần 2–6, 2.5 sprint — dài hơn bản dùng framework vì code tay chậm hơn)

**Mục tiêu:** Chạy được end-to-end: đăng ký → xem bài tập → tạo lịch tập → xem TDEE/macro.

### Sprint 1.1 (tuần 2–3)
- **A:** Bảng `users`, trang đăng ký/đăng nhập/đăng xuất, session, hàm `requireLogin()` dùng chung trong `auth.php` (✅ đã xong); tiếp theo là trang hồ sơ cá nhân `profile.php` (tuổi, chiều cao, cân nặng, giới tính, mức vận động, mục tiêu) để C có dữ liệu tính TDEE
- **B:** Trang danh sách bài tập dạng lưới có ảnh, bộ lọc theo nhóm cơ/loại/độ khó và ô tìm kiếm bằng PDO query động (`WHERE` có điều kiện)
- **C:** Viết hàm PHP tính TDEE/Macro (Mifflin-St Jeor) thuần túy, test độc lập bằng vài bộ số liệu tay (chưa cần giao diện)
- **D:** Rà soát `schema.sql` cùng A/B/C trước khi ai cũng bắt đầu code (đã bỏ 4 bảng bài viết, thêm cột `instructions`, `common_mistakes` cho `exercises`); nhập 40–50 bài tập vào `seed.sql` kèm ảnh, ID video YouTube, hướng dẫn; làm trang chi tiết bài tập có video nhúng (`<iframe>` YouTube, chỉ lưu ID video trong DB)
- **E:** `style.css` khung (biến màu, typography, layout chung); giao diện trang đăng ký/đăng nhập theo A code phần PHP
- **Demo cuối sprint:** đăng ký → đăng nhập → duyệt thư viện bài tập → mở 1 bài xem video hướng dẫn

### Sprint 1.2 (tuần 4–5)
- **B:** Bảng `workout_plans`/`workout_plan_exercises`, thuật toán gợi ý split (thuần PHP, dùng `if/switch` có trọng số rõ ràng — tự viết, không gọi thư viện ngoài)
- **C:** API `/api/calculate-tdee.php` (AJAX, trả JSON), form nhập chỉ số cơ thể, hiển thị kết quả không reload trang
- **A:** Hỗ trợ B/C review phần session/bảo mật trong code mới
- **E:** Giao diện trang lịch tập (xem theo tuần), trang kết quả TDEE/macro
- **Demo (Milestone giữa kỳ):** luồng đầy đủ đăng ký → nhập chỉ số → nhận lịch tập gợi ý → xem TDEE/macro

### Sprint 1.3 (tuần 6, buffer core)
- Cả nhóm: hoàn thiện phần còn thiếu, sửa lỗi bảo mật cơ bản (kiểm tra chéo: mỗi người xem code 1 bạn khác, tìm chỗ thiếu `prepare()`/`htmlspecialchars()`)

---

## Giai đoạn 2 — Nên có (tuần 7–9, 1.5 sprint)

### Sprint 2.1 (tuần 7–8)
- **B:** Bảng `workout_logs`, API check-in set/reps/weight bằng AJAX
- **C:** Bảng `body_metrics`, trang biểu đồ tiến trình (dùng Chart.js qua CDN, dữ liệu lấy từ `/api/*.php`)
- **D:** Hoàn thiện nội dung video/hướng dẫn cho toàn bộ bài tập, gợi ý 3 bài cùng nhóm cơ ở cuối trang chi tiết, cửa sổ bật lên (modal) xem video ngay trong trang lịch tập (phối hợp B)
- **A, E:** Hỗ trợ review chung, hoàn thiện responsive CSS cho các trang mới

### Sprint 2.2 (tuần 9, buffer)
- Cả nhóm: sửa bug, dọn code
- **Kiểm tra mốc:** nếu trễ tiến độ, bỏ hẳn giai đoạn 3, dồn cho giai đoạn 4

---

## Giai đoạn 3 — Chatbot AI (tuần 10–12, tùy chọn, 3 mức)

- **E** chủ trì, cả nhóm hỗ trợ khi cần (vì các module khác đã ổn định)

### Mức 1 — Cơ bản
- `/api/chatbot.php` gọi API AI qua `curl`, system prompt giới hạn phạm vi gym/dinh dưỡng
- Giao diện widget chat nổi góc màn hình (JS thuần, `fetch()` gọi API)

### Mức 2 — Cá nhân hóa
- Trước khi gọi API AI, PHP truy vấn `nutrition_targets`/`workout_plans` của user đang đăng nhập, nhét vào prompt
- Bảng `chatbot_sessions`/`chatbot_messages` lưu lịch sử hội thoại

### Mức 3 — RAG đơn giản
- Khi user hỏi, PHP query bảng `exercises` (tên, nhóm cơ, mô tả, `instructions`, `common_mistakes`) theo từ khóa liên quan, nhét nội dung vào prompt; chatbot trả lời dựa trên dữ liệu đó và dẫn tới trang chi tiết bài tập

> Dừng ở mức nào cũng demo được — ghi rõ trong báo cáo mức đã đạt và giải thích được cơ chế bằng lời, không chỉ chỉ vào code.

---

## Giai đoạn 4 — Hoàn thiện & báo cáo (2 tuần cuối, tuần 13–14)

| Việc | Người phụ trách |
|---|---|
| Kiểm thử toàn hệ thống, đặc biệt test SQL Injection/XSS cơ bản (tự thử nhập `' OR '1'='1` vào form) | Cả nhóm |
| Responsive, tối ưu UX mobile | E |
| Viết báo cáo: kiến trúc, ERD, thuật toán, lý do chọn PHP thuần (gắn với yêu cầu hiểu bản chất) | D viết DB/kiến trúc, B/C viết thuật toán |
| Chuẩn bị demo — **mỗi người phải tự demo và giải thích được phần mình làm**, không chỉ 1 người thuyết trình hộ | Cả nhóm |
| Quay video demo dự phòng | A |

---

## Cơ chế đảm bảo hiểu bản chất (quan trọng — đáp ứng yêu cầu của nhóm)

- **Cuối mỗi sprint:** mỗi người trình bày ngắn (5 phút) giải thích phần mình code cho cả nhóm nghe — không đọc code, nói bằng lời
- **Code review chéo bắt buộc:** trước khi merge vào nhánh chính, 1 thành viên khác phải đọc và hiểu được đoạn code đó, không chỉ chạy thử là xong
- **Không vibe code:** tự viết trước, chỉ hỏi AI khi bí ở điểm cụ thể (xem mục "Cách hỏi AI khi bí" trong `CLAUDE.md`) — cấm "copy code AI đưa về chạy mà không đọc", phải tự giải thích lại được logic trước khi commit
- **Khi bảo vệ đồ án:** phân công ngẫu nhiên — giảng viên có thể hỏi bất kỳ ai về bất kỳ phần nào, không chỉ người "chính chủ" — nên cả nhóm nên nắm sơ bộ cả hệ thống, không chỉ phần mình làm

---

## Checklist kiểm thử tối thiểu trước báo cáo

- [ ] Đăng ký/đăng nhập/session hoạt động đúng, đăng xuất xóa session
- [ ] Thử SQL Injection cơ bản vào các form — hệ thống không bị lỗi/bypass
- [ ] Thư viện bài tập lọc đúng theo nhóm cơ/loại
- [ ] Trang chi tiết bài tập hiển thị đúng ảnh, video YouTube, hướng dẫn; bài chưa có video không bị lỗi trang
- [ ] Mọi bài tập đều ghi nguồn ảnh/video hợp pháp (wger, YouTube nhúng hoặc tự quay), không dùng ảnh/video lấy từ mạng xã hội
- [ ] Tạo lịch tập với ít nhất 3 tổ hợp input khác nhau ra kết quả hợp lý
- [ ] TDEE/Macro tính đúng với vài bộ số liệu kiểm tra tay
- [ ] Biểu đồ tiến trình hiển thị đúng khi có/không có dữ liệu
- [ ] Mọi trang admin-only chặn đúng user thường (kiểm tra cả ở PHP, không chỉ ẩn nút ở giao diện)
- [ ] (Nếu làm) Chatbot không trả lời lạc đề, không tư vấn y tế nguy hiểm

---

## Rủi ro cần lưu ý

- **PHP thuần dễ viết trùng lặp code** (mỗi trang tự query lại) → dùng chung hàm trong `/includes/functions.php` ngay từ đầu, đừng để tới cuối mới dọn
- **5 người cùng sửa schema** dễ xung đột → D là đầu mối duy nhất chỉnh bảng trên phpMyAdmin rồi export lại `schema.sql`, người khác đề xuất thay đổi qua D thay vì tự sửa bảng trực tiếp
- **Lỗi bảo mật cơ bản (SQL Injection, quên hash password)** dễ xảy ra hơn khi không có framework che chắn sẵn → checklist bảo mật phải kiểm tra kỹ ở giai đoạn 4, đừng để tới lúc demo mới phát hiện
- **Tốc độ code tay chậm hơn dùng framework** → lịch trình trong file này đã cộng thêm buffer so với bản dùng React/Spring Boot, đừng rút ngắn thêm
