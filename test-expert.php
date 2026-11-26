<?php
/**
 * test-expert.php — tư vấn sức khỏe từ chuyên gia (A-55). Chạy: php test-expert.php
 * Phần 1: quy tắc thuần (includes/expert-rules.php), không cần DB.
 * Phần 2: lớp DB (includes/expert-db.php) trên SQLite in-memory, cần pdo_sqlite.
 */
require __DIR__ . '/public/includes/expert-db.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $fails++;
}
function errOf(callable $fn): ?string
{
    try { $fn(); } catch (CoachException $e) { return $e->getMessage(); }
    return null;
}

// ===================== PHẦN 1: QUY TẮC THUẦN =====================
$app = [
    'profession' => 'DOCTOR', 'title' => 'Bác sĩ Y học thể thao', 'workplace' => 'Bệnh viện mẫu', 'license_no' => '001234/HCM-CCHN',
    'experience_years' => '7', 'topics' => ['INJURY', 'RECOVERY'], 'bio' => str_repeat('Tư vấn chấn thương thể thao và phục hồi. ', 3),
    'agree_truth' => '1', 'agree_rules' => '1',
];
$v = validateExpertApplication($app);
check('Đơn chuyên gia hợp lệ', $v['errors'] === [] && $v['data']['topics'] === 'INJURY,RECOVERY' && $v['data']['experience_years'] === 7);
check('Ngành ngoài danh sách bị chặn', isset(validateExpertApplication(['profession' => 'HEALER'] + $app)['errors']['profession']));
check('Số chứng chỉ có ký tự lạ (<script>) bị chặn', isset(validateExpertApplication(['license_no' => '<script>'] + $app)['errors']['license_no']));
check('Chủ đề ngoài whitelist bị chặn', isset(validateExpertApplication(['topics' => ['INJURY', 'HACK']] + $app)['errors']['topics']));
check('Quá 4 chủ đề bị chặn', isset(validateExpertApplication(['topics' => ['INJURY', 'CONDITION', 'NUTRITION', 'RECOVERY', 'WOMEN']] + $app)['errors']['topics']));
check('Chưa tick cam kết quy tắc tư vấn bị chặn', isset(validateExpertApplication(['agree_rules' => ''] + $app)['errors']['agree_rules']));
check('Giới thiệu dưới 80 ký tự bị chặn', isset(validateExpertApplication(['bio' => 'ngắn'] + $app)['errors']['bio']));

check('Nhận ra đau ngực và khó thở', healthRedFlags('Tập xong bị TỨC NGỰC, hơi khó thở') === ['Đau hoặc tức ngực', 'Khó thở']);
check('"liệt kê" không bị nhầm là dấu hiệu đột quỵ', healthRedFlags('Em liệt kê các bài đang tập: squat, deadlift') === []);
check('Ý nghĩ tự hại được nhận ra', healthRedFlags('dạo này em muốn chết') === ['Ý nghĩ tự làm hại bản thân']);

$q = ['topic' => 'INJURY', 'title' => 'Đau gối khi squat sâu', 'body' => str_repeat('Đau mặt trước gối khi xuống sâu. ', 2), 'share_profile' => '1'];
$v = validateHealthQuestion($q);
check('Câu hỏi hợp lệ, có chia sẻ hồ sơ', $v['errors'] === [] && $v['data']['share_profile'] === true && $v['data']['red_flags'] === []);
check('Chủ đề lạ bị chặn', isset(validateHealthQuestion(['topic' => 'X'] + $q)['errors']['topic']));
check('Nội dung dưới 30 ký tự bị chặn', isset(validateHealthQuestion(['body' => 'đau gối'] + $q)['errors']['body']));
$red = ['body' => 'Tập xong bị đau ngực trái lan ra tay, kéo dài 10 phút rồi hết.'] + $q;
check('Có dấu hiệu cấp cứu mà chưa xác nhận → lỗi nhắc gọi 115', str_contains((string)(validateHealthQuestion($red)['errors']['emergency_ack'] ?? ''), '115'));
check('Đã tick "không khẩn cấp" → gửi được, vẫn ghi dấu hiệu', validateHealthQuestion($red + ['emergency_ack' => '1'])['errors'] === []
    && validateHealthQuestion($red + ['emergency_ack' => '1'])['data']['red_flags'] === ['Đau hoặc tức ngực']);

