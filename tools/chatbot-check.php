<?php
/**
 * tools/chatbot-check.php — kiểm tra vì sao chatbot không trả lời.
 *
 * Chạy ở thư mục gốc project (nơi có config.php), bằng PHP của XAMPP:
 *     C:\xampp\php\php.exe tools\chatbot-check.php
 * hoặc nếu đã có php trong PATH:
 *     php tools/chatbot-check.php
 *
 * Script đọc config.php, kiểm tra từng bước (cURL, khóa API, tên model, chứng chỉ SSL), rồi gọi thử
 * Gemini một câu ngắn và in ra NGUYÊN NHÂN thật cùng cách sửa. Không in khóa API đầy đủ.
 */

if (PHP_SAPI !== 'cli') {
    exit('Chạy script này bằng dòng lệnh: php tools/chatbot-check.php');
}

$root = dirname(__DIR__);
$config = $root . '/config.php';
$ai = null;
foreach ([$root . '/public/includes/ai.php', $root . '/includes/ai.php'] as $cand) {
    if (is_file($cand)) {
        $ai = $cand;
        break;
    }
}

function line(string $mark, string $text): void
{
    echo $mark . str_repeat(' ', max(1, 7 - mb_strlen($mark))) . $text . PHP_EOL;   // mb_strlen: nhãn có chữ có dấu
}

echo PHP_EOL . "=== Kiểm tra chatbot GymSmart ===" . PHP_EOL . PHP_EOL;
$bad = 0;

line('INFO', 'PHP ' . PHP_VERSION);
if (!function_exists('curl_init')) {
    line('LỖI', 'PHP chưa bật cURL. Mở php.ini (XAMPP: nút Config của Apache → PHP (php.ini)), bỏ dấu ; trước "extension=curl", lưu rồi Stop/Start Apache.');
    exit(1);
}
line('OK', 'Đã bật extension cURL');

if ($ai === null) {
    line('LỖI', 'Không thấy includes/ai.php. Bạn đã chép file mới vào public/includes/ chưa?');
    exit(1);
}
require_once $ai;
line('OK', 'Thấy ' . str_replace($root . '/', '', $ai));

if (!is_file($config)) {
    line('LỖI', "Không thấy $config. Chép config.example.php thành config.php rồi điền khóa vào.");
    exit(1);
}
require_once $config;
line('OK', 'Đọc được config.php');

$key = aiApiKey();
if ($key === '') {
    line('LỖI', "Chưa có AI_API_KEY. Thêm vào config.php:  define('AI_API_KEY', 'khóa của bạn');  (lấy khóa miễn phí ở https://aistudio.google.com/apikey)");
    $bad++;
} else {
    line('OK', 'AI_API_KEY: ' . substr($key, 0, 4) . '… (' . strlen($key) . ' ký tự)');
}
if (!defined('AI_MODEL')) {
    line('LƯU Ý', "Chưa có AI_MODEL, hệ thống dùng mặc định '" . aiModel() . "'. Muốn đổi: define('AI_MODEL', 'gemini-3.8-flash');");
} else {
    line('OK', 'AI_MODEL: ' . aiModel());
}
foreach (aiConfigProblems() as $p) {
    line('LỖI', $p);
    $bad++;
}
$cainfo = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
line('INFO', 'Chứng chỉ SSL trong php.ini: ' . ($cainfo ?: '(chưa đặt)') . (defined('AI_CA_BUNDLE') ? ' | AI_CA_BUNDLE: ' . AI_CA_BUNDLE : ''));

if ($key === '') {
    exit(1);
}

echo PHP_EOL . 'Đang gọi thử Gemini...' . PHP_EOL;
$t0 = microtime(true);
$res = aiGenerate('Bạn là bộ kiểm tra kết nối. Chỉ trả lời đúng một từ: OK', [['role' => 'user', 'text' => 'ping']], ['maxTokens' => 20, 'temperature' => 0]);
$ms = (int)((microtime(true) - $t0) * 1000);

if ($res['ok']) {
    line('OK', "Gemini trả lời sau {$ms} ms: " . substr($res['text'], 0, 60));
    echo PHP_EOL . 'Mọi thứ ổn: nếu khung chat vẫn báo lỗi, mở khung chat bằng tài khoản admin hoặc trên localhost để xem "Chi tiết kỹ thuật".' . PHP_EOL;
    exit(0);
}

line('LỖI', "{$res['code']} (HTTP {$res['http']}): {$res['detail']}");
$fix = [
    'AI_SSL'     => "Thiếu chứng chỉ gốc trên XAMPP/Windows. Tải https://curl.se/ca/cacert.pem → lưu thành C:\\xampp\\php\\extras\\ssl\\cacert.pem → thêm vào config.php:\n        define('AI_CA_BUNDLE', 'C:/xampp/php/extras/ssl/cacert.pem');\n      (hoặc đặt curl.cainfo trong php.ini rồi restart Apache).",
    'AI_NETWORK' => 'Máy không ra được internet, hoặc bị tường lửa/proxy/VPN chặn. Thử mở https://generativelanguage.googleapis.com trong trình duyệt.',
    'AI_AUTH'    => 'Khóa API sai, hết hạn hoặc bị thu hồi. Tạo khóa mới ở https://aistudio.google.com/apikey và dán lại vào config.php (không thừa dấu cách).',
    'AI_MODEL'   => "Tên model không tồn tại/đã ngừng. Đổi: define('AI_MODEL', 'gemini-3.8-flash');",
    'AI_QUOTA'   => 'Hết hạn mức miễn phí (số yêu cầu mỗi phút/ngày). Đợi một lúc hoặc đổi sang model nhẹ hơn.',
    'AI_BLOCKED' => 'Câu thử bị bộ lọc chặn (hiếm gặp). Thử lại.',
    'AI_EMPTY'   => 'Google trả nội dung rỗng. Thử đổi AI_MODEL sang gemini-3.8-flash.',
    'AI_HTTP'    => 'Lỗi phía Google hoặc yêu cầu sai. Đọc nội dung lỗi ở dòng trên.',
];
if (isset($fix[$res['code']])) {
    echo PHP_EOL . '  Cách sửa: ' . $fix[$res['code']] . PHP_EOL;
}
exit(1);
