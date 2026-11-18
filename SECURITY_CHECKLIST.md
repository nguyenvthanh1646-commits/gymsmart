# SECURITY_CHECKLIST — GymSmart (việc A-5)

Mục tiêu: trước khi demo, cả nhóm tìm và sửa các lỗi bảo mật cơ bản nhất. Làm theo 4 bước: **quét tự động → kiểm tra bằng tay trên trình duyệt → đọc chéo code → ghi kết quả**.

Quy tắc: người review chỉ **ghi lỗi** (comment trong PR hoặc issue) và nói rõ file, dòng, cách tái hiện. Người viết code tự sửa phần của mình. Không sửa thẳng nhánh của bạn khác.

---

## Bước 1. Quét tự động (2 phút)

Ở thư mục gốc project:
```
php tools/audit.php public
```
Công cụ tìm các mẫu đáng nghi: SQL ghép chuỗi, in biến không `htmlspecialchars()`, trang thiếu `requireLogin()`/`requireAdmin()`, form POST thiếu CSRF, `md5()` cho mật khẩu, `user_id` lấy từ form, code gỡ lỗi còn sót, `config.php` chưa nằm trong `.gitignore`.

Cách đọc kết quả:
- Mỗi dòng báo ra là **chỗ đáng nghi, chưa chắc là lỗi**. Mở đúng file và dòng, đọc, rồi kết luận.
- Nếu đọc xong thấy an toàn (ví dụ giá trị là hằng số viết sẵn trong code), thêm chú thích `audit:ok lý do` vào dòng đó để lần quét sau bỏ qua.
- Báo "không phát hiện gì" **không có nghĩa là an toàn**. Công cụ chỉ so khớp chữ, không hiểu logic. Vẫn phải làm bước 2 và 3.

---

## Bước 2. Kiểm tra bằng tay trên trình duyệt

### 2.1 SQL Injection (mọi ô nhập và mọi tham số URL)

Thử lần lượt các chuỗi sau vào: ô đăng nhập (email), ô tìm kiếm thư viện, `?id=` của trang chi tiết, các ô trong form admin.

| Chuỗi thử | Kết quả đúng |
|---|---|
| `' OR '1'='1` | Không đăng nhập được, không lộ thêm dữ liệu, không lỗi SQL |
| `' OR 1=1 -- ` | Giống trên |
| `1 OR 1=1` (vào `?id=`) | Báo id không hợp lệ |
| `1; DROP TABLE users` (vào `?id=` hoặc ô nhập) | Không có gì xảy ra, bảng vẫn còn |
| `'` (một dấu nháy đơn) | Không hiện thông báo lỗi SQL ra màn hình |

Dấu hiệu **sai**: thấy chữ `SQLSTATE`, `You have an error in your SQL syntax`, hoặc đường dẫn file trên máy chủ.

### 2.2 XSS (chèn mã vào trang)

Nhập chuỗi này vào: họ tên (đăng ký, hồ sơ), tên bài tập và các ô mô tả (admin):
```
<script>alert('xss')</script>
```
và
```
<img src=x onerror=alert(1)>
```
Rồi xem **mọi trang hiển thị lại dữ liệu đó**: trang hồ sơ, lời chào ở trang chủ, danh sách bài tập, trang chi tiết, danh sách người dùng trong admin, thanh menu. Kết quả đúng: chữ hiện nguyên văn, **không** hiện hộp thoại. Nếu hiện hộp thoại alert thì chỗ đó thiếu `htmlspecialchars()`.

Nhớ xóa dữ liệu thử sau khi kiểm tra xong.

### 2.3 Phân quyền (quan trọng nhất)

Mở từng URL trong 3 trạng thái: **chưa đăng nhập**, **USER**, **ADMIN**.

| URL | Chưa đăng nhập | USER | ADMIN |
|---|---|---|---|
| `/index.php` | Trang giới thiệu | Trang chủ | Chuyển sang `/admin/dashboard.php` |
| `/login.php`, `/register.php` | Xem được | Chuyển về trang chủ | Chuyển về trang chủ |
| `/profile.php` | Chuyển về đăng nhập | Xem được | Xem được |
| `/exercises.php` | Xem được (chỉ đọc, có dải mời đăng ký; từ A-45) | Xem được | Xem được |
| `/exercise-detail.php?id=1` | Xem được (chỉ đọc; từ A-45) | Xem được | Xem được |
| `/articles.php`, `/article.php?slug=` | Xem được (không ghi "đã đọc" cho khách; từ A-45) | Xem được | Xem được |
| `/workout-plan.php`, `/nutrition.php`, `/progress.php`, `/report.php` | Chuyển về đăng nhập | Xem được | Xem được |
| `/admin/dashboard.php` | Chuyển về đăng nhập | **Bị chặn** | Xem được |
| `/admin/exercises.php` | Chuyển về đăng nhập | **Bị chặn** | Xem được |
| `/admin/exercise-form.php` | Chuyển về đăng nhập | **Bị chặn** | Xem được |
| `/admin/users.php` | Chuyển về đăng nhập | **Bị chặn** | Xem được |
| `/admin/user-detail.php?id=2` | Chuyển về đăng nhập | **Bị chặn** | Xem được |
| `/admin/export.php?type=users` (và `exercises`) | Chuyển về đăng nhập | **Bị chặn** | Tải được CSV, không có cột mật khẩu |
| Các `/api/*.php` của B, C, D (khi có) | 401 hoặc chuyển hướng | Theo thiết kế | Theo thiết kế |

Thử riêng với quản lý người dùng (đăng nhập ADMIN): tự đổi vai trò hoặc tự xóa chính mình phải bị từ chối; không có nút xóa ở dòng ADMIN; gửi form thiếu `csrf_token` phải ra lỗi 403.

Thêm 3 phép thử:
- **Đổi id của người khác:** với mọi trang có dữ liệu cá nhân (lịch tập, tiến trình, số đo), thử đổi `?id=` hoặc `?user_id=` sang số của người khác. Kết quả đúng: không xem được. Dữ liệu cá nhân phải luôn lấy theo `$_SESSION['user_id']`.
- **Sau khi đăng xuất:** bấm nút Back của trình duyệt, trang cá nhân không được hiện dữ liệu cũ khi tải lại.
- **Đăng nhập xong rồi mở `/admin/...` trong cửa sổ ẩn danh:** phải bị chặn.

### 2.4 CSRF (form thay đổi dữ liệu)

Với mỗi form POST (đăng nhập, đăng ký, hồ sơ, thêm/sửa/xóa bài tập, và các form sau này của B, C):
1. Mở DevTools (F12), tab Elements, xóa ô ẩn `csrf_token`.
2. Bấm gửi form.
3. Kết quả đúng: báo "sai mã bảo vệ" (mã 403), dữ liệu không bị thay đổi.

Form nào gửi được mà không cần mã thì thiếu `verifyCsrf()`.

### 2.5 Mật khẩu và phiên đăng nhập

- Đăng ký thử mật khẩu 3 ký tự: bị từ chối (tối thiểu 8). Thử 100 ký tự: bị từ chối (tối đa 72).
- Trong phpMyAdmin, bảng `users`, cột `password_hash` phải là chuỗi bắt đầu bằng `$2y$`, **không** phải mật khẩu thật.
- Thử đăng nhập sai 5 lần liên tiếp cùng một email: lần thứ 6 phải bị chặn, kể cả nhập đúng mật khẩu. Email không tồn tại cũng bị chặn y như vậy (không để lộ email nào có thật). Đăng nhập đúng bằng email khác vẫn được.
- Đăng nhập sai email và sai mật khẩu phải cho **cùng một** thông báo (để không dò ra email nào đã đăng ký).
- Sau khi đăng nhập, mã phiên (cookie `PHPSESSID`) phải **đổi** so với trước đăng nhập (đã có `session_regenerate_id`).

### 2.6 Lỗi và rò rỉ thông tin

- Mở một trang với tham số cố tình hỏng (`?id=abc`, `?id=99999999999999999999`): không hiện cảnh báo PHP (Warning, Notice, Fatal error) và không lộ đường dẫn `C:\xampp\...`.
- Mở `/config.php`, `/includes/db.php`, `/sql/schema.sql` bằng URL: không được tải về hoặc hiện mã nguồn. (Khi chạy bằng `php -S ... -t public`, thư mục `sql/` nằm ngoài `public/` nên an toàn. Nếu có file nhạy cảm trong `public/`, chuyển ra ngoài.)