check('Chuyên gia nhận câu đang chờ', healthNextStatus('OPEN', 'expert', 'claim') === 'CLAIMED');
check('Không nhận câu đã có người nhận', healthNextStatus('CLAIMED', 'expert', 'claim') === null);
check('Trả lại được khi chưa trả lời', healthNextStatus('CLAIMED', 'expert', 'release', false) === 'OPEN');
check('Đã trả lời thì không trả lại được', healthNextStatus('CLAIMED', 'expert', 'release', true) === null);
check('Người hỏi hủy khi đang chờ, không hủy khi đã có người nhận', healthNextStatus('OPEN', 'asker', 'cancel') === 'CANCELLED'
    && healthNextStatus('CLAIMED', 'asker', 'cancel') === null);
check('Người hỏi đóng khi đang trao đổi', healthNextStatus('CLAIMED', 'asker', 'close') === 'CLOSED');
check('Người hỏi không tự "nhận" câu hỏi', healthNextStatus('OPEN', 'asker', 'claim') === null);
check('Chủ đề khớp; "Vấn đề khác" ai cũng nhận', expertTopicMatches(['INJURY'], 'INJURY') && !expertTopicMatches(['INJURY'], 'WOMEN') && expertTopicMatches(['INJURY'], 'OTHER'));
check('Giới hạn: 3 câu chưa đóng, 5 câu/24 giờ', healthAskLimitError(3, 0) !== null && healthAskLimitError(0, 5) !== null && healthAskLimitError(2, 4) === null);

$snap = healthProfileSnapshot(['age' => 28, 'gender' => 'FEMALE', 'height_cm' => '160.0', 'weight_kg' => '56', 'activity_level' => 'MODERATE', 'goal' => 'LOSE_FAT'],
    ['recorded_date' => '2026-11-20', 'weight_kg' => '55.5', 'waist_cm' => '70']);
check('Số liệu chia sẻ: dùng cân nặng đo gần nhất, không có họ tên/email', $snap === "Tuổi: 28\nGiới tính: Nữ\nChiều cao: 160 cm\nCân nặng: 55,5 kg (đo ngày 20/11/2026)\nVòng eo: 70 cm\nMức vận động: Vận động vừa\nMục tiêu tập: Giảm mỡ");
check('Hồ sơ trống → chuỗi rỗng', healthProfileSnapshot([], null) === '');
check('Chuyên gia chỉ thấy tên người hỏi', healthAskerName('Nguyễn Thị Lan') === 'Lan' && healthAskerName('') === 'Người hỏi');

// ===================== PHẦN 2: LỚP DB TRÊN SQLITE =====================
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "[BỎ QUA] Không có pdo_sqlite nên không chạy phần DB\n";
} else {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT, role TEXT, avatar TEXT, age INT, gender TEXT, height_cm REAL, weight_kg REAL, activity_level TEXT, goal TEXT)");
    $pdo->exec("CREATE TABLE expert_applications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, profession TEXT, title TEXT, workplace TEXT, license_no TEXT,
        experience_years INT, topics TEXT, bio TEXT, certificate_file TEXT, status TEXT DEFAULT 'PENDING', admin_note TEXT NOT NULL DEFAULT '', reviewed_by INT, reviewed_at TEXT, created_at TEXT,
        open_user_id INT GENERATED ALWAYS AS (CASE WHEN status = 'PENDING' THEN user_id END) STORED UNIQUE)");
    $pdo->exec("CREATE TABLE expert_profiles (user_id INTEGER PRIMARY KEY, profession TEXT, title TEXT, workplace TEXT, license_no TEXT, experience_years INT, topics TEXT, bio TEXT,
        accepting INT DEFAULT 1, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE health_questions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, topic TEXT, title TEXT, body TEXT, profile_snapshot TEXT, red_flags TEXT NOT NULL DEFAULT '',
        status TEXT DEFAULT 'OPEN', expert_id INT, helpful INT, created_at TEXT, claimed_at TEXT, closed_at TEXT, closed_by INT)");
    $pdo->exec("CREATE TABLE health_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, question_id INT, sender_id INT, body TEXT, read_at TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE body_metrics (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, recorded_date TEXT, weight_kg REAL, waist_cm REAL)");
    $pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT, title TEXT, body TEXT DEFAULT '', link TEXT DEFAULT '', read_at TEXT, created_at TEXT)");
    foreach ([[1, 'Quản Trị', 'ADMIN'], [2, 'BS Hai', 'USER'], [3, 'CG Ba', 'USER'], [4, 'Nguyễn Thị Lan', 'USER'], [5, 'Trần Bình', 'USER'], [6, 'HLV Sáu', 'COACH']] as $u) {
        $pdo->prepare('INSERT INTO users (id, full_name, role) VALUES (?, ?, ?)')->execute($u);
    }
    $pdo->exec("UPDATE users SET age = 28, gender = 'FEMALE', height_cm = 160, weight_kg = 56, goal = 'LOSE_FAT' WHERE id = 4");
    $pdo->exec("INSERT INTO body_metrics (user_id, recorded_date, weight_kg, waist_cm) VALUES (4, '2026-11-20', 55.5, 70)");

    check('DB đủ bảng', expertTablesReady($pdo));
    $data = validateExpertApplication($app)['data'] + ['certificate_file' => 'c2-0123456789abcdef.png'];
    check('Nộp đơn thiếu file chứng chỉ bị chặn', errOf(fn() => expertSubmitApplication($pdo, 2, ['certificate_file' => null] + $data)) !== null);
    $a2 = expertSubmitApplication($pdo, 2, $data);
    check('Nộp đơn: ADMIN được báo', (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 1 AND type = 'admin.expert_application'")->fetchColumn() === 1);
    check('Nộp đơn thứ hai khi đang chờ bị chặn', errOf(fn() => expertSubmitApplication($pdo, 2, $data)) !== null);
    check('HLV không nộp đơn chuyên gia được', errOf(fn() => expertSubmitApplication($pdo, 6, $data)) !== null);
    check('Từ chối không ghi lý do bị chặn', errOf(fn() => expertRejectApplication($pdo, $a2, 1, '')) !== null);
    expertApproveApplication($pdo, $a2, 1, '');
    check('Duyệt: vai trò EXPERT, có hồ sơ chuyên gia', $pdo->query('SELECT role FROM users WHERE id = 2')->fetchColumn() === 'EXPERT' && expertProfile($pdo, 2)['topics'] === 'INJURY,RECOVERY');
    check('Không duyệt lại đơn đã xử lý', errOf(fn() => expertApproveApplication($pdo, $a2, 1, '')) !== null);
    $a3 = expertSubmitApplication($pdo, 3, ['profession' => 'NUTRITIONIST', 'topics' => 'NUTRITION,WOMEN'] + $data);
    expertApproveApplication($pdo, $a3, 1, '');
    check('Danh sách công khai không có số chứng chỉ', count(expertListPublic($pdo)) === 2 && !array_key_exists('license_no', expertListPublic($pdo)[0]));

    $qd = validateHealthQuestion($q)['data'];
    $q1 = healthAsk($pdo, 4, $qd);
    check('Hỏi có chia sẻ: chụp số liệu đo gần nhất', str_contains((string)$pdo->query("SELECT profile_snapshot FROM health_questions WHERE id = $q1")->fetchColumn(), '55,5 kg'));
    check('Chỉ chuyên gia đúng chủ đề (chấn thương) được báo', (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'expert.question_new' AND user_id = 2")->fetchColumn() === 1
        && (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'expert.question_new' AND user_id = 3")->fetchColumn() === 0);
    $q2 = healthAsk($pdo, 4, ['share_profile' => false, 'topic' => 'NUTRITION'] + $qd);
    check('Không tick chia sẻ thì không lưu số liệu', $pdo->query("SELECT profile_snapshot FROM health_questions WHERE id = $q2")->fetchColumn() === null);
    healthAsk($pdo, 4, ['topic' => 'OTHER'] + $qd);
    check('Câu thứ 4 khi đang có 3 câu chưa đóng bị chặn', str_contains((string)errOf(fn() => healthAsk($pdo, 4, $qd)), 'chưa đóng'));
    check('ADMIN không gửi câu hỏi được', errOf(fn() => healthAsk($pdo, 1, $qd)) !== null);
    check('HLV gửi câu hỏi được', errOf(fn() => healthAsk($pdo, 6, $qd)) === null);

    check('Hàng chờ chuyên gia chấn thương: câu chấn thương + vấn đề khác, không có câu dinh dưỡng', count(healthPool($pdo, 2)) === 3
        && !in_array('NUTRITION', array_column(healthPool($pdo, 2), 'topic'), true));
    check('Chuyên gia dinh dưỡng không xem được câu chấn thương', healthQuestionForExpert($pdo, 3, $q1) === null);
    check('Chuyên gia dinh dưỡng không nhận được câu chấn thương', errOf(fn() => healthAction($pdo, 'expert', 3, $q1, 'claim')) !== null);
    check('Người không phải chuyên gia không xem được', healthQuestionForExpert($pdo, 5, $q1) === null);
    check('Người khác không xem được câu hỏi của Lan', healthQuestionForAsker($pdo, 5, $q1) === null);
    check('Chuyên gia thấy tên người hỏi rút gọn', healthQuestionForExpert($pdo, 2, $q1)['asker_name'] === 'Lan');

    check('Người hỏi bổ sung thông tin khi đang chờ', errOf(fn() => healthSendMessage($pdo, 'asker', 4, $q1, 'Em đau 2 tuần nay rồi ạ')) === null);
    check('Chuyên gia chưa nhận thì không trả lời được', errOf(fn() => healthSendMessage($pdo, 'expert', 2, $q1, 'Trả lời trước khi nhận')) !== null);
    healthAction($pdo, 'expert', 2, $q1, 'claim');
    check('Nhận câu hỏi: người hỏi được báo', $pdo->query("SELECT expert_id FROM health_questions WHERE id = $q1")->fetchColumn() == 2
        && (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 4 AND type = 'expert.question'")->fetchColumn() === 1);
    check('Người hỏi không hủy được câu đã có người nhận', errOf(fn() => healthAction($pdo, 'asker', 4, $q1, 'cancel')) !== null);
    check('Đã nhận thì chuyên gia khác không xem được nữa', healthQuestionForExpert($pdo, 3, $q1) === null);
    check('Chuyên gia trả lời, người hỏi được báo', errOf(fn() => healthSendMessage($pdo, 'expert', 2, $q1, 'Có thể do quá tải gân bánh chè, nên giảm độ sâu và đi khám nếu sưng.')) === null
        && (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 4 AND type = 'expert.reply'")->fetchColumn() === 1);
    check('Gửi liên tiếp dưới 2 giây bị chặn', str_contains((string)errOf(fn() => healthSendMessage($pdo, 'expert', 2, $q1, 'Tin thứ hai ngay lập tức')), 'nhanh'));
    check('Đã trả lời thì không trả lại hàng chờ được', str_contains((string)errOf(fn() => healthAction($pdo, 'expert', 2, $q1, 'release')), 'không trả lại'));
    healthMarkRead($pdo, $q1, 4);
    check('Người hỏi đọc xong: hết tin chưa đọc', (int)healthQuestionsForAsker($pdo, 4)[0]['unread'] === 0);
    healthAction($pdo, 'asker', 4, $q1, 'close', 1);
    check('Người hỏi đóng kèm "hữu ích"', $pdo->query("SELECT status || '|' || helpful FROM health_questions WHERE id = $q1")->fetchColumn() === 'CLOSED|1');
    check('Đã đóng thì không gửi thêm được', errOf(fn() => healthSendMessage($pdo, 'asker', 4, $q1, 'Hỏi thêm chút')) !== null);
    $pub = array_column(expertListPublic($pdo), null, 'id');
    check('Thống kê chuyên gia: 1 câu đã trả lời, 1 hữu ích', (int)$pub[2]['answered'] === 1 && (int)$pub[2]['helpful_yes'] === 1);

    // Trả lại khi chưa trả lời; thu hồi chuyên gia đưa câu về hàng chờ
    $qOther = (int)$pdo->query("SELECT id FROM health_questions WHERE topic = 'OTHER' AND user_id = 4")->fetchColumn();
    healthAction($pdo, 'expert', 3, $qOther, 'claim');
    check('Chuyên gia trả lại câu chưa trả lời: về hàng chờ', healthAction($pdo, 'expert', 3, $qOther, 'release')['status'] === 'OPEN');
    healthAction($pdo, 'expert', 2, $qOther, 'claim');
    check('Chuyên gia khác không đóng được câu không phải của mình', errOf(fn() => healthAction($pdo, 'expert', 3, $qOther, 'close')) !== null);
    $res = expertRevoke($pdo, 2, 1);
    check('Thu hồi chuyên gia: câu đang trao đổi về hàng chờ, vai trò về USER', $res['released'] === 1
        && $pdo->query("SELECT status FROM health_questions WHERE id = $qOther")->fetchColumn() === 'OPEN'
        && $pdo->query('SELECT role FROM users WHERE id = 2')->fetchColumn() === 'USER');
    check('Đã bị thu hồi thì không xem / trả lời được nữa', healthQuestionForExpert($pdo, 2, $q1) === null && errOf(fn() => healthAction($pdo, 'expert', 2, $qOther, 'claim')) !== null);
    expertSaveProfile($pdo, 3, str_repeat('Giới thiệu mới về dinh dưỡng thể thao. ', 3), 'NUTRITION', false);
    check('Chuyên gia tạm nghỉ thì không nhận câu mới', str_contains((string)errOf(fn() => healthAction($pdo, 'expert', 3, $q2, 'claim')), 'tạm ngừng'));
    check('Người không phải chuyên gia không sửa hồ sơ chuyên gia được', errOf(fn() => expertSaveProfile($pdo, 2, 'x', 'INJURY', true)) !== null);
}

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
