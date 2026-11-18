# UI_UX_PROMPT.md — Quy chuẩn giao diện GymSmart

> File này dành cho Claude Code và cả nhóm. Đọc trước khi làm hoặc sửa bất kỳ trang nào.
> Mục tiêu: mọi trang trông như cùng một sản phẩm. Khi có mâu thuẫn, **các trang chức năng hiện tại (thư viện bài tập, lịch tập, TDEE/Macro, tiến trình, bài viết, hồ sơ) là chuẩn**.

---

## 1. Bối cảnh sản phẩm

- **Tên:** GymSmart. **Đề tài:** website hỗ trợ tập luyện và dinh dưỡng cá nhân hóa tích hợp trí tuệ nhân tạo.
- **Người dùng:** người mới và người tập trung cấp, thường mở web trên điện thoại khi đang ở phòng gym.
- **Vai trò:**
  - Khách
  - USER (người tập)
  - COACH (huấn luyện viên, phân hệ mới)
  - ADMIN
- **Chức năng chính:**
  - thư viện bài tập có ảnh và video
  - lịch tập cá nhân hóa
  - TDEE/macro và lịch ăn
  - nhật ký buổi tập và số đo
  - bài viết có dẫn chứng
  - chatbot GymSmart AI
  - khu quản trị
  - khu HLV
- **Kỹ thuật:** PHP 8 thuần, HTML, CSS thuần, JavaScript thuần. Không React, không Tailwind, không Bootstrap, không thư viện UI mới. Chart.js đã có sẵn thì dùng lại.

## 2. Cảm giác thương hiệu

- Năng lượng, sạch sẽ, đáng tin cậy. Không màu mè, không rối mắt.
- Một màu nhấn duy nhất là **cam** (`#ff5a1f`). Còn lại là trắng, xám nhạt và xanh đen đậm cho chữ.
- Thông tin sức khỏe phải rõ ràng và trung thực: không bịa số liệu, lời chứng thực, đánh giá sao hay huy hiệu.

## 3. Design tokens (bắt buộc dùng biến, không viết mã màu trực tiếp)

Hiện CSS có nhiều tiền tố biến (`--color-*`, `--ui-*`, `--gc-*`, `--gs-*`). Từ nay **chỉ dùng bộ dưới đây**, khai báo một lần trong `public/css/ui.css`. Khi sửa trang nào thì chuyển biến cũ của trang đó sang bộ này.

| Nhóm | Biến | Giá trị |
|---|---|---|
| Màu nhấn | `--accent` | `#ff5a1f` |
| | `--accent-dark` (hover) | `#e8480f` |
| | `--accent-2` (gradient) | `#ff8a3d` |
| | `--accent-soft` (nền nhạt) | `#fff1ea` |
| Nền | `--bg` (nền trang) | `#f4f6fb` |
| | `--surface` (thẻ) | `#ffffff` |
| Chữ | `--text` | `#131a2e` |
| | `--text-muted` | `#667085` |
| Viền | `--line` | `#e4e8f1` |
| Nền tối (chỉ hero khách, khu admin) | `--ink` | `#0b1020` |
| | `--ink-2` | `#111a33` |
| | `--text-on-dark` | `#c7d0e6` |
| Trạng thái | `--ok` | `#12b76a` |
| | `--warn` | `#f79009` |
| | `--danger` | `#f04438` |
| | `--info` | `#2e90fa` |
| Ánh sáng lạnh (**chỉ trang khách**) | `--glow-cool` | `#22d3ee` — chỉ làm quầng sáng/gradient trang trí trong `home-guest.css`; không dùng cho chữ, nút hay trạng thái |
| Bo góc | `--r-sm` / `--r` / `--r-lg` / `--r-pill` | `8px` / `12px` / `18px` / `999px` |
| Bóng | `--shadow-sm` | `0 1px 2px rgba(16,24,40,.05), 0 1px 3px rgba(16,24,40,.06)` |
| | `--shadow-md` | `0 8px 24px rgba(16,24,40,.08)` |
| | `--shadow-lg` | `0 24px 48px rgba(16,24,40,.14)` |
| Khoảng cách | `--s-1` … `--s-8` | `4, 8, 12, 16, 24, 32, 48, 64px` |
| Chuyển động | `--ease` | `cubic-bezier(.2,.7,.2,1)` |
| | thời lượng | 150–250ms |

