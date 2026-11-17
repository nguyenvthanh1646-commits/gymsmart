// coach.js — hành vi nhỏ dùng chung cho các trang HLV (phía học viên và khu /coach/).
// Không chứa logic nghiệp vụ: mọi kiểm tra thật đều ở PHP, phần này chỉ giúp dễ dùng hơn.
//   [data-count="id-ô-nhập"]   bộ đếm ký tự "120/1500", đỏ khi vượt maxlength
//   form[data-confirm="..."]   hỏi lại trước khi gửi (thay cho onclick="return confirm()")
//   [data-toggle="id-khối"]    bấm để hiện/ẩn một khối (ví dụ form gửi yêu cầu sửa)
//   button[data-busy-text]     khi form đang gửi thì đổi chữ nút (main.js đã khóa nút)
(function () {
    'use strict';

    document.querySelectorAll('[data-count]').forEach(function (out) {
        var input = document.getElementById(out.getAttribute('data-count'));
        if (!input) return;
        var max = parseInt(input.getAttribute('maxlength') || '0', 10);
        function update() {
            var n = Array.from(input.value).length;   // đếm theo ký tự (tiếng Việt có dấu vẫn tính 1)
            out.textContent = n + (max ? '/' + max : '') + ' ký tự';
            out.classList.toggle('is-over', max > 0 && n > max);
        }
        input.addEventListener('input', update);
        update();
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
            return;
        }
        setTimeout(function () {
            if (e.defaultPrevented) return;
            form.querySelectorAll('button[data-busy-text]').forEach(function (b) {
                b.textContent = b.getAttribute('data-busy-text');
            });
        }, 0);
    });

    document.querySelectorAll('[data-toggle]').forEach(function (btn) {
        var box = document.getElementById(btn.getAttribute('data-toggle'));
        if (!box) return;
        btn.setAttribute('aria-controls', box.id);
        btn.setAttribute('aria-expanded', box.hidden ? 'false' : 'true');
        btn.addEventListener('click', function () {
            box.hidden = !box.hidden;
            btn.setAttribute('aria-expanded', box.hidden ? 'false' : 'true');
            if (!box.hidden) {
                var first = box.querySelector('textarea, input:not([type="hidden"]), select');
                if (first) first.focus();
            }
        });
    });
})();
