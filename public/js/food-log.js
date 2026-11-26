// food-log.js — trang Nhật ký ăn (food-log.php).
// - Chọn thực phẩm có đơn vị quen (quả trứng, thìa dầu...): gợi ý số gam của 1 đơn vị vào ô khối lượng nếu ô đang trống.
// - Form có data-confirm: hỏi lại trước khi xóa.
// Không có JS trang vẫn dùng được đầy đủ (chỉ thiếu phần gợi ý).
(function () {
    'use strict';
    var food = document.getElementById('fl-food');
    var grams = document.getElementById('fl-grams');
    var hint = document.querySelector('[data-fl-hint]');
    var baseHint = hint ? hint.textContent : '';
    if (food && grams && hint) {
        food.addEventListener('change', function () {
            var opt = food.options[food.selectedIndex];
            var unit = opt && opt.getAttribute('data-unit');
            var g = opt && Number(opt.getAttribute('data-unit-g'));
            if (unit && g) {
                hint.textContent = '1 ' + unit + ' ≈ ' + g + ' g. ' + baseHint;
                if (grams.value === '') { grams.value = g; }
            } else {
                hint.textContent = baseHint;
            }
        });
    }
    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });
})();