---

## Bước 3. Đọc chéo code

Phân công đọc (mỗi người đọc phần của bạn khác, chưa từng đọc phần đó):

| Người đọc | Đọc code của | Tập trung vào |
|---|---|---|
| A | D | trang chi tiết bài tập, `seed.sql` |
| B | A | đăng ký, đăng nhập, hồ sơ, khu admin |
| C | B | thư viện bài tập, lịch tập |
| D | C | `tdee.php`, API tính TDEE, số đo |
| E | A và D | chỉ xem **phần HTML/JS đầu ra**: chỗ nào in dữ liệu ra trang, JS có `innerHTML` không |

Mẫu đọc, với mỗi file PHP, trả lời 7 câu:
1. Đầu file có `requireLogin()` (hoặc `requireAdmin()` với trang admin) chưa?
2. Mọi câu SQL có dùng `prepare()` + `?` chưa? Có chỗ nào ghép biến vào chuỗi SQL không?
3. Mọi dữ liệu in ra HTML có qua `htmlspecialchars()` chưa? (Số nguyên thì ép `(int)`.)
4. Mọi giá trị từ `$_GET`/`$_POST` có được kiểm tra (kiểu, độ dài, danh sách cho phép) trước khi dùng chưa?
5. Dữ liệu cá nhân có lấy theo `$_SESSION['user_id']` chưa, hay đang tin id từ URL/form?
6. Form POST có `csrfField()` và `verifyCsrf()` chưa?
7. Lỗi xảy ra thì người dùng thấy thông báo gọn, hay thấy lỗi PHP/SQL?

Với JavaScript (đặc biệt trang tiến trình, chatbot):
- Tránh `innerHTML` với dữ liệu từ server hoặc người dùng. Dùng `textContent`.
- API trả JSON: server vẫn phải kiểm tra quyền, không dựa vào việc nút đã bị ẩn.

---

## Bước 4. Việc cấu hình nên làm một lần (A thực hiện)

**a. Cookie phiên an toàn.** Trong `includes/auth.php`, **trước** `session_start()`:
```php
session_set_cookie_params([
    'httponly' => true,    // JavaScript không đọc được cookie phiên
    'samesite' => 'Lax',   // trình duyệt không gửi cookie trong yêu cầu chéo trang nguy hiểm
    // 'secure' => true,   // bật khi chạy qua HTTPS (khi đưa website lên mạng)
]);
ini_set('session.use_strict_mode', '1'); // không chấp nhận mã phiên do người khác tự đặt
session_start();
```

**b. Các header bảo vệ.** Ở đầu `includes/header.php`, trước khi in bất kỳ HTML nào:
```php
header('X-Content-Type-Options: nosniff');          // không đoán kiểu file
header('X-Frame-Options: SAMEORIGIN');               // không cho trang khác nhúng website trong iframe (chống clickjacking)
header('Referrer-Policy: strict-origin-when-cross-origin');
```

**c. Tắt hiển thị lỗi khi chạy thật.** Khi demo hoặc đưa lên mạng, trong `config.php`: `ini_set('display_errors', '0');`. Khi đang code ở máy mình thì để bật cho dễ sửa.

**d. `.gitignore` phải có** `config.php`. Nếu đã lỡ commit `config.php` có mật khẩu thật thì **đổi mật khẩu DB ngay**; xóa file khỏi lịch sử Git là việc riêng, hỏi A.

---

## Hạn chế đã biết (ghi vào báo cáo, không phải lỗi bị bỏ sót)

