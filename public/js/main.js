// main.js — logic JS dùng chung toàn site (nạp ở footer.php, có cả trong khu quản trị).
// Mỗi tính năng nên tạo file JS riêng (VD: workout-plan.js, chatbot.js)
// và include riêng trong trang .php tương ứng bằng <script src="/js/...">,
// không gộp hết logic vào file này.

// Chống gửi form 2 lần: sau khi bấm gửi một form POST, khóa các nút gửi của form đó
// cho tới khi trang mới tải xong. Server vẫn phải tự kiểm tra, đây chỉ là trải nghiệm.
(function () {
    'use strict';

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post' || form.hasAttribute('data-no-guard')) {
            return;
        }
        // Đợi 1 nhịp rồi mới khóa: trình duyệt đã gom xong dữ liệu form (kể cả name/value của nút vừa bấm,
        // nút bị disabled sẽ không được gửi), và các trang tự xử lý bằng fetch hoặc hộp "Bạn chắc chứ?"
        // đã kịp gọi preventDefault() nếu cần.
        setTimeout(function () {
            if (e.defaultPrevented) {
                return; // form gửi bằng fetch (tự quản lý nút) hoặc người dùng bấm Hủy ở hộp xác nhận
            }
            form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach(function (btn) {
                btn.disabled = true;
                btn.classList.add('is-busy');
                btn.setAttribute('aria-busy', 'true');
            });
        }, 0);
    });

    // Bấm "Quay lại" trình duyệt có thể hiện lại trang cũ từ bộ nhớ đệm, lúc đó nút vẫn đang khóa: mở lại
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) {
            return;
        }
        document.querySelectorAll('.is-busy').forEach(function (btn) {
            btn.disabled = false;
            btn.classList.remove('is-busy');
            btn.removeAttribute('aria-busy');
        });
    });
})();
