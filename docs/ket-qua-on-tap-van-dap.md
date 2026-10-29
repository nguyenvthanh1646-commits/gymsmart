# Kết quả ôn tập vấn đáp — buổi 1

Ghi lại buổi ôn vấn đáp ngày 22/10/2026 (Claude đóng vai thầy hỏi, leader trả lời). Tài liệu nền là `docs/on-tap.md`. Số dòng code theo commit `28b6c8f` trên nhánh `main`.

Đã ôn: **9 câu** (đăng nhập, session, mật khẩu, CSRF, quên mật khẩu, ảnh đại diện, thư viện bài tập, lịch tập). Câu 10 (TDEE) chưa trả lời; câu hỏi và đáp án để ở cuối file để tự luyện.

---

## Bảng điểm

| # | Chủ đề | Điểm | Nhận xét chính |
|---|---|---|---|
| 1 | Luồng đăng nhập | **0/10** | Nhầm sang đăng ký (`register.php`) |
| 2 | `session_regenerate_id` | **0/10** | Chưa biết, đã được giảng lại |
| 2b | Session fixation (hỏi lại) | **6/10** | Mới dịch tên, chưa định nghĩa; thiếu số dòng |
| 3 | Mật khẩu được băm ở đâu | **8/10** | Đúng; thiếu số dòng 146 |
| 4 | CSRF token | **5/10** | Bỏ trống ý "so sánh cái gì với cái gì" |
| 5 | Mã quên mật khẩu | **6/10** | **Sai:** DB lưu bản băm SHA-256, không lưu mã gốc |
| 6 | Upload ảnh đại diện | **7/10** | Thiếu ý "vẽ lại ảnh bằng GD" |
| 7 | SQL Injection ở bộ lọc bài tập | **4/10** | Thiếu lớp whitelist `readChoice()` |
| 8 | Lịch tập: mục tiêu có ảnh hưởng không | (được giảng) | Câu bẫy: mục tiêu **không** ảnh hưởng lịch |
| 9 | Con trỏ xoay vòng | **4/10** | Đúng từ khóa, chưa giải thích cách chạy |

**Điểm yếu lặp lại nhiều lần:**
1. **Không nêu số dòng.** Thầy hỏi "file nào, dòng nào" thì phải chỉ được dòng.
2. **Trả lời bằng một cụm từ** ("cố định phiên", "xoay vòng bằng con trỏ") mà không giải thích cơ chế.
3. **Bỏ trống ý quan trọng nhất** (CSRF so sánh gì; ảnh có giấu mã).
4. **Nhầm luồng** đăng nhập với đăng ký.

---

## Câu 1 — Luồng đăng nhập

**Câu hỏi:** Mô tả chuyện gì xảy ra ở server từ lúc bấm "Đăng nhập" đến khi vào trang chủ.

**Em đã trả lời:** "File register xử lý, kiểm tra tên người dùng, mật khẩu có trùng không..." → **Sai**, đó là luồng đăng ký.

**Đáp án** (`public/login.php`):

| Bước | Việc | Dòng |
|---|---|---|
| 1 | Nạp `auth.php` (session), `db.php` (`$pdo`), `csrf.php` | 12–14 |
| 2 | Đã đăng nhập thì chuyển về trang chủ | 17–18 |
| 3 | Chỉ xử lý khi là POST | 25 |
| 4 | `verifyCsrf()`, sai thì trang lỗi 403 | 27 |
| 5 | Lấy email (`trim`) và mật khẩu, rỗng thì báo lỗi | 31–34 |
| 6 | `SELECT id, full_name, password_hash, role FROM users WHERE email = ?` (prepared statement) | 38–41 |
| 7 | `password_verify($password, $user['password_hash'])` | 46 |
| 8 | `session_regenerate_id(true)` | 49 |
| 9 | Lưu `$_SESSION['user_id']`, `role`, `full_name` | 51–53 |
| 10 | Chuyển `/index.php`; ADMIN được chuyển tiếp sang `/admin/dashboard.php` | 55 (`index.php` dòng 21) |
| 11 | Sai email hoặc sai mật khẩu đều báo **cùng một câu** để không lộ email nào đã đăng ký | 61 |

**Mẹo nhớ:** POST → CSRF → rỗng? → SELECT theo email → `password_verify` → `regenerate_id` → lưu session → chuyển trang.

**Phân biệt:** kiểm tra email **trùng** là việc của **đăng ký** (`register.php` dòng 127–130). Đăng nhập thì **tìm** user theo email.

**Hạn chế nên tự nói ra:** chưa giới hạn số lần đăng nhập sai (A-8 chưa làm).

