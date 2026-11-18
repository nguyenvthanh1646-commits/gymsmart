// workout-session.js — chế độ "Đang tập" (workout-session.php).
// - Bấm "Xong" ở một set: kiểm tra kg/lặp, đánh dấu set, chạy đồng hồ nghỉ, chép mức tạ sang set kế tiếp.
// - Đồng hồ nghỉ tính theo mốc thời gian kết thúc (không đếm từng giây) nên vẫn đúng khi tab bị ẩn hoặc máy khóa màn hình.
// - Tiến độ chưa lưu được nhớ tạm trong localStorage của trình duyệt (khóa theo người dùng + ngày + lịch + thứ),
//   để lỡ tải lại trang không mất. Đây chỉ là tiện ích cho chính máy này; nhật ký thật chỉ có khi bấm "Lưu buổi tập".
// - Lưu: gửi MỘT lần tới /api/log-session.php các set đã xong; máy chủ tự gộp và kiểm tra lại mọi giá trị.
(function () {
    'use strict';
    var root = document.querySelector('[data-session]');
    var list = root && root.querySelector('.ws-list');
    if (!list) {
        return;
    }
    var REST = Number(root.dataset.rest) || 180;
    var KEY = root.dataset.key;
    var MAX_SETS = 10;
    var form = document.getElementById('ws-form');
    var saveBtn = root.querySelector('[data-save]');
    var msg = root.querySelector('[data-msg]');
    var progress = root.querySelector('[data-progress]');
    var timerBox = root.querySelector('[data-timer]');
    var timerTime = root.querySelector('[data-timer-time]');
    var timerLabel = root.querySelector('[data-timer-label]');
    var saved = false;

    // ===== Nhớ tạm =====
    function store() { try { return window.localStorage; } catch (e) { return null; } }
    function readState() {
        try { var s = store(); return s ? JSON.parse(s.getItem(KEY) || 'null') : null; } catch (e) { return null; }
    }
    function writeState() {
        var state = {};
        list.querySelectorAll('[data-ex]').forEach(function (ex) {
            state[ex.dataset.rowId] = Array.prototype.map.call(ex.querySelectorAll('[data-set]'), function (tr) {
                return { w: tr.querySelector('[name=weight]').value, r: tr.querySelector('[name=reps]').value, d: tr.classList.contains('is-done') };
            });
        });
        try { var s = store(); if (s) { s.setItem(KEY, JSON.stringify(state)); } } catch (e) { /* bỏ qua: trình duyệt chặn lưu */ }
    }
    function clearState() { try { var s = store(); if (s) { s.removeItem(KEY); } } catch (e) { /* bỏ qua */ } }

    // ===== Set =====
    function setDone(tr, done) {
        tr.classList.toggle('is-done', done);
        var btn = tr.querySelector('[data-done]');
        btn.setAttribute('aria-pressed', done ? 'true' : 'false');
        btn.textContent = done ? '✓ Xong' : 'Xong';
    }
    function addSetRow(ex) {
        var body = ex.querySelector('[data-sets]');
        var rows = body.querySelectorAll('[data-set]');
        if (rows.length >= MAX_SETS) { return null; }
        var last = rows[rows.length - 1];
        var tr = last.cloneNode(true);
        var no = rows.length + 1;
        tr.querySelector('.ws-set__no').textContent = no;
        tr.querySelectorAll('input').forEach(function (inp) {
            inp.setAttribute('aria-label', inp.getAttribute('aria-label').replace(/set \d+/, 'set ' + no));
        });
        tr.querySelector('[name=reps]').value = '';
        setDone(tr, false);
        body.appendChild(tr);
        if (body.querySelectorAll('[data-set]').length >= MAX_SETS) { ex.querySelector('[data-add-set]').hidden = true; }
        return tr;
    }
    function valid(tr) {
        var w = tr.querySelector('[name=weight]');
        var r = tr.querySelector('[name=reps]');
        var wv = Number(String(w.value).replace(',', '.'));
        var rv = Number(r.value);
        if (w.value === '' || !isFinite(wv) || wv < 0 || wv > 500) { w.focus(); return 'Nhập mức tạ từ 0 đến 500 kg (0 nếu không dùng tạ).'; }
        if (!Number.isInteger(rv) || rv < 1 || rv > 100) { r.focus(); return 'Nhập số lặp đã làm được (1 đến 100).'; }
        return '';
    }
    function refresh() {
        var all = list.querySelectorAll('[data-set]').length;
        var done = list.querySelectorAll('[data-set].is-done').length;
        progress.textContent = done + '/' + all + ' set xong';
        list.querySelectorAll('[data-ex]').forEach(function (ex) {
            var sets = ex.querySelectorAll('[data-set]');
            ex.classList.toggle('is-complete', sets.length > 0 && ex.querySelectorAll('[data-set].is-done').length === sets.length);
        });
        saveBtn.disabled = saved || done === 0;
        if (!saved) { saveBtn.textContent = done === 0 ? 'Lưu buổi tập' : 'Lưu buổi tập (' + done + ' set)'; }
    }

    // ===== Đồng hồ nghỉ =====
    var endAt = 0;
    var tick = null;
    var audio = null;
    function fmt(sec) { sec = Math.max(0, Math.ceil(sec)); return Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0'); }
    function beep() {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            var osc = audio.createOscillator();
            var gain = audio.createGain();
            osc.frequency.value = 880;
            gain.gain.value = 0.15;
            osc.connect(gain);
            gain.connect(audio.destination);
            osc.start();
            osc.stop(audio.currentTime + 0.35);
        } catch (e) { /* trình duyệt không cho phát tiếng: bỏ qua */ }
        if (navigator.vibrate) { navigator.vibrate([200, 100, 200]); }
    }
    function render() {
        var left = (endAt - Date.now()) / 1000;
        if (left <= 0) {
            stopTimer();
            timerBox.hidden = false;
            timerBox.classList.add('is-over');
            timerLabel.textContent = 'Hết giờ nghỉ';
            timerTime.textContent = 'Vào set tiếp';
            beep();
            return;
        }
        timerTime.textContent = fmt(left);
    }
    function startTimer(seconds) {
        // Tạo AudioContext ngay trong lúc người dùng bấm, vì trình duyệt chỉ cho phát tiếng sau một thao tác của người dùng
        try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { audio = null; }
        endAt = Date.now() + seconds * 1000;
        timerBox.hidden = false;
        timerBox.classList.remove('is-over');
        timerLabel.textContent = 'Nghỉ';
        clearInterval(tick);
        tick = setInterval(render, 250);
        render();
    }
    function stopTimer() { clearInterval(tick); tick = null; }
    root.querySelectorAll('[data-timer-add]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!tick) { return; }
            endAt = Math.max(Date.now() + 5000, endAt + Number(b.dataset.timerAdd) * 1000);
            render();
        });
    });
    root.querySelector('[data-timer-skip]').addEventListener('click', function () { stopTimer(); timerBox.hidden = true; });

    // ===== Bấm trong danh sách =====
    list.addEventListener('click', function (e) {
        var doneBtn = e.target.closest('[data-done]');
        var addBtn = e.target.closest('[data-add-set]');
        if (doneBtn) {
            var tr = doneBtn.closest('[data-set]');
            if (tr.classList.contains('is-done')) {
                setDone(tr, false);
            } else {
                var err = valid(tr);
                msg.textContent = err;
                if (err) { return; }
                setDone(tr, true);
                // Chép mức tạ sang set kế tiếp còn trống để khỏi gõ lại
                var next = tr.nextElementSibling;
                if (next && next.querySelector('[name=weight]').value === '') {
                    next.querySelector('[name=weight]').value = tr.querySelector('[name=weight]').value;
                }
                var remaining = list.querySelectorAll('[data-set]:not(.is-done)').length;
                if (remaining > 0) { startTimer(REST); } else { stopTimer(); timerBox.hidden = true; msg.textContent = 'Xong mọi set. Bấm "Lưu buổi tập" để ghi vào nhật ký.'; }
            }
            writeState();
            refresh();
        } else if (addBtn) {
            var ex = addBtn.closest('[data-ex]');
            var row = addSetRow(ex);
            if (row) { row.querySelector('[name=reps]').focus(); }
            writeState();
            refresh();
        }
    });
    list.addEventListener('input', function (e) { if (e.target.matches('.ws-in')) { writeState(); } });

    // ===== Lưu một lần =====
    saveBtn.addEventListener('click', function () {
        var fd = new FormData(form);   // chỉ có csrf_token
        var i = 0;
        list.querySelectorAll('[data-ex]').forEach(function (ex) {
            var done = ex.querySelectorAll('[data-set].is-done');
            if (done.length === 0) { return; }
            fd.append('rows[' + i + '][row_id]', ex.dataset.rowId);
            Array.prototype.forEach.call(done, function (tr, j) {
                fd.append('rows[' + i + '][sets][' + j + '][weight]', String(tr.querySelector('[name=weight]').value).replace(',', '.'));
                fd.append('rows[' + i + '][sets][' + j + '][reps]', tr.querySelector('[name=reps]').value);
            });
            i++;
        });
        if (i === 0) { return; }
        saveBtn.disabled = true;
        saveBtn.textContent = 'Đang lưu…';
        msg.textContent = '';
        fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json().catch(function () { return { success: false, message: 'Máy chủ trả dữ liệu không đọc được.' }; }); })
            .then(function (data) {
                if (data.success) {
                    saved = true;
                    clearState();
                    stopTimer();
                    timerBox.hidden = true;
                    saveBtn.textContent = '✓ Đã lưu';
                    msg.innerHTML = '';
                    msg.appendChild(document.createTextNode((data.message || 'Đã lưu.') + ' '));
                    // Kỷ lục mới (máy chủ so kỷ lục trước và sau khi lưu)
                    (data.records || []).forEach(function (r) {
                        var b = document.createElement('b');
                        b.className = 'ws-pr';
                        b.textContent = '🏆 Kỷ lục mới: ' + r.name + ' ' + (r.weight > 0 ? String(r.weight).replace('.', ',') + ' kg × ' + r.reps + ' lặp' : r.reps + ' lặp') + '. ';
                        msg.appendChild(b);
                    });
                    var a = document.createElement('a');
                    a.href = '/index.php';
                    a.textContent = 'Về trang chủ';
                    msg.appendChild(a);
                    list.querySelectorAll('[data-done], .ws-in, [data-add-set]').forEach(function (el) { el.disabled = true; });
                } else {
                    msg.textContent = data.message || 'Không lưu được. Bạn thử lại nhé.';
                    refresh();
                }
            })
            .catch(function () {
                msg.textContent = 'Mất kết nối, chưa lưu được. Tiến độ vẫn được giữ trên máy này, bạn bấm lưu lại khi có mạng.';
                refresh();
            });
    });

    // Rời trang khi còn set đã xong mà chưa lưu: hỏi lại
    window.addEventListener('beforeunload', function (e) {
        if (!saved && list.querySelector('[data-set].is-done')) { e.preventDefault(); e.returnValue = ''; }
    });

    // ===== Khôi phục tiến độ chưa lưu =====
    var state = readState();
    if (state) {
        list.querySelectorAll('[data-ex]').forEach(function (ex) {
            var sets = state[ex.dataset.rowId];
            if (!Array.isArray(sets)) { return; }
            while (ex.querySelectorAll('[data-set]').length < Math.min(sets.length, MAX_SETS)) { addSetRow(ex); }
            ex.querySelectorAll('[data-set]').forEach(function (tr, j) {
                if (!sets[j]) { return; }
                tr.querySelector('[name=weight]').value = sets[j].w;
                tr.querySelector('[name=reps]').value = sets[j].r;
                setDone(tr, !!sets[j].d);
            });
        });
        if (list.querySelector('[data-set].is-done')) { msg.textContent = 'Đã khôi phục tiến độ chưa lưu trên máy này.'; }
    }
    refresh();
})();
