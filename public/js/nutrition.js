// nutrition.js — phần tương tác của trang Dinh dưỡng (nutrition.php).
//  1. Bấm "Tùy chỉnh" thì hiện 3 ô nhập tỉ lệ %, chọn kiểu khác thì ẩn đi.
//  2. Khi gõ tỉ lệ: hiện luôn số gram tương ứng và tổng % (đúng 100% mới hợp lệ).
// Server vẫn kiểm tra lại toàn bộ khi gửi form (xem validateCustomPercent trong includes/meal-plan.php).
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
