# Admin: bài viết, nhật ký hoạt động, thống kê

## Cài đặt
1. phpMyAdmin → database `gymsmart` → Import `sql/admin_features.sql` (3 bảng: `admin_audit_log`, `articles`, `article_views`).
2. Chép các file trong gói vào `public/` (cùng cấu trúc thư mục). Không đụng `config.php`.
3. Báo D thêm 3 bảng vào `sql/schema.sql` và `ERD.md`.

## File mới
| File | Việc |
|---|---|
| `public/admin/articles.php` | Danh sách bài (bài admin soạn + bài file), đăng / gỡ về nháp / xóa, lượt xem |
| `public/admin/article-form.php` | Soạn và sửa bài, có cú pháp soạn ngay trong form |
| `public/admin/audit-log.php` | Nhật ký hoạt động, lọc theo hành động, từ khóa, ngày, có phân trang |
| `public/admin/stats.php` | Thống kê: đăng ký mới và buổi tập theo tuần, top bài tập, mục tiêu, top bài viết |
| `public/includes/audit-log.php` | Hàm `auditLog()` và danh sách loại hành động |
| `public/includes/article-markup.php` | Đổi chữ soạn bài thành các khối cho `renderArticleBlocks()` |
| `public/includes/admin-style-extra.php` | CSS bổ sung (class `adm-`) |
| `sql/admin_features.sql` | 3 bảng mới |

## File cũ đã sửa (nếu bản của bạn khác, chỉ cần thêm đúng các dòng này)
- `includes/admin-style.php`: menu admin gọn hơn (padding 10px, ẩn nút "Xem trang người dùng" dưới 1240px) để chữ Đăng xuất không bị cắt.
- `includes/admin-nav.php`: thêm 3 mục `articles`, `stats`, `audit` vào `$admNavItems`.
- `includes/admin-users.php`: `require_once audit-log.php`; sau `UPDATE role` gọi `auditLog($pdo,'user.set_role',...)`; sau `DELETE` gọi `auditLog($pdo,'user.delete',...)`.
- `admin/exercises.php`: `require_once audit-log.php`; ghi log khi nhân bản và khi xóa (lấy tên bài trước khi xóa).
- `admin/exercise-form.php`: `require_once audit-log.php`; ghi log sau INSERT (`exercise.create`) và UPDATE (`exercise.update`).
- `includes/articles.php`: tách `loadFileArticles()`, thêm `articleNormalize`, `articleFromDbRow`, `articleDbBySlug`, `articleRecordView`; `loadArticles()` gộp bài file với bài admin đã đăng.
- `article.php`: admin xem nháp bằng `?preview=1`; gọi `articleRecordView($slug)` để đếm lượt xem.

## Cách hoạt động và lưu ý
- Bài file trong `content/articles/` vẫn là bài hệ thống: chỉ xem trong admin. Bài admin soạn nằm trong bảng `articles`. Trùng slug thì bài file thắng, và form chặn không cho tạo slug trùng.
- Nội dung bài gõ theo cú pháp đơn giản (không HTML), mọi chữ vẫn bị escape khi hiển thị. Ảnh bìa chỉ chọn trong `img/articles/`. Chưa có tải ảnh lên để tránh rủi ro file độc hại.
- Mọi thao tác ghi dữ liệu đều là POST + CSRF + `requireAdmin()`. Log chỉ ghi khi thành công. Log không có nút sửa hoặc xóa.
- Lượt xem bài đếm theo ngày, mỗi phiên đăng nhập đếm một lần cho mỗi bài. Admin xem trước nháp không bị đếm.
- Thiếu bảng thì các trang vẫn mở được và hiện nhắc import, trang người dùng không bị lỗi.
- Đã chạy `php tools/audit.php public`: không có mẫu đáng nghi.

## Chưa làm / có thể làm tiếp
- Ghi log đăng nhập admin và các lần đăng nhập sai.
- Sửa bài hệ thống (file) trên giao diện.
- Tải ảnh bìa lên.