---

## Câu 2 và 2b — `session_regenerate_id(true)` và session fixation

**Câu hỏi:** Xóa dòng `session_regenerate_id(true)` thì bị tấn công thế nào? Tham số `true` để làm gì?

**Em đã trả lời:** lần đầu "chưa biết"; lần hỏi lại: "là cố định phiên; `session_regenerate_id(true)`; đăng nhập đúng thì mới đổi mã phiên mới".

**Đáp án:**
- **Session là gì:** `session_start()` (`includes/auth.php` dòng 35) cấp một mã phiên (cookie `PHPSESSID`); server giữ dữ liệu `$_SESSION` ứng với mã đó. **Ai cầm mã phiên thì được coi là chủ phiên.**
- **Session fixation:** kẻ xấu lấy một mã phiên mà chúng biết, gài vào trình duyệt nạn nhân. Nạn nhân đăng nhập, server ghi `user_id` vào **đúng mã đó**. Kẻ xấu dùng mã đó là vào được tài khoản mà không cần mật khẩu.
- **Cách chống:** `login.php` **dòng 49** đổi sang mã phiên mới ngay khi đăng nhập đúng, nên mã cũ vô dụng. `true` = **xóa luôn dữ liệu phiên cũ** trên server.
- **Vì sao gọi sau khi kiểm tra mật khẩu và trước khi ghi session:** mã phải đổi đúng lúc người dùng chuyển từ "khách" sang "đã đăng nhập" (quyền tăng lên). Dòng 49 đứng **trước** dòng 51–53 là có chủ ý. Nhập sai mật khẩu thì quyền không đổi, đổi mã cũng vô ích.
- **Hai lớp bảo vệ thêm** (`includes/auth.php`): dòng 18 `use_only_cookies` (không nhận mã phiên qua URL), dòng 21 `use_strict_mode` (không nhận mã phiên do client tự bịa).
- Cũng dùng ở `profile.php` dòng 300, sau khi đổi mật khẩu.

**Mẹo nhớ:** "Đổi khóa ngay sau khi chủ nhà vào nhà."

---

## Câu 3 — Chứng minh mật khẩu được băm

**Em đã trả lời:** `register.php`, hàm `password_hash`, chuỗi bắt đầu `$2y$` → **Đúng (8/10)**.

**Bổ sung:**
- Vị trí chính xác: **`public/register.php` dòng 146**, `password_hash($password, PASSWORD_DEFAULT)`; `PASSWORD_DEFAULT` là **bcrypt**.
- Chuỗi trong DB dài **60 ký tự**, dạng `$2y$10$...`: `2y` = bcrypt, `10` = độ khó (cost), phần sau chứa **salt ngẫu nhiên** và giá trị băm. Hai người cùng mật khẩu vẫn ra hai chuỗi khác nhau.
- Có 3 chỗ băm: `register.php` dòng 146, `profile.php` dòng 299 (đổi mật khẩu), `reset-password.php` dòng 58 (đặt lại).
- So sánh khi đăng nhập: `password_verify()` (`login.php` dòng 46), không tự băm rồi so bằng `==`.

---

## Câu 4 — CSRF token

**Em đã trả lời:** "In mã ngẫu nhiên từ session, mở `csrf.php`" → **5/10**.

**Đáp án** (`public/includes/csrf.php`):
- `csrfToken()` (dòng 15–22): session chưa có mã thì tạo `bin2hex(random_bytes(32))` (64 ký tự hex) và lưu vào `$_SESSION['csrf_token']`.
- `csrfField()` (dòng 25–29): in ra **ô ẩn** `<input type="hidden" name="csrf_token" value="...">` trong form.
- `verifyCsrf()` (dòng 32–36): so **`$_POST['csrf_token']` (mã form gửi lên)** với **`$_SESSION['csrf_token']` (mã lưu ở server)** bằng **`hash_equals()`**. Không khớp thì `showCsrfError()` → 403.
- **Vì sao chặn được CSRF:** trang của kẻ xấu làm trình duyệt gửi kèm cookie phiên, nhưng **không đọc được** mã trong session và trong HTML của website, nên không gửi được mã đúng.
- **Vì sao dùng `hash_equals` mà không dùng `===`:** `===` dừng ở ký tự sai đầu tiên, nên thời gian xử lý để lộ thông tin (timing attack); `hash_equals` luôn so hết chuỗi.

---

## Câu 5 — Mã quên mật khẩu

**Em đã trả lời:** "`random_byte32`, lưu chính mã đó, 30 phút và 1 lần" → **6/10, ý 2 sai**.