- **Chặn dò mật khẩu đã có ở mức cơ bản** (`includes/login-throttle.php`: 5 lần sai cùng email hoặc 20 lần sai cùng IP trong 15 phút thì khóa 15 phút). Chưa có: thông báo cho chủ tài khoản, CAPTCHA, khóa theo thiết bị. Nếu chưa import bảng `login_attempts` thì việc chặn tự tắt (đăng nhập vẫn chạy).
- **Đăng xuất bằng đường dẫn GET** (`/api/logout.php`). Kẻ xấu có thể ép đăng xuất người dùng, hậu quả nhỏ. Có thể đổi thành form POST sau.
- **Quyền admin đọc lại từ DB** ở mỗi trang `/admin` (`refreshSessionRole()` trong `includes/auth.php`) và ở chatbot chế độ admin, nên hạ quyền/xóa tài khoản có hiệu lực ngay. Trang người dùng thường vẫn chỉ kiểm tra `user_id` trong session.
- **Đăng ký báo "Email này đã được sử dụng"** cho phép dò xem một email đã có tài khoản chưa. Chấp nhận được ở quy mô đồ án.
- Ảnh bài tập lưu trong `public/img/exercises/` (cột `image_url` chỉ chứa tên file, `exerciseImageSrc()` lọc tên lạ). Video là iframe YouTube: nếu kênh gốc gỡ video thì khung video trống.

---

## Bảng ghi kết quả (điền khi làm)

| # | Hạng mục | Người kiểm | Kết quả | Ghi chú / lỗi tìm được |
|---|---|---|---|---|
| 1 | Quét `tools/audit.php` | | | |
| 2.1 | SQL Injection | | | |
| 2.2 | XSS | | | |
| 2.3 | Phân quyền | | | |
| 2.4 | CSRF | | | |
| 2.5 | Mật khẩu, phiên | | | |
| 2.6 | Lỗi và rò rỉ thông tin | | | |
| 3 | Đọc chéo code (mỗi người một phần) | | | |
| 4 | Cấu hình (cookie phiên, header, `.gitignore`) | | | |

## Bổ sung: Quên mật khẩu (A-10)

| Thử | Kết quả đúng |
|---|---|
| Nhập email không tồn tại ở `/forgot-password.php` | Hiện cùng thông báo như email có thật (không lộ email nào đã đăng ký) |
| Mở `/reset-password.php?token=abc` hoặc `?token=1' OR '1'='1` | "Link không còn hiệu lực", không lỗi PHP |
| Dùng lại link đã đổi mật khẩu thành công | "Link không còn hiệu lực" |
| Mở link sau 30 phút | "Link không còn hiệu lực" |
| Xem bảng `password_resets` trong phpMyAdmin | Cột `token_hash` là 64 ký tự băm, không phải mã trong link |
| Gửi form đặt lại với mã bảo vệ sai | Trang "Phiên làm việc đã hết hạn" (403) |

Giới hạn đã biết: trên localhost link hiện ngay trên màn hình để demo; khi triển khai thật phải cấu hình gửi email và đặt `APP_URL`.

## Bổ sung: Bài viết (A-12)

| Thử | Kết quả đúng |
|---|---|
| `/articles.php` và `/article.php?slug=...` khi chưa đăng nhập | Chuyển về đăng nhập |
| `/article.php?slug=../../includes/db`, `?slug=abc`, `?slug[]=x`, `?slug=<script>` | Trang 404 đẹp, không lỗi PHP, không đọc được file lạ |
| `/articles.php?cat=zzz` hoặc `?cat[]=x` | Hiện tất cả bài, không lỗi |
| Thêm file bài mới có chữ `<b>` hoặc `<script>` trong nội dung | Hiện nguyên chữ, không chạy mã (mọi chữ đều qua htmlspecialchars) |

## Bổ sung: Chatbot (A-13)

| Thử | Kết quả đúng |
|---|---|
| Gọi `/api/chatbot.php` bằng GET, hoặc POST khi chưa đăng nhập | 405 / 401 dạng JSON, không lỗi PHP |
| POST không kèm header `X-CSRF-Token` (hoặc sai mã) | 403 "Phiên không hợp lệ" |
| Tài khoản USER gửi `"mode":"admin"` hoặc `"task":"exercise_draft"` bằng DevTools/curl | 403 "chỉ dành cho quản trị viên" (server kiểm tra vai trò, không tin client) |
| Gửi tin nhắn > 500 ký tự, rỗng, hoặc `history` có phần tử lạ | 400 / bỏ qua phần lạ, không lỗi |
| Gửi liên tiếp dưới 3 giây (user) | 429 "gửi quá nhanh" |
| Hỏi chatbot admin "cho tôi email của người dùng X" | Trả lời không có thông tin cá nhân (chỉ có số liệu tổng hợp) |
| Tắt "Dùng hồ sơ của tôi", hỏi chatbot | Prompt gửi đi không chứa tuổi/chiều cao/cân nặng |
| AI trả về `<script>alert(1)</script>` (thử bằng cách sửa tạm văn bản trả về) | Hiện nguyên chữ, không chạy mã (JS dùng `textContent`) |
| Đổi `AI_API_KEY` thành sai | User thường thấy câu chung chung; admin/localhost thấy thêm "Chi tiết kỹ thuật"; khóa không xuất hiện trong chi tiết |
| `config.php` có trong `git status` | KHÔNG được xuất hiện (đã nằm trong `.gitignore`) |

