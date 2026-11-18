<?php
/**
 * tools/run-tests.php — chạy MỌI script test của dự án bằng một lệnh và in bảng tổng kết.
 *
 * Chạy ở gốc repo:
 *   php tools/run-tests.php          Các test không cần MySQL (test-*.php ở gốc repo; phần DB dùng SQLite trong bộ nhớ)
 *   php tools/run-tests.php --http   Thêm kịch bản HLV qua HTTP (tools/test-coach-http.php, cần MySQL đang chạy, tự tạo và xóa DB thử)
 *
 * Mỗi script test tự in [OK]/[FAIL] và dòng "TẤT CẢ ĐỀU PASS" khi đạt. Script này chỉ đếm và tổng hợp, không đổi gì.
 * Mã thoát 0 = tất cả đạt, 1 = có script lỗi (dùng được trong CI nếu sau này cần).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy từ dòng lệnh.');
}

$root = dirname(__DIR__);
$scripts = glob($root . '/test-*.php');
$scripts = array_values(array_filter($scripts, fn($f) => basename($f) !== 'test-exercises-fixture.php'));   // file dữ liệu, không phải test
if (in_array('--http', $argv, true)) {
    $scripts[] = $root . '/tools/test-coach-http.php';
}

$rows = [];
$allPass = true;
foreach ($scripts as $file) {
    $start = microtime(true);
    $out = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    $text = implode("\n", $out);
    $ok = substr_count($text, '[OK]');
    $fail = substr_count($text, '[FAIL]');
    $pass = $code === 0 && $fail === 0 && str_contains($text, 'TẤT CẢ ĐỀU PASS');
    $allPass = $allPass && $pass;
    $rows[] = [basename($file), $ok, $fail, round(microtime(true) - $start, 1), $pass];
    if (!$pass) {
        // In các dòng lỗi để biết ngay chỗ hỏng mà không phải chạy lại từng file
        foreach ($out as $line) {
            if (str_contains($line, '[FAIL]') || stripos($line, 'error') !== false) {
                echo '  ' . basename($file) . ': ' . $line . "\n";
            }
        }
    }
}

echo "\n" . str_pad('Script', 30) . str_pad('OK', 7) . str_pad('FAIL', 7) . str_pad('Giây', 7) . "Kết quả\n";
echo str_repeat('-', 60) . "\n";
foreach ($rows as [$name, $ok, $fail, $sec, $pass]) {
    echo str_pad($name, 30) . str_pad((string)$ok, 7) . str_pad((string)$fail, 7) . str_pad((string)$sec, 7) . ($pass ? 'ĐẠT' : 'LỖI') . "\n";
}
echo "\n" . ($allPass ? 'TẤT CẢ ĐỀU PASS' : 'CÓ SCRIPT LỖI') . "\n";
exit($allPass ? 0 : 1);
