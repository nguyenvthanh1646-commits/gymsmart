<?php
/**
 * includes/expert-rules.php — quy tắc thuần của phần tư vấn sức khỏe từ chuyên gia (A-55).
 *
 * File này THUẦN: không đụng DB, không đọc $_POST/$_SESSION. Test ở test-expert.php không cần MySQL.
 * Phần truy vấn ở includes/expert-db.php, phân quyền ở includes/expert.php.
 *
 * Giới hạn an toàn (ghi ở trang và trong cam kết của chuyên gia): tư vấn trực tuyến KHÔNG thay thế khám trực tiếp,
 * chuyên gia không chẩn đoán xác định và không kê đơn thuốc; câu hỏi có dấu hiệu cấp cứu thì nhắc gọi 115.
 */

require_once __DIR__ . '/coach-rules.php';   // coachCleanText(), coachTextError(), coachMessageRateError()

const EXPERT_PROFESSIONS = [
    'DOCTOR'       => 'Bác sĩ',
    'NUTRITIONIST' => 'Chuyên gia dinh dưỡng',
    'PHYSIO'       => 'Vật lý trị liệu',
];
/** Chủ đề câu hỏi. Chuyên gia chọn 1-4 chủ đề mình nhận; "Vấn đề khác" mọi chuyên gia đều thấy. */
const HEALTH_TOPICS = [
    'INJURY'    => 'Chấn thương, đau khi tập',
    'CONDITION' => 'Bệnh nền và tập luyện',
    'NUTRITION' => 'Dinh dưỡng, cân nặng',
    'RECOVERY'  => 'Phục hồi, giấc ngủ',
    'WOMEN'     => 'Sức khỏe phụ nữ (mang thai, sau sinh)',
    'OTHER'     => 'Vấn đề khác',
];
const EXPERT_TOPICS_MAX = 4;
const HEALTH_Q_OPEN_MAX = 3;        // mỗi người tối đa 3 câu chưa đóng (chờ nhận + đang trao đổi)
const HEALTH_Q_PER_DAY = 5;         // tối đa 5 câu mới mỗi 24 giờ
const EXPERT_CLAIM_MAX = 15;        // mỗi chuyên gia tối đa 15 câu đang trao đổi cùng lúc
const HEALTH_MSG_MAX = 2000;

const HEALTH_STATUS_LABELS = [
    'OPEN'      => 'Chờ chuyên gia nhận',
    'CLAIMED'   => 'Đang trao đổi',
    'CLOSED'    => 'Đã đóng',
    'CANCELLED' => 'Đã hủy',
];
const HEALTH_STATUS_TONES = ['OPEN' => 'warn', 'CLAIMED' => 'info', 'CLOSED' => 'ok', 'CANCELLED' => ''];

/**
 * Dấu hiệu cần cấp cứu: cụm từ (viết thường) => nhãn. Chỉ để NHẮC gọi 115, không phải chẩn đoán.
 * Cụm từ chọn cụ thể để ít bắt nhầm (ví dụ không dùng "liệt" vì trùng "liệt kê").
 */
const HEALTH_RED_FLAGS = [
    'đau ngực' => 'Đau hoặc tức ngực', 'tức ngực' => 'Đau hoặc tức ngực', 'thắt ngực' => 'Đau hoặc tức ngực',
    'khó thở' => 'Khó thở', 'không thở được' => 'Khó thở',
    'ngất' => 'Ngất, xỉu', 'xỉu' => 'Ngất, xỉu', 'bất tỉnh' => 'Ngất, xỉu',
    'yếu nửa người' => 'Dấu hiệu đột quỵ', 'tê nửa người' => 'Dấu hiệu đột quỵ', 'méo miệng' => 'Dấu hiệu đột quỵ',
    'nói ngọng đột ngột' => 'Dấu hiệu đột quỵ', 'bị liệt' => 'Dấu hiệu đột quỵ',
    'co giật' => 'Co giật',
    'ho ra máu' => 'Chảy máu bất thường', 'nôn ra máu' => 'Chảy máu bất thường', 'đi ngoài ra máu' => 'Chảy máu bất thường',
    'gãy xương' => 'Nghi gãy xương', 'xương lòi' => 'Nghi gãy xương',
    'tự tử' => 'Ý nghĩ tự làm hại bản thân', 'muốn chết' => 'Ý nghĩ tự làm hại bản thân', 'tự làm hại' => 'Ý nghĩ tự làm hại bản thân',
];

/** Các dấu hiệu cấp cứu tìm thấy trong đoạn chữ (không trùng lặp, theo thứ tự xuất hiện trong bảng). */
function healthRedFlags(string $text): array
{
    $t = mb_strtolower($text);
    $found = [];
    foreach (HEALTH_RED_FLAGS as $phrase => $label) {
        if (mb_strpos($t, $phrase) !== false && !in_array($label, $found, true)) {
            $found[] = $label;
        }
    }
    return $found;
}

/** Đổi chuỗi mã chủ đề trong DB thành danh sách nhãn; mã lạ bị bỏ qua. */
function healthTopicLabels(string $csv): array
{
    $out = [];
    foreach (explode(',', $csv) as $code) {
        $code = trim($code);
        if (isset(HEALTH_TOPICS[$code])) {
            $out[] = HEALTH_TOPICS[$code];
        }
    }
    return $out;
}

/** Lọc mảng mã chủ đề gửi lên: chỉ nhận mã trong whitelist, bỏ trùng. Trả về ['codes' => [...], 'error' => ?string]. */
function expertPickTopics($raw): array
{
    $codes = is_array($raw) ? array_values(array_unique(array_filter($raw, 'is_string'))) : [];
    $valid = array_values(array_filter($codes, fn($c) => isset(HEALTH_TOPICS[$c])));
    if (count($valid) !== count($codes)) {
        return ['codes' => [], 'error' => 'Chủ đề không hợp lệ.'];
    }
    if (!$valid) {
        return ['codes' => [], 'error' => 'Chọn ít nhất 1 chủ đề.'];
    }
    if (count($valid) > EXPERT_TOPICS_MAX) {
        return ['codes' => [], 'error' => 'Chọn tối đa ' . EXPERT_TOPICS_MAX . ' chủ đề đúng chuyên môn của bạn.'];
    }
    return ['codes' => $valid, 'error' => null];
}

/**
 * Kiểm tra đơn đăng ký làm chuyên gia. $in là mảng thô (thường là $_POST).
 * Trả về ['data' => mảng đã làm sạch, 'errors' => [tên ô => lỗi]]. File chứng chỉ kiểm tra riêng (coachStoreCertificate).
 */
function validateExpertApplication(array $in): array
{
    $errors = [];
    $data = [
        'profession' => is_string($in['profession'] ?? null) ? $in['profession'] : '',
        'title'      => coachNormalizeName($in['title'] ?? ''),
        'workplace'  => coachNormalizeName($in['workplace'] ?? ''),
        'license_no' => coachNormalizeName($in['license_no'] ?? ''),
        'bio'        => coachCleanText($in['bio'] ?? ''),
    ];
    if (!isset(EXPERT_PROFESSIONS[$data['profession']])) {
        $errors['profession'] = 'Chọn ngành của bạn.';
    }
    if ($e = coachTextError($data['title'], 'Chức danh', 5, 120)) $errors['title'] = $e;
    if ($e = coachTextError($data['workplace'], 'Nơi công tác', 3, 150)) $errors['workplace'] = $e;
    if ($e = coachTextError($data['license_no'], 'Số chứng chỉ hành nghề', 3, 50)) {
        $errors['license_no'] = $e;
    } elseif (!preg_match('#^[\p{L}0-9/.\- ]+$#u', $data['license_no'])) {
        $errors['license_no'] = 'Số chứng chỉ hành nghề chỉ gồm chữ, số và các dấu / . -';
    }
    if ($e = coachTextError($data['bio'], 'Giới thiệu', 80, 1500)) $errors['bio'] = $e;

    $t = expertPickTopics($in['topics'] ?? []);
    if ($t['error'] !== null) {
        $errors['topics'] = $t['error'];
    }
    $data['topics'] = implode(',', $t['codes']);

    $years = filter_var($in['experience_years'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 60]]);
    if ($years === false) {
        $errors['experience_years'] = 'Số năm hành nghề phải là số nguyên từ 0 đến 60.';
    }
    $data['experience_years'] = $years === false ? 0 : $years;

    if (($in['agree_truth'] ?? '') !== '1') {
        $errors['agree_truth'] = 'Bạn cần xác nhận thông tin và chứng chỉ là thật.';
    }
    if (($in['agree_rules'] ?? '') !== '1') {
        $errors['agree_rules'] = 'Bạn cần đồng ý với quy tắc tư vấn.';
    }
    return ['data' => $data, 'errors' => $errors];
}