Giới hạn đã biết: nếu bật "Dùng hồ sơ của tôi", mục tiêu/trình độ/chiều cao/cân nặng được gửi tới Google Gemini (tên và email thì không). Lịch sử chat chỉ lưu trong `sessionStorage` của trình duyệt, không lưu DB.

## Bổ sung: Tiến trình / số đo cơ thể (C-3)

| Thử | Kết quả đúng |
|---|---|
| Mở `/progress.php` khi chưa đăng nhập | Chuyển về đăng nhập |
| User mới chưa có số đo | Hiện "Chưa có số đo nào", không lỗi PHP |
| Nhập cân nặng `abc`, `-5`, `1e5`, `999`, bỏ trống | Báo lỗi tiếng Việt, giữ nguyên chữ đã gõ, không ghi DB |
| Nhập ngày tương lai, `2026-02-31` hoặc trước năm 2000 | Báo lỗi, không ghi DB |
| Gửi `weight_kg[]=1` bằng curl/DevTools | Coi như trống, báo "Vui lòng nhập cân nặng", không lỗi PHP |
| Nhập lại cùng một ngày | Cập nhật bản ghi cũ, không tạo dòng trùng |
| Sửa bản ghi sang ngày đã có bản ghi khác | Báo "Bạn đã có số đo ngày ..." |
| Gửi form xóa/sửa với `id` của người khác (DevTools) | Không xóa/sửa được; xóa báo "Không tìm thấy", sửa quay về trang kèm báo lỗi |
| Mở `/progress.php?edit=<id người khác>` hoặc `?edit=abc` | Mở form Thêm mới trống, không lộ dữ liệu người khác |
| Gửi form không kèm mã CSRF | Trang "Phiên làm việc đã hết hạn" (403) |

### Biểu đồ và API Tiến trình (C-4)
| Cách thử | Kết quả mong đợi |
|---|---|
| Mở `/api/body-metrics.php` và `/api/nutrition-history.php` khi chưa đăng nhập | JSON `{"success":false}` kèm mã 401, không chuyển trang |
| Gửi POST tới 2 API (curl) | 405, header `Allow: GET` |
| `?from=abc` hoặc `?from=2026-02-01&to=2026-01-01` | 400 kèm thông báo lỗi tiếng Việt |
| Đăng nhập user khác rồi mở API | Chỉ thấy số đo của chính user đó |
| Tài khoản chưa có số đo hoặc chưa tính TDEE | Biểu đồ hiện thông báo "Chưa có dữ liệu", không lỗi JS (F12 > Console) |
| Đổi 30 ngày / 90 ngày / Tất cả | Biểu đồ vẽ lại theo khoảng chọn, không gọi lại API |
| Chặn tải `js/lib/chart.umd.min.js` (DevTools) | Hiện dòng báo lỗi, bảng lịch sử vẫn dùng được |

### Hồ sơ và ảnh đại diện (A-15)
| Cách thử | Kết quả mong đợi |
|---|---|
| Chọn file .txt/.php đổi tên thành .jpg rồi gửi (kể cả bỏ qua JS bằng DevTools) | Server báo "Chỉ nhận ảnh JPG, PNG hoặc WebP", không lưu gì |
| Chọn ảnh lớn hơn 2 MB | Báo lỗi (JS chặn trước, server vẫn kiểm tra lại) |
| Gửi form ảnh không kèm mã CSRF | Trang "Phiên làm việc đã hết hạn" (403) |
| Gửi form ảnh không chọn file | Báo "Bạn chưa chọn ảnh" |
| Tải ảnh mới | File cũ bị xóa, thư mục `uploads/avatars/` chỉ còn 1 ảnh của user |
| Mở file ảnh trong `uploads/avatars/` bằng cách đổi đuôi .php | Không chạy được mã (xem `.htaccess` trong thư mục) |
| Đổi `avatar` trong DB thành `../../config.php` rồi mở Hồ sơ | Bỏ qua đường dẫn lạ, hiện chữ cái đầu |
| Chưa import `users_avatar.sql` | Trang vẫn mở được, nút đổi ảnh ẩn và có nhắc import |

