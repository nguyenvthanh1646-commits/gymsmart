<?php
// test-coach-templates.php — kiểm tra mẫu lịch tập của HLV (includes/coach-templates.php): hàm thuần và luồng DB trên SQLite.
// Chạy ở gốc repo: php test-coach-templates.php

require __DIR__ . '/public/includes/coach-templates.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}
function errOf(callable $fn): ?string
{
    try {
        $fn();
    } catch (CoachException $e) {
        return $e->getMessage();
    }
    return null;
}

// ===== Hàm thuần =====
check('Tên mẫu quá ngắn bị chặn', coachTemplateNameError('ab') !== null);
check('Tên mẫu 81 ký tự bị chặn', coachTemplateNameError(str_repeat('a', 81)) !== null);
check('Tên mẫu hợp lệ', coachTemplateNameError('Toàn thân 3 buổi cho người mới') === null);
$plan = coachTemplateToPlan(['split_type' => 'FULL_BODY', 'days_per_week' => 3], [
    ['exercise_id' => 5, 'day_of_week' => 3, 'sets' => 2, 'reps' => 8, 'order_index' => 0],
    ['exercise_id' => 1, 'day_of_week' => 1, 'sets' => 3, 'reps' => 8, 'order_index' => 0],
    ['exercise_id' => 2, 'day_of_week' => 1, 'sets' => 2, 'reps' => 10, 'order_index' => 1],
    ['exercise_id' => 9, 'day_of_week' => 9, 'sets' => 2, 'reps' => 8, 'order_index' => 0],
]);
check('Dựng lịch: ngày xếp tăng dần, bỏ ngày sai (9)', array_column($plan['days'], 'day_of_week') === [1, 3]);
check('Dựng lịch: số buổi tính theo số ngày có bài', $plan['days_per_week'] === 2);
check('Dựng lịch: giữ đúng set/lặp/thứ tự', $plan['days'][0]['exercises'][1] === ['exercise_id' => 2, 'sets' => 2, 'reps' => 10, 'order_index' => 1]);

