<?php
/**
 * ai.php — gọi mô hình AI (Gemini) bằng cURL. Dùng chung cho api/chatbot.php và tools/chatbot-check.php.
 *
 * Cấu hình nằm trong config.php (KHÔNG commit file đó):
 *   define('AI_API_KEY', 'khóa lấy ở https://aistudio.google.com/apikey');   // bắt buộc
 *   define('AI_MODEL',   'gemini-3.8-flash');                                // tùy chọn, mặc định như bên
 *   define('AI_MODEL_FALLBACK', 'ten-model-du-phong');                       // tùy chọn, dùng khi model chính quá tải
 *   define('AI_CA_BUNDLE', 'C:/xampp/php/extras/ssl/cacert.pem');            // tùy chọn, chỉ cần khi XAMPP báo lỗi SSL
 *   define('AI_BASE_URL', 'http://127.0.0.1:8099/v1beta');                    // tùy chọn, chỉ dùng khi thử bằng server giả
 *
 * Vì sao có file này? Trước đây hằng AI_MODEL / AI_API_KEY chưa khai báo thì PHP dừng giữa chừng
 * (Fatal error), còn khung chat chỉ hiện chung chung "không phản hồi". Giờ mọi lỗi đều có MÃ + lời giải thích.
 */

/** Giá trị hằng cấu hình, hoặc $default nếu chưa khai báo (tránh Fatal error "Undefined constant"). */
function aiConst(string $name, $default = '')
{
    return defined($name) ? constant($name) : $default;
}

function aiApiKey(): string
{
    return trim((string)aiConst('AI_API_KEY', ''));
}

function aiModel(): string
{
    $m = trim((string)aiConst('AI_MODEL', ''));
    // Chỉ cho ký tự hợp lệ của tên model, tránh chèn "/" hay "?" vào đường dẫn URL
    return preg_match('/^[A-Za-z0-9._-]{3,64}$/', $m) ? $m : 'gemini-3.8-flash';
}

/** Danh sách vấn đề cấu hình (rỗng = ổn). Dùng cho trang chẩn đoán. */
function aiConfigProblems(): array
{
    $p = [];
    if (!function_exists('curl_init')) {
        $p[] = 'PHP chưa bật extension cURL (XAMPP: mở php.ini, bỏ dấu ; trước extension=curl, rồi restart Apache).';
    }
    $key = aiApiKey();
    if ($key === '') {
        $p[] = 'Chưa có AI_API_KEY trong config.php.';
    } elseif (!preg_match('/^[A-Za-z0-9_-]{20,}$/', $key)) {
        $p[] = 'AI_API_KEY có ký tự lạ hoặc quá ngắn (có thể bị thừa dấu cách/ngoặc khi dán).';
    }
    $m = trim((string)aiConst('AI_MODEL', ''));
    if ($m !== '' && !preg_match('/^[A-Za-z0-9._-]{3,64}$/', $m)) {
        $p[] = 'AI_MODEL có ký tự không hợp lệ.';
    }
    $ca = (string)aiConst('AI_CA_BUNDLE', '');
    if ($ca !== '' && !is_file($ca)) {
        $p[] = 'AI_CA_BUNDLE trỏ tới file không tồn tại: ' . $ca;
    }
    return $p;
}

/** Che khóa API nếu lỡ nằm trong thông báo lỗi trước khi ghi log/hiển thị. */
function aiScrub(string $text): string
{
    $key = aiApiKey();
    return $key !== '' ? str_replace($key, '***', $text) : $text;
}

/**
 * Gọi Gemini generateContent.
 *
 * @param string      $system     chỉ dẫn hệ thống (vai trò, quy tắc)
 * @param array       $turns      [['role'=>'user'|'model','text'=>'...'], ...] — lượt cuối phải là 'user'
 * @param array       $options    maxTokens (int), json (bool), schema (array|null), temperature (float)
 * @return array ['ok'=>bool, 'text'=>string, 'code'=>string, 'http'=>int, 'detail'=>string]
 *   code: OK | AI_NO_CURL | AI_NO_KEY | AI_NETWORK | AI_SSL | AI_AUTH | AI_MODEL | AI_QUOTA | AI_BUSY | AI_HTTP | AI_BLOCKED | AI_EMPTY
 */
function aiGenerate(string $system, array $turns, array $options = []): array
{
    // Lỗi 5xx của Google (đặc biệt 503 "quá tải") thường chỉ thoáng qua: thử lại tối đa 3 lần,
    // chờ lâu dần (1,2 giây, 2,4 giây). Vẫn lỗi thì đổi sang model dự phòng nếu có khai báo AI_MODEL_FALLBACK.
    $models = [aiModel()];
    $fb = trim((string)aiConst('AI_MODEL_FALLBACK', ''));
    if ($fb !== '' && preg_match('/^[A-Za-z0-9._-]{3,64}$/', $fb) && $fb !== $models[0]) {
        $models[] = $fb;
    }
    $res = null;
    foreach ($models as $model) {
        for ($try = 0; $try < 3; $try++) {
            $res = aiGenerateOnce($system, $turns, $options, $model);
            if ($res['ok'] || $res['code'] !== 'AI_BUSY') {
                return $res;
            }
            if ($try < 2) {
                usleep(1200000 * ($try + 1));
            }
        }
    }
    return $res;
}

