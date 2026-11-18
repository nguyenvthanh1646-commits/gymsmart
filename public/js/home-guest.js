// home-guest.js — trang chủ khi chưa đăng nhập (chỉ nạp ở trang này, cuối includes/home-guest.php).
// Toàn bộ là phần "thêm cho đẹp": tắt JS thì trang vẫn hiện đủ nội dung với số liệu cuối cùng.
//   1. Menu điện thoại: bấm liên kết hoặc Esc thì đóng
//   2. Bảng điều khiển ví dụ: vòng calo + số đếm lên, thanh macro chạy đầy, AI "gõ" câu trả lời, nghiêng 3D theo chuột (máy tính)
//   3. Dừng hiệu ứng chạy liên tục (đường dữ liệu, dải chữ) khi cuộn qua hoặc tab bị ẩn
//   4. Xuất hiện khi cuộn, đường nối "3 bước" dài ra theo vị trí cuộn
//   5. Nút "Tạo tài khoản" dính đáy trên điện thoại sau khi cuộn qua hero
//   6. Công cụ tính calo: cùng công thức với includes/tdee.php, tính trong trình duyệt, không gửi dữ liệu đi
// Người dùng bật "giảm chuyển động" (prefers-reduced-motion): bỏ qua mọi hiệu ứng, hiện ngay kết quả.
(function () {
    'use strict';
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var root = document.documentElement;
    root.classList.add('gh-js');   // CSS chỉ ẩn khối chờ "xuất hiện khi cuộn" khi có lớp này

    // ---------- 1. Menu điện thoại ----------
    var nb = document.getElementById('nb');
    if (nb) {
        var toggle = nb.querySelector('.nb__toggle');
        var closeMenu = function () {
            nb.classList.remove('is-open');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        };
        nb.querySelectorAll('.nb__links a').forEach(function (a) { a.addEventListener('click', closeMenu); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nb.classList.contains('is-open')) {
                closeMenu();
                if (toggle) toggle.focus();   // trả focus về nút ☰
            }
        });
    }

    // ---------- 2. Bảng điều khiển ví dụ ----------
    var dash = document.getElementById('gh-dash');
    if (dash && !reduce) {
        var ring = dash.querySelector('.gh-kcal__value');
        var bars = dash.querySelectorAll('.gh-macro i');
        var num = dash.querySelector('[data-count]');
        var typed = dash.querySelector('[data-type]');
        var fullText = typed ? typed.textContent : '';
        // Về trạng thái bắt đầu, rồi chạy ở khung hình kế tiếp để trình duyệt kịp vẽ trạng thái 0
        ring.style.strokeDasharray = '0 100';
        bars.forEach(function (b) { b.dataset.w = b.style.getPropertyValue('--w'); b.style.setProperty('--w', '0%'); });
        if (typed) { typed.textContent = ''; typed.classList.add('is-typing'); }
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                ring.style.strokeDasharray = '';   // về giá trị trong CSS (78 100) → có transition
                bars.forEach(function (b) { b.style.setProperty('--w', b.dataset.w); });
            });
        });
        if (num) {
            var target = parseInt(num.dataset.count, 10), t0 = performance.now();
            var tick = function (t) {
                var p = Math.min(1, (t - t0) / 1200);
                num.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString('vi-VN');
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        }
        if (typed) {
            var i = 0;
            setTimeout(function step() {
                typed.textContent = fullText.slice(0, ++i);
                if (i < fullText.length) setTimeout(step, 26);
                else typed.classList.remove('is-typing');
            }, 600);
        }
        // Nghiêng 3D nhẹ theo chuột, chỉ máy tính có chuột
        var stage = document.getElementById('gh-stage');
        if (stage && window.matchMedia('(min-width: 961px) and (pointer: fine)').matches) {
            stage.addEventListener('mousemove', function (e) {
                var r = stage.getBoundingClientRect();
                var x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
                dash.style.transform = 'rotateY(' + (x * 10).toFixed(2) + 'deg) rotateX(' + (-y * 8).toFixed(2) + 'deg)';
            });
            stage.addEventListener('mouseleave', function () { dash.style.transform = ''; });
        }
    }

    // ---------- 3. Dừng hiệu ứng chạy liên tục khi không ai nhìn thấy ----------
    var looping = [document.getElementById('gh-hero'), document.getElementById('gh-marquee')].filter(Boolean);
    var onScreen = new Map();
    var updateLoops = function () {
        looping.forEach(function (el) { el.classList.toggle('is-paused', document.hidden || !onScreen.get(el)); });
    };
    if ('IntersectionObserver' in window) {
        var loopObs = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { onScreen.set(en.target, en.isIntersecting); });
            updateLoops();
        });
        looping.forEach(function (el) { onScreen.set(el, true); loopObs.observe(el); });
    }
    document.addEventListener('visibilitychange', updateLoops);

    // ---------- 4. Xuất hiện khi cuộn + đường nối 3 bước ----------
    var reveals = document.querySelectorAll('[data-reveal]');
    if (reduce || !('IntersectionObserver' in window)) {
        reveals.forEach(function (el) { el.classList.add('is-in'); });
    } else {
        var revObs = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('is-in'); revObs.unobserve(en.target); }
            });
        }, { rootMargin: '0px 0px -10% 0px' });
        reveals.forEach(function (el) { revObs.observe(el); });
    }
    var steps = document.getElementById('gh-steps');
    if (steps) {
        if (reduce) {
            steps.style.setProperty('--p', '1');
        } else {
            var ticking = false;
            var updateSteps = function () {
                ticking = false;
                var r = steps.getBoundingClientRect(), vh = window.innerHeight;
                // 0 khi đầu khối chạm 85% chiều cao màn hình, 1 khi cuối khối lên tới 55%
                var p = (vh * .85 - r.top) / (r.height + vh * .3);
                steps.style.setProperty('--p', Math.max(0, Math.min(1, p)).toFixed(3));
            };
            window.addEventListener('scroll', function () {
                if (!ticking) { ticking = true; requestAnimationFrame(updateSteps); }
            }, { passive: true });
            updateSteps();
        }
    }

    // ---------- 5. Nút dính đáy trên điện thoại ----------
    var sticky = document.getElementById('gh-sticky');
    var hero = document.getElementById('gh-hero'), fin = document.getElementById('gh-final');
    if (sticky && hero && fin && 'IntersectionObserver' in window) {
        sticky.hidden = false;   // CSS chỉ hiện khi có lớp is-on và màn hình hẹp
        var heroVisible = true, finalVisible = false;
        new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.target === hero) heroVisible = en.isIntersecting;
                if (en.target === fin) finalVisible = en.isIntersecting;
            });
            sticky.classList.toggle('is-on', !heroVisible && !finalVisible);
        }).observe(hero);
        new IntersectionObserver(function (entries) {
            finalVisible = entries[0].isIntersecting;
            sticky.classList.toggle('is-on', !heroVisible && !finalVisible);
        }).observe(fin);
    }

    // ---------- 6. Công cụ tính calo ----------
    // Cùng công thức với includes/tdee.php (calculateBMR, calculateTDEE, calculateTargets nhịp "standard"):
    //   BMR = 10 × kg + 6,25 × cm − 5 × tuổi + (Nam +5 | Nữ −161 | Khác −78)
    //   TDEE = BMR × hệ số vận động; calo mục tiêu = TDEE × (giảm mỡ 0,8 | duy trì 1 | tăng cơ 1,1); protein người mới 1,6 g/kg
    var form = document.getElementById('gh-calc');
    if (form) {
        var go = document.getElementById('gh-calc-go');
        go.type = 'submit';   // có JS mới cho gửi form (để khi tắt JS, chỉ số cơ thể không bị gửi lên máy chủ qua URL)
        var out = document.getElementById('gh-calc-out'), err = document.getElementById('gh-calc-err'), res = document.getElementById('gh-calc-res');
        var genderAdd = { MALE: 5, FEMALE: -161, OTHER: -78 };
        var limits = { age: [10, 100, 'Tuổi'], height: [100, 250, 'Chiều cao'], weight: [30, 300, 'Cân nặng'] };
        var fmt = function (n) { return Math.round(n).toLocaleString('vi-VN'); };
        form.addEventListener('submit', function (e) {
            e.preventDefault();   // không gửi đi đâu cả
            var v = {}, bad = [];
            Object.keys(limits).forEach(function (k) {
                var input = form.elements[k], n = parseFloat(String(input.value).replace(',', '.'));
                var ok = isFinite(n) && n >= limits[k][0] && n <= limits[k][1];
                input.setAttribute('aria-invalid', ok ? 'false' : 'true');
                if (!ok) bad.push(limits[k][2] + ' từ ' + limits[k][0] + ' đến ' + limits[k][1]);
                v[k] = n;
            });
            out.hidden = false;
            if (bad.length) {
                err.textContent = 'Hãy kiểm tra lại: ' + bad.join('; ') + '.';
                err.hidden = false;
                res.hidden = true;
                return;
            }
            var bmr = 10 * v.weight + 6.25 * v.height - 5 * v.age + genderAdd[form.elements.gender.value];
            var tdee = bmr * parseFloat(form.elements.activity.value);
            var targetKcal = tdee * parseFloat(form.elements.goal.value);
            err.hidden = true;
            res.hidden = false;
            document.getElementById('gh-r-tdee').textContent = fmt(tdee);
            document.getElementById('gh-r-target').textContent = fmt(targetKcal);
            document.getElementById('gh-r-protein').textContent = fmt(1.6 * v.weight);
        });
    }
})();
