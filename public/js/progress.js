/**
 * progress.js — trang Tiến trình (progress.php).
 * Hiện hộp xác nhận trước khi xóa một số đo. Form xóa nào có thuộc tính data-confirm thì hỏi trước khi gửi.
 * (Nếu JavaScript bị tắt, form vẫn gửi bình thường, chỉ là không có hộp hỏi.)
 */
(function () {
    'use strict';
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.getAttribute) { return; }
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });
})();
