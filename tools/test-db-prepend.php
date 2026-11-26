<?php
/**
 * tools/test-db-prepend.php — CHỈ DÙNG KHI KIỂM THỬ (tools/test-coach-http.php nạp file này bằng auto_prepend_file).
 * Nếu có biến môi trường GS_TEST_DB (tên phải bắt đầu bằng gymsmart_test) thì trỏ ứng dụng sang database thử đó,
 * để kịch bản thử không bao giờ đụng vào database gymsmart thật.
 * config.php định nghĩa lại DB_NAME sau đó sẽ bị PHP bỏ qua (hằng số chỉ định nghĩa được 1 lần).
 */
$gsTestDb = getenv('GS_TEST_DB');
if (is_string($gsTestDb) && preg_match('/^gymsmart_test[a-z0-9_]*$/', $gsTestDb) === 1) {
    define('DB_NAME', $gsTestDb);
}
unset($gsTestDb);