**Đáp án:**
- Tạo mã: **`bin2hex(random_bytes(32))`** (`forgot-password.php` dòng 62), 64 ký tự hex. Không dùng `rand()`/`time()` vì đoán được.
- **DB lưu bản băm**, không lưu mã gốc: `hash('sha256', $token)` (dòng 68), cột `token_hash CHAR(64)`.
  - **Lý do:** mã có tác dụng như mật khẩu tạm. Lộ DB mà có mã gốc thì kẻ xấu đổi được mật khẩu người khác.
  - Khi bấm link, `reset-password.php` **băm lại** mã trong URL rồi tìm theo bản băm (dòng 26–28).
  - Dùng SHA-256 (không cần bcrypt) vì mã có 256 bit ngẫu nhiên, không dò được.
- **30 phút:** `const RESET_MINUTES = 30;` (dòng 25); kiểm tra hết hạn ở `reset-password.php` dòng 30.
- **1 lần:** chỉ nhận mã có `used_at IS NULL` (dòng 26); đổi mật khẩu xong thì dòng 59 ghi `used_at` cho mọi mã của user. `forgot-password.php` dòng 58 xóa mã cũ chưa dùng trước khi tạo mã mới.

---

## Câu 6 — Upload ảnh đại diện có mã độc

**Em đã trả lời:** "Kiểm tra bằng `finfo`, không nhìn đuôi file, mở `profile.php`, hàm `avatarprocess`" → **7/10**.

**Đáp án** (`public/profile.php`, hàm **`avatarProcess()` dòng 69**):
1. **Kiểm tra nội dung:** `finfo` (dòng 90) chỉ nhận `image/jpeg`, `image/png`, `image/webp`; `getimagesize()` (dòng 95) phải đọc được, tối đa 6000×6000. `virus.jpg` thật ra là chữ PHP nên bị từ chối.
2. **Ảnh thật có giấu mã vẫn an toàn** (ý em bỏ trống):
   - **Vẽ lại ảnh mới bằng GD:** `imagecopyresampled` (dòng 125) → `imagejpeg` (dòng 133). Chỉ điểm ảnh được chép sang, mã giấu và EXIF bị bỏ. File gốc không bao giờ được lưu.
   - **Server tự đặt tên:** `u<id>-<12 hex ngẫu nhiên>.jpg` (dòng 132).
   - **Thư mục không chạy PHP:** `public/uploads/avatars/.htaccess` (chỉ có tác dụng trên Apache).
   - `avatarPathOk()` (dòng 50) chặn đường dẫn lạ trong DB, ví dụ `../../config.php`.

**Mẹo nhớ:** Kiểm nội dung → vẽ lại ảnh → server tự đặt tên → thư mục không chạy PHP.

---

## Câu 7 — Thử SQL Injection ở bộ lọc bài tập

**Câu hỏi:** `/exercises.php?muscle_group=CHEST' OR '1'='1` thì sao?

**Em đã trả lời:** "Giá trị người dùng luôn đi qua `$params` → không bị SQL Injection" → **4/10**.

**Đáp án** (`public/exercises.php`):
- **Kết quả thật:** không lỗi, không lộ dữ liệu. Giá trị lạ bị bỏ, trang hiện **toàn bộ bài tập** như khi không lọc.
- **Lớp 1, whitelist:** `readChoice()` (dòng 47–55, gọi ở dòng 78) chỉ nhận khóa có trong `$muscleLabels`; giá trị lạ hoặc mảng thành `''`. Chuỗi tấn công **không tới được câu SQL**.
- **Lớp 2, prepared statement:** dòng 96 ghép chữ cố định `'muscle_group = ?'`; dòng 97 đưa giá trị vào `$params`; dòng 135 nối bằng `AND`; dòng 139–140 `prepare()` rồi `execute($params)`.
- **Vì sao ghép chuỗi vẫn an toàn:** chỉ ghép **đoạn SQL cố định do mình viết**; dữ liệu người dùng luôn đi riêng qua `execute()`.
- **Cần lớp 1 làm gì khi đã có lớp 2:** lớp 2 chặn injection, lớp 1 đảm bảo dữ liệu có nghĩa. Đây là phòng thủ nhiều lớp.

---

## Câu 8 — Lịch tập: mục tiêu có ảnh hưởng không (câu bẫy)

**Đáp án ý 1–3:**
1. `generateWorkoutPlan()` trong `public/includes/workout-plan.php` dòng 174, gọi qua `createWorkoutPlanForUser()` (`workout-plan-db.php`).
2. Người mới chỉ lấy bài **BEGINNER** (`WORKOUT_LEVEL_DIFFICULTIES`, dòng 159–163).
3. **2 set × 8 lần** (nâng cao 3 set), nghỉ 3–4 phút.