function aiGenerateOnce(string $system, array $turns, array $options, string $model): array
{
    $fail = function (string $code, string $detail, int $http = 0): array {
        return ['ok' => false, 'text' => '', 'code' => $code, 'http' => $http, 'detail' => aiScrub($detail)];
    };

    if (!function_exists('curl_init')) {
        return $fail('AI_NO_CURL', 'PHP chưa bật extension cURL.');
    }
    $key = aiApiKey();
    if ($key === '') {
        return $fail('AI_NO_KEY', 'Chưa khai báo AI_API_KEY trong config.php.');
    }

    $gen = [
        'maxOutputTokens' => (int)($options['maxTokens'] ?? 1500),
        'temperature'     => (float)($options['temperature'] ?? 0.6),
    ];
    // thinkingBudget = 0 (tắt "suy nghĩ" để khỏi ăn hết token) chỉ hợp lệ với dòng 2.5-flash.
    // Gửi cho model khác (ví dụ 2.5-pro) sẽ bị Google từ chối với lỗi 400.
    if (strpos($model, 'gemini-2.5-flash') === 0) {
        $gen['thinkingConfig'] = ['thinkingBudget' => 0];
    } else {
        $gen['maxOutputTokens'] += 1500; // chừa chỗ cho phần suy nghĩ
    }
    if (!empty($options['json'])) {
        $gen['responseMimeType'] = 'application/json';
        if (!empty($options['schema'])) {
            $gen['responseSchema'] = $options['schema'];
        }
    }

    $contents = [];
    foreach ($turns as $t) {
        $contents[] = ['role' => $t['role'] === 'model' ? 'model' : 'user', 'parts' => [['text' => (string)$t['text']]]];
    }

    $payload = json_encode([
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents'          => $contents,
        'generationConfig'  => $gen,
    ], JSON_UNESCAPED_UNICODE);

    $base = rtrim((string)aiConst('AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/');
    $ch = curl_init($base . '/models/' . rawurlencode($model) . ':generateContent');
    $opts = [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_HTTPHEADER     => ['content-type: application/json', 'x-goog-api-key: ' . $key],
        CURLOPT_POSTFIELDS     => $payload,
    ];
    $ca = (string)aiConst('AI_CA_BUNDLE', '');
    if ($ca !== '' && is_file($ca)) {
        $opts[CURLOPT_CAINFO] = $ca; // XAMPP/Windows thường thiếu bộ chứng chỉ gốc → lỗi SSL 60
    }
    curl_setopt_array($ch, $opts);
    $raw     = curl_exec($ch);
    $http    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errNo   = curl_errno($ch);
    $errText = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        // 35, 51, 58, 60, 77, 82, 83, 90, 91: nhóm lỗi chứng chỉ/SSL
        if (in_array($errNo, [35, 51, 58, 60, 77, 82, 83, 90, 91], true)) {
            return $fail('AI_SSL', "cURL lỗi SSL ($errNo): $errText. Trên XAMPP/Windows: tải https://curl.se/ca/cacert.pem, lưu vào C:/xampp/php/extras/ssl/cacert.pem rồi khai báo define('AI_CA_BUNDLE', ...) trong config.php.");
        }
        return $fail('AI_NETWORK', "Không kết nối được tới Google ($errNo): $errText");
    }

    $data = json_decode($raw, true);
    $apiMsg = is_array($data) ? (string)($data['error']['message'] ?? '') : '';

    if ($http !== 200) {
        $detail = "HTTP $http" . ($apiMsg !== '' ? ": $apiMsg" : ': ' . substr($raw, 0, 200));
        if ($http === 400 && stripos($apiMsg, 'API key') !== false) {
            return $fail('AI_AUTH', $detail, $http);
        }
        if ($http === 401 || $http === 403) {
            return $fail('AI_AUTH', $detail, $http);
        }
        if ($http === 404) {
            return $fail('AI_MODEL', $detail . " (model hiện tại: $model)", $http);
        }
        if ($http === 429) {
            return $fail('AI_QUOTA', $detail, $http);
        }
        if (in_array($http, [500, 502, 503, 504], true)) {
            return $fail('AI_BUSY', $detail, $http);   // Google quá tải/lỗi tạm thời
        }
        return $fail('AI_HTTP', $detail, $http);
    }

    // Ghép mọi phần chữ (không chỉ parts[0])
    $text = '';
    foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
        $text .= $part['text'] ?? '';
    }
    $text = trim($text);

    if ($text === '') {
        $block  = (string)($data['promptFeedback']['blockReason'] ?? '');
        $finish = (string)($data['candidates'][0]['finishReason'] ?? '');
        if ($block !== '' || $finish === 'SAFETY') {
            return $fail('AI_BLOCKED', 'Bị bộ lọc an toàn của Google chặn (' . ($block ?: $finish) . ').', $http);
        }
        return $fail('AI_EMPTY', 'Google trả về nội dung rỗng (finishReason=' . ($finish ?: 'không rõ') . ').', $http);
    }
    return ['ok' => true, 'text' => $text, 'code' => 'OK', 'http' => $http, 'detail' => ''];
}

/** Lời nhắn thân thiện cho người dùng thường (không lộ chi tiết kỹ thuật). */
function aiFriendlyError(string $code): string
{
    switch ($code) {
        case 'AI_NO_CURL':
        case 'AI_NO_KEY':
        case 'AI_AUTH':
        case 'AI_MODEL':
            return 'Chatbot chưa được cấu hình xong. Vui lòng báo quản trị viên.';
        case 'AI_BUSY':
            return 'Máy chủ AI đang quá tải, bạn bấm "Thử lại" sau vài giây nhé.';
        case 'AI_QUOTA':
            return 'Chatbot đang quá tải, bạn thử lại sau ít phút nhé.';
        case 'AI_BLOCKED':
            return 'Mình không thể trả lời câu này. Bạn thử hỏi cách khác về tập luyện hoặc dinh dưỡng nhé.';
        default:
            return 'Chatbot tạm thời không phản hồi, thử lại sau.';
    }
}
