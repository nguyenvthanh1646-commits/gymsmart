// home-guest.js — trang chủ khi chưa đăng nhập.
(function () {
    'use strict';

    // Menu điện thoại: bấm một liên kết hoặc nhấn Esc thì đóng lại.
    // Việc bật/tắt menu bằng nút ☰ đã có sẵn trong navbar.php.
    var nb = document.getElementById('nb');
    var toggle = nb.querySelector('.nb__toggle');

    function closeMenu() {
        nb.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    nb.querySelectorAll('.nb__links a').forEach(function (link) {
        link.addEventListener('click', closeMenu);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && nb.classList.contains('is-open')) {
            closeMenu();
            toggle.focus(); // trả focus về nút ☰ để người dùng bàn phím không bị lạc
        }
    });
})();