**Ý 4 — giảm mỡ và tăng cơ khác nhau thế nào:** **Giống hệt nhau.**
- `$goal` chỉ được kiểm tra hợp lệ ở dòng 179–181, sau đó **không dùng nữa**. Set theo trình độ, lặp luôn là 8, bài theo trình độ + kiểu chia.
- Đã chạy thử: `LOSE_FAT` và `GAIN_MUSCLE` ra cùng một kết quả.

**Câu trả lời mẫu:**
> "Hiện mục tiêu chỉ được kiểm tra hợp lệ, chưa dùng để thay đổi lịch. Đây là phần nhóm em chưa hoàn thành. Hướng sửa: giảm mỡ thì thêm bài cardio (id 37–39) như kế hoạch trong `docs/chia-lich-tap-40-bai.md`; tăng cơ thì thêm 1 set cho nhóm cơ chính hoặc lặp 8–12; dùng một bảng hằng kiểu `WORKOUT_GOAL_ADJUST`, áp dụng giống cách đang làm với `$level`."

**Không nên** trả lời "giảm mỡ tập nhẹ hơn, tăng cơ tập nặng hơn": thầy mở code là thấy sai.

---

## Câu 9 — Cơ chế xoay vòng bài tập

**Em đã trả lời:** "Xoay vòng bằng con trỏ" → **4/10**.

**Đáp án** (`public/includes/workout-plan.php`):
1. `buildRolePools()` (dòng 259) và `mergeRolePools()` (dòng 274) gom bài theo vai trò, lọc độ khó, sắp theo id. Người mới, nhóm ngực: **[1 Hít đất, 2 Ép ngực máy]**.
2. `$cursors = []` (dòng 193) tạo **một lần, ngoài vòng lặp ngày**, nên con trỏ giữ qua các buổi; mỗi vai trò có con trỏ riêng (dòng 205–207).
3. `takeExercises()` (dòng 290–303) lấy `$pool[$cursor % $n]` (dòng 295) rồi tăng con trỏ; `$usedToday` (dòng 297) chặn trùng bài trong cùng buổi.

| Buổi | Con trỏ | `% 2` | Bài |
|---|---|---|---|
| Thứ Hai | 0 | 0 | Hít đất |
| Thứ Tư | 1 | 1 | Ép ngực máy |
| Thứ Sáu | 2 | 0 | Hít đất (quay lại) |

- **Vì sao quay lại:** người mới chỉ có 2 bài ngực dễ, hết danh sách thì `%` đưa về đầu. Cùng lý do, buổi nào cũng có 22 Gập đùi sau (danh sách chỉ có 1 bài).
- **Vì sao không random:** cùng đầu vào luôn ra cùng lịch nên viết test được (`test-workout-plan.php`).

---

## Câu 10 — TDEE / Macro (chưa trả lời, tự luyện)

**Đề:** Nam, 30 tuổi, 170 cm, 80 kg, vận động nhẹ (LIGHT), giảm mỡ, người mới. Tính BMR, TDEE, calo mục tiêu, protein, ghi rõ công thức.

Tự làm trước, rồi mới xem đáp án.

<details>
<summary>Đáp án (đã chạy bằng hàm thật trong <code>includes/tdee.php</code>)</summary>

- **BMR** = 10 × 80 + 6,25 × 170 − 5 × 30 + 5 = 800 + 1062,5 − 150 + 5 = **1717,5 kcal** (`calculateBMR`)
- **TDEE** = 1717,5 × 1,375 = **2361,6 kcal** (`calculateTDEE`, hệ số LIGHT = 1,375)
- **Calo mục tiêu** = 2361,6 × 0,80 = **1889,3 kcal** (`calculateTargets`, giảm mỡ = 0,80)
- **Protein** = 1,6 × 80 = **128 g** (người mới 1,6 g/kg)
- Thêm: fat = 0,8 × 80 = **64 g**; carb = (1889,3 − 128 × 4 − 64 × 9) / 4 = (1889,3 − 512 − 576) / 4 = **200,3 g**

</details>

---

## Ôn tiếp lần sau

Chưa ôn: TDEE/Macro (câu 10), lịch ăn gợi ý, tiến trình và biểu đồ, bài viết, chatbot Gemini, trang chủ, khu admin, 15 câu hỏi chung (xem `docs/on-tap.md` mục 6–13).

Việc cần làm trước buổi sau: luyện trả lời mỗi câu theo mẫu **"cơ chế là gì → file nào, dòng nào → vì sao an toàn/đúng → hạn chế"**.