Quy tắc màu:
- **Chữ không bao giờ có màu cam trên nền cam nhạt** nếu tương phản dưới 4.5:1. Chữ thường dùng `--text` hoặc `--text-muted`.
- **Màu trạng thái chỉ dùng cho trạng thái** (thành công, cảnh báo, lỗi), không dùng để trang trí. Luôn kèm chữ hoặc biểu tượng, không chỉ dùng màu.
- **Độ khó bài tập:** Dễ là xanh lá nhạt, Trung bình là vàng nhạt, Khó là đỏ nhạt; chữ đậm cùng tông, đủ tương phản.

## 4. Typography

- **Font:** `'Be Vietnam Pro', 'Segoe UI', system-ui, sans-serif`, tự host trong `public/fonts/`.
- **Cỡ chữ (desktop / điện thoại):**
  - H1: 40 / 30px, đậm 800
  - H2: 26 / 22px, đậm 700
  - H3: 19 / 17px, đậm 700
  - Body: 16px, cao dòng 1.6
  - Chữ phụ: 14px
  - Nhãn nhỏ: 12px, chữ hoa, giãn chữ .04em
- **Số liệu lớn** (calo, cân nặng): đậm 800 với `font-variant-numeric: tabular-nums`.
- **Ngôn ngữ:** tiếng Việt có dấu, câu ngắn. Ngày dạng `Thứ Năm, 05/11/2026`; số dạng `2.788 kcal`, `80,0 kg`.

## 5. Bố cục

- **Mobile-first, kiểm tra ở 390px, 768px và 1440px.** Không có thanh cuộn ngang ở bất kỳ kích thước nào.
- **Khung nội dung:** `max-width: 1200px`, lề hai bên 16px trên điện thoại và 24px trên desktop.
- **Lưới thẻ:** `repeat(auto-fill, minmax(260px, 1fr))`, khoảng cách 22–24px.
- **Đầu trang chức năng:** H1, một dòng mô tả `--text-muted`, nút chính đặt bên phải (xuống dòng trên điện thoại).
- **Mỗi trang đúng một H1.** Thứ tự tiêu đề không nhảy cấp.

## 6. Component dùng chung (định nghĩa một lần trong `ui.css`, không viết lại theo trang)

- **Thanh điều hướng người dùng:** nền trắng, bóng nhẹ, cố định khi cuộn.
  - Logo GymSmart (chữ "Smart" màu cam), các mục Bài tập & Video, Lịch tập, TDEE/Macro, Tiến trình, Bài viết, Hồ sơ.
  - Mục đang mở có chữ cam và gạch chân cam 3px.
  - Bên phải là avatar tròn cam và nút Đăng xuất dạng viền.
  - Trên điện thoại thu gọn thành nút menu.
- **Thanh điều hướng quản trị:** nền `--ink`, nhãn `ADMIN` cam, nút "Xem trang người dùng". Khu HLV dùng cùng kiểu với nhãn `HLV`.
- **Nút:**
  - Chính: nền cam, chữ trắng, bo `--r`, có hover/active/focus.
  - Phụ: viền `--line`, nền trắng.
  - Nguy hiểm: đỏ, luôn có hộp xác nhận.
  - Cao tối thiểu 44px. Khi đang gửi thì vô hiệu và hiện "Đang xử lý…".
- **Chip lọc:** dạng viên thuốc. Chip đang chọn có nền `--text`, chữ trắng.
- **Thẻ:** nền `--surface`, viền `--line`, bo `--r-lg`, bóng `--shadow-sm`, padding 20–24px; hover nâng nhẹ chỉ khi bấm được.
- **Ô nhập:**
  - Cao 48px, viền `--line`, focus là viền cam kèm vòng `--accent-soft`.
  - Luôn có `<label>`. Lỗi hiện ngay dưới ô (chữ đỏ và biểu tượng).
  - Ô mật khẩu có nút ẩn/hiện.
