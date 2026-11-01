// home-user.js — trang chủ sau đăng nhập: ghi nhật ký nhanh trong thẻ "Hôm nay".
(function () {
    'use strict';

    var todayCard = document.getElementById('hom-nay');
    if (!todayCard) {
        return;
    }

    // Bấm "Ghi nhanh": mở hoặc đóng form của đúng bài đó
    todayCard.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-log-toggle]');
        if (!toggle) {
            return;
        }
        var form = document.getElementById(toggle.getAttribute('aria-controls'));
        form.hidden = !form.hidden;
        toggle.setAttribute('aria-expanded', form.hidden ? 'false' : 'true');
        if (!form.hidden) {
            form.querySelector('[name="sets"]').focus();
        }
    });

    // Gửi form bằng fetch tới API có sẵn, server vẫn tự kiểm tra lại mọi giá trị
    todayCard.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-log-form]');
        if (!form) {
            return;
        }
        e.preventDefault();
        var msg = form.querySelector('[data-log-msg]');
        var save = form.querySelector('[type="submit"]');
        save.disabled = true;
        msg.textContent = 'Đang lưu...';

        fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    markLogged(form);
                } else {
                    msg.textContent = data.message || 'Không lưu được. Bạn thử lại nhé.';
                    save.disabled = false;
                }
            })
            .catch(function () {
                msg.textContent = 'Không lưu được. Kiểm tra kết nối rồi thử lại.';
                save.disabled = false;
            });
    });

    // Lưu xong: đổi nút thành "Đã ghi" để không ghi trùng, rồi cập nhật số liệu trên trang mà không cần tải lại
    function markLogged(form) {
        var row = form.closest('[data-log-row]');
        var toggle = row.querySelector('[data-log-toggle]');
        var done = document.createElement('span');
        done.className = 'du-ex__done';
        done.textContent = 'Đã ghi';
        done.tabIndex = -1;
        toggle.replaceWith(done);
        form.remove();
        done.focus(); // nút vừa bấm đã biến mất nên chuyển focus sang chữ thay thế

        var countEl = todayCard.querySelector('[data-today-count]');
        var total = Number(countEl.dataset.total);
        var count = Number(countEl.textContent) + 1;
        countEl.textContent = count;
        updateTopArea(count, total);
        updateWeek(count);
    }

    function updateTopArea(count, total) {
        var primary = document.querySelector('[data-dash-primary]');
        var lead = document.querySelector('[data-dash-lead]');
        if (count >= total) {
            primary.textContent = 'Xem tiến trình';
            primary.setAttribute('href', '/progress.php');
            lead.textContent = 'Bạn đã ghi đủ buổi tập hôm nay.';
        } else {
            primary.textContent = 'Tiếp tục buổi tập hôm nay';
        }
    }

    // Thẻ "Tuần này": bài đầu tiên được ghi làm buổi hôm nay thành "đã tập"
    function updateWeek(count) {
        var week = document.querySelector('[data-week]');
        var today = document.querySelector('[data-week-today]');
        if (!week || !today) {
            return;
        }
        today.querySelector('[data-week-logged]').textContent = count;
        if (count !== 1) {
            return;
        }
        today.className = today.className.replace('is-today-pending', 'is-done');
        today.querySelector('[data-week-mark]').textContent = '✓';
        today.querySelector('[data-week-state]').textContent = 'đã tập';

        var done = Number(week.dataset.done) + 1;
        var planned = Number(week.dataset.planned);
        var percent = Math.round(done / planned * 100);
        week.dataset.done = done;
        week.querySelector('[data-week-done]').textContent = done;
        var bar = week.querySelector('[role="progressbar"]');
        bar.setAttribute('aria-valuenow', percent);
        bar.setAttribute('aria-label', 'Đã tập ' + done + ' trên ' + planned + ' buổi trong tuần');
        bar.firstElementChild.style.setProperty('--du-value', percent + '%');
    }
})();
