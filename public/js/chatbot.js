/**
 * chatbot.js — hành vi của khung chat (markup: includes/chatbot-widget.php, giao diện: css/chatbot.css).
 *
 * Luồng chính:  người dùng gõ → send() → fetch POST /api/chatbot.php (JSON + header X-CSRF-Token)
 *               → hiện "đang soạn" → nhận { reply, links, draft } → vẽ tin nhắn.
 *
 * Lưu ý bảo mật: mọi chữ từ server/AI đều đưa vào trang bằng textContent (không innerHTML),
 * nên AI có trả về thẻ <script> cũng chỉ hiện như chữ thường (không bị XSS). Markdown cơ bản (**đậm**, danh sách,
 * xuống dòng) được dựng bằng các phần tử DOM, không phải bằng cách chèn HTML.
 *
 * Hai chế độ (cfg.mode): "user" = huấn luyện viên ảo, "admin" = trợ lý quản trị (có thêm thẻ "Soạn bài tập").
 * Lịch sử chat lưu trong sessionStorage của trình duyệt (mất khi đóng tab), mỗi tài khoản + chế độ một khóa riêng.
 */
(function () {
    'use strict';

    var root = document.getElementById('gschat');
    var cfgEl = document.getElementById('gc-cfg');
    if (!root || !cfgEl || root.getAttribute('data-ready')) { return; }   // đã khởi tạo rồi thì thôi
    root.setAttribute('data-ready', '1');

    var cfg;
    try { cfg = JSON.parse(cfgEl.textContent); } catch (e) { return; }
    var csrf = root.getAttribute('data-csrf') || '';
    var isAdmin = cfg.mode === 'admin';

    var $ = function (id) { return document.getElementById(id); };
    var fab = $('gc-fab'), panel = $('gc-panel'), body = $('gc-body'), form = $('gc-form');
    var input = $('gc-input'), sendBtn = $('gc-send'), countEl = $('gc-count');
    var statusText = $('gc-status-text'), nudge = $('gc-nudge'), dot = $('gc-dot'), toast = $('gc-toast');
    var tabsEl = $('gc-tabs'), persBox = $('gc-pers');

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var KEY = 'gc:' + cfg.mode + ':' + cfg.uid;          // khóa lưu lịch sử
    var DRAFT_KEY = 'gc:draft:' + cfg.uid;               // bản nháp bài tập chờ điền vào form
    var NUDGE_KEY = 'gc:nudged';

    var state = { open: false, busy: false, tab: 'chat', msgs: [], lastText: '', lastTask: 'chat', opener: null };

    // ---------- Lưu trữ (có thể bị chặn ở chế độ ẩn danh → luôn bọc try/catch) ----------
    function store(kind) { try { return window[kind]; } catch (e) { return null; } }
    function load(key, kind) {
        try { var s = store(kind || 'sessionStorage'); return s ? JSON.parse(s.getItem(key) || 'null') : null; } catch (e) { return null; }
    }
    function save(key, val, kind) {
        try { var s = store(kind || 'sessionStorage'); if (s) { s.setItem(key, JSON.stringify(val)); } } catch (e) { /* bỏ qua */ }
    }
    function drop(key, kind) { try { var s = store(kind || 'sessionStorage'); if (s) { s.removeItem(key); } } catch (e) { /* bỏ qua */ } }
    function persist() { save(KEY, state.msgs.slice(-40)); }

    // ---------- Dựng phần tử ----------
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text !== undefined) { n.textContent = text; }
        return n;
    }
    function svgUse(id, cls) {                           // <svg><use href="#id"></svg> (logo đã có sẵn trong trang)
        var ns = 'http://www.w3.org/2000/svg';
        var s = document.createElementNS(ns, 'svg');
        s.setAttribute('viewBox', '0 0 64 64');
        if (cls) { s.setAttribute('class', cls); }
        var u = document.createElementNS(ns, 'use');
        u.setAttribute('href', '#' + id);
        s.appendChild(u);
        return s;
    }
    function avatar() {
        var a = el('div', 'gc-av');
        a.setAttribute('aria-hidden', 'true');
        a.appendChild(svgUse(isAdmin ? 'gc-glyph' : 'gc-logo'));
        return a;
    }
    function scrollDown(force) {
        var near = body.scrollHeight - body.scrollTop - body.clientHeight < 160;
        if (force || near) { body.scrollTop = body.scrollHeight; }
    }
    function showToast(text) {
        toast.textContent = text;
        toast.hidden = false;
        clearTimeout(showToast.t);
        showToast.t = setTimeout(function () { toast.hidden = true; }, 4200);
    }
    function safeUrl(u) { return typeof u === 'string' && u.charAt(0) === '/' && u.charAt(1) !== '/'; }  // chỉ link nội bộ

    // ---------- Vẽ nội dung chữ: đoạn văn, danh sách, **đậm**, hiện dần từng từ. Không bao giờ dùng innerHTML ----------
    function fillRich(container, text, animate) {
        var lines = String(text).replace(/\r/g, '').split('\n');
        var total = String(text).split(/\s+/).length;
        var step = animate && !reduceMotion ? Math.min(0.03, 1.5 / Math.max(total, 1)) : 0;   // tối đa ~1,5 giây cho cả tin
        var n = 0, list = null, listTag = '', para = null;

        function words(target, str) {
            var parts = str.split(/(\s+)/);
            for (var i = 0; i < parts.length; i++) {
                if (parts[i] === '') { continue; }
                if (/^\s+$/.test(parts[i])) { target.appendChild(document.createTextNode(' ')); continue; }
                if (step === 0) { target.appendChild(document.createTextNode(parts[i])); continue; }
                var w = el('span', 'gc-w', parts[i]);
                w.style.setProperty('--d', (n * step).toFixed(3) + 's');
                n++;
                target.appendChild(w);
            }
        }
        // Tách **đậm**: phần tử lẻ của split là chữ nằm giữa hai cặp dấu sao
        function inline(target, str) {
            str.split(/\*\*(.+?)\*\*/g).forEach(function (part, i) {
                if (part === '') { return; }
                if (i % 2 === 1) { var b = el('strong'); words(b, part); target.appendChild(b); } else { words(target, part); }
            });
        }
        function addItem(tag, str) {
            if (!list || listTag !== tag) { list = el(tag); listTag = tag; container.appendChild(list); }
            var li = el('li'); inline(li, str); list.appendChild(li);
            para = null;
        }
        lines.forEach(function (line) {
            var t = line.trim();
            if (t === '') { list = null; para = null; return; }
            var bullet = /^[-•*]\s+(.*)$/.exec(t);
            var numbered = /^\d+[.)]\s+(.*)$/.exec(t);
            var heading = /^#{1,4}\s+(.*)$/.exec(t);          // AI lỡ viết tiêu đề Markdown thì hiện thành dòng đậm
            if (bullet) { addItem('ul', bullet[1]); return; }
            if (numbered) { addItem('ol', numbered[1]); return; }
            list = null;
            if (!para) { para = el('p'); container.appendChild(para); } else { para.appendChild(document.createElement('br')); }
            if (heading) { var h = el('strong'); inline(h, heading[1]); para.appendChild(h); } else { inline(para, t); }
        });
        return step * n;                                  // thời gian hiệu ứng (giây)
    }
    function timeLabel(ms) {
        return typeof ms === 'number' ? new Date(ms).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : '';
    }

    // ---------- Tin nhắn ----------
    function copyText(text, btn) {
        function done() { var old = btn.textContent; btn.textContent = 'Đã chép'; setTimeout(function () { btn.textContent = old; }, 1400); }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, done);
        } else {
            var ta = el('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch (e) { /* bỏ qua */ }
            document.body.removeChild(ta); done();
        }
    }
    function labelOf(map, k) { return map[k] || k; }
    var TYPE = { RESISTANCE: 'Kháng lực', CARDIO: 'Cardio' };
    var MUSCLE = { CHEST: 'Ngực', BACK: 'Lưng', SHOULDERS: 'Vai', LEGS: 'Chân', ARMS: 'Tay', ABS: 'Bụng', FULL_BODY: 'Toàn thân' };
    var LEVEL = { BEGINNER: 'Dễ', INTERMEDIATE: 'Trung bình', ADVANCED: 'Khó' };

    function listFrom(text, ordered) {
        var l = el(ordered ? 'ol' : 'ul');
        String(text).split('\n').forEach(function (s) { if (s.trim()) { l.appendChild(el('li', '', s.trim())); } });
        return l;
    }
    function draftCard(d) {
        var c = el('div', 'gc-draft');
        c.appendChild(el('h4', '', d.name));
        var tags = el('div', 'gc-tags');
        [labelOf(TYPE, d.type), labelOf(MUSCLE, d.muscle_group), labelOf(LEVEL, d.difficulty)].forEach(function (t) { tags.appendChild(el('span', '', t)); });
        c.appendChild(tags);
        c.appendChild(el('p', '', d.description));
        c.appendChild(el('h5', '', 'Các bước thực hiện'));
        c.appendChild(listFrom(d.instructions, true));
        c.appendChild(el('h5', '', 'Lỗi thường gặp'));
        c.appendChild(listFrom(d.common_mistakes, false));
        var acts = el('div', 'gc-draft__acts');
        var fillBtn = el('button', 'gc-btn', 'Điền vào form thêm bài');
        fillBtn.type = 'button';
        fillBtn.addEventListener('click', function () { applyDraft(d); });
        var cpBtn = el('button', 'gc-btn gc-btn--ghost', 'Sao chép');
        cpBtn.type = 'button';
        cpBtn.addEventListener('click', function () {
            copyText(d.name + '\n\n' + d.description + '\n\nCác bước:\n' + d.instructions + '\n\nLỗi thường gặp:\n' + d.common_mistakes, cpBtn);
        });
        acts.appendChild(fillBtn); acts.appendChild(cpBtn);
        c.appendChild(acts);
        return c;
    }

    /** Vẽ một tin nhắn. m = {who:'me'|'bot', text, at?, links?, draft?, err?, retry?, retryAfter?, retryLabel?} */
    function renderMsg(m, animate) {
        var row = el('div', 'gc-msg' + (m.who === 'me' ? ' gc-msg--me' : '') + (m.err ? ' gc-msg--err' : '') + (animate ? '' : ' is-static'));
        if (m.who !== 'me') { row.appendChild(avatar()); }
        var col = el('div', 'gc-col');
        var bub = el('div', 'gc-bub');
        if (m.who === 'me') {
            bub.textContent = m.text;
        } else {
            fillRich(bub, m.text, animate);
        }
        col.appendChild(bub);
        if (timeLabel(m.at)) { col.appendChild(el('span', 'gc-time', timeLabel(m.at))); }

        if (m.draft) { col.appendChild(draftCard(m.draft)); }
        if (m.links && m.links.length) {
            var ls = el('div', 'gc-links');
            m.links.forEach(function (l) {
                if (l && safeUrl(l.url)) { var a = el('a', 'gc-link', String(l.label || l.url)); a.href = l.url; ls.appendChild(a); }
            });
            col.appendChild(ls);
        }
        if (m.who === 'bot' && !m.err && !m.draft) {
            var acts = el('div', 'gc-acts');
            var cp = el('button', 'gc-mini', 'Sao chép');
            cp.type = 'button';
            cp.addEventListener('click', function () { copyText(m.text, cp); });
            acts.appendChild(cp);
            col.appendChild(acts);
        }
        if (m.err && m.retry) {
            var label = m.retryLabel || 'Thử lại';
            var rt = el('button', 'gc-btn gc-btn--ghost', label);
            rt.type = 'button';
            rt.addEventListener('click', function () {
                row.parentNode.removeChild(row);
                state.msgs = state.msgs.filter(function (x) { return x !== m; });
                persist();
                send(state.lastText, state.lastTask, true);
            });
            // Server báo gửi quá nhanh: đếm ngược rồi mới cho bấm, khỏi bị từ chối lần nữa
            if (m.retryAfter > 0) {
                var left = m.retryAfter;
                rt.disabled = true;
                rt.textContent = label + ' sau ' + left + ' giây';
                var cd = setInterval(function () {
                    left--;
                    if (left <= 0) { clearInterval(cd); rt.disabled = false; rt.textContent = label; } else { rt.textContent = label + ' sau ' + left + ' giây'; }
                }, 1000);
            }
            col.appendChild(rt);
        }
        row.appendChild(col);
        body.appendChild(row);
        scrollDown(true);
        return row;
    }
    function addMsg(m, animate) {
        m.at = Date.now();
        state.msgs.push(m);
        persist();
        var row = renderMsg(m, animate !== false);
        // Trong lúc chữ hiện dần, cuộn theo để người đọc không bị tụt lại
        if (animate !== false && m.who === 'bot' && !reduceMotion) {
            var t0 = Date.now();
            var iv = setInterval(function () { scrollDown(false); if (Date.now() - t0 > 1800) { clearInterval(iv); } }, 90);
        }
        return row;
    }

    var typingRow = null;
    function showTyping() {
        typingRow = el('div', 'gc-msg');
        typingRow.appendChild(avatar());
        var b = el('div', 'gc-bub gc-typing');
        b.appendChild(el('span', 'gc-sr', 'Đang soạn câu trả lời'));
        b.appendChild(el('i')); b.appendChild(el('i')); b.appendChild(el('i'));
        typingRow.appendChild(b);
        body.appendChild(typingRow);
        scrollDown(true);
    }
    function hideTyping() { if (typingRow && typingRow.parentNode) { typingRow.parentNode.removeChild(typingRow); } typingRow = null; }

    // ---------- Chip gợi ý: hiện khi chưa có tin nhắn (màn chào) hoặc ngay sau một câu trả lời ----------
    function chipList() {
        return (isAdmin && state.tab === 'draft') ? (cfg.draftChips || []).map(function (t) { return { text: t, icon: '' }; }) : cfg.chips;
    }
    function buildChip(c, i) {
        var b = el('button', 'gc-chip' + (c.icon ? '' : ' gc-chip--plain'));
        b.type = 'button';
        b.style.setProperty('--i', i);
        if (c.icon) { b.appendChild(el('span', 'gc-chip__ic', c.icon)); }
        b.appendChild(el('span', '', c.text));
        b.addEventListener('click', function () {
            input.value = c.text;                         // điền vào ô nhập rồi gửi như người dùng tự gõ
            send(c.text, state.tab === 'draft' ? 'exercise_draft' : 'chat');
        });
        return b;
    }

    var hero = null, follow = null;
    function showHero() {
        hero = el('div', 'gc-hero');
        var av = el('div', 'gc-hero__av');
        av.appendChild(svgUse(isAdmin ? 'gc-glyph' : 'gc-logo'));
        hero.appendChild(av);
        hero.appendChild(el('p', '', (isAdmin && state.tab === 'draft') ? cfg.draftHint : cfg.welcome));
        var chips = el('div', 'gc-chips');
        chipList().forEach(function (c, i) { chips.appendChild(buildChip(c, i)); });
        hero.appendChild(chips);
        body.appendChild(hero);
    }
    function hideHero() {
        if (hero && hero.parentNode) { hero.parentNode.removeChild(hero); }
        hero = null;
    }
    function hideFollow() {
        if (follow && follow.parentNode) { follow.parentNode.removeChild(follow); }
        follow = null;
    }
    // Sau câu trả lời bình thường mới gợi ý tiếp; lỗi hay bản nháp thì thôi. Bỏ câu vừa hỏi và chỉ lấy tối đa 3 chip cho gọn.
    function showFollow() {
        hideFollow();
        var last = state.msgs[state.msgs.length - 1];
        if (state.busy || !last || last.who !== 'bot' || last.err || last.draft) { return; }
        var list = chipList().filter(function (c) { return c.text !== state.lastText; }).slice(0, 3);
        if (!list.length) { return; }
        follow = el('div', 'gc-follow');
        list.forEach(function (c, i) { follow.appendChild(buildChip(c, i)); });
        body.appendChild(follow);
        scrollDown(true);
    }

    // ---------- Trạng thái bận / rảnh ----------
    function setBusy(b) {
        state.busy = b;
        // Không dùng tên .is-busy: ui.css có rule chung cùng tên làm mờ 0,65. Rule đó làm khung chat mờ đi và
        // mất z-index (opacity tạo ngữ cảnh xếp chồng mới), nên các thẻ của trang đè lên khung trong lúc chờ.
        root.classList.toggle('gc-is-busy', b);
        statusText.textContent = b ? 'Đang soạn câu trả lời...' : cfg.subtitle;
        refreshSend();
    }
    function refreshSend() {
        var len = input.value.trim().length;
        sendBtn.disabled = state.busy || len === 0 || input.value.length > cfg.maxLen;
        countEl.textContent = input.value.length + '/' + cfg.maxLen;
        countEl.classList.toggle('is-warn', input.value.length > cfg.maxLen - 40);
    }
    function autosize() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 112) + 'px'; }

    // ---------- Gửi / nhận ----------
    function history() {
        // Chỉ gửi các lượt hỏi-đáp bình thường (bỏ lỗi, bỏ bản nháp), tối đa 10 lượt gần nhất
        return state.msgs.filter(function (m) { return !m.err && !m.draft && !m.task; })
            .slice(-10).map(function (m) { return { role: m.who === 'me' ? 'user' : 'model', text: m.text }; });
    }
    function send(text, task, isRetry) {
        text = String(text || '').trim();
        if (!text || state.busy) { return; }
        if (text.length > cfg.maxLen) { showToast('Tin nhắn tối đa ' + cfg.maxLen + ' ký tự.'); return; }
        task = task || 'chat';
        state.lastText = text; state.lastTask = task;
        hideHero();

        var hist = history();                              // lấy trước khi thêm tin hiện tại
        if (!isRetry) { addMsg({ who: 'me', text: text, task: task === 'exercise_draft' ? 1 : 0 }); }
        input.value = ''; autosize(); setBusy(true); showTyping();
        sendBtn.classList.remove('fly'); void sendBtn.offsetWidth; sendBtn.classList.add('fly');

        var payload = { message: text, mode: cfg.mode, task: task, history: hist, personalize: !!(persBox && persBox.checked) };
        var ctrl = window.AbortController ? new AbortController() : null;
        var timer = setTimeout(function () { if (ctrl) { ctrl.abort(); } }, 50000);

        fetch(cfg.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(payload),
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (res) {
            return res.text().then(function (raw) {
                var data = null;
                try { data = JSON.parse(raw); } catch (e) { /* server trả HTML → xử lý bên dưới */ }
                return { ok: res.ok, status: res.status, data: data, raw: raw };
            });
        }).then(function (r) {
            clearTimeout(timer); hideTyping(); setBusy(false);
            if (r.ok && r.data && typeof r.data.reply === 'string') {
                var m = { who: 'bot', text: r.data.reply, links: r.data.links || [], draft: r.data.draft || null };
                if (m.draft) { m.task = 1; }
                addMsg(m);
                if (!state.open) { dot.hidden = false; }
            } else {
                var msg = (r.data && r.data.error) ? r.data.error : 'Chatbot tạm thời không phản hồi, thử lại sau.';
                var detail = (r.data && r.data.detail) ? r.data.detail : '';
                if (!r.data && (root.getAttribute('data-mode') === 'admin' || /^(localhost|127\.|\[?::1)/.test(location.hostname))) {
                    detail = 'Server trả về dữ liệu không phải JSON (HTTP ' + r.status + '): ' + r.raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200);
                }
                if (r.status === 403 && r.data && /tải lại/i.test(msg)) { msg += ' (bấm F5)'; }
                var links = (r.data && Array.isArray(r.data.links)) ? r.data.links : [];   // bài tập liên quan, có cả khi AI lỗi
                addMsg({ who: 'bot', text: msg, err: true, detail: detail, links: links, retry: r.status !== 401 && r.status !== 403 && r.status !== 429 });
            }
        }).catch(function (e) {
            clearTimeout(timer); hideTyping(); setBusy(false);
            var offline = e && e.name === 'AbortError' ? 'Chờ quá lâu mà chưa có phản hồi.' : 'Không kết nối được tới máy chủ.';
            addMsg({ who: 'bot', text: offline + ' Kiểm tra mạng rồi thử lại nhé.', err: true, retry: true });
        });
    }

    // ---------- Mở / đóng ----------
    function openPanel() {
        if (state.open) { return; }
        state.open = true;
        // Nhớ nơi focus đang đứng (nút nổi hoặc lối tắt trên trang) để trả về đúng chỗ khi đóng
        state.opener = document.activeElement && document.activeElement !== document.body ? document.activeElement : fab;
        root.classList.add('is-open');
        fab.setAttribute('aria-expanded', 'true');
        panel.classList.remove('is-out');
        if (panel.classList.contains('is-max')) { root.classList.add('is-max'); }
        panel.hidden = false;
        var g = styleGeo();
        if (g && !isMobile()) { applyGeo(fitGeo(g)); }   // cửa sổ có thể đã đổi cỡ trong lúc khung đóng
        nudge.hidden = true; dot.hidden = true;
        save(NUDGE_KEY, 1);
        if (hero) { body.scrollTop = 0; } else { scrollDown(true); }   // màn chào thì đọc từ trên xuống
        input.focus({ preventScroll: true });
    }
    function closePanel() {
        if (!state.open) { return; }
        state.open = false;
        root.classList.remove('is-open');
        fab.setAttribute('aria-expanded', 'false');
        panel.classList.add('is-out');
        root.classList.remove('is-max');
        setTimeout(function () { if (!state.open) { panel.hidden = true; } }, reduceMotion ? 0 : 160);
        var back = state.opener && state.opener.isConnected ? state.opener : fab;
        back.focus({ preventScroll: true });
    }
    fab.addEventListener('click', function () { state.open ? closePanel() : openPanel(); });
    $('gc-close').addEventListener('click', closePanel);
    nudge.addEventListener('click', openPanel);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && state.open) { closePanel(); } });

    $('gc-reset').addEventListener('click', function () {
        if (state.busy) { return; }
        state.msgs = []; drop(KEY);
        while (body.firstChild) { body.removeChild(body.firstChild); }
        hero = null; follow = null; state.lastText = ''; showHero();
        input.focus({ preventScroll: true });
    });

    // ---------- Kéo, đổi cỡ, phóng to (chỉ máy tính; điện thoại luôn dùng khung gần toàn màn hình) ----------
    var GEO_KEY = 'gc:geo:' + cfg.mode;
    var head = panel.querySelector('.gc-head'), grip = $('gc-grip'), maxBtn = $('gc-max');
    var MIN_W = 320, MIN_H = 380;
    function isMobile() { return window.matchMedia && window.matchMedia('(max-width: 520px)').matches; }
    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
    function curGeo() { var r = panel.getBoundingClientRect(); return { l: r.left, t: r.top, w: r.width, h: r.height }; }
    // Vị trí đã đặt qua style. Khác curGeo(): vẫn đúng khi khung đang ẩn (đo khung ẩn sẽ ra toàn số 0) hoặc đang chạy hiệu ứng mở.
    function styleGeo() {
        if (!panel.style.left) { return null; }
        return { l: parseFloat(panel.style.left), t: parseFloat(panel.style.top), w: parseFloat(panel.style.width), h: parseFloat(panel.style.height) };
    }
    function fitGeo(g) {                                 // giữ khung nằm trọn trong cửa sổ, kể cả cửa sổ nhỏ hơn cỡ tối thiểu
        var vw = window.innerWidth, vh = window.innerHeight;
        var maxW = Math.max(200, vw - 16), maxH = Math.max(200, vh - 16);
        g.w = clamp(g.w, Math.min(MIN_W, maxW), maxW);
        g.h = clamp(g.h, Math.min(MIN_H, maxH), maxH);
        g.l = clamp(g.l, 8, Math.max(8, vw - g.w - 8));
        g.t = clamp(g.t, 8, Math.max(8, vh - g.h - 8));
        return g;
    }
    function applyGeo(g) {
        panel.style.right = 'auto'; panel.style.bottom = 'auto';
        panel.style.left = g.l + 'px'; panel.style.top = g.t + 'px';
        panel.style.width = g.w + 'px'; panel.style.height = g.h + 'px';
    }
    function clearGeo() { ['left', 'top', 'right', 'bottom', 'width', 'height'].forEach(function (k) { panel.style[k] = ''; }); }
    function saveGeo() { var g = styleGeo(); if (g) { save(GEO_KEY, g, 'localStorage'); } }

    function drag(handle, onMove, cls) {                 // khung chung cho kéo/đổi cỡ bằng chuột, cảm ứng, bút
        handle.addEventListener('pointerdown', function (e) {
            if (isMobile() || panel.classList.contains('is-max') || e.button !== 0) { return; }
            if (e.target.closest && e.target.closest('button')) { return; }
            e.preventDefault();
            var start = curGeo(), sx = e.clientX, sy = e.clientY;
            applyGeo(start);
            panel.classList.add(cls);
            try { handle.setPointerCapture(e.pointerId); } catch (x) { /* bỏ qua */ }
            function mv(ev) { applyGeo(onMove(start, ev.clientX - sx, ev.clientY - sy)); }
            function up() {
                handle.removeEventListener('pointermove', mv);
                handle.removeEventListener('pointerup', up);
                handle.removeEventListener('pointercancel', up);
                panel.classList.remove(cls);
                saveGeo();
            }
            handle.addEventListener('pointermove', mv);
            handle.addEventListener('pointerup', up);
            handle.addEventListener('pointercancel', up);
        });
    }
    // Kéo tiêu đề = di chuyển
    drag(head, function (g, dx, dy) { return fitGeo({ l: g.l + dx, t: g.t + dy, w: g.w, h: g.h }); }, 'is-dragging');
    // Đổi cỡ bằng 4 cạnh + góc trên trái. Cạnh đối diện đứng yên; không cho nhỏ hơn MIN_W x MIN_H hay lọt khỏi cửa sổ.
    function resizeGeo(g, dx, dy, edge) {
        var vw = window.innerWidth, vh = window.innerHeight, right = g.l + g.w, bottom = g.t + g.h;
        var n = { l: g.l, t: g.t, w: g.w, h: g.h };
        if (edge.indexOf('l') >= 0) { n.w = clamp(g.w - dx, MIN_W, Math.max(MIN_W, right - 8)); n.l = right - n.w; }
        if (edge.indexOf('r') >= 0) { n.w = clamp(g.w + dx, MIN_W, Math.max(MIN_W, vw - 8 - g.l)); }
        if (edge.indexOf('t') >= 0) { n.h = clamp(g.h - dy, MIN_H, Math.max(MIN_H, bottom - 8)); n.t = bottom - n.h; }
        if (edge.indexOf('b') >= 0) { n.h = clamp(g.h + dy, MIN_H, Math.max(MIN_H, vh - 8 - g.t)); }
        return n;
    }
    Array.prototype.forEach.call(panel.querySelectorAll('[data-edge]'), function (h) {
        var edge = h.getAttribute('data-edge');
        drag(h, function (g, dx, dy) { return resizeGeo(g, dx, dy, edge); }, 'is-resizing');
        h.addEventListener('dblclick', function () { clearGeo(); drop(GEO_KEY, 'localStorage'); });   // bấm đúp cạnh/góc = về mặc định
    });

    function setMax(on) {
        panel.classList.toggle('is-max', on);
        root.classList.toggle('is-max', on);
        maxBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        maxBtn.setAttribute('aria-label', on ? 'Thu nhỏ khung chat' : 'Phóng to khung chat');
        setTimeout(function () { scrollDown(true); }, 60);
    }
    maxBtn.addEventListener('click', function () { setMax(!panel.classList.contains('is-max')); });
    head.addEventListener('dblclick', function (e) {
        if (isMobile() || (e.target.closest && e.target.closest('button'))) { return; }
        setMax(!panel.classList.contains('is-max'));
    });

    // Khôi phục vị trí/cỡ lần trước; cửa sổ đổi cỡ thì kéo khung vào lại cho khỏi lọt ra ngoài
    var geo = load(GEO_KEY, 'localStorage');
    if (geo && typeof geo.l === 'number' && typeof geo.w === 'number' && !isMobile()) { applyGeo(fitGeo(geo)); }
    window.addEventListener('resize', function () {
        var g = styleGeo();
        if (g && !panel.hidden && !panel.classList.contains('is-max') && !isMobile()) { applyGeo(fitGeo(g)); }
    });

    // Bàn phím ảo trên điện thoại: khung bám theo vùng nhìn thấy thật để ô nhập không bị che (dùng trong css/chatbot.css)
    function syncViewport() {
        var vv = window.visualViewport;
        if (!vv) { return; }
        root.style.setProperty('--gc-vv', vv.height + 'px');
        root.style.setProperty('--gc-vv-top', vv.offsetTop + 'px');
        if (state.open) { scrollDown(false); }
    }
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncViewport);
        window.visualViewport.addEventListener('scroll', syncViewport);
        syncViewport();
    }

    // ---------- Ô nhập ----------
    input.addEventListener('input', function () { autosize(); refreshSend(); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {   // isComposing: đang gõ dấu tiếng Việt kiểu IME thì không gửi
            e.preventDefault();
            if (!sendBtn.disabled) { send(input.value, state.tab === 'draft' ? 'exercise_draft' : 'chat'); }
        }
    });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!sendBtn.disabled) { send(input.value, state.tab === 'draft' ? 'exercise_draft' : 'chat'); }
    });

    // Công tắc "Dùng hồ sơ của tôi" (người dùng thường): nhớ lựa chọn trong trình duyệt
    if (persBox) {
        persBox.checked = load('gc:pers:' + cfg.uid, 'localStorage') === 1;
        persBox.addEventListener('change', function () {
            save('gc:pers:' + cfg.uid, persBox.checked ? 1 : 0, 'localStorage');
            showToast(persBox.checked ? 'Đã bật: chatbot dùng mục tiêu, trình độ, chiều cao, cân nặng của bạn.' : 'Đã tắt cá nhân hóa.');
        });
    }

    // Thẻ Hỏi đáp / Soạn bài tập (admin)
    if (tabsEl) {
        var tabBtns = tabsEl.querySelectorAll('.gc-tab');
        Array.prototype.forEach.call(tabBtns, function (btn, i) {
            btn.addEventListener('click', function () {
                state.tab = btn.getAttribute('data-tab');
                tabsEl.style.setProperty('--i', i);
                Array.prototype.forEach.call(tabBtns, function (b) {
                    var on = b === btn; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                cfg.tabs.forEach(function (t) { if (t.id === state.tab) { input.placeholder = t.placeholder; } });
                if (hero) { hideHero(); showHero(); } else { showFollow(); }
                if (!hero && state.tab === 'draft' && !state.msgs.some(function (m) { return m.task; })) {
                    addMsg({ who: 'bot', text: cfg.draftHint, task: 1 });
                    showFollow();
                }
                input.focus();
            });
        });
    }

    // ---------- Điền bản nháp vào form bài tập (chỉ admin) ----------
    var FORM_FIELDS = ['name', 'type', 'muscle_group', 'difficulty', 'description', 'instructions', 'common_mistakes'];
    function onExerciseForm() { return /\/admin\/exercise-form\.php$/.test(location.pathname) && document.getElementById('name'); }
    function fillForm(d) {
        FORM_FIELDS.forEach(function (f) {
            var node = document.getElementById(f);
            if (!node || typeof d[f] !== 'string') { return; }
            node.value = d[f];
            node.dispatchEvent(new Event('input', { bubbles: true }));
            node.dispatchEvent(new Event('change', { bubbles: true }));
            node.classList.remove('gc-filled'); void node.offsetWidth; node.classList.add('gc-filled');
        });
        var first = document.getElementById('name');
        if (first && first.scrollIntoView) { first.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' }); }
    }
    function applyDraft(d) {
        if (!isAdmin) { return; }
        if (onExerciseForm()) {
            var editing = !!document.querySelector('input[name="id"]');
            var hasText = (document.getElementById('name').value || '').trim() !== '';
            if ((editing || hasText) && !window.confirm('Form đang có nội dung. Ghi đè bằng bản nháp này?')) { return; }
            fillForm(d);
            showToast('Đã điền vào form. Hãy kiểm tra, thêm ảnh/video rồi bấm Lưu.');
        } else {
            save(DRAFT_KEY, d);
            window.location.href = '/admin/exercise-form.php';   // sang trang Thêm bài tập; bản nháp được điền tự động ở đó
        }
    }
    if (isAdmin && onExerciseForm()) {
        var pending = load(DRAFT_KEY);
        if (pending && !document.querySelector('input[name="id"]')) {
            drop(DRAFT_KEY);
            fillForm(pending);
            showToast('Đã điền bản nháp từ trợ lý. Hãy kiểm tra, thêm ảnh/video rồi bấm Lưu.');
        } else if (pending) {
            drop(DRAFT_KEY);
        }
    }

    // ---------- Khởi động ----------
    var saved = load(KEY);
    if (saved && saved.length) {
        state.msgs = saved;
        saved.forEach(function (m) { m.retry = false; renderMsg(m, false); });   // sau khi tải lại trang không còn gì để "thử lại"
    } else {
        showHero();
    }
    refreshSend();

    // Bóng thoại nhắc nhẹ, chỉ một lần mỗi phiên
    if (!load(NUDGE_KEY) && !(saved && saved.length)) {
        setTimeout(function () {
            if (state.open || load(NUDGE_KEY)) { return; }
            nudge.textContent = cfg.nudge;
            nudge.hidden = false;
            save(NUDGE_KEY, 1);
            setTimeout(function () { nudge.hidden = true; }, 7000);
        }, 2500);
    }
})();