- **Huy hiệu/nhãn:** bo `--r-pill`, chữ 12–13px, nền nhạt cùng tông.
- **Bảng:** tiêu đề chữ hoa 12px, màu `--text-muted`; dòng cách nhau bằng viền `--line`. Trên điện thoại bảng cuộn ngang trong khung riêng hoặc chuyển thành thẻ.
- **Thông báo:** hộp gợi ý (vàng nhạt), toast thành công/lỗi góc trên, có `role="status"`.
- **Thanh và vòng tiến độ:** phần đạt màu cam, phần còn lại `--line`, luôn kèm số hoặc chữ.
- **Trạng thái rỗng:** biểu tượng tròn nền `--accent-soft`, tiêu đề ngắn, một câu hướng dẫn, một nút hành động. Không để ô trống.
- **Đang tải:** khung xương (skeleton) xám nhạt. Không dùng vòng quay toàn trang.
- **Chatbot:**
  - Nút nổi góc phải dưới, 56px.
  - Khung chat nền đặc (không trong suốt), z-index cao nhất, nằm trọn trong viewport.
  - Vùng tin nhắn cuộn riêng, ô nhập cố định ở đáy, bộ đếm 0/500.
  - Chip gợi ý và chỉ báo "đang gõ" nằm trong vùng tin nhắn.
  - Trên điện thoại khung gần toàn màn hình.

## 7. Quy định theo trang

- **Trang chủ khách (hướng "Future sport", 11/2026):**
  - Hero nền tối `--ink` (ngoại lệ duy nhất): lưới sàn phối cảnh, quầng sáng cam và `--glow-cool`, tiêu đề viết hoa nghiêng từng dòng (dòng giữa chỉ có viền, có dự phòng chữ đặc), dải số liệu THẬT (số bài tập, nhóm cơ, bài viết có dẫn chứng). Bên phải: ảnh gym tông cam/tối cắt hình bình hành có vạch quét (nguồn ghi trong `public/assets/img/CREDITS.md`) và **một** bảng điều khiển kính tối có nhãn "Ví dụ".
  - Không dùng ảnh người nổi tiếng/influencer: vừa vướng bản quyền, vừa khiến người xem hiểu nhầm là họ quảng cáo cho GymSmart.
  - Nút cam dùng chữ `--ink` (chữ trắng trên `#ff5a1f` chỉ đạt 3,12:1).
  - Bên dưới: dải từ khóa chạy ngang, công cụ tính calo (tính trong trình duyệt), lưới bento, 3 bước, chatbot mẫu, bài viết thật, kêu gọi cuối trang nền cam; mọi khối nền sáng.
  - Chuyển động chỉ chạy khi nhìn thấy và tab đang mở; tắt hết khi `prefers-reduced-motion`.
- **Trang chủ đã đăng nhập (bảng điều khiển):**
  - Nền tối `--ink` (thẻ xanh đen). Đầu trang theo hướng "Future sport" giống hero trang khách: lưới sàn phối cảnh, quầng sáng cam (không dùng `--glow-cool`), lời chào viết hoa nghiêng có tên tô gradient cam, ô số liệu nghiêng viền trái cam. Từ 900px có thêm ảnh gym cắt bình hành bên phải; màn hình hẹp ẩn ảnh để số liệu lên sớm.
  - Gồm: lời chào và ngày, một nút hành động theo trạng thái (Tạo lịch tập, Bắt đầu buổi tập, Hoàn thiện hồ sơ), thẻ Hôm nay, thẻ Tuần này (dải 7 ngày, có chữ Đã tập, Chưa tập, Nghỉ), thẻ Dinh dưỡng (vòng macro), thẻ Tiến trình (sparkline cân nặng).
- **Đăng ký:** 3 bước (Tài khoản, Cơ thể, Mục tiêu) có thanh tiến độ, giữ lại dữ liệu khi lỗi.
- **Đăng nhập, quên mật khẩu:** tối giản, có liên kết qua lại, thông báo chung không lộ email có tồn tại.
- **Thư viện bài tập:**
  - Chip nhóm cơ, ô tìm, chọn loại, chọn độ khó, nút Lọc.
  - Thẻ có ảnh cùng tỷ lệ 4:3 và huy hiệu độ khó.
  - Trang chi tiết có video 16:9, các bước, lỗi thường gặp và bài cùng nhóm cơ.
- **Lịch tập:** xem theo ngày trong tuần; mỗi bài là một dòng (set × rep, mức tạ gợi ý); ghi nhật ký nhanh.
- **Đang tập (`workout-session.php`):** dùng một tay trong phòng gym: ô nhập và nút cao từ 48px, bàn phím số; mỗi set một dòng có nút "Xong"; gợi ý mức tạ có nhãn chữ (Tăng tạ, Giữ mức, Giảm tạ), không chỉ dùng màu; thanh dính đáy chứa đồng hồ nghỉ và nút lưu, chừa chỗ cho nút chatbot.
- **TDEE/Macro:** số lớn kèm vòng macro, đổi mục tiêu thì tính lại ngay, lịch ăn theo bữa.
- **Tiến trình:** form số đo ngắn, biểu đồ đường một trục, chọn khoảng 7/30/90 ngày, có trạng thái rỗng.
- **Bài viết:**
  - Khối "Đọc trước khi tập" ở đầu.
  - Thẻ bài có ảnh bìa, chuyên mục, thời gian đọc.
  - Trang đọc có trả lời nhanh, bảng chứng cứ và nguồn.
