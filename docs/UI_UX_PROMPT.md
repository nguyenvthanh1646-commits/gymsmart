# Prompt thiết kế UI/UX — GymSmart (dùng cho Claude / Claude Code / Claude Design)

Copy nguyên đoạn dưới đây và gửi cho Claude khi bắt đầu thiết kế UI, có thể tách nhỏ theo từng trang nếu muốn làm dần.

---

## Prompt

Tôi đang xây dựng một website gym tên **GymSmart** cho bài tập lớn môn Phát triển ứng dụng Web (nhóm 3 sinh viên, ít kinh nghiệm lập trình). Tôi cần bạn thiết kế UI/UX cho các trang phía người dùng (không cần trang admin).

**Bối cảnh sản phẩm:**
Web app giúp người dùng tự tập gym tại nhà/phòng gym: xem thư viện bài tập kháng lực + cardio, nhận lịch tập cá nhân hóa theo mục tiêu, tính TDEE/macro dinh dưỡng, theo dõi tiến trình (cân nặng, buổi tập), xem video và hướng dẫn kỹ thuật cho từng bài tập, và hỏi đáp với chatbot AI.

**Định hướng phong cách:**
- Cảm giác: năng lượng, sạch sẽ, đáng tin cậy — không màu mè, không rối mắt
- 1 màu accent chính (gợi ý: cam/đỏ cam hoặc xanh lá, gắn với "năng lượng, sức khỏe"), còn lại dùng tông trung tính (trắng/xám)
- Font sans-serif rõ ràng, hỗ trợ tốt tiếng Việt (VD: Inter, Be Vietnam Pro)
- Mobile-first: người dùng thường xem trên điện thoại khi đang ở phòng gym
- Component nhất quán xuyên suốt: cùng 1 kiểu button, card, input, không tự sáng tạo lại mỗi trang

**Các trang cần thiết kế (ưu tiên theo thứ tự):**
1. Đăng nhập / Đăng ký — tối giản, ít trường nhất có thể
2. Onboarding nhập chỉ số cơ thể lần đầu (chiều cao, cân nặng, tuổi, mức vận động, mục tiêu) — chia step-by-step, không dồn 1 form dài
3. Dashboard — trang chính sau đăng nhập: tổng quan calo/macro hôm nay, lịch tập hôm nay (có thể tick hoàn thành), lối tắt sang các trang khác
4. Thư viện bài tập — dạng lưới thẻ có ảnh minh họa cùng tỷ lệ, filter theo nhóm cơ (ngực/lưng/vai/chân/tay/bụng) và loại (kháng lực/cardio), ô tìm kiếm theo tên; trang chi tiết 1 bài tập có video YouTube nhúng (16:9) ở trên cùng, rồi nhóm cơ, dụng cụ, độ khó, "Cách thực hiện", "Lỗi thường gặp", nút "Thêm vào lịch tập" và gợi ý 3 bài cùng nhóm cơ
5. Lịch tập theo tuần — xem theo tab từng ngày, mỗi bài tập trong buổi tập là 1 dòng có thể tick/sửa
6. Trang kết quả TDEE & Macro — hiển thị trực quan (biểu đồ donut chia macro), không chỉ là số liệu khô khan
7. Trang tiến trình — biểu đồ đường cân nặng/số đo theo thời gian, nhật ký các buổi tập đã hoàn thành
8. Chatbot AI — widget chat nổi góc màn hình, không chiếm hết trang chính

**Ràng buộc kỹ thuật:**
- Frontend: HTML + CSS thuần + JavaScript thuần (không React, không Tailwind). Màu, khoảng cách, bo góc đặt thành biến CSS trong `style.css`. Không dùng hiệu ứng quá phức tạp (parallax, 3D, animation nặng)
- Nhóm ít kinh nghiệm → ưu tiên bố cục đơn giản, dễ implement, dễ tái sử dụng component giữa các trang
- Không cần thiết kế responsive chi tiết từng breakpoint, chỉ cần đảm bảo layout hợp lý trên mobile (ưu tiên) và desktop

**Việc tôi cần bạn làm:**
1. Đề xuất bảng màu cụ thể (mã hex) và bộ typography (font, cỡ chữ heading/body)
2. Với mỗi trang ở trên, mô tả bố cục (layout), các thành phần chính, và cách sắp xếp
3. Tạo mockup trực quan cho 2-3 trang quan trọng nhất (Dashboard, Thư viện bài tập, Lịch tập) để tôi hình dung trước khi code
4. Gợi ý bộ component dùng chung (button, card, input, badge...) để đảm bảo nhất quán giữa các trang

Hãy hỏi lại tôi nếu cần làm rõ thêm trước khi bắt đầu thiết kế.

---

## Cách dùng

- **Dùng nguyên văn:** copy cả prompt trên, gửi cho Claude trong 1 conversation mới dành riêng cho việc thiết kế UI, tách biệt với các câu hỏi code backend
- **Dùng từng phần:** nếu muốn làm dần theo từng trang, giữ nguyên phần "Bối cảnh sản phẩm" + "Định hướng phong cách" + "Ràng buộc kỹ thuật", chỉ đổi phần "Các trang cần thiết kế" thành 1 trang cụ thể mỗi lần
- **Sau khi có mockup:** hỏi tiếp Claude cách viết HTML + CSS cho trang này theo đúng mockup trên (tự viết trước, bí chỗ nào mới hỏi) để chuyển từ thiết kế sang code
