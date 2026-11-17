// coach-client.js — biểu đồ cân nặng của học viên trên coach/client.php.
// Dữ liệu: GET /api/body-metrics.php?client_id=...  (máy chủ kiểm tra HLV có quyền xem học viên này, sai thì 404).
// Có 3 trạng thái: đang tải (khung xương), rỗng (chưa có số đo), lỗi (mất mạng / hết phiên / không có quyền).
(function () {
    'use strict';
    var box = document.getElementById('cc-chart');
    if (!box) return;
    var msg = document.getElementById('cc-chart-msg');
    var wrap = box.querySelector('.adm-chart');
    var skel = box.querySelector('.ch-skel');

    function show(text) {
        skel.hidden = true;
        msg.textContent = text;
        msg.hidden = false;
    }

    fetch('/api/body-metrics.php?client_id=' + encodeURIComponent(box.dataset.client), {
        credentials: 'same-origin', headers: { 'Accept': 'application/json' }
    }).then(function (res) {
        return res.json().catch(function () { return { success: false, error: 'Máy chủ trả dữ liệu không đọc được.' }; })
            .then(function (body) { return { ok: res.ok, body: body }; });
    }).then(function (r) {
        if (!r.ok || !r.body.success) {
            show(r.body.error || 'Không tải được số đo.');
            return;
        }
        var rows = r.body.data.filter(function (d) { return d.weightKg !== null; });
        if (rows.length === 0) {
            show('Học viên chưa nhập số đo nào. Nhắc học viên cân và ghi ở trang Tiến trình mỗi tuần.');
            return;
        }
        if (typeof Chart === 'undefined') {
            show('Không tải được thư viện biểu đồ. Cân nặng gần nhất: ' + rows[rows.length - 1].weightKg + ' kg.');
            return;
        }
        skel.hidden = true;
        msg.hidden = true;
        wrap.hidden = false;
        var css = getComputedStyle(document.documentElement);
        var accent = css.getPropertyValue('--accent').trim() || '#ff5a1f';
        var line = css.getPropertyValue('--line').trim() || '#e4e8f1';
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        new Chart(document.getElementById('cc-canvas'), {
            type: 'line',
            data: {
                labels: rows.map(function (d) { var p = d.date.split('-'); return p[2] + '/' + p[1]; }),
                datasets: [{ label: 'Cân nặng (kg)', data: rows.map(function (d) { return d.weightKg; }),
                    borderColor: accent, backgroundColor: accent, tension: 0.25, pointRadius: 3 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, animation: reduce ? false : { duration: 250 },
                plugins: { legend: { display: false } },
                scales: { y: { grid: { color: line }, ticks: { callback: function (v) { return v + ' kg'; } } }, x: { grid: { display: false } } }
            }
        });
        // Mô tả bằng chữ cho trình đọc màn hình
        var first = rows[0], last = rows[rows.length - 1];
        document.getElementById('cc-canvas').setAttribute('aria-label',
            'Cân nặng từ ' + first.weightKg + ' kg (' + first.date + ') đến ' + last.weightKg + ' kg (' + last.date + '), ' + rows.length + ' lần đo.');
    }).catch(function () {
        show('Mất kết nối, không tải được số đo. Tải lại trang để thử lại.');
    });
})();
