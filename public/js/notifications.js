// notifications.js — chuông thông báo (includes/notif-bell.php, A-44).
// - Bấm chuông: mở khung, tải 15 thông báo mới nhất từ /api/notifications.php. Không có JS thì chuông là liên kết tới /notifications.php.
// - Bấm một thông báo: gửi POST đánh dấu đã đọc (kèm csrf_token) rồi chuyển tới trang liên quan. Lỗi mạng thì vẫn chuyển trang.
// - Mọi nội dung gán bằng textContent (không innerHTML) nên chữ trong thông báo không thể chèn mã HTML.
// - Cập nhật số trên chuông mỗi 60 giây, chỉ khi tab đang hiển thị (không gọi máy chủ khi tab ẩn).
(function () {
    'use strict';
    var root = document.querySelector('[data-ntf]');
    if (!root) {
        return;
    }
    var btn = root.querySelector('[data-ntf-btn]');
    var panel = root.querySelector('[data-ntf-panel]');
    var list = root.querySelector('[data-ntf-list]');
    var state = root.querySelector('[data-ntf-state]');
    var badge = root.querySelector('[data-ntf-badge]');
    var allBtn = root.querySelector('[data-ntf-all]');
    var csrf = root.dataset.csrf || '';
    var API = '/api/notifications.php';

    function setCount(n) {
        n = Number(n) || 0;
        badge.hidden = n === 0;
        badge.textContent = n > 99 ? '99+' : String(n);
        btn.setAttribute('aria-label', n > 0 ? 'Thông báo, ' + n + ' chưa đọc' : 'Thông báo');
        allBtn.hidden = n === 0;
    }
    function ago(at) {
        var t = new Date(String(at).replace(' ', 'T'));
        var s = Math.round((Date.now() - t.getTime()) / 1000);
        if (!isFinite(s)) { return ''; }
        if (s < 60) { return 'vừa xong'; }
        if (s < 3600) { return Math.floor(s / 60) + ' phút trước'; }
        if (s < 86400) { return Math.floor(s / 3600) + ' giờ trước'; }
        return t.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }
    function post(data) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); });
    }
    function render(items) {
        while (list.firstChild) { list.removeChild(list.firstChild); }
        if (!items.length) {
            state.textContent = 'Chưa có thông báo nào.';
            state.hidden = false;
            return;
        }
        state.hidden = true;
        items.forEach(function (n) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.className = 'ntf__item' + (n.read ? '' : ' is-unread');
            a.href = n.link || '/notifications.php';
            var t = document.createElement('b');
            t.textContent = n.title;
            a.appendChild(t);
            if (!n.read) {
                var sr = document.createElement('span');
                sr.className = 'ntf__sr';
                sr.textContent = ' (chưa đọc)';
                t.appendChild(sr);
            }
            if (n.body) {
                var b = document.createElement('span');
                b.textContent = n.body;
                a.appendChild(b);
            }
            var time = document.createElement('small');
            time.textContent = ago(n.created_at);
            a.appendChild(time);
            a.addEventListener('click', function (e) {
                if (n.read) { return; }                          // đã đọc: đi thẳng theo liên kết
                e.preventDefault();
                post({ action: 'read', id: n.id })
                    .catch(function () { return null; })
                    .then(function () { window.location.href = a.href; });
            });
            li.appendChild(a);
            list.appendChild(li);
        });
    }
    function load() {
        state.textContent = 'Đang tải…';
        state.hidden = false;
        fetch(API, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) { throw new Error('bad'); }
                setCount(res.data.unread);
                render(res.data.items || []);
            })
            .catch(function () { state.textContent = 'Chưa tải được thông báo. Bạn thử lại sau.'; state.hidden = false; });
    }
    function open() {
        panel.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        load();
    }
    function close(returnFocus) {
        if (panel.hidden) { return; }
        panel.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
        if (returnFocus) { btn.focus(); }
    }
    btn.addEventListener('click', function (e) {
        e.preventDefault();                                     // có JS thì mở khung thay vì sang trang /notifications.php
        if (panel.hidden) { open(); } else { close(false); }
    });
    document.addEventListener('click', function (e) { if (!root.contains(e.target)) { close(false); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(true); } });
    allBtn.addEventListener('click', function () {
        post({ action: 'read_all' }).then(function (res) { if (res && res.success) { load(); } }).catch(function () { /* bỏ qua */ });
    });

    // Cập nhật số trên chuông định kỳ, chỉ khi tab đang hiển thị
    function refreshCount() {
        if (document.hidden) { return; }
        fetch(API + '?count=1', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) { if (res && res.success) { setCount(res.data.unread); } })
            .catch(function () { /* mất mạng: thử lại lần sau */ });
    }
    setInterval(refreshCount, 60000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { refreshCount(); } });
    allBtn.hidden = badge.hidden;
})();