### Đăng ký 3 bước (A-16)
| Cách thử | Kết quả mong đợi |
|---|---|
| Bấm Tiếp tục khi bỏ trống / email sai / nhập lại mật khẩu khác | Ở lại bước đó, báo lỗi ngay dưới từng ô |
| Tắt JS hoặc dùng curl gửi thẳng form với tuổi 5, chiều cao 50, `goal=HACK` | Server từ chối, hiện lại form ở đúng bước có lỗi, giữ dữ liệu đã nhập |
| Gửi form không kèm mã CSRF | Trang "Phiên làm việc đã hết hạn" (403) |
| Đăng ký email đã tồn tại | Báo "Email này đã được sử dụng" |
| Nhập `<script>alert(1)</script>` vào họ tên rồi để lỗi | Hiện nguyên chữ, không chạy script |
| Gửi `full_name[]=x` (mảng) | Coi như trống, báo lỗi, không lỗi PHP |
| Xem HTML trang sau khi lỗi | Không có mật khẩu đã nhập trong HTML |
| Đăng ký xong, xem bảng users | `password_hash` bắt đầu bằng `$2y$`, có đủ age, height, weight, gender, activity, experience, goal |

## Bổ sung: Phân hệ Huấn luyện viên (A-30 → A-35)

Nguyên tắc: HLV chỉ thấy học viên **đang ACTIVE** với mình, quyết định bằng câu SQL `coachActiveRelationId()` (quan hệ ACTIVE **và** vai trò HLV trong DB vẫn là COACH), không tin id trên URL. Mọi trang `/coach/` gọi `requireCoach()` (đọc lại vai trò trong DB); trang về một học viên gọi thêm `requireCoachClient()`. `tools/audit.php` báo lỗi nếu trang trong `/coach/` thiếu `requireCoach()`, hoặc đọc `?id=` mà không kiểm tra quan hệ.

Dữ liệu thử: `php tools/seed-coach-demo.php` (mật khẩu chung `Test@12345`; coach1 có user1, coach2 có yêu cầu chờ của user2, user3 chưa có HLV).

### Phân quyền theo URL
| URL | Khách | USER | COACH | ADMIN |
|---|---|---|---|---|
| `/coaches.php`, `/my-coach.php`, `/coach-apply.php` | Về đăng nhập | Xem được | Xem được (không gửi yêu cầu, không nộp đơn) | Xem được (không gửi yêu cầu) |
| `/coach/dashboard.php` (và mọi trang `/coach/`) | Về đăng nhập | **403** | Xem được | **403** |
| `/coach/client.php?id=` học viên của HLV khác, học viên đã kết thúc, `id=abc`, `id=0` | — | 403 | **404** | 403 |
| `/admin/*` | Về đăng nhập | 403 | **403** | Xem được |
| `/api/body-metrics.php?client_id=` không phải học viên ACTIVE | 401 | 404 | 404 | 404 |
| `/api/coach-messages.php?with=` người không có quan hệ ACTIVE | 401 | 404 | 404 | 404 |

