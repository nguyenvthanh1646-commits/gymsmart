/**
 * register.js — trang Đăng ký 3 bước (register.php).
 * - Chia form thành 3 bước, kiểm tra từng bước trước khi sang bước sau, hiện lỗi ngay dưới ô.
 * - Đo độ mạnh mật khẩu, xem trước BMI ở bước 2.
 * Đây chỉ là tiện cho người dùng: server (register.php) vẫn kiểm tra lại toàn bộ.
 * Nếu JavaScript bị tắt, cả 3 phần hiện liền nhau và nút "Tạo tài khoản" vẫn gửi form bình thường.
 */
(function () {
    'use strict';
    var form = document.getElementById('rg-form');
    if (!form) { return; }

    var steps = Array.prototype.slice.call(form.querySelectorAll('.rg-step'));
    var dots = Array.prototype.slice.call(document.querySelectorAll('#rg-steps li'));
    var back = document.getElementById('rg-back');
    var next = document.getElementById('rg-next');
    var submit = document.getElementById('rg-submit');
    var TOTAL = steps.length;
    var current = Math.min(TOTAL, Math.max(1, parseInt(form.getAttribute('data-start'), 10) || 1));

    /* ---------- hiện/ẩn lỗi ---------- */
    function holder(el) { return el.closest('.field') || el.closest('.rg-seg'); }

    function clearError(el) {
        var h = holder(el);
        if (!h) { return; }
        h.classList.remove('has-err');
        var old = h.querySelector('.rg-err[data-js]');
        if (old) { old.remove(); }
    }

    function showError(el, text) {
        clearError(el);
        var h = holder(el);
        if (!h) { return; }
        h.classList.add('has-err');
        var p = document.createElement('p');
        p.className = 'rg-err';
        p.setAttribute('data-js', '');
        p.setAttribute('role', 'alert');
        p.textContent = text;
        h.appendChild(p);
    }

    function messageFor(el) {
        var v = el.validity, name = el.name;
        if (v.valueMissing) { return el.type === 'radio' || el.tagName === 'SELECT' ? 'Vui lòng chọn một mục.' : 'Vui lòng nhập thông tin này.'; }
        if (v.typeMismatch) { return 'Email không đúng định dạng.'; }
        if (v.tooShort) { return 'Mật khẩu phải có ít nhất 8 ký tự.'; }
        if (v.rangeUnderflow || v.rangeOverflow) { return 'Giá trị phải từ ' + el.min + ' đến ' + el.max + '.'; }
        if (v.badInput || v.stepMismatch) { return 'Giá trị không hợp lệ.'; }
        return 'Giá trị chưa hợp lệ (' + name + ').';
    }

    /** Kiểm tra các ô của một bước. Trả về ô lỗi đầu tiên (hoặc null nếu ổn). */
    function validateStep(n) {
        var first = null;
        var seenRadio = {};
        var fields = steps[n - 1].querySelectorAll('input, select');
        Array.prototype.forEach.call(fields, function (el) {
            if (el.type === 'radio') { // mỗi nhóm radio chỉ báo một lần
                if (seenRadio[el.name]) { return; }
                seenRadio[el.name] = true;
            }
            clearError(el);
            var msg = el.checkValidity() ? '' : messageFor(el);
            if (el.name === 'password2' && el.value !== '' && el.value !== form.elements.password.value) { msg = 'Nhập lại mật khẩu chưa khớp.'; }
            if (msg) {
                showError(el, msg);
                if (!first) { first = el; }
            }
        });
        return first;
    }

    form.addEventListener('input', function (e) { if (e.target.name) { clearError(e.target); } });
    form.addEventListener('change', function (e) { if (e.target.name) { clearError(e.target); } });

    /* ---------- chuyển bước ---------- */
    function render(focus) {
        form.classList.add('is-js');
        steps.forEach(function (s, i) { s.hidden = (i + 1) !== current; });
        dots.forEach(function (d, i) {
            d.classList.toggle('is-current', (i + 1) === current);
            d.classList.toggle('is-done', (i + 1) < current);
            if ((i + 1) === current) { d.setAttribute('aria-current', 'step'); } else { d.removeAttribute('aria-current'); }
        });
        back.hidden = current === 1;
        next.hidden = current === TOTAL;
        submit.hidden = current !== TOTAL;
        if (focus) {
            var f = steps[current - 1].querySelector('.has-err input, .has-err select') || steps[current - 1].querySelector('input:not([type=hidden]), select');
            if (f) { f.focus({ preventScroll: true }); }
        }
    }

    function goNext() {
        var bad = validateStep(current);
        if (bad) { bad.focus(); return; }
        if (current < TOTAL) { current++; render(true); }
    }

    next.addEventListener('click', goNext);
    back.addEventListener('click', function () { if (current > 1) { current--; render(true); } });

    // Enter ở bước 1 và 2: sang bước tiếp theo thay vì gửi form sớm
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && current < TOTAL && e.target.tagName !== 'BUTTON') {
            e.preventDefault();
            goNext();
        }
    });

    // Gửi form: kiểm tra đủ 3 bước, nếu bước nào lỗi thì quay về bước đó
    form.addEventListener('submit', function (e) {
        for (var n = 1; n <= TOTAL; n++) {
            var bad = validateStep(n);
            if (bad) {
                e.preventDefault();
                current = n;
                render(false);
                bad.focus();
                return;
            }
        }
    });

    /* ---------- độ mạnh mật khẩu ---------- */
    var pw = form.elements.password;
    var meter = document.getElementById('rg-meter');
    var meterText = document.getElementById('rg-meter-text');
    var LABELS = ['Từ 8 đến 72 ký tự', 'Yếu', 'Trung bình', 'Khá', 'Mạnh'];
    function strength(s) {
        if (!s) { return 0; }
        var score = 0;
        if (s.length >= 8) { score++; }
        if (s.length >= 12) { score++; }
        if (/[a-z]/.test(s) && /[A-Z]/.test(s)) { score++; }
        if (/\d/.test(s) && /[^A-Za-z0-9]/.test(s)) { score++; }
        else if (/\d/.test(s) || /[^A-Za-z0-9]/.test(s)) { score += 0.5; }
        if (s.length < 8) { return 1; }
        return Math.max(1, Math.min(4, Math.floor(score)));
    }
    pw.addEventListener('input', function () {
        var lv = strength(pw.value);
        meter.setAttribute('data-level', String(lv));
        meterText.textContent = LABELS[lv];
    });

    /* ---------- xem trước BMI ---------- */
    var hEl = form.elements.height_cm, wEl = form.elements.weight_kg, bmiEl = document.getElementById('rg-bmi');
    function bmi() {
        var h = parseFloat(String(hEl.value).replace(',', '.')), w = parseFloat(String(wEl.value).replace(',', '.'));
        if (!(h >= 100 && h <= 250 && w >= 30 && w <= 300)) { bmiEl.hidden = true; return; }
        var b = w / Math.pow(h / 100, 2);
        var label = b < 18.5 ? 'Thiếu cân' : (b < 25 ? 'Bình thường' : (b < 30 ? 'Thừa cân' : 'Béo phì'));
        bmiEl.innerHTML = '';
        bmiEl.appendChild(document.createTextNode('BMI ước tính của bạn: '));
        var strong = document.createElement('b');
        strong.textContent = b.toFixed(1);
        bmiEl.appendChild(strong);
        bmiEl.appendChild(document.createTextNode(' (' + label + ')'));
        bmiEl.hidden = false;
    }
    hEl.addEventListener('input', bmi);
    wEl.addEventListener('input', bmi);
    bmi();

    render(true);
})();
