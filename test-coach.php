<?php
/**
 * test-coach.php — test phân hệ HLV. Chạy: php test-coach.php
 * Phần 1: quy tắc thuần (includes/coach-rules.php), không cần DB.
 * Phần 2: lớp DB (includes/coach-db.php) trên SQLite in-memory, cần pdo_sqlite (bảng giống sql/coach_features.sql,
 *         cột "open_*" dùng GENERATED ALWAYS AS ... STORED của SQLite thay cho PERSISTENT của MariaDB).
 */
require __DIR__ . '/public/includes/coach-db.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $fails++;
}
/** Chạy $fn, trả về câu lỗi CoachException (null nếu không ném). */
function coachErr(callable $fn): ?string
{
    try { $fn(); } catch (CoachException $e) { return $e->getMessage(); }
    return null;
}

// ===================== PHẦN 1: QUY TẮC THUẦN =====================
$validApp = [
    'headline' => 'HLV thể hình, chuyên giảm mỡ',
    'specialties' => ['FAT_LOSS', 'BEGINNER'],
    'experience_years' => '4',
    'certifications' => "Chứng chỉ A - Trung tâm B - 2022",
    'bio' => str_repeat('Tôi hướng dẫn người mới tập đúng kỹ thuật. ', 3),
    'approach' => 'Tăng tải từ từ, theo dõi nhật ký mỗi tuần.',
    'max_clients' => '8',
    'availability' => 'Tối thứ 2-6',
    'agree_truth' => '1', 'agree_rules' => '1',
];
$r = validateCoachApplication($validApp);
check('Đơn hợp lệ: không có lỗi', $r['errors'] === []);
check('Đơn hợp lệ: chuyên môn lưu dạng CSV', $r['data']['specialties'] === 'FAT_LOSS,BEGINNER');
check('Đơn hợp lệ: số năm là int', $r['data']['experience_years'] === 4);

$r = validateCoachApplication(['specialties' => ['HACKER']] + $validApp);
check('Chuyên môn ngoài whitelist bị từ chối', isset($r['errors']['specialties']));
$r = validateCoachApplication(['specialties' => ['FAT_LOSS', 'HYPERTROPHY', 'STRENGTH', 'BEGINNER', 'NUTRITION']] + $validApp);
check('Quá 4 chuyên môn bị từ chối', isset($r['errors']['specialties']));
$r = validateCoachApplication(['bio' => 'ngắn'] + $validApp);
check('Giới thiệu quá ngắn bị từ chối', isset($r['errors']['bio']));
$r = validateCoachApplication(['experience_years' => '-1', 'max_clients' => '99'] + $validApp);
check('Số năm âm và số học viên 99 bị từ chối', isset($r['errors']['experience_years'], $r['errors']['max_clients']));
$r = validateCoachApplication(['agree_rules' => ''] + $validApp);
check('Chưa đồng ý quy tắc bị từ chối', isset($r['errors']['agree_rules']));
$r = validateCoachApplication(['agree_rules' => ''] + $validApp, false);
check('Sửa hồ sơ (không cần tick đồng ý) vẫn hợp lệ', $r['errors'] === []);
$r = validateCoachApplication(['headline' => "Dòng 1 dài đủ ký tự\nDòng 2"] + $validApp);
check('Chức danh nhiều dòng bị từ chối', isset($r['errors']['headline']));

