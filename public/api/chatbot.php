<?php
require_once __DIR__ . '/../includes/auth.php';   // có session + isLoggedIn()
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

function jsonOut($code, $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(405, ['error' => 'Phương thức không hợp lệ.']);
}
if (!isLoggedIn()) {
    jsonOut(401, ['error' => 'Bạn cần đăng nhập.']);
}

$sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($sent === '' || !hash_equals(csrfToken(), $sent)) {
    jsonOut(403, ['error' => 'Phiên không hợp lệ, hãy tải lại trang.']);
}

// ----- Kiểm tra dữ liệu -----
$body = json_decode(file_get_contents('php://input'), true);
$message = is_array($body) && isset($body['message']) && is_string($body['message'])
    ? trim($body['message']) : '';
if ($message === '') {
    jsonOut(400, ['error' => 'Vui lòng nhập tin nhắn.']);
}
if (mb_strlen($message) > 500) {
    jsonOut(400, ['error' => 'Tin nhắn tối đa 500 ký tự.']);
}

// ----- Giới hạn tần suất theo session: >= 3 giây/tin, tối đa 30 tin/giờ -----
$now  = time();
$last = $_SESSION['chat_last'] ?? 0;
$hist = array_filter($_SESSION['chat_hist'] ?? [], fn($t) => $now - $t < 3600);
if ($now - $last < 3 || count($hist) >= 30) {
    jsonOut(429, ['error' => 'Bạn gửi quá nhanh, vui lòng chờ một chút.']);
}
$hist[] = $now;
$_SESSION['chat_hist'] = array_values($hist);
$_SESSION['chat_last'] = $now;
session_write_close();   // nhả khóa session trước khi chờ API, khỏi chặn các tab khác

// ----- Gọi mô hình AI (chỉ gửi đúng câu hỏi, không gửi dữ liệu cá nhân) -----
$system = 'Bạn là trợ lý của website GymSmart. Chỉ trả lời về tập luyện và dinh dưỡng cơ bản, '
    . 'bằng tiếng Việt, ngắn gọn (tối đa 100 từ). Nhắc rằng đây không phải tư vấn y tế khi câu hỏi '
    . 'liên quan sức khỏe hoặc chấn thương. Nếu câu hỏi ngoài chủ đề, từ chối lịch sự và mời '
    . 'người dùng hỏi về tập gym hoặc dinh dưỡng. '
    . 'Trả lời bằng văn bản thuần, không dùng dấu * hay # hay định dạng Markdown.';

$payload = json_encode([
    'systemInstruction' => ['parts' => [['text' => $system]]],
    'contents'          => [['role' => 'user', 'parts' => [['text' => $message]]]],
    'generationConfig'  => [
        'maxOutputTokens' => 1500,
        'thinkingConfig'  => ['thinkingBudget' => 0],   // giảm phần "suy nghĩ" để không ăn hết token
    ],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/'
    . rawurlencode(AI_MODEL) . ':generateContent');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 40,
    CURLOPT_HTTPHEADER     => [
        'content-type: application/json',
        'x-goog-api-key: ' . AI_API_KEY,
    ],
    CURLOPT_POSTFIELDS     => $payload,
]);
$raw     = curl_exec($ch);
$status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

$data  = $raw ? json_decode($raw, true) : null;

// Ghép mọi phần chữ (không chỉ parts[0])
$reply = '';
foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
    $reply .= $part['text'] ?? '';
}
$reply = trim($reply);

if ($raw === false || $status !== 200 || $reply === '') {
    error_log('chatbot: http=' . $status . ' curl=' . $curlErr . ' body=' . substr((string)$raw, 0, 300));
    jsonOut(502, ['error' => 'Chatbot tạm thời không phản hồi, thử lại sau.']);
}
jsonOut(200, ['reply' => $reply]);