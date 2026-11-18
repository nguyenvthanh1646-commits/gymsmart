<?php
// Copy file này thành "config.php" (cùng thư mục gốc), rồi điền thông tin DB thật của máy bạn.
// config.php KHÔNG được commit lên Git (đã có trong .gitignore).

define('DB_HOST', 'localhost');
define('DB_PORT', 3306); // đổi nếu MySQL của bạn chạy cổng khác (xem trong XAMPP Control Panel)
define('DB_NAME', 'gymsmart');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP/MAMP mặc định thường để trống

// (Tùy chọn) Múi giờ của ứng dụng, dùng chung cho PHP và MySQL. Bỏ trống thì mặc định Asia/Ho_Chi_Minh.
// define('APP_TIMEZONE', 'Asia/Ho_Chi_Minh');

// API key cho Chatbot AI (chỉ cần khi làm tới phần chatbot, để trống cũng được)
define('AI_API_KEY', '');
