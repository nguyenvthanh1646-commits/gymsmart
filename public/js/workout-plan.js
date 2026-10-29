// public/js/workout-plan.js
// Trang Lịch tập: chọn ngày, form tạo lịch (B10), ghi log từng bài, chỉnh sửa lịch (B11).
// Mọi lời gọi API dùng fetch, nhận JSON; tạo/sửa lịch thành công thì tải lại trang để hiện dữ liệu mới.
(function () {
    // Mã CSRF do PHP in sẵn ở thẻ gốc của trang; API ghi dữ liệu sẽ từ chối nếu thiếu mã này.
    const rootEl = document.getElementById('workout-plan-root');
    const csrfToken = rootEl ? (rootEl.dataset.csrf || '') : '';

    // Gửi POST dạng form, trả về object JSON; nếu server trả HTML (404, lỗi PHP...) thì ném lỗi dễ hiểu.
    async function postForm(url, params) {
        const body = new URLSearchParams(params);
        body.set('csrf_token', csrfToken);
        const response = await fetch(url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        });
        const raw = await response.text();
        try {
            return JSON.parse(raw);
        } catch (parseError) {
            console.error('API trả về không phải JSON (HTTP ' + response.status + '):', raw);
            throw new Error('Máy chủ trả về dữ liệu không hợp lệ (HTTP ' + response.status + '). Xem Console (F12).');
        }
    }

    function showMessage(element, text, isError) {
        if (!element) return;
        element.textContent = text;
        element.classList.toggle('workout-plan__message--error', isError);
    }

    // ===== Chọn ngày trong tuần =====
    const days = document.querySelectorAll('.wp-day');
    function selectDay(day) {
        days.forEach(function (btn) {
            const on = btn.dataset.day === day;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        document.querySelectorAll('.wp-panel').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.id === 'day-content-' + day);
        });
    }
    days.forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectDay(btn.dataset.day);
            try { sessionStorage.setItem('wpDay', btn.dataset.day); } catch (e) {}
        });
    });
    // Sau khi lưu chỉnh sửa trang tải lại: quay về đúng ngày đang xem
    try {
        const savedDay = sessionStorage.getItem('wpDay');
        if (savedDay && document.getElementById('day-content-' + savedDay)) selectDay(savedDay);
    } catch (e) {}

    // ===== Mở / đóng form tạo lịch =====
    const createSection = document.getElementById('create-plan-section');
    const toggleCreate = document.getElementById('workout-plan-toggle-create');
    if (toggleCreate && createSection) {
        toggleCreate.addEventListener('click', function () {
            createSection.hidden = !createSection.hidden;
            toggleCreate.setAttribute('aria-expanded', createSection.hidden ? 'false' : 'true');
            if (!createSection.hidden) createSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    // ===== B10: tạo lịch =====
    const form = document.getElementById('workout-plan-form');
    if (form) {
        const message = document.getElementById('workout-plan-message');
        const button = form.querySelector('button[type="submit"]');
        const splitField = document.getElementById('wp-split-field');
        const splitOptions = JSON.parse(form.dataset.splitOptions || '{}'); // {"4": ["UPPER_LOWER", ...], ...}
        const splitDefault = JSON.parse(form.dataset.splitDefault || '{}'); // {"4": "UPPER_LOWER", ...}

        // Mọi kiểu chia luôn hiện. Kiểu không hợp với số buổi đang chọn thì làm mờ (vẫn bấm được).
        // Đổi số buổi -> nếu kiểu đang chọn không hợp thì chọn kiểu đầu tiên hợp.
        // Chọn một kiểu không hợp -> số buổi tự nhảy sang số gần nhất mà kiểu đó có.
        function checkedDays() {
            const r = form.querySelector('[name="days_per_week"]:checked');
            return r ? r.value : '';
        }
        function refreshSplitChoices() {
            const allowed = splitOptions[checkedDays()] || [];
            let firstAllowed = null;
            splitField.querySelectorAll('[data-split]').forEach(function (item) {
                const ok = allowed.indexOf(item.dataset.split) !== -1;
                const radio = item.querySelector('input');
                item.classList.toggle('is-off', !ok);
                if (!ok) radio.checked = false;
                if (ok && !firstAllowed) firstAllowed = radio;
            });
            if (!splitField.querySelector('input:checked')) {
                // ưu tiên kiểu mặc định của số buổi này (VD 4 buổi -> Trên / Dưới)
                const def = splitField.querySelector('input[value="' + splitDefault[checkedDays()] + '"]') || firstAllowed;
                if (def) def.checked = true;
            }
        }
        form.querySelectorAll('[name="days_per_week"]').forEach(function (radio) {
            radio.addEventListener('change', refreshSplitChoices);
        });
        splitField.querySelectorAll('[data-split] input').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const days = radio.closest('[data-split]').dataset.days.split(',').map(Number);
                const current = Number(checkedDays());
                if (days.indexOf(current) !== -1) { refreshSplitChoices(); return; }
                // số buổi gần với số đang chọn nhất
                const best = days.reduce(function (a, b) { return Math.abs(b - current) < Math.abs(a - current) ? b : a; });
                form.querySelector('[name="days_per_week"][value="' + best + '"]').checked = true;
                refreshSplitChoices();
                radio.checked = true;
            });
        });
        refreshSplitChoices();

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            // Tạo lịch mới sẽ chuyển lịch hiện tại sang lưu trữ -> hỏi xác nhận
            if (form.dataset.hasPlan === '1' &&
                !confirm('Tạo lịch mới sẽ thay thế lịch hiện tại (kể cả các chỉnh sửa của bạn). Tiếp tục?')) {
                return;
            }

            button.disabled = true;
            showMessage(message, 'Đang tạo lịch...', false);
            try {
                const data = await postForm('api/workout-plan-generate.php', new FormData(form));
                if (data.success) {
                    try { sessionStorage.removeItem('wpDay'); } catch (e) {}
                    window.location.hash = '';
                    window.location.reload();
                    return;
                }
                showMessage(message, data.message || 'Không tạo được lịch.', true);
            } catch (error) {
                console.error(error);
                showMessage(message, error.message || 'Lỗi kết nối, vui lòng thử lại.', true);
            }
            button.disabled = false;
        });
    }

    const root = document.getElementById('workout-plan-root');
    if (!root) return;

    // ===== Ghi log từng bài =====
    root.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-log-toggle]');
        if (!toggle) return;
        const panel = toggle.closest('.wp-ex').querySelector('.wp-log');
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        if (!panel.hidden) panel.querySelector('[name="weight"]').focus();
    });

    root.addEventListener('submit', async function (event) {
        const logForm = event.target.closest('[data-log-form]');
        if (!logForm) return;
        event.preventDefault();

        const btn = logForm.querySelector('.wp-log__submit');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Đang lưu...';
        try {
            const data = await postForm('api/log-workout.php', new FormData(logForm));
            if (!data.success) throw new Error(data.message || 'Không lưu được.');
            btn.textContent = 'Đã lưu';
            btn.classList.add('is-done');
            setTimeout(function () {
                const item = logForm.closest('.wp-ex');
                item.querySelector('.wp-log').hidden = true;
                item.querySelector('[data-log-toggle]').setAttribute('aria-expanded', 'false');
                btn.textContent = originalText;
                btn.classList.remove('is-done');
                btn.disabled = false;
            }, 1000);
        } catch (error) {
            alert(error.message || 'Lỗi kết nối máy chủ');
            btn.textContent = originalText;
            btn.disabled = false;
        }
    });

    // ===== B11: chỉnh sửa lịch =====
    const toggleEdit = document.getElementById('workout-plan-toggle-edit');
    const editMessage = document.getElementById('workout-plan-edit-message');
    if (!toggleEdit) return; // chưa có lịch -> không có phần chỉnh sửa

    function setEditing(on) {
        root.classList.toggle('workout-plan--editing', on);
        toggleEdit.textContent = on ? 'Xong' : 'Chỉnh sửa lịch';
    }
    toggleEdit.addEventListener('click', function () {
        const on = !root.classList.contains('workout-plan--editing');
        setEditing(on);
        window.location.hash = on ? 'edit' : '';
    });
    // Sau khi lưu trang tải lại: giữ nguyên chế độ chỉnh sửa
    if (window.location.hash === '#edit') setEditing(true);

    // Một trình nghe chung cho mọi nút chỉnh sửa (event delegation)
    root.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;

        const action = button.dataset.action;
        const params = { action: action };
        // Lấy ô nhập trong đúng khung chỉnh sửa (form ghi log cũng có ô sets/reps cùng tên)
        const box = button.closest('.workout-plan__edit');

        if (action === 'add') {
            params.day_of_week = box.querySelector('[name="day_of_week"]').value;
            params.exercise_id = box.querySelector('[name="exercise_id"]').value;
            params.sets = box.querySelector('[name="sets"]').value;
            params.reps = box.querySelector('[name="reps"]').value;
            if (!params.exercise_id) {
                showMessage(editMessage, 'Hãy chọn một bài tập để thêm.', true);
                return;
            }
        } else {
            params.row_id = button.closest('[data-row-id]').dataset.rowId;

            if (action === 'update') {
                params.sets = box.querySelector('[name="sets"]').value;
                params.reps = box.querySelector('[name="reps"]').value;
            } else if (action === 'replace') {
                params.new_exercise_id = box.querySelector('[name="new_exercise_id"]').value;
                if (!params.new_exercise_id) {
                    showMessage(editMessage, 'Hãy chọn bài muốn đổi sang.', true);
                    return;
                }
            } else if (action === 'remove') {
                if (!confirm('Xóa bài này khỏi lịch?')) return;
            }
        }

        button.disabled = true;
        showMessage(editMessage, 'Đang lưu...', false);
        try {
            const data = await postForm('api/workout-plan-edit.php', params);
            if (data.success) {
                window.location.hash = 'edit';
                window.location.reload();
                return;
            }
            showMessage(editMessage, data.message || 'Không lưu được thay đổi.', true);
        } catch (error) {
            console.error(error);
            showMessage(editMessage, error.message || 'Lỗi kết nối, vui lòng thử lại.', true);
        }
        button.disabled = false;
    });
})();
