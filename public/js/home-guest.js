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

    // Dừng chuyển động của hero khi không ai nhìn thấy: tab bị ẩn hoặc đã cuộn qua hero, cho đỡ tốn CPU và pin.
    var hero = document.getElementById('gh-hero');
    var heroOnScreen = true;

    function updateHeroMotion() {
        hero.classList.toggle('is-paused', document.hidden || !heroOnScreen);
    }

    document.addEventListener('visibilitychange', updateHeroMotion);
    new IntersectionObserver(function (entries) {
        heroOnScreen = entries[0].isIntersecting;
        updateHeroMotion();
    }).observe(hero);
})();