### Kịch bản phải đúng
| Cách thử | Kết quả mong đợi |
|---|---|
| USER gửi yêu cầu tới HLV thứ hai khi đang chờ / đang tập với HLV thứ nhất (kể cả gửi bằng curl) | Bị từ chối "Mỗi học viên chỉ đăng ký 1 HLV"; 2 yêu cầu gửi cùng lúc thì DB chặn bằng UNIQUE `open_client_id` |
| Sửa `relation_id` trong form Hủy / Ngừng tập thành quan hệ của người khác | "Không tìm thấy yêu cầu này", không đổi gì |
| HLV sửa `relation_id` khi Duyệt thành yêu cầu gửi cho HLV khác | Bị từ chối |
| Học viên ngừng tập rồi HLV tải lại `/coach/client.php?id=` | 404 ngay lập tức; API biểu đồ và tin nhắn cũng 404 |
| ADMIN thu hồi HLV đang có học viên | Quan hệ ACTIVE → ENDED, PENDING → REJECTED, vai trò về USER; session HLV cũ mở `/coach/` thì 403 ở lần tải sau |
| ADMIN đổi COACH thẳng sang ADMIN, hoặc gửi `role=COACH` ở form đổi vai trò | Bị từ chối (COACH chỉ có qua duyệt đơn; muốn lên ADMIN phải thu hồi HLV trước) |
| Học viên đang có HLV gọi thẳng `POST /api/workout-plan-edit.php` hoặc `workout-plan-generate.php` | 403 "Lịch tập đang do HLV ... quản lý" |
| Học viên đang có HLV gửi `action=goal` / `diet` / `rhythm` tới `nutrition.php` | Bị chặn, mục tiêu không đổi; số bữa và thực phẩm loại trừ vẫn đổi được |
| HLV đặt macro protein 4 g/kg, chất béo 10 g, tổng 900 kcal (gửi bằng curl) | Máy chủ từ chối từng mục, không lưu |
| Tin nhắn 1001 ký tự / rỗng / gửi 2 tin trong 1 giây | 422 / 422 / 429 |
| Nhập `<script>alert(1)</script>` vào tin nhắn, nhận xét, lời nhắn, mẫu đơn HLV, lý do từ chối | Hiện nguyên chữ ở mọi nơi (tin nhắn gán bằng `textContent`) |
| Xem HTML trang HLV, trang danh sách HLV | Không có email học viên / email HLV (chỉ ADMIN thấy email ở trang duyệt đơn) |
| Gửi bất kỳ form HLV nào không kèm `csrf_token` | 403 "Phiên làm việc đã hết hạn" |

### Mở rộng (A-36) và kiểm thử tự động
Chạy `php tools/test-coach-http.php`: 118 bước, gồm toàn bộ bảng trên và các mục dưới. Công cụ tự tạo database `gymsmart_test_hlv` và máy chủ thử, xong thì xóa; không đụng database `gymsmart`.

| Cách thử | Kết quả mong đợi |
|---|---|
| HLV gửi `draft_remove` với `row_id` của lịch ĐANG DÙNG (không phải bản nháp) | "Không tìm thấy bài này trong bản nháp", không xóa |
| Học viên mở lịch tập khi HLV đang có bản nháp | Không thấy bản nháp, lịch đang dùng không đổi |
| HLV khác gửi `feedback_delete` qua trang học viên không phải của mình | 404, nhận xét còn nguyên |
| Học viên mở `my-coach.php?history=` của người khác / id không tồn tại | 404 |
| USER gọi chatbot `mode=coach`; HLV gửi `client_id` của học viên HLV khác | 403, không gọi AI |
| Tải file PHP đổi đuôi `.png` làm chứng chỉ; file > 3 MB | Bị từ chối (kiểm tra nội dung, không tin đuôi) |
| Mở `/storage/certificates/<tên>` bằng URL; USER mở `admin/certificate.php` | 404 (ngoài `public/`); 403 |
| Đổi `certificate_file` trong DB thành `../../config.php` rồi admin mở | 404, không đọc được file ngoài thư mục |

## Bổ sung: Nâng cấp 11/2026 (A-39)

| Thử | Kết quả đúng |
|---|---|
| Xem header của một trang bất kỳ (F12 > Network) | Có `Content-Security-Policy` chỉ cho script/CSS/fetch từ chính website, khung nhúng chỉ `youtube-nocookie.com` |
| Mở trang chi tiết bài tập có video, F12 > Console | Video chạy, không có dòng "Refused to ..." (CSP chặn nhầm) |
| `admin/users.php?q=%25` hoặc `?q=_` | Tìm đúng chữ `%`/`_`, không thành mẫu khớp mọi dòng (đã thoát ký tự LIKE) |
| `admin/users.php?role=ABC&page=-5` | Bỏ qua vai trò lạ, về trang 1, không lỗi |
| Ghi nhật ký lúc 0-6 giờ sáng | Ngày nhật ký, "Hôm nay" ở trang chủ và tỉ lệ tập của HLV cùng một ngày (PHP và MySQL cùng múi giờ) |
| `php tools/install.php` khi DB thật đã có bảng | Dừng, không ghi đè; `--fresh` trên DB thật bắt buộc thêm `--yes` |

