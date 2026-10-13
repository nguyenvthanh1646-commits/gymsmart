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
| `/exercises.php` | Chuyển về đăng nhập | Xem được | Xem được |
| `/exercise-detail.php?id=1` | Chuyển về đăng nhập | Xem được | Xem được |
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
- **Quyền admin lưu trong session**: nếu bị hạ quyền trong DB thì phiên đang mở vẫn còn quyền cũ cho tới khi đăng xuất.
- **Đăng ký báo "Email này đã được sử dụng"** cho phép dò xem một email đã có tài khoản chưa. Chấp nhận được ở quy mô đồ án.
- Ảnh bài tập là link ngoài: nếu trang gốc xóa ảnh thì thẻ hiện ảnh vỡ. Không phải lỗi bảo mật nhưng ảnh hưởng demo.

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