check('coachCleanText: bỏ ký tự điều khiển, đổi \r\n', coachCleanText("  a\x07b\r\nc  ") === "ab\nc");
check('coachCleanText: mảng → chuỗi rỗng', coachCleanText(['x']) === '');
check('coachTextError: vượt độ dài', coachTextError(str_repeat('a', 1001), 'Tin nhắn', 1, 1000) !== null);
check('coachTextError: tiếng Việt đếm theo ký tự, không theo byte', coachTextError(str_repeat('ệ', 1000), 'Tin nhắn', 1, 1000) === null);
check('Tên HLV: gộp khoảng trắng và xuống dòng thành 1 dấu cách', coachNormalizeName("  Nguyễn 
	 Văn   An ") === 'Nguyễn Văn An');
check('Tên HLV: ô gửi lên dạng mảng coi như trống', coachNormalizeName(['x']) === '');
check('Tên HLV: trống hoặc 1 ký tự bị chặn, quá 100 ký tự bị chặn', coachNameError('') !== null && coachNameError('A') !== null && coachNameError(str_repeat('ê', 101)) !== null);
check('Tên HLV: tên tiếng Việt 100 ký tự hợp lệ', coachNameError(str_repeat('ệ', 100)) === null);

check('Máy trạng thái: HLV duyệt PENDING → ACTIVE', coachNextStatus('PENDING', 'coach', 'accept') === 'ACTIVE');
check('Máy trạng thái: HLV từ chối PENDING → REJECTED', coachNextStatus('PENDING', 'coach', 'reject') === 'REJECTED');
check('Máy trạng thái: học viên hủy PENDING → ENDED', coachNextStatus('PENDING', 'client', 'cancel') === 'ENDED');
check('Máy trạng thái: học viên ngừng ACTIVE → ENDED', coachNextStatus('ACTIVE', 'client', 'end') === 'ENDED');
check('Máy trạng thái: không duyệt lại quan hệ đã kết thúc', coachNextStatus('ENDED', 'coach', 'accept') === null);
check('Máy trạng thái: học viên không tự duyệt', coachNextStatus('PENDING', 'client', 'accept') === null);

$m = validateCoachMacros('150', '250', '70', 75.0);
check('Macro hợp lệ: kcal = 4P+4C+9F = 2230', $m['errors'] === [] && $m['kcal'] === 2230);
check('Macro: protein 4 g/kg bị từ chối', isset(validateCoachMacros('300', '200', '70', 75.0)['errors']['protein_g']));
check('Macro: chất béo dưới 0,5 g/kg bị từ chối', isset(validateCoachMacros('150', '400', '25', 75.0)['errors']['fat_g']));
check('Macro: tổng dưới 1200 kcal bị từ chối', isset(validateCoachMacros('60', '50', '30', null)['errors']['kcal']));
check('Macro: chữ thay vì số bị từ chối', isset(validateCoachMacros('abc', '200', '60', null)['errors']['protein_g']));
check('Macro: số lẻ thập phân bị từ chối', isset(validateCoachMacros('150.5', '200', '60', null)['errors']['protein_g']));

check('Số chỗ còn không âm', coachSeatsLeft(3, 5) === 0 && coachSeatsLeft(5, 3) === 2);
check('Nhịp gửi tin: quá nhanh bị chặn', coachMessageRateError(1, 0) !== null);
check('Nhịp gửi tin: 60 tin/giờ bị chặn', coachMessageRateError(100, 60) !== null);
check('Nhịp gửi tin: bình thường được gửi', coachMessageRateError(null, 0) === null && coachMessageRateError(30, 10) === null);
check('Nhãn chuyên môn bỏ mã lạ', coachSpecialtyLabels('FAT_LOSS,XYZ') === ['Giảm mỡ']);

// Tỉ lệ tập đúng lịch tuần này. 2026-11-18 là thứ Tư (N = 3), tuần bắt đầu thứ Hai 2026-11-16.
$w = coachWeekCompliance([1, 3, 5], ['2026-11-16', '2026-11-18'], '2026-11-18');
check('Tỉ lệ tập: lịch T2-T4-T6, tới thứ Tư đã tập 2/2 = 100%', $w['planned_week'] === 3 && $w['planned_so_far'] === 2 && $w['done'] === 2 && $w['percent'] === 100);
$w = coachWeekCompliance([1, 3, 5], ['2026-11-13', '2026-11-16'], '2026-11-18');
check('Tỉ lệ tập: log tuần trước không tính, 1/2 = 50%', $w['done'] === 1 && $w['percent'] === 50);
$w = coachWeekCompliance([1, 3, 5], ['2026-11-17', '2026-11-17', '2026-11-18', '2026-11-20'], '2026-11-18');
check('Tỉ lệ tập: tập bù ngày ngoài lịch vẫn tính, trùng ngày tính 1, ngày tương lai không tính', $w['done'] === 2);
check('Tỉ lệ tập: chưa tới buổi nào thì percent = null', coachWeekCompliance([5, 6], [], '2026-11-18')['percent'] === null);
check('Tỉ lệ tập: không vượt 100%', coachWeekCompliance([1], ['2026-11-16', '2026-11-17', '2026-11-18'], '2026-11-18')['percent'] === 100);
check('Tỉ lệ tập: thứ trong lịch sai (0, 9) bị bỏ', coachWeekCompliance([0, 9, 1], [], '2026-11-18')['planned_week'] === 1);
check('Chờ 6 ngày: chưa nhắc', coachPendingStaleDays('2026-11-12 10:00:00', '2026-11-18 09:00:00') === 0);
check('Chờ 7 ngày: nhắc, trả về 7', coachPendingStaleDays('2026-11-11 08:00:00', '2026-11-18 09:00:00') === 7);

// ===================== PHẦN 2: LỚP DB TRÊN SQLITE =====================
if (!extension_loaded('pdo_sqlite')) {
    echo "[BỎ QUA] Không có pdo_sqlite nên không chạy phần DB\n";
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT, email TEXT, role TEXT DEFAULT 'USER', avatar TEXT,
        age INT, gender TEXT, height_cm REAL, weight_kg REAL, activity_level TEXT, experience_level TEXT, goal TEXT, created_at TEXT)");
    $pdo->exec('CREATE TABLE exercises (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo->exec('CREATE TABLE workout_plans (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, status TEXT, assigned_by INT)');
    $pdo->exec('CREATE TABLE workout_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, exercise_id INT, log_date TEXT,
        sets_completed INT, reps_completed INT, weight_kg_used REAL, note TEXT)');
    $pdo->exec('CREATE TABLE nutrition_targets (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, tdee_kcal REAL, target_kcal REAL,
        protein_g REAL, carbs_g REAL, fat_g REAL, calculated_date TEXT, assigned_by INT)');
    $pdo->exec("CREATE TABLE coach_applications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT REFERENCES users(id) ON DELETE CASCADE,
        headline TEXT, specialties TEXT, experience_years INT, certifications TEXT, bio TEXT, approach TEXT, max_clients INT,
        availability TEXT, status TEXT DEFAULT 'PENDING', admin_note TEXT DEFAULT '', reviewed_by INT, reviewed_at TEXT, created_at TEXT,
        open_user_id INT GENERATED ALWAYS AS (CASE WHEN status = 'PENDING' THEN user_id END) STORED UNIQUE)");
    $pdo->exec('CREATE TABLE coach_profiles (user_id INTEGER PRIMARY KEY, headline TEXT, specialties TEXT, experience_years INT,
        certifications TEXT, bio TEXT, approach TEXT, max_clients INT, availability TEXT, accepting INT DEFAULT 1, created_at TEXT, updated_at TEXT)');
    $pdo->exec("CREATE TABLE coach_clients (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_id INT REFERENCES users(id) ON DELETE CASCADE,
        client_id INT REFERENCES users(id) ON DELETE CASCADE, status TEXT DEFAULT 'PENDING', request_note TEXT DEFAULT '',
        created_at TEXT, accepted_at TEXT, ended_at TEXT, ended_by INT,
        open_client_id INT GENERATED ALWAYS AS (CASE WHEN status IN ('PENDING','ACTIVE') THEN client_id END) STORED UNIQUE)");
    $pdo->exec('CREATE TABLE coach_feedback (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_client_id INT REFERENCES coach_clients(id) ON DELETE CASCADE,
        log_date TEXT, workout_log_id INT, content TEXT, read_at TEXT, created_at TEXT)');
    $pdo->exec('CREATE TABLE coach_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_client_id INT REFERENCES coach_clients(id) ON DELETE CASCADE,
        sender_id INT, content TEXT, read_at TEXT, created_at TEXT)');
    $pdo->exec("CREATE TABLE coach_change_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_client_id INT REFERENCES coach_clients(id) ON DELETE CASCADE,
        area TEXT, reason TEXT, status TEXT DEFAULT 'PENDING', coach_reply TEXT DEFAULT '', unlock_until TEXT, created_at TEXT, resolved_at TEXT,
        open_key TEXT GENERATED ALWAYS AS (CASE WHEN status = 'PENDING' THEN coach_client_id || '-' || area END) STORED UNIQUE)");
    $pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT, title TEXT, body TEXT DEFAULT '',
        link TEXT DEFAULT '', read_at TEXT, created_at TEXT)");
    // Thông báo chưa đọc của một người theo loại (A-44)
    $notif = function (int $userId, string $type) use ($pdo): array {
        $s = $pdo->prepare('SELECT title, body, link FROM notifications WHERE user_id = ? AND type = ? AND read_at IS NULL ORDER BY id DESC');
        $s->execute([$userId, $type]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    };

    // 1 admin (1), 2 người sẽ làm HLV (2, 3), 3 học viên (4, 5, 6)
    $ins = $pdo->prepare('INSERT INTO users (id, full_name, email, role, weight_kg, goal) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ([[1, 'Quản Trị', 'a@t', 'ADMIN'], [2, 'HLV Một', 'c1@t', 'USER'], [3, 'HLV Hai', 'c2@t', 'USER'],
              [4, 'Học Viên A', 'u1@t', 'USER'], [5, 'Học Viên B', 'u2@t', 'USER'], [6, 'Học Viên C', 'u3@t', 'USER']] as $u) {
        $ins->execute([$u[0], $u[1], $u[2], $u[3], 70, 'LOSE_FAT']);
    }
    $appData = validateCoachApplication($validApp)['data'];
    $count = fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return (int)$s->fetchColumn(); })();

    check('coachTablesReady nhận ra bảng đã có', coachTablesReady($pdo));

    // --- Đơn đăng ký HLV ---
    $app2 = coachSubmitApplication($pdo, 2, $appData);
    check('USER nộp đơn được', $app2 > 0);
    check('Nộp đơn thứ 2 khi đang chờ bị chặn', coachErr(fn() => coachSubmitApplication($pdo, 2, $appData)) !== null);
    check('ADMIN không nộp đơn được', coachErr(fn() => coachSubmitApplication($pdo, 1, $appData)) !== null);
    check('Từ chối đơn phải có lý do', coachErr(fn() => coachRejectApplication($pdo, $app2, 1, '')) !== null);
    coachApproveApplication($pdo, $app2, 1, 'Đủ điều kiện');
    check('Duyệt đơn: vai trò thành COACH', coachUserRole($pdo, 2) === 'COACH');
    check('Duyệt đơn: tạo hồ sơ HLV', coachProfile($pdo, 2) !== null);
    $prof = coachProfile($pdo, 2);
    $profData = ['headline' => (string)$prof['headline'], 'specialties' => (string)$prof['specialties'], 'experience_years' => (int)$prof['experience_years'],
        'certifications' => (string)$prof['certifications'], 'bio' => (string)$prof['bio'], 'approach' => (string)$prof['approach'],
        'max_clients' => (int)$prof['max_clients'], 'availability' => (string)$prof['availability']];
    coachSaveProfile($pdo, 2, $profData, true, 'HLV Đổi Tên');
    check('HLV đổi tên hiển thị ở trang Hồ sơ HLV', coachProfile($pdo, 2)['full_name'] === 'HLV Đổi Tên');
    coachSaveProfile($pdo, 1, $profData, true, 'Không Được Đổi');
    check('Đổi tên chỉ áp dụng cho tài khoản COACH (ADMIN id 1 không bị đổi)', $pdo->query('SELECT full_name FROM users WHERE id = 1')->fetchColumn() !== 'Không Được Đổi');
    check('Duyệt lại đơn đã duyệt bị chặn', coachErr(fn() => coachApproveApplication($pdo, $app2, 1, '')) !== null);
    check('Thông báo: ADMIN nhận "Đơn đăng ký HLV mới"', count($notif(1, 'admin.coach_application')) === 1);
    check('Thông báo: người nộp nhận "đã được duyệt" kèm link khu HLV', ($notif(2, 'coach.application_approved')[0]['link'] ?? '') === '/coach/dashboard.php');
    $app3 = coachSubmitApplication($pdo, 3, $appData);
    coachApproveApplication($pdo, $app3, 1, '');
    check('COACH không nộp đơn lần nữa', coachErr(fn() => coachSubmitApplication($pdo, 2, $appData)) !== null);

    // --- Học viên chọn HLV ---
    $rel = coachRequest($pdo, 4, 2, 'Em muốn giảm mỡ');
    check('Học viên gửi yêu cầu được', $rel > 0);
    check('Thông báo: HLV nhận yêu cầu học viên mới kèm lời nhắn', str_contains($notif(2, 'coach.request')[0]['body'] ?? '', 'Học Viên A muốn tập với bạn: Em muốn giảm mỡ'));
    check('Thông báo: yêu cầu bị chặn thì không có thông báo thừa', count($notif(3, 'coach.request')) === 0);
    check('Mỗi học viên chỉ 1 HLV: gửi tiếp HLV khác bị chặn', coachErr(fn() => coachRequest($pdo, 4, 3, '')) !== null);
    check('Đang chờ HLV thì không nộp đơn làm HLV', coachErr(fn() => coachSubmitApplication($pdo, 4, $appData)) !== null);
    check('HLV không chọn chính mình', coachErr(fn() => coachRequest($pdo, 2, 2, '')) !== null);
    check('HLV không làm học viên của HLV khác', coachErr(fn() => coachRequest($pdo, 2, 3, '')) !== null);
    check('Chưa duyệt thì HLV chưa xem được học viên', !canCoachAccessClient($pdo, 2, 4));
    check('HLV khác không duyệt được yêu cầu không phải của mình', coachErr(fn() => coachChangeRelation($pdo, 'coach', 3, $rel, 'accept')) !== null);
    check('Học viên khác không hủy được yêu cầu của người khác', coachErr(fn() => coachChangeRelation($pdo, 'client', 5, $rel, 'cancel')) !== null);

    // Hủy rồi gửi lại
    coachChangeRelation($pdo, 'client', 4, $rel, 'cancel');
    check('Hủy yêu cầu: không còn quan hệ mở', coachOpenRelationForClient($pdo, 4) === null);
    $rel = coachRequest($pdo, 4, 2, 'Gửi lại');
    check('Hủy xong gửi lại được', $rel > 0);

    coachChangeRelation($pdo, 'coach', 2, $rel, 'accept');
    check('HLV duyệt: xem được học viên', canCoachAccessClient($pdo, 2, 4));
    check('Thông báo: học viên biết HLV đã nhận', ($notif(4, 'coach.relation')[0]['title'] ?? '') === 'HLV đã nhận bạn làm học viên');
    check('HLV khác không xem được học viên đó', !canCoachAccessClient($pdo, 3, 4));
    check('HLV không xem được học viên chưa từng đăng ký', !canCoachAccessClient($pdo, 2, 5));
    check('Id rác không lọt qua', !canCoachAccessClient($pdo, 2, 0) && !canCoachAccessClient($pdo, 2, 2));
    check('Duyệt 2 lần bị chặn', coachErr(fn() => coachChangeRelation($pdo, 'coach', 2, $rel, 'accept')) !== null);
    $basics = coachClientBasics($pdo, 4);
    check('Thông tin HLV xem được không có email', $basics !== null && !array_key_exists('email', $basics));

    // Đủ chỗ: HLV 2 chỉ nhận 1 học viên
    $pdo->exec('UPDATE coach_profiles SET max_clients = 1 WHERE user_id = 2');
    check('HLV đủ học viên: gửi yêu cầu mới bị chặn', coachErr(fn() => coachRequest($pdo, 5, 2, '')) !== null);
    $pdo->exec('UPDATE coach_profiles SET max_clients = 2 WHERE user_id = 2');
    $relB = coachRequest($pdo, 5, 2, '');
    $pdo->exec('UPDATE coach_profiles SET max_clients = 1 WHERE user_id = 2');
    check('Đủ chỗ thì không duyệt thêm được', coachErr(fn() => coachChangeRelation($pdo, 'coach', 2, $relB, 'accept')) !== null);
    coachChangeRelation($pdo, 'coach', 2, $relB, 'reject');
    check('Từ chối: học viên B không còn quan hệ mở', coachOpenRelationForClient($pdo, 5) === null);
    $pdo->exec('UPDATE coach_profiles SET accepting = 0 WHERE user_id = 3');
    check('HLV tạm ngừng nhận: gửi yêu cầu bị chặn', coachErr(fn() => coachRequest($pdo, 5, 3, '')) !== null);
    $pdo->exec('UPDATE coach_profiles SET accepting = 1, max_clients = 5 WHERE user_id IN (2, 3)');

    // --- Khóa tự sửa + yêu cầu xin sửa ---
    $st = coachSelfEditState($pdo, 4, 'PLAN');
    check('Đang có HLV: lịch tập bị khóa', $st['locked'] === true && $st['coach_id'] === 2);
    check('Học viên không có HLV: không khóa', coachSelfEditState($pdo, 6, 'PLAN')['locked'] === false);
    check('Lý do quá ngắn bị từ chối', coachErr(fn() => coachRequestChange($pdo, 4, 'PLAN', 'a')) !== null);
    $req = coachRequestChange($pdo, 4, 'PLAN', 'Tuần này em bị đau vai');
    check('Gửi yêu cầu xin sửa được', $req > 0 && coachSelfEditState($pdo, 4, 'PLAN')['pending']);
    check('Gửi trùng yêu cầu đang chờ bị chặn', coachErr(fn() => coachRequestChange($pdo, 4, 'PLAN', 'Lần nữa')) !== null);
    check('Phần dinh dưỡng vẫn gửi riêng được', coachRequestChange($pdo, 4, 'NUTRITION', 'Em muốn đổi bữa') > 0);
    check('HLV khác không duyệt được yêu cầu này', coachErr(fn() => coachResolveChange($pdo, 3, $req, true, '')) !== null);
    coachResolveChange($pdo, 2, $req, true, 'Ok, bỏ bài đẩy vai');
    $st = coachSelfEditState($pdo, 4, 'PLAN');
    check('HLV duyệt: mở khóa có hạn', $st['locked'] === false && $st['unlock_until'] !== null);
    check('Dinh dưỡng vẫn khóa', coachSelfEditState($pdo, 4, 'NUTRITION')['locked'] === true);
    $pdo->exec("UPDATE coach_change_requests SET unlock_until = '2000-01-01 00:00:00' WHERE id = $req");
    check('Hết hạn mở khóa thì khóa lại', coachSelfEditState($pdo, 4, 'PLAN')['locked'] === true);

    // --- Mục tiêu dinh dưỡng ---
    $today = date('Y-m-d');
    coachSaveNutrition($pdo, 2, 4, 2400, 2230, 150, 250, 70, $today);
    coachSaveNutrition($pdo, 2, 4, 2400, 2000, 150, 200, 64, $today);
    check('Đặt macro 2 lần trong ngày: chỉ 1 dòng', $count('SELECT COUNT(*) FROM nutrition_targets WHERE user_id = 4') === 1);
    check('Mục tiêu HLV đọc lại đúng', (int)coachAssignedNutrition($pdo, 4, 2)['target_kcal'] === 2000);

    // --- Nhận xét + tin nhắn ---
    $pdo->exec("INSERT INTO exercises (id, name) VALUES (1, 'Squat')");
    $pdo->exec("INSERT INTO workout_logs (user_id, exercise_id, log_date, sets_completed, reps_completed) VALUES (4, 1, '$today', 3, 8)");
    check('Nhận xét buổi không có tập bị chặn', coachErr(fn() => coachAddFeedback($pdo, $rel, 4, '2001-01-01', 'Tốt')) !== null);
    check('Nhận xét ngày sai định dạng bị chặn', coachErr(fn() => coachAddFeedback($pdo, $rel, 4, '2026-02-31', 'Tốt')) !== null);
    check('Nhận xét buổi có tập được', coachAddFeedback($pdo, $rel, 4, $today, 'Squat sâu hơn tuần trước') > 0);
    check('Nhận xét quá 1000 ký tự bị chặn', coachErr(fn() => coachAddFeedback($pdo, $rel, 4, null, str_repeat('a', 1001))) !== null);
    check('Học viên thấy 1 nhận xét chưa đọc', coachClientNotice($pdo, 4)['feedback'] === 1);
    check('Thông báo: nhận xét mới có trích nội dung', str_contains($notif(4, 'coach.feedback')[0]['body'] ?? '', 'Squat sâu hơn tuần trước'));
    coachMarkFeedbackRead($pdo, $rel);
    check('Đọc rồi thì hết thông báo', coachClientNotice($pdo, 4)['feedback'] === 0);
    check('Thông báo: mở trang HLV của tôi thì chuông về đã đọc', count($notif(4, 'coach.feedback')) === 0);

    $msg = coachSendMessage($pdo, $rel, 4, 'Chào thầy');
    check('Gửi tin được', $msg['id'] > 0);
    check('Gửi tin liên tục quá nhanh bị chặn', coachErr(fn() => coachSendMessage($pdo, $rel, 4, 'Tin 2')) !== null);
    check('Tin rỗng bị chặn', coachErr(fn() => coachSendMessage($pdo, $rel, 2, '')) !== null);
    check('HLV thấy 1 tin chưa đọc', coachDashboardCounts($pdo, 2, $today)['unread'] === 1);
    check('Thông báo: HLV nhận tin nhắn mới, link tới đúng khung chat', ($notif(2, 'coach.message')[0]['link'] ?? '') === '/coach/messages.php?id=4');
    check('Thông báo: tin rỗng bị chặn thì học viên không nhận gì', count($notif(4, 'coach.message')) === 0);
    coachMarkMessagesRead($pdo, $rel, 2);
    check('Thông báo: HLV mở khung chat thì thông báo tin nhắn thành đã đọc', count($notif(2, 'coach.message')) === 0);
    check('HLV mở khung chat: hết chưa đọc', coachDashboardCounts($pdo, 2, $today)['unread'] === 0);
    check('Học viên tập hôm nay: không tính "chưa tập 7 ngày"', coachDashboardCounts($pdo, 2, $today)['inactive7'] === 0);
    check('Tin mới sau id lấy đúng', count(coachMessages($pdo, $rel, $msg['id'])) === 0 && count(coachMessages($pdo, $rel, 0)) === 1);

    // --- Học viên ngừng tập: HLV mất quyền ngay ---
    coachChangeRelation($pdo, 'client', 4, $rel, 'end');
    check('Ngừng tập: HLV mất quyền xem', !canCoachAccessClient($pdo, 2, 4));
    check('Ngừng tập: yêu cầu xin sửa còn chờ bị hủy', $count("SELECT COUNT(*) FROM coach_change_requests WHERE status = 'PENDING'") === 0);
    check('Ngừng tập: lịch/dinh dưỡng không còn khóa', coachSelfEditState($pdo, 4, 'NUTRITION')['locked'] === false);

    // --- ADMIN thu hồi HLV đang có học viên ---
    $r6 = coachRequest($pdo, 6, 3, '');
    coachChangeRelation($pdo, 'coach', 3, $r6, 'accept');
    $r5 = coachRequest($pdo, 5, 3, '');
    $res = coachRevoke($pdo, 3, 1);
    check('Thông báo: thu hồi HLV báo cả học viên đang tập và đang chờ',
        in_array('Quan hệ huấn luyện đã kết thúc', array_column($notif(6, 'coach.relation'), 'title'), true)
        && in_array('Yêu cầu tới HLV không còn hiệu lực', array_column($notif(5, 'coach.relation'), 'title'), true));
    check('Thông báo: HLV bị thu hồi cũng được báo', count($notif(3, 'coach.revoked')) === 1);
    check('Thu hồi: báo 1 học viên bị kết thúc, 1 yêu cầu bị từ chối', $res['ended'] === 1 && $res['rejected'] === 1);
    check('Thu hồi: vai trò về USER', coachUserRole($pdo, 3) === 'USER');
    check('Thu hồi: mất quyền xem ngay', !canCoachAccessClient($pdo, 3, 6));
    check('Thu hồi: học viên chọn HLV khác được', coachRequest($pdo, 6, 2, '') > 0);
    check('Thu hồi người không phải HLV bị chặn', coachErr(fn() => coachRevoke($pdo, 3, 1)) !== null);

    // Role đổi trong DB nhưng quan hệ còn ACTIVE (ví dụ ai đó sửa tay trong phpMyAdmin): vẫn phải mất quyền
    $r7 = $count("SELECT id FROM coach_clients WHERE client_id = 6 AND status = 'PENDING'");
    coachChangeRelation($pdo, 'coach', 2, $r7, 'accept');
    $pdo->exec("UPDATE users SET role = 'USER' WHERE id = 2");
    check('Không còn role COACH thì canCoachAccessClient = false', !canCoachAccessClient($pdo, 2, 6));
    $pdo->exec("UPDATE users SET role = 'COACH' WHERE id = 2");

    // Xóa tài khoản học viên: quan hệ, tin nhắn bị xóa theo (CASCADE)
    $pdo->exec('DELETE FROM users WHERE id = 4');
    check('Xóa học viên: xóa theo quan hệ và tin nhắn', $count('SELECT COUNT(*) FROM coach_clients WHERE client_id = 4') === 0
        && $count('SELECT COUNT(*) FROM coach_messages') === 0);
}

echo PHP_EOL . ($fails === 0 ? 'TẤT CẢ ĐỀU PASS' : "CÓ $fails TEST FAIL") . PHP_EOL;
exit($fails === 0 ? 0 : 1);
