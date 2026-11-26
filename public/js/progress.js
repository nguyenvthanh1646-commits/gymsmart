/**
 * progress.js — trang Tiến trình (progress.php).
 * 1) Hộp xác nhận trước khi xóa số đo (form nào có data-confirm).
 * 2) Biểu đồ Chart.js (cân nặng; mỡ + vòng eo; khối lượng cơ bắp ước tính): lấy dữ liệu từ /api/body-metrics.php,
 *    lọc theo 30 ngày / 90 ngày / tất cả ngay trên trình duyệt (không gọi lại API).
 * Nếu JavaScript hoặc Chart.js không tải được, trang vẫn dùng bình thường (chỉ không có biểu đồ).
 */
(function () {
    'use strict';

    /* ---------- 1. Xác nhận xóa ---------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.getAttribute) { return; }
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    /* ---------- 2. Biểu đồ ---------- */
    var root = document.getElementById('pr-charts');
    if (!root) { return; }

    var statusEl = document.getElementById('pr-status');
    var buttons = root.querySelectorAll('.pr-range__btn');
    var COLORS = { weight: '#f26a1b', fat: '#6366f1', waist: '#0e9384', lean: '#0e9384', fatMass: '#f5a524' };
    var charts = {};
    var data = { body: [] };
    var days = 90; // khoảng đang chọn; 0 = tất cả

    function showStatus(msg) {
        statusEl.textContent = msg || '';
        statusEl.hidden = !msg;
    }

    function dateOnly(s) { return String(s).slice(0, 10); }

    function label(s) { // 2026-09-20 -> 20/09
        var p = dateOnly(s).split('-');
        return p[2] + '/' + p[1];
    }

    function cutoff() {
        if (days === 0) { return null; }
        var d = new Date();
        d.setHours(0, 0, 0, 0);
        d.setDate(d.getDate() - days);
        var m = ('0' + (d.getMonth() + 1)).slice(-2), day = ('0' + d.getDate()).slice(-2);
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function inRange(rows) {
        var c = cutoff();
        return c === null ? rows : rows.filter(function (r) { return dateOnly(r.date) >= c; });
    }

    function hasValue(rows, key) {
        return rows.some(function (r) { return r[key] !== null && r[key] !== undefined; });
    }

    // Đồng bộ số ngày nhiều năm: nhãn có năm nếu dữ liệu trải qua nhiều năm
    function labels(rows) {
        var years = {};
        rows.forEach(function (r) { years[dateOnly(r.date).slice(0, 4)] = true; });
        var multi = Object.keys(years).length > 1;
        return rows.map(function (r) { return multi ? label(r.date) + '/' + dateOnly(r.date).slice(2, 4) : label(r.date); });
    }

    function line(name, key, rows, color, axis) {
        return {
            label: name, data: rows.map(function (r) { return r[key]; }), borderColor: color, backgroundColor: color,
            borderWidth: 2.5, pointRadius: rows.length > 40 ? 0 : 3.5, pointHoverRadius: 5, tension: .25, spanGaps: true, yAxisID: axis || 'y'
        };
    }

    function scale(title, side) {
        return {
            position: side || 'left', title: { display: true, text: title, color: '#667085', font: { size: 11 } },
            grid: { color: side === 'right' ? 'transparent' : '#eef0f4' }, ticks: { color: '#667085', maxTicksLimit: 6 }
        };
    }

    function baseOptions(extra) {
        var o = {
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: '#344054' } } },
            scales: { x: { grid: { display: false }, ticks: { color: '#667085', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } } }
        };
        Object.keys(extra).forEach(function (k) { o.scales[k] = extra[k]; });
        return o;
    }

    function paint(id, type, labs, datasets, scales, hideLegend) {
        var canvas = document.getElementById(id);
        var card = canvas.closest('.pr-chart');
        var box = card.querySelector('.pr-chart__box');
        var empty = card.querySelector('.pr-chart__empty');
        var ok = labs.length > 0 && datasets.length > 0;
        box.hidden = !ok;
        empty.hidden = ok;
        if (charts[id]) { charts[id].destroy(); delete charts[id]; }
        if (!ok) { return; }
        var opts = baseOptions(scales);
        if (hideLegend) { opts.plugins.legend.display = false; }
        charts[id] = new window.Chart(canvas, { type: type, data: { labels: labs, datasets: datasets }, options: opts });
    }

    function render() {
        var body = inRange(data.body);

        // Cân nặng
        var wRows = body.filter(function (r) { return r.weightKg !== null; });
        paint('pr-c-weight', 'line', labels(wRows), wRows.length ? [line('Cân nặng (kg)', 'weightKg', wRows, COLORS.weight)] : [],
            { y: scale('kg') }, true);

        // Mỡ (trục trái, %) và vòng eo (trục phải, cm)
        var bRows = body.filter(function (r) { return r.bodyFatPercent !== null || r.waistCm !== null; });
        // Chỉ tạo trục cho đường có dữ liệu: chưa nhập % mỡ thì không hiện trục % trống (0 đến 1) bên trái
        var bSets = [];
        var hasFat = hasValue(bRows, 'bodyFatPercent');
        var hasWaist = hasValue(bRows, 'waistCm');
        var bScales = {};
        if (hasFat) {
            bSets.push(line('Mỡ cơ thể (%)', 'bodyFatPercent', bRows, COLORS.fat, 'y'));
            bScales.y = scale('%');
        }
        if (hasWaist) {
            var waistAxis = hasFat ? 'y1' : 'y';   // có cả hai thì vòng eo dùng trục phải
            bSets.push(line('Vòng eo (cm)', 'waistCm', bRows, COLORS.waist, waistAxis));
            bScales[waistAxis] = scale('cm', hasFat ? 'right' : 'left');
        }
        paint('pr-c-body', 'line', labels(bRows), bSets, bScales);

        // Khối lượng cơ bắp ước tính = cân nặng × (1 − %mỡ); khối lượng mỡ = cân nặng − khối lượng nạc
        var mRows = body.filter(function (r) { return r.weightKg !== null && r.bodyFatPercent !== null; }).map(function (r) {
            var fatKg = r.weightKg * r.bodyFatPercent / 100;
            return { date: r.date, leanKg: Math.round((r.weightKg - fatKg) * 10) / 10, fatKg: Math.round(fatKg * 10) / 10 };
        });
        var mSets = mRows.length ? [line('Khối lượng nạc / cơ bắp (kg)', 'leanKg', mRows, COLORS.lean), line('Khối lượng mỡ (kg)', 'fatKg', mRows, COLORS.fatMass)] : [];
        paint('pr-c-muscle', 'line', labels(mRows), mSets, { y: scale('kg') });
    }

    // Đọc phản hồi dạng chữ rồi tự parse JSON, để khi server trả HTML (404, lỗi PHP, trang đăng nhập...) vẫn báo được nguyên nhân rõ ràng
    function getJson(url) {
        var ctrl = window.AbortController ? new AbortController() : null;
        var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, 15000) : null; // 15 giây không phản hồi thì báo lỗi, không để trống mãi
        return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, signal: ctrl ? ctrl.signal : undefined }).then(function (res) {
            if (timer) { clearTimeout(timer); }
            return res.text().then(function (text) {
                var j = null;
                try { j = JSON.parse(text); } catch (e) { /* không phải JSON */ }
                if (j === null) {
                    var hint = res.status === 404 ? 'không tìm thấy file (kiểm tra đã chép api/ và includes/api.php chưa)'
                        : res.redirected ? 'bị chuyển sang trang khác (phiên đăng nhập hết hạn?)'
                        : 'server trả HTML thay vì JSON (xem lỗi PHP trong log Apache)';
                    throw new Error(url + ' trả mã ' + res.status + ': ' + hint);
                }
                if (!res.ok || !j.success) { throw new Error(j.error || ('Lỗi ' + res.status)); }
                return j.data;
            });
        });
    }

    function setRange(n) {
        days = n;
        Array.prototype.forEach.call(buttons, function (b) {
            b.setAttribute('aria-pressed', Number(b.getAttribute('data-days')) === n ? 'true' : 'false');
        });
        render();
    }

    Array.prototype.forEach.call(buttons, function (b) {
        b.addEventListener('click', function () { setRange(Number(b.getAttribute('data-days'))); });
    });

    function start() {
        if (!window.Chart) { showStatus('Không tải được thư viện biểu đồ. Bạn vẫn xem được số liệu ở bảng bên dưới.'); return; }
        window.Chart.defaults.font.family = 'inherit';
        showStatus('Đang tải biểu đồ...');
        getJson('/api/body-metrics.php').then(function (rows) {
            showStatus('');
            data.body = rows;
            render();
        }).catch(function (err) {
            if (window.console) { console.error('progress.js:', err); }
            showStatus('Không tải được dữ liệu biểu đồ: ' + (err && err.name === 'AbortError' ? 'máy chủ không phản hồi sau 15 giây' : err.message));
        });
    }

    // Script này có thuộc tính defer và nằm sau Chart.js nên khi chạy tới đây thư viện đã sẵn sàng: chạy luôn,
    // KHÔNG chờ sự kiện "load" (sự kiện này bị trễ nếu có tài nguyên nào đó trên trang tải chậm, làm biểu đồ trống mãi).
    start();
})();
