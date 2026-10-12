// password-toggle.js — nút con mắt để hiện/ẩn mật khẩu (dùng ở login.php, register.php).
// Mỗi nút có thuộc tính data-pw-toggle="id-của-ô-mật-khẩu".
// Cách hoạt động: đổi type của ô nhập giữa "password" (hiện dấu chấm) và "text" (hiện chữ).
document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
    var input = document.getElementById(btn.getAttribute('data-pw-toggle'));
    if (!input) return;

    btn.addEventListener('click', function () {
        var show = input.type === 'password';          // đang ẩn thì chuyển sang hiện
        input.type = show ? 'text' : 'password';
        btn.classList.toggle('is-on', show);           // CSS đổi biểu tượng mắt / mắt gạch
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        input.focus();                                 // giữ con trỏ trong ô để gõ tiếp
    });
});