/**
 * Kiểm tra câu hỏi tư vấn. Nếu câu hỏi có dấu hiệu cấp cứu (healthRedFlags) mà người hỏi chưa tick
 * "tôi không trong tình huống khẩn cấp" thì báo lỗi ô emergency_ack để trang hiện cảnh báo gọi 115.
 * Trả về ['data' => [topic, title, body, share_profile, red_flags], 'errors' => [...]].
 */
function validateHealthQuestion(array $in): array
{
    $errors = [];
    $data = [
        'topic'         => is_string($in['topic'] ?? null) ? $in['topic'] : '',
        'title'         => coachNormalizeName($in['title'] ?? ''),
        'body'          => coachCleanText($in['body'] ?? ''),
        'share_profile' => ($in['share_profile'] ?? '') === '1',
    ];
    if (!isset(HEALTH_TOPICS[$data['topic']])) {
        $errors['topic'] = 'Chọn chủ đề câu hỏi.';
    }
    if ($e = coachTextError($data['title'], 'Tiêu đề', 10, 150)) $errors['title'] = $e;
    if ($e = coachTextError($data['body'], 'Nội dung', 30, 2000)) $errors['body'] = $e;
    $data['red_flags'] = healthRedFlags($data['title'] . "\n" . $data['body']);
    if ($data['red_flags'] && ($in['emergency_ack'] ?? '') !== '1') {
        $errors['emergency_ack'] = 'Câu hỏi có dấu hiệu cần cấp cứu (' . implode(', ', $data['red_flags'])
            . '). Nếu đang xảy ra, hãy gọi 115 hoặc tới cơ sở y tế gần nhất ngay. Nếu không khẩn cấp, tick xác nhận rồi gửi lại.';
    }
    return ['data' => $data, 'errors' => $errors];
}

/**
 * Máy trạng thái câu hỏi. Trả về trạng thái mới hoặc null nếu không được làm.
 *   asker : cancel (OPEN → CANCELLED), close (CLAIMED → CLOSED)
 *   expert: claim (OPEN → CLAIMED), close (CLAIMED → CLOSED),
 *           release (CLAIMED → OPEN) chỉ khi chuyên gia CHƯA trả lời tin nào ($expertReplied = false)
 */
function healthNextStatus(string $current, string $actor, string $action, bool $expertReplied = false): ?string
{
    $rules = [
        'asker'  => ['cancel' => ['OPEN' => 'CANCELLED'], 'close' => ['CLAIMED' => 'CLOSED']],
        'expert' => ['claim' => ['OPEN' => 'CLAIMED'], 'close' => ['CLAIMED' => 'CLOSED'], 'release' => ['CLAIMED' => 'OPEN']],
    ];
    $next = $rules[$actor][$action][$current] ?? null;
    if ($action === 'release' && $expertReplied) {
        return null;   // đã trả lời rồi thì phải đóng, không trả câu hỏi về cho người khác
    }
    return $next;
}

/** Chuyên gia với các chủ đề $expertTopics (mã) có nhận được câu hỏi chủ đề $topic không. "Vấn đề khác" ai cũng nhận. */
function expertTopicMatches(array $expertTopics, string $topic): bool
{
    return $topic === 'OTHER' || in_array($topic, $expertTopics, true);
}