- **Hồ sơ:** khu ảnh đại diện, form thông tin cơ thể, mục bảo mật đổi mật khẩu.
- **Quản trị:**
  - Nền sáng cho nội dung, thanh điều hướng tối.
  - Bảng có tìm kiếm và lọc; thao tác xóa và đổi vai trò có xác nhận.
  - Mọi trang có trạng thái thiếu bảng hoặc rỗng rõ ràng.
- **Khu HLV (`/coach/`):**
  - Cùng phong cách khu quản trị.
  - Bảng điều khiển gồm học viên, yêu cầu chờ duyệt, học viên chưa tập 7 ngày, tin nhắn chưa đọc.
  - Lịch hoặc mục tiêu do HLV giao có nhãn "Do HLV … giao" ở phía học viên.

## 8. Khả năng tiếp cận (bắt buộc)

- **Tương phản chữ:** ≥ 4.5:1 (chữ lớn ≥ 3:1).
- **Focus:** nhìn thấy được, dùng `:focus-visible` với vòng cam 3px.
- **Ảnh:** ảnh nội dung có `alt` mô tả; ảnh trang trí có `alt=""`.
- **Biểu đồ:** có số liệu hoặc `aria-label` thay thế.
- **Bàn phím:**
  - Mọi thao tác dùng được bằng bàn phím.
  - Hộp thoại và chatbot có `role="dialog"`, đóng bằng Esc, trả focus khi đóng.
- **Chuyển động:** tôn trọng `prefers-reduced-motion` (tắt parallax, zoom, đếm số). Không chạy hiệu ứng khi tab bị ẩn.

## 9. Hiệu năng

- **Ảnh:**
  - Có `width`/`height`.
  - Ngoài màn hình đầu dùng `loading="lazy"`.
  - Ảnh hero dùng WebP, tối đa khoảng 200 KB, `fetchpriority="high"`.
- **Không tải thư viện thừa:** chỉ nạp CSS và JS của trang đang mở.
- **Không** dùng parallax nặng, 3D hay animation chạy liên tục.

## 10. Quy trình khi Claude Code sửa giao diện

1. **Khảo sát:** chạy `php -S localhost:8000 -t public`, chụp ảnh trước ở 390/768/1440px vào `docs/screens/<trang>-before-<kích thước>.png`.
2. **Đề xuất trước:** liệt kê thay đổi dự kiến và **dừng lại chờ duyệt** nếu thay đổi lớn hơn một trang.
3. **Sửa đúng phạm vi:**
   - Dùng token và component ở trên; xóa CSS màu riêng thừa; không dùng `!important` để vá.
   - Không đổi logic PHP, truy vấn hay bảo mật (CSRF, escape đầu ra, kiểm tra quyền).
4. **Kiểm tra:**
   - `php -l` các file đã sửa.
   - Thử khi là khách, USER, COACH, ADMIN.
   - Chụp ảnh sau (`-after-`) và so với ảnh trước.
5. **Bàn giao:**
   - Nhánh git riêng, commit nhỏ.
   - Báo cáo gồm: đã đổi gì, file nào, cách tự kiểm tra trên XAMPP, lệnh git viết trên **một dòng** dùng được trong PowerShell.

## 11. Danh sách kiểm tra nhanh trước khi commit

- [ ] Dùng biến token, không có mã màu viết trực tiếp mới
- [ ] Thanh điều hướng, nút, thẻ giống các trang khác
- [ ] Đẹp ở 390px, không cuộn ngang
- [ ] Có trạng thái rỗng, lỗi, đang tải
- [ ] Một H1; label cho mọi ô nhập; focus nhìn thấy được
- [ ] Tương phản đạt; trạng thái không chỉ bằng màu
- [ ] Chatbot không che nội dung, khung nằm trọn trong màn hình
- [ ] Không bịa dữ liệu; ảnh có nguồn
- [ ] `php -l` sạch; thử đủ các vai trò
