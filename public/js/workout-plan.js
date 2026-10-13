// public/js/workout-plan.js
// B10: gửi form tạo lịch.  B11: chỉnh sửa lịch (đổi / xóa / thêm bài, sửa set-lặp).
// Cả hai đều gọi API bằng fetch, nhận JSON, thành công thì tải lại trang để hiển thị dữ liệu mới.
(function () {
    // Gửi POST dạng form, trả về object JSON; nếu server trả HTML (404, lỗi PHP...) thì ném lỗi dễ hiểu.
    async function postForm(url, params) {
        const response = await fetch(url, {
            method: 'POST',
            body: new URLSearchParams(params),
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

    // ===== B10: tạo lịch =====
    const form = document.getElementById('workout-plan-form');
    if (form) {
        const message = document.getElementById('workout-plan-message');
        const button = form.querySelector('button[type="submit"]');

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

    // ===== B11: chỉnh sửa lịch =====
    const root = document.getElementById('workout-plan-root');
    const toggle = document.getElementById('workout-plan-toggle-edit');
    const editMessage = document.getElementById('workout-plan-edit-message');
    if (!root || !toggle) return; // chưa có lịch -> không có phần chỉnh sửa

    function setEditing(on) {
        root.classList.toggle('workout-plan--editing', on);
        toggle.textContent = on ? 'Xong' : 'Chỉnh sửa lịch';
    }
    toggle.addEventListener('click', function () {
        const on = !root.classList.contains('workout-plan--editing');
        setEditing(on);
        window.location.hash = on ? 'edit' : '';
    });
    // Sau khi lưu trang tải lại: giữ nguyên chế độ chỉnh sửa
    if (window.location.hash === '#edit') setEditing(true);

    // Một trình nghe chung cho mọi nút (event delegation)
    root.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;

        const action = button.dataset.action;
        const params = { action: action };

        if (action === 'add') {
            const box = button.closest('.workout-plan__edit');
            params.day_of_week = box.querySelector('[name="day_of_week"]').value;
            params.exercise_id = box.querySelector('[name="exercise_id"]').value;
            params.sets = box.querySelector('[name="sets"]').value;
            params.reps = box.querySelector('[name="reps"]').value;
            if (!params.exercise_id) {
                showMessage(editMessage, 'Hãy chọn một bài tập để thêm.', true);
                return;
            }
        } else {
            const item = button.closest('[data-row-id]');
            params.row_id = item.dataset.rowId;

            if (action === 'update') {
                params.sets = item.querySelector('[name="sets"]').value;
                params.reps = item.querySelector('[name="reps"]').value;
            } else if (action === 'replace') {
                params.new_exercise_id = item.querySelector('[name="new_exercise_id"]').value;
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