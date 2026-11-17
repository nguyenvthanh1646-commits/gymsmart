// coach-client.js — biểu đồ trên coach/client.php (hồ sơ học viên phía HLV).
// Mỗi khối <div class="cc-chart" data-client data-api data-field data-unit data-label data-empty> là một biểu đồ đường:
//   data-api   = body-metrics | nutrition-history  (GET /api/<api>.php?client_id=..., máy chủ kiểm tra quyền, sai thì 404)
//   data-field = tên trường số trong mỗi dòng dữ liệu (weightKg, targetKcal...)
// Có 3 trạng thái: đang tải (khung xương), rỗng (chưa có dữ liệu), lỗi (mất mạng / hết phiên / không có quyền).
(function () {
    'use strict';
    var css = getComputedStyle(document.documentElement);
    var accent = css.getPropertyValue('--accent').trim() || '#ff5a1f';
    var line = css.getPropertyValue('--line').trim() || '#e4e8f1';
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var allowed = { 'body-metrics': true, 'nutrition-history': true };

    document.querySelectorAll('.cc-chart').forEach(function (box) {
        var msg = box.querySelector('.cc-chart__msg');
        var wrap = box.querySelector('.adm-chart');
        var skel = box.querySelector('.ch-skel');
        var canvas = box.querySelector('canvas');
        var api = box.dataset.api, field = box.dataset.field, unit = box.dataset.unit || '';
        function show(text) {
            skel.hidden = true;
            msg.textContent = text;
            msg.hidden = false;
        }
        if (!allowed[api]) { show('Biểu đồ không hợp lệ.'); return; }

        fetch('/api/' + api + '.php?client_id=' + encodeURIComponent(box.dataset.client), {
            credentials: 'same-origin', headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            return res.json().catch(function () { return { success: false, error: 'Máy chủ trả dữ liệu không đọc được.' }; })
                .then(function (body) { return { ok: res.ok, body: body }; });
        }).then(function (r) {
            if (!r.ok || !r.body.success) { show(r.body.error || 'Không tải được dữ liệu.'); return; }
            // Mỗi ngày lấy giá trị cuối cùng (ví dụ cùng ngày có mục tiêu tự tính và mục tiêu HLV đặt thì lấy dòng sau)
            var byDate = {};
            r.body.data.forEach(function (d) { if (d[field] !== null && d[field] !== undefined) byDate[d.date] = d[field]; });
            var dates = Object.keys(byDate).sort();
            if (dates.length === 0) { show(box.dataset.empty || 'Chưa có dữ liệu.'); return; }
            if (typeof Chart === 'undefined') {
                show('Không tải được thư viện biểu đồ. Giá trị gần nhất: ' + byDate[dates[dates.length - 1]] + ' ' + unit + '.');
                return;
            }
            skel.hidden = true;
            msg.hidden = true;
            wrap.hidden = false;
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: dates.map(function (d) { var p = d.split('-'); return p[2] + '/' + p[1]; }),
                    datasets: [{ label: box.dataset.label, data: dates.map(function (d) { return byDate[d]; }),
                        borderColor: accent, backgroundColor: accent, tension: 0.25, pointRadius: 3, stepped: api === 'nutrition-history' }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: reduce ? false : { duration: 250 },
                    plugins: { legend: { display: false } },
                    scales: { y: { grid: { color: line }, ticks: { callback: function (v) { return v + ' ' + unit; } } }, x: { grid: { display: false } } }
                }
            });
            // Mô tả bằng chữ cho trình đọc màn hình
            var first = dates[0], last = dates[dates.length - 1];
            canvas.setAttribute('aria-label', box.dataset.label + ' từ ' + byDate[first] + ' ' + unit + ' (' + first + ') đến '
                + byDate[last] + ' ' + unit + ' (' + last + '), ' + dates.length + ' mốc.');
        }).catch(function () {
            show('Mất kết nối, không tải được dữ liệu. Tải lại trang để thử lại.');
        });
    });
})();