/** Người hỏi gửi thêm câu hỏi được không: chưa đóng < 3 và trong 24 giờ < 5. Trả câu lỗi hoặc null. */
function healthAskLimitError(int $openCount, int $last24h): ?string
{
    if ($openCount >= HEALTH_Q_OPEN_MAX) {
        return 'Bạn đang có ' . $openCount . ' câu hỏi chưa đóng. Hãy đóng bớt câu đã được trả lời rồi hỏi tiếp.';
    }
    if ($last24h >= HEALTH_Q_PER_DAY) {
        return 'Bạn đã gửi ' . HEALTH_Q_PER_DAY . ' câu hỏi trong 24 giờ qua. Hãy thử lại sau.';
    }
    return null;
}

const HEALTH_GENDER_LABELS = ['MALE' => 'Nam', 'FEMALE' => 'Nữ', 'OTHER' => 'Khác'];
const HEALTH_GOAL_LABELS = ['LOSE_FAT' => 'Giảm mỡ', 'MAINTAIN' => 'Duy trì', 'GAIN_MUSCLE' => 'Tăng cơ'];
const HEALTH_ACTIVITY_LABELS = [
    'SEDENTARY' => 'Ít vận động', 'LIGHT' => 'Vận động nhẹ', 'MODERATE' => 'Vận động vừa',
    'ACTIVE' => 'Vận động nhiều', 'EXTRA_ACTIVE' => 'Vận động rất nhiều',
];

/**
 * Số liệu hồ sơ người hỏi đồng ý chia sẻ, dạng nhiều dòng "Nhãn: giá trị". Bỏ qua ô trống. Không có email, họ tên.
 * $user: các cột age, gender, height_cm, weight_kg, activity_level, goal. $metric: số đo gần nhất ['recorded_date','weight_kg','waist_cm'] hoặc null.
 */
function healthProfileSnapshot(array $user, ?array $metric): string
{
    $lines = [];
    if (!empty($user['age'])) $lines[] = 'Tuổi: ' . (int)$user['age'];
    if (isset(HEALTH_GENDER_LABELS[$user['gender'] ?? ''])) $lines[] = 'Giới tính: ' . HEALTH_GENDER_LABELS[$user['gender']];
    if (!empty($user['height_cm'])) $lines[] = 'Chiều cao: ' . rtrim(rtrim(number_format((float)$user['height_cm'], 1, ',', ''), '0'), ',') . ' cm';
    if ($metric !== null && !empty($metric['weight_kg'])) {
        $lines[] = 'Cân nặng: ' . rtrim(rtrim(number_format((float)$metric['weight_kg'], 1, ',', ''), '0'), ',') . ' kg (đo ngày ' . date('d/m/Y', strtotime((string)$metric['recorded_date'])) . ')';
    } elseif (!empty($user['weight_kg'])) {
        $lines[] = 'Cân nặng: ' . rtrim(rtrim(number_format((float)$user['weight_kg'], 1, ',', ''), '0'), ',') . ' kg';
    }
    if ($metric !== null && !empty($metric['waist_cm'])) $lines[] = 'Vòng eo: ' . rtrim(rtrim(number_format((float)$metric['waist_cm'], 1, ',', ''), '0'), ',') . ' cm';
    if (isset(HEALTH_ACTIVITY_LABELS[$user['activity_level'] ?? ''])) $lines[] = 'Mức vận động: ' . HEALTH_ACTIVITY_LABELS[$user['activity_level']];
    if (isset(HEALTH_GOAL_LABELS[$user['goal'] ?? ''])) $lines[] = 'Mục tiêu tập: ' . HEALTH_GOAL_LABELS[$user['goal']];
    return implode("\n", $lines);
}

/** Tên người hỏi chuyên gia thấy: chỉ TÊN (từ cuối của họ tên), không họ tên đầy đủ, không email. */
function healthAskerName(string $fullName): string
{
    $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    return $parts ? (string)end($parts) : 'Người hỏi';
}