// ===== Luồng DB =====
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "[BỎ QUA] Không có pdo_sqlite nên không chạy phần DB\n";
} else {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, full_name TEXT, role TEXT, goal TEXT)");
    $pdo->exec("CREATE TABLE exercises (id INTEGER PRIMARY KEY, name TEXT, muscle_group TEXT, type TEXT DEFAULT 'RESISTANCE')");
    $pdo->exec("CREATE TABLE coach_clients (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_id INT, client_id INT, status TEXT)");
    $pdo->exec("CREATE TABLE workout_plans (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, name TEXT, split_type TEXT, days_per_week INT,
        status TEXT DEFAULT 'ACTIVE', assigned_by INT, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE workout_plan_exercises (id INTEGER PRIMARY KEY AUTOINCREMENT, workout_plan_id INT, exercise_id INT, day_of_week INT,
        sets INT, reps INT, weight_kg_suggested REAL, order_index INT DEFAULT 0, created_at TEXT)");
    $pdo->exec("CREATE TABLE coach_plan_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, coach_id INT, name TEXT, split_type TEXT, days_per_week INT, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE coach_plan_template_items (id INTEGER PRIMARY KEY AUTOINCREMENT, template_id INT, exercise_id INT, day_of_week INT, sets INT, reps INT, order_index INT, created_at TEXT)");
    $pdo->exec("CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, type TEXT, title TEXT, body TEXT DEFAULT '', link TEXT DEFAULT '', read_at TEXT, created_at TEXT)");

    // HLV 2 (học viên 4, 5 ACTIVE; 6 đã kết thúc), HLV 3 (học viên 7)
    foreach ([[2, 'HLV Một', 'COACH'], [3, 'HLV Hai', 'COACH'], [4, 'Học Viên A', 'USER'], [5, 'Học Viên B', 'USER'], [6, 'Học Viên C', 'USER'], [7, 'Học Viên D', 'USER']] as $u) {
        $pdo->prepare('INSERT INTO users (id, full_name, role) VALUES (?, ?, ?)')->execute($u);
    }
    foreach ([[2, 4, 'ACTIVE'], [2, 5, 'ACTIVE'], [2, 6, 'ENDED'], [3, 7, 'ACTIVE']] as $r) {
        $pdo->prepare('INSERT INTO coach_clients (coach_id, client_id, status) VALUES (?, ?, ?)')->execute($r);
    }
    foreach ([[1, 'Hít đất', 'CHEST'], [7, 'Kéo cáp xuống', 'BACK'], [18, 'Squat tạ đơn', 'LEGS']] as $e) {
        $pdo->prepare('INSERT INTO exercises (id, name, muscle_group) VALUES (?, ?, ?)')->execute($e);
    }
    // Lịch đang dùng của học viên 4: thứ 2 và thứ 5
    $pdo->exec("INSERT INTO workout_plans (id, user_id, name, split_type, days_per_week, status) VALUES (10, 4, 'Lịch cũ', 'FULL_BODY', 2, 'ACTIVE')");
    $pdo->exec("INSERT INTO workout_plan_exercises (workout_plan_id, exercise_id, day_of_week, sets, reps, order_index) VALUES
        (10, 1, 1, 3, 8, 0), (10, 18, 1, 3, 8, 1), (10, 7, 4, 2, 10, 0)");

    check('Không có bản nháp thì không lưu từ bản nháp', errOf(fn() => coachTemplateSaveFromClient($pdo, 2, 4, 'Mẫu A', true)) !== null);
    $tpl = coachTemplateSaveFromClient($pdo, 2, 4, 'Toàn thân 2 buổi', false);
    check('Lưu lịch đang dùng thành mẫu', $tpl > 0);
    $t = coachTemplate($pdo, 2, $tpl);
    check('Mẫu có đủ 3 bài, đúng ngày và thứ tự', count($t['items']) === 3 && $t['items'][0]['name'] === 'Hít đất' && (int)$t['items'][2]['day_of_week'] === 4);
    check('Danh sách mẫu: 2 buổi, 3 bài', ($l = coachTemplates($pdo, 2)) && (int)$l[0]['days'] === 2 && (int)$l[0]['exercises'] === 3);
    check('HLV khác không đọc được mẫu', errOf(fn() => coachTemplate($pdo, 3, $tpl)) !== null);
    check('HLV khác không xóa được mẫu', errOf(fn() => coachTemplateDelete($pdo, 3, $tpl)) !== null);

    $res = coachTemplateApply($pdo, 2, $tpl, [5, 6, 7, 4, 5, 'abc', -1]);
    check('Giao: chỉ học viên đang ACTIVE với HLV này (4, 5)', array_keys($res['applied']) === [5, 4]);
    check('Giao: bỏ qua học viên đã kết thúc (6) và của HLV khác (7)', $res['skipped'] === [6, 7]);
    $p5 = fetchActivePlan($pdo, 5);
    check('Học viên 5 có lịch mới tên theo mẫu, do HLV giao', $p5 !== null && $p5['name'] === 'Toàn thân 2 buổi'
        && (int)$pdo->query('SELECT assigned_by FROM workout_plans WHERE id = ' . (int)$p5['id'])->fetchColumn() === 2);
    check('Học viên 5 nhận đủ 3 bài vào thứ 2 và thứ 5', count($p5['days'][1] ?? []) === 2 && count($p5['days'][4] ?? []) === 1);
    check('Lịch cũ của học viên 4 được lưu trữ', $pdo->query('SELECT status FROM workout_plans WHERE id = 10')->fetchColumn() === 'ARCHIVED');
    check('Học viên 7 (của HLV khác) không bị đổi lịch', fetchActivePlan($pdo, 7) === null);
    check('Học viên được báo "HLV đã giao lịch tập mới"', (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 5 AND type = 'coach.plan'")->fetchColumn() === 1);
    check('Không chọn ai thì báo lỗi', errOf(fn() => coachTemplateApply($pdo, 2, $tpl, [])) !== null);

    coachTemplateDelete($pdo, 2, $tpl);
    check('Xóa mẫu: xóa cả các bài của mẫu', (int)$pdo->query('SELECT COUNT(*) FROM coach_plan_template_items')->fetchColumn() === 0 && coachTemplates($pdo, 2) === []);
    check('Lịch đã giao vẫn ở lại với học viên sau khi xóa mẫu', fetchActivePlan($pdo, 5) !== null);
}

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