### Chế độ Đang tập (A-41)

| Thử | Kết quả đúng |
|---|---|
| Gọi `api/log-session.php` khi chưa đăng nhập / thiếu `csrf_token` | 401 / 403 |
| Gửi `row_id` thuộc lịch của người khác | 404, không ghi dòng nào (transaction) |
| Tạ 9999, lặp 0, trùng `row_id`, `rows=abc` | 422, không ghi |
| Gửi số đã gộp sẵn từ trình duyệt | Máy chủ bỏ qua, tự gộp lại từ từng set |
| Mở `workout-session.php?day=abc` | Về ngày hôm nay, không lỗi |

### Chatbot dùng dữ liệu thật và lịch sử (A-42)

| Thử | Kết quả đúng |
|---|---|
| Tắt "Dùng hồ sơ của tôi", hỏi "hôm nay tôi tập gì" | AI trả lời chung và nhắc bật công tắc, không có số liệu riêng |
| Bật công tắc, hỏi lại | Có lịch, mức tạ gợi ý thật; không có tên, email, ghi chú trong prompt (`test-chat.php`) |
| Khách gọi `GET api/chatbot-history.php` | 401 |
| `POST api/chatbot-history.php {"action":"clear"}` thiếu `X-CSRF-Token` | 403, không xóa |
| Tài khoản A đọc lịch sử | Chỉ thấy hội thoại của A (user_id lấy từ session) |
| Gửi hơn 30 câu/giờ, đăng xuất rồi đăng nhập lại gửi tiếp | Vẫn 429 (đếm theo tài khoản trong DB) |
| Trợ lý quản trị, trợ lý HLV | Không lưu lịch sử vào DB |

### Báo cáo và tải dữ liệu cá nhân (A-43)

| Thử | Kết quả đúng |
|---|---|
| Khách mở `report.php` hoặc `export-my-data.php?type=logs` | Chuyển sang đăng nhập |
| `export-my-data.php?type=users` | 400, không xuất gì |
| Thêm `&user_id=` khác vào URL tải CSV | Không có tác dụng: chỉ xuất dữ liệu của tài khoản đang đăng nhập |
| Ghi chú nhật ký bắt đầu bằng `=HYPERLINK(...)` rồi tải CSV, mở bằng Excel | Ô hiện chữ, không chạy công thức (có dấu ' phía trước) |
| `report.php?month=2099-01`, `?month=abc` | Về tháng hiện tại, không lỗi |

### Thông báo (A-44)

| Thử | Kết quả đúng |
|---|---|
| Khách gọi `GET api/notifications.php` | 401 |
| `POST api/notifications.php action=read&id=<id của người khác>` | 404, không đổi gì |
| `POST` thiếu `csrf_token` | 403 |
| HLV nhắn tin chứa `<script>alert(1)</script>` | Học viên thấy đúng chữ đó trong chuông, không chạy script (textContent) |
| Sửa cột `link` trong DB thành `https://web-la.com` hoặc `javascript:...` | Chuông và trang Thông báo bỏ link đó (`notifSafeLink()`), không chuyển ra ngoài |
| DB cũ chưa import `sql/notifications.sql` | Mọi trang và thao tác HLV vẫn chạy, chuông không có số |

### Tổng kết tuần bằng AI (A-46)

| Thử | Kết quả đúng |
|---|---|
| POST `report.php` thiếu `csrf_token` | Trang báo lỗi mã bảo vệ, không gọi AI |
| Bấm "Tạo tổng kết" lần 2 trong cùng ngày (sửa HTML để hiện lại nút) | Báo "tối đa 1 lần mỗi ngày", không gọi AI |
| Tuần trước không có dữ liệu | Báo chưa có gì để tổng kết, không gọi AI |
| Xem `stats_json` trong DB | Chỉ có số liệu tổng hợp, không có tên, email, ghi chú |
| AI trả lời có thẻ HTML | Hiện đúng chữ (htmlspecialchars), không chạy mã |
