/**
 * profile.js — trang Hồ sơ (profile.php).
 * - Chọn ảnh đại diện: kiểm tra loại/dung lượng ngay trên trình duyệt, xem trước trong vòng tròn,
 *   hiện nút "Lưu ảnh" / "Hủy". (Server vẫn kiểm tra lại tất cả, đây chỉ để người dùng đỡ chờ.)
 * - Xóa ảnh: hộp xác nhận cho form có data-confirm.
 * Nếu JavaScript bị tắt, vẫn chọn file rồi gửi form được (chỉ không có xem trước).
 */
(function () {
    'use strict';
    var MAX = 2 * 1024 * 1024;
    var TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    document.addEventListener('submit', function (e) {
        var msg = e.target && e.target.getAttribute && e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) { e.preventDefault(); }
    });

    var input = document.getElementById('pf-file');
    if (!input) { return; }
    var pic = document.getElementById('pf-ava-pic');
    var bar = document.getElementById('pf-ava-bar');
    var pick = document.getElementById('pf-pick');
    var hint = document.getElementById('pf-ava-hint');
    var msg = document.getElementById('pf-ava-msg');
    var cancel = document.getElementById('pf-ava-cancel');
    var original = pic.innerHTML;
    var url = null;

    function reset() {
        if (url) { URL.revokeObjectURL(url); url = null; }
        input.value = '';
        pic.innerHTML = original;
        bar.hidden = true;
        pick.hidden = false;
        if (hint) { hint.hidden = false; }
    }

    function error(text) {
        reset();
        msg.textContent = text;
        msg.hidden = false;
    }

    input.addEventListener('change', function () {
        msg.hidden = true;
        var f = input.files && input.files[0];
        if (!f) { reset(); return; }
        if (TYPES.indexOf(f.type) === -1) { error('Chỉ nhận ảnh JPG, PNG hoặc WebP.'); return; }
        if (f.size > MAX) { error('Ảnh quá lớn (' + (f.size / 1048576).toFixed(1) + ' MB). Vui lòng chọn ảnh tối đa 2 MB.'); return; }
        if (url) { URL.revokeObjectURL(url); }
        url = URL.createObjectURL(f);
        var img = document.createElement('img');
        img.src = url;
        img.alt = 'Xem trước ảnh đại diện';
        pic.innerHTML = '';
        pic.appendChild(img);
        bar.hidden = false;
        pick.hidden = true;
        if (hint) { hint.hidden = true; }
    });

    cancel.addEventListener('click', reset);
})();
