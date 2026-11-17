// coach-chat.js — khung nhắn tin HLV ↔ học viên (my-coach.php?tab=messages và coach/messages.php).
//
// Cách chạy:
//   - Mở trang: GET /api/coach-messages.php?with=ID lấy 100 tin gần nhất.
//   - Cứ 15 giây hỏi tin mới (?after=id cuối) NHƯNG chỉ khi tab đang hiển thị (tab ẩn thì dừng, quay lại thì hỏi ngay).
//     Không dùng WebSocket để giữ dự án đơn giản; 15 giây là đủ cho trao đổi giữa HLV và học viên.
//   - Gửi: POST /api/coach-message-send.php (with, content, csrf_token). Enter để gửi, Shift+Enter xuống dòng.
// An toàn: nội dung tin gán bằng textContent (không innerHTML) nên chèn mã HTML vào tin nhắn không chạy được.
(function () {
    'use strict';
    var box = document.getElementById('ch-chat');
    if (!box) return;
    var withId = box.dataset.with;
    var csrf = box.dataset.csrf;
    var log = document.getElementById('ch-log');
    var form = document.getElementById('ch-form');
    var input = document.getElementById('ch-input');
    var status = document.getElementById('ch-status');
    var counter = document.getElementById('ch-counter');
    var sendBtn = form ? form.querySelector('button[type="submit"]') : null;
    var lastId = 0;
    var lastDay = '';
    var timer = null;
    var loaded = false;
    var POLL_MS = 15000;
    var MAX = 1000;

    function setStatus(text, isErr) {
        if (!status) return;
        status.textContent = text || '';
        status.classList.toggle('is-err', !!isErr);
    }

    function dayLabel(at) {
        var d = at.slice(0, 10);
        var today = new Date();
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var iso = function (x) { return x.getFullYear() + '-' + pad(x.getMonth() + 1) + '-' + pad(x.getDate()); };
        if (d === iso(today)) return 'Hôm nay';
        var y = new Date(today); y.setDate(today.getDate() - 1);
        if (d === iso(y)) return 'Hôm qua';
        var p = d.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function add(m) {
        if (m.id <= lastId) return;            // đã có (gửi xong rồi lại nhận trong lần hỏi tiếp theo)
        lastId = m.id;
        var day = m.at.slice(0, 10);
        if (day !== lastDay) {
            lastDay = day;
            var sep = document.createElement('p');
            sep.className = 'ch-day';
            sep.textContent = dayLabel(m.at);
            log.appendChild(sep);
        }
        var el = document.createElement('div');
        el.className = 'ch-msg' + (m.mine ? ' is-me' : '');
        el.textContent = m.content;
        var t = document.createElement('time');
        t.dateTime = m.at.replace(' ', 'T');
        t.textContent = m.at.slice(11, 16) + (m.mine ? ' · Bạn' : '');
        el.appendChild(t);
        log.appendChild(el);
    }

    function render(list) {
        var nearBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 80;
        if (!loaded) {
            log.textContent = '';                 // bỏ khung xương đang tải
            loaded = true;
            if (list.length === 0) {
                var empty = document.createElement('p');
                empty.className = 'ch-day';
                empty.id = 'ch-empty';
                empty.textContent = 'Chưa có tin nhắn. Hãy gửi lời chào đầu tiên!';
                log.appendChild(empty);
            }
        }
        if (list.length) {
            var e = document.getElementById('ch-empty');
            if (e) e.remove();
        }
        list.forEach(add);
        if (list.length && (nearBottom || list.some(function (m) { return m.mine; }))) {
            log.scrollTop = log.scrollHeight;
        }
    }

    function poll() {
        clearTimeout(timer);
        if (document.hidden) return;              // tab ẩn: không hỏi, chờ visibilitychange
        fetch('/api/coach-messages.php?with=' + encodeURIComponent(withId) + '&after=' + lastId, {
            credentials: 'same-origin', headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            return res.json().then(function (b) { return { ok: res.ok, code: res.status, body: b }; },
                function () { return { ok: false, code: res.status, body: {} }; });
        }).then(function (r) {
            if (r.ok && r.body.success) {
                render(r.body.data.messages);
                setStatus('');
                timer = setTimeout(poll, POLL_MS);
                return;
            }
            if (r.code === 401) { setStatus('Phiên đăng nhập đã hết. Hãy đăng nhập lại.', true); return; }
            if (r.code === 404) { closeChat(r.body.error || 'Cuộc trò chuyện đã kết thúc.'); return; }
            if (!loaded) { log.textContent = ''; loaded = true; }
            setStatus(r.body.error || 'Không tải được tin nhắn. Sẽ thử lại.', true);
            timer = setTimeout(poll, POLL_MS);
        }).catch(function () {
            setStatus('Mất kết nối. Sẽ thử lại sau 15 giây.', true);
            timer = setTimeout(poll, POLL_MS);
        });
    }

    function closeChat(text) {
        clearTimeout(timer);
        if (!loaded) { log.textContent = ''; loaded = true; }
        if (form) form.hidden = true;
        setStatus(text, true);
    }

    function updateCounter() {
        if (!counter) return;
        var n = Array.from(input.value).length;
        counter.textContent = n + '/' + MAX;
        counter.classList.toggle('is-err', n > MAX);
    }

    if (form) {
        input.addEventListener('input', function () {
            updateCounter();
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 140) + 'px';   // ô nhập tự cao lên tới 140px
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {      // isComposing: đang gõ dấu tiếng Việt bằng bộ gõ
                e.preventDefault();
                form.requestSubmit();
            }
        });
        form.addEventListener('submit', function (e) {
            e.preventDefault();                   // gửi bằng fetch, main.js thấy defaultPrevented nên không khóa nút
            var text = input.value.trim();
            if (!text) { input.focus(); return; }
            if (Array.from(text).length > MAX) { setStatus('Tin nhắn tối đa ' + MAX + ' ký tự.', true); return; }
            sendBtn.disabled = true;
            sendBtn.textContent = 'Đang gửi…';
            var data = new URLSearchParams({ with: withId, content: text, csrf_token: csrf });
            fetch('/api/coach-message-send.php', { method: 'POST', credentials: 'same-origin', body: data })
                .then(function (res) {
                    return res.json().then(function (b) { return { ok: res.ok, code: res.status, body: b }; },
                        function () { return { ok: false, code: res.status, body: {} }; });
                })
                .then(function (r) {
                    if (r.ok && r.body.success) {
                        input.value = '';
                        input.style.height = '';
                        updateCounter();
                        render([r.body.item]);
                        setStatus('');
                    } else if (r.code === 404) {
                        closeChat(r.body.message || 'Cuộc trò chuyện đã kết thúc.');
                    } else {
                        setStatus(r.body.message || 'Không gửi được, thử lại.', true);
                    }
                })
                .catch(function () { setStatus('Mất kết nối, tin chưa được gửi.', true); })
                .finally(function () {
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Gửi';
                    input.focus();
                });
        });
        updateCounter();
    }

    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    poll();
})();
