// nutrition.js — phần tương tác của trang Dinh dưỡng (nutrition.php).
//  1. Bấm "Tùy chỉnh" thì hiện 3 ô nhập tỉ lệ %, chọn kiểu khác thì ẩn đi.
//  2. Khi gõ tỉ lệ: hiện luôn số gram tương ứng và tổng % (đúng 100% mới hợp lệ).
// Server vẫn kiểm tra lại toàn bộ khi gửi form (xem validateCustomPercent trong includes/meal-plan.php).
//  3. Bấm "Đổi món": tải ngầm trang mới rồi chỉ thay phần lịch ăn, nên trang không nhảy về chỗ khác.
(function () {
    var box = document.getElementById('nt-custom');
    if (!box) return;

    var radios = document.querySelectorAll('input[name="diet"]');
    var inputs = { p: document.getElementById('nt-p'), c: document.getElementById('nt-c'), f: document.getElementById('nt-f') };
    var total = document.getElementById('nt-total');
    var kcal = parseFloat(box.getAttribute('data-kcal')) || 0;
    var kcalPerGram = { p: 4, c: 4, f: 9 };   // protein 4, tinh bột 4, chất béo 9 kcal/g

    function update() {
        var sum = 0;
        ['p', 'c', 'f'].forEach(function (k) {
            var pct = parseInt(inputs[k].value, 10);
            if (isNaN(pct)) pct = 0;
            sum += pct;
            var grams = Math.round(kcal * pct / 100 / kcalPerGram[k]);
            box.querySelector('[data-for="' + k + '"]').textContent = '≈ ' + grams + ' g';
        });
        total.innerHTML = 'Tổng: <b>' + sum + '%</b>' + (sum === 100 ? '' : ' (cần đúng 100%)');
        total.classList.toggle('is-bad', sum !== 100);
    }

    function toggle() {
        var custom = document.querySelector('input[name="diet"][value="custom"]');
        box.classList.toggle('is-open', !!(custom && custom.checked));
    }

    radios.forEach(function (r) { r.addEventListener('change', toggle); });
    Object.keys(inputs).forEach(function (k) { inputs[k].addEventListener('input', update); });
    update();
})();

(function () {
    var section = document.getElementById('lich-an');
    if (!section) return;

    // Các phần phụ thuộc vào món đang chọn. Không thay form "Thực phẩm không ăn" để giữ các ô người dùng đang tick dở.
    var parts = ['.nt-meals__ctrl', '.nt-meal-grid', '.nt-day'];
    var loading = false;

    section.addEventListener('click', function (event) {
        var link = event.target.closest('.nt-swap');
        // Ctrl/Shift + bấm thì để trình duyệt mở tab mới như liên kết bình thường
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
        event.preventDefault();
        if (loading) return;    // đang tải thì bỏ qua lần bấm tiếp theo
        loading = true;
        link.setAttribute('aria-busy', 'true');
        link.textContent = 'Đang đổi...';

        var slot = link.getAttribute('data-slot');
        fetch(link.href, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(function (html) {
                var fresh = new DOMParser().parseFromString(html, 'text/html');
                // Hết phiên đăng nhập thì trang trả về là trang login, không có lịch ăn
                if (!fresh.querySelector('#lich-an .nt-meal-grid')) throw new Error('Không có lịch ăn');
                parts.forEach(function (selector) {
                    var current = section.querySelector(selector);
                    var next = fresh.querySelector('#lich-an ' + selector);
                    if (current && next) current.replaceWith(next);
                });
                history.replaceState(null, '', link.href);   // tải lại trang hoặc gửi link vẫn đúng món đang xem
                // Nút cũ đã bị thay: đưa focus về nút của đúng bữa đó cho người dùng bàn phím, không cuộn trang
                var again = section.querySelector('.nt-swap[data-slot="' + slot + '"]');
                if (again) again.focus({ preventScroll: true });
                loading = false;
            })
            .catch(function () {
                window.location.href = link.href;            // lỗi mạng hoặc hết phiên: tải cả trang như cũ
            });
    });
})();
