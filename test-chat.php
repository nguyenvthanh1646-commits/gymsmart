<?php
// test-chat.php — kiểm tra dữ liệu đưa vào chatbot (includes/chat-context.php) và lịch sử chat (includes/chat-history.php).
// Chạy ở gốc repo: php test-chat.php   (phần DB dùng SQLite trong bộ nhớ, không cần MySQL)

require __DIR__ . '/public/includes/chat-context.php';
require __DIR__ . '/public/includes/chat-history.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}

// ===== formatTrainingContext (thuần) =====
$tip = fn(string $a, ?float $w) => ['action' => $a, 'weight' => $w, 'text' => ''];
$full = [
    'plan' => ['split' => 'Toàn thân', 'days' => 3, 'trainingDays' => [1, 3, 5]],
    'todayDow' => 3, 'todayLabel' => 'Toàn thân',
    'today' => [['name' => 'Hít đất', 'sets' => 2, 'reps' => 8, 'tip' => $tip('keep', 0.0)],
                ['name' => 'Squat tạ đơn', 'sets' => 3, 'reps' => 8, 'tip' => $tip('increase', 22.5)]],
    'next' => ['day' => 5, 'label' => 'Toàn thân', 'count' => 6],
    'week' => ['days' => 2, 'sets' => 18, 'recent' => [['date' => '2026-11-16', 'name' => 'Squat tạ đơn', 'sets' => 3, 'reps' => 8, 'weight' => 20.0]]],
    'metrics' => ['date' => '2026-11-18', 'weight' => 72.4, 'waist' => 85.6, 'fat' => null, 'change' => -2.1, 'since' => '2026-10-20', 'count' => 5],
];
$txt = formatTrainingContext($full);
check('Ngữ cảnh: có kiểu lịch và số buổi', str_contains($txt, 'Toàn thân, 3 buổi/tuần'));
check('Ngữ cảnh: buổi hôm nay có khoảng lặp 6-8 và gợi ý tăng tạ 22,5 kg', str_contains($txt, 'Squat tạ đơn 3x6-8 (gợi ý 22,5 kg, tăng tạ)'));
check('Ngữ cảnh: bài không tạ không ghi "gợi ý 0 kg"', !str_contains($txt, '0 kg, giữ mức'));
check('Ngữ cảnh: nhật ký 7 ngày có số ngày và số set', str_contains($txt, 'tập 2 ngày, tổng 18 set') && str_contains($txt, '16/11 Squat tạ đơn 20 kg 3x8'));
check('Ngữ cảnh: số đo có xu hướng âm', str_contains($txt, '72,4 kg, vòng eo 85,6 cm; so với 20/10: -2,1 kg (5 lần đo)'));
check('Ngữ cảnh: không lọt tên/email người dùng', !preg_match('/@|email/i', $txt));

$empty = ['plan' => null, 'todayDow' => 1, 'today' => [], 'next' => null, 'week' => ['days' => 0, 'sets' => 0, 'recent' => []], 'metrics' => null];
$txt = formatTrainingContext($empty);
check('Ngữ cảnh rỗng: báo chưa có lịch, chưa có nhật ký, chưa có số đo',
    str_contains($txt, 'chưa có lịch') && str_contains($txt, 'chưa ghi nhật ký') && str_contains($txt, 'Số đo: chưa ghi'));
$rest = $full;
$rest['today'] = [];
check('Ngày nghỉ: nói rõ và nêu buổi kế tiếp', str_contains(formatTrainingContext($rest), 'là ngày nghỉ; buổi kế tiếp: Thứ Sáu (Toàn thân, 6 bài)'));
check('Ngữ cảnh ngắn gọn (dưới 1.200 ký tự)', mb_strlen(formatTrainingContext($full)) < 1200);

// ===== Lịch sử chat (SQLite) =====
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('CREATE TABLE chatbot_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, title TEXT NULL, created_at TEXT, updated_at TEXT)');
    $db->exec("CREATE TABLE chatbot_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id INT NOT NULL, role TEXT NOT NULL, content TEXT NOT NULL, created_at TEXT)");

    check('Chưa có gì: lịch sử rỗng', chatHistoryLoad($db, 1) === []);
    chatHistoryAppend($db, 1, 'Hỏi 1', 'Đáp 1');
    chatHistoryAppend($db, 1, 'Hỏi 2', 'Đáp 2');
    chatHistoryAppend($db, 2, 'Câu của người khác', 'Đáp');
    $h = chatHistoryLoad($db, 1);
    check('Lưu và đọc đúng thứ tự cũ đến mới', count($h) === 4 && $h[0]['text'] === 'Hỏi 1' && $h[0]['who'] === 'me' && $h[3]['who'] === 'bot');
    check('Không đọc được hội thoại của người khác', !in_array('Câu của người khác', array_column($h, 'text'), true));
    check('Tiêu đề cuộc trò chuyện là câu hỏi đầu', $db->query('SELECT title FROM chatbot_sessions WHERE user_id = 1')->fetchColumn() === 'Hỏi 1');
    check('Đếm câu hỏi 1 giờ qua theo tài khoản', chatHistoryCountRecent($db, 1, 3600) === 2 && chatHistoryCountRecent($db, 2, 3600) === 1);

    $first = chatLatestSessionId($db, 1);
    $new = chatHistoryStartNew($db, 1);
    check('Cuộc trò chuyện mới: khung chat trống, cuộc cũ vẫn giữ', $new !== $first && chatHistoryLoad($db, 1) === []
        && (int)$db->query("SELECT COUNT(*) FROM chatbot_messages WHERE session_id = $first")->fetchColumn() === 4);
    check('Bấm "mới" 2 lần liền không tạo cuộc trống thứ hai', chatHistoryStartNew($db, 1) === $new);
    chatHistoryAppend($db, 1, 'Hỏi 3', 'Đáp 3');
    check('Cuộc mới lấy câu hỏi đầu làm tiêu đề', $db->query("SELECT title FROM chatbot_sessions WHERE id = $new")->fetchColumn() === 'Hỏi 3');

    for ($i = 0; $i < 6; $i++) {
        chatHistoryStartNew($db, 1);
        chatHistoryAppend($db, 1, "Hỏi lặp $i", 'Đáp');
    }
    check('Chỉ giữ 5 cuộc gần nhất', (int)$db->query('SELECT COUNT(*) FROM chatbot_sessions WHERE user_id = 1')->fetchColumn() === CHAT_KEEP_SESSIONS);
    check('Xóa cuộc cũ thì xóa cả tin nhắn của nó', (int)$db->query("SELECT COUNT(*) FROM chatbot_messages WHERE session_id = $first")->fetchColumn() === 0);

    check('Xóa lịch sử: trả số cuộc đã xóa', chatHistoryClear($db, 1) === CHAT_KEEP_SESSIONS);
    check('Xóa lịch sử: không còn tin nào của user 1', chatHistoryLoad($db, 1) === [] && chatHistoryCountRecent($db, 1, 3600) === 0);
    check('Xóa lịch sử: không đụng người khác', count(chatHistoryLoad($db, 2)) === 2);

    chatHistoryAppend($db, 3, str_repeat('a', 5000), str_repeat('b', 5000));
    $long = chatHistoryLoad($db, 3);
    check('Tin quá dài bị cắt còn 4.000 ký tự', mb_strlen($long[0]['text']) === CHAT_MAX_LEN && mb_strlen($long[1]['text']) === CHAT_MAX_LEN);
} else {
    echo "[BỎ QUA] Không có pdo_sqlite nên không chạy phần DB\n";
}

// ===== Trợ lý HLV: coach-ai.php phải tự nạp đủ hằng nó dùng =====
// api/chatbot.php chỉ nạp auth/csrf/ai rồi coach-ai.php (không nạp coach.php như các trang /coach/).
// Lỗi cũ: thiếu COACH_GOAL_LABELS nên mọi câu hỏi tới Trợ lý HLV đều báo "GymSmart AI gặp sự cố".
// Chạy trong tiến trình PHP riêng để không bị các file test khác nạp sẵn hằng.
$coachAi = __DIR__ . '/public/includes/coach-ai.php';
preg_match_all('/\bCOACH_[A-Z_]+\b/', (string)file_get_contents($coachAi), $m);
$consts = array_values(array_unique($m[0]));
$probe = tempnam(sys_get_temp_dir(), 'gsai') . '.php';
file_put_contents($probe, '<?php require ' . var_export($coachAi, true) . '; $miss = []; foreach (' . var_export($consts, true)
    . ' as $c) { if (!defined($c)) { $miss[] = $c; } } echo implode(",", $miss);');
$out = trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=1 ' . escapeshellarg($probe) . ' 2>&1'));
@unlink($probe);
check('Trợ lý HLV: nạp riêng coach-ai.php (như api/chatbot.php) thì đủ ' . count($consts) . ' hằng COACH_* nó dùng', $out === '' || $out === null);
if ($out) {
    echo "       Thiếu hoặc lỗi: $out\n";
}

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
