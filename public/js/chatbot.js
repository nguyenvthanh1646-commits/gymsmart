(function () {
    const csrf = document.currentScript.dataset.csrf || '';

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'chatbot__toggle';
    toggle.setAttribute('aria-label', 'Mở khung chat');
    toggle.textContent = '💬';

    const panel = document.createElement('section');
    panel.className = 'chatbot__panel';
    panel.hidden = true;
    panel.innerHTML = `
        <header class="chatbot__header">
            <span>Trợ lý GymSmart</span>
            <button type="button" class="chatbot__close" aria-label="Đóng">×</button>
        </header>
        <div class="chatbot__messages" aria-live="polite"></div>
        <form class="chatbot__form" autocomplete="off">
            <input type="text" class="chatbot__input" placeholder="Nhập câu hỏi..." maxlength="500" required>
            <button type="submit" class="btn chatbot__send">Gửi</button>
        </form>`;
    // innerHTML chỉ dùng cho khung tĩnh ở trên; tin nhắn luôn dùng textContent

    document.body.append(toggle, panel);

    const messages = panel.querySelector('.chatbot__messages');
    const form = panel.querySelector('.chatbot__form');
    const input = panel.querySelector('.chatbot__input');
    const sendBtn = panel.querySelector('.chatbot__send');

    function addBubble(text, who) {
        const div = document.createElement('div');
        div.className = 'chatbot__bubble chatbot__bubble--' + who;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    function setOpen(open) {
        panel.hidden = !open;
        if (open) input.focus();
    }

    addBubble('Xin chào! Mình có thể giúp gì về tập luyện và dinh dưỡng?', 'bot');

    toggle.addEventListener('click', () => setOpen(panel.hidden));
    panel.querySelector('.chatbot__close').addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.hidden) setOpen(false);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();               // Enter trong ô nhập cũng chạy vào đây
        const text = input.value.trim();
        if (!text) return;

        addBubble(text, 'user');
        input.value = '';
        sendBtn.disabled = true;
        const pending = addBubble('Đang trả lời...', 'bot');
        pending.classList.add('chatbot__bubble--pending');

        try {
            const res = await fetch('/api/chatbot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ message: text }),
            });
            const data = await res.json().catch(() => ({}));
            pending.textContent = data.reply || data.error || 'Chatbot tạm thời không phản hồi, thử lại sau.';
        } catch (err) {
            pending.textContent = 'Chatbot tạm thời không phản hồi, thử lại sau.';
        } finally {
            pending.classList.remove('chatbot__bubble--pending');
            sendBtn.disabled = false;
            messages.scrollTop = messages.scrollHeight;
            input.focus();
        }
    });
})();