<?php
// test-dashboard.php — kiểm tra các hàm thuần và lớp đọc DB của includes/dashboard.php (không cần MySQL).
// Chạy ở gốc repo: php test-dashboard.php

require __DIR__ . '/public/includes/dashboard.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}

// Lịch mẫu: thứ Hai 2 bài, thứ Tư 1 bài, thứ Sáu 1 bài
$row = fn(int $id, int $sets = 3) => ['row_id' => $id, 'exercise_id' => $id, 'name' => "Bài $id", 'muscle_group' => 'CHEST', 'sets' => $sets, 'reps' => 10];
$plan = [1 => [$row(11), $row(12)], 3 => [$row(31)], 5 => [$row(51)]];
$wed = '2026-10-28'; // thứ Tư, tuần từ 26/10 đến 01/11

// ===== Tuần này =====
$w = dashBuildWeek($wed, $plan, []);
check('Tuần chưa ghi: thứ Hai đã qua là missed', $w['days'][1]['status'] === 'missed');
check('Tuần chưa ghi: hôm nay có buổi là today', $w['days'][3]['status'] === 'today' && $w['days'][3]['isToday']);
check('Tuần chưa ghi: thứ Sáu sắp tới là upcoming', $w['days'][5]['status'] === 'upcoming');
check('Tuần chưa ghi: ngày không có buổi là rest', $w['days'][2]['status'] === 'rest');
check('Tuần chưa ghi: 0/3 buổi', $w['planned'] === 3 && $w['done'] === 0 && $w['percent'] === 0);
check('Ngày đầu tuần là thứ Hai 26/10', $w['days'][1]['date'] === '2026-10-26' && $w['days'][7]['date'] === '2026-11-01');

$w = dashBuildWeek($wed, $plan, [['log_date' => '2026-10-26', 'workout_plan_exercise_id' => 11]]);
check('Ghi 1 bài của buổi là đã tập, kèm 1/2 bài', $w['days'][1]['status'] === 'done' && $w['days'][1]['logged'] === 1 && $w['days'][1]['total'] === 2);
check('Tiến độ 1/3 buổi = 33%', $w['done'] === 1 && $w['percent'] === 33);

$w = dashBuildWeek($wed, $plan, [
    ['log_date' => '2026-10-26', 'workout_plan_exercise_id' => 11],
    ['log_date' => '2026-10-26', 'workout_plan_exercise_id' => 11],
]);
check('Ghi trùng một bài vẫn chỉ tính 1 bài', $w['days'][1]['logged'] === 1);

$w = dashBuildWeek($wed, $plan, [['log_date' => '2026-10-27', 'workout_plan_exercise_id' => null]]);
check('Tập vào ngày không có lịch là tập thêm', $w['days'][2]['status'] === 'extra');
check('Tập thêm không cộng vào tiến độ', $w['done'] === 0);

$w = dashBuildWeek($wed, $plan, [['log_date' => '2026-10-26', 'workout_plan_exercise_id' => 31]]);
check('Bài của buổi khác không tính là đã tập', $w['days'][1]['status'] === 'missed' && $w['days'][1]['hasExtra']);

$w = dashBuildWeek($wed, $plan, [['log_date' => '2026-10-19', 'workout_plan_exercise_id' => 11]]);
check('Nhật ký tuần trước không tính vào tuần này', $w['done'] === 0);

$w = dashBuildWeek('2026-11-01', $plan, []);
check('Chủ nhật vẫn thuộc tuần bắt đầu từ thứ Hai trước đó', $w['days'][1]['date'] === '2026-10-26' && $w['days'][7]['isToday']);

// ===== Buổi kế tiếp =====
check('Sau thứ Tư là thứ Sáu', dashNextSessionDay(3, $plan) === ['day' => 5, 'nextWeek' => false]);
check('Sau thứ Sáu vòng sang thứ Hai tuần sau', dashNextSessionDay(5, $plan) === ['day' => 1, 'nextWeek' => true]);
check('Chỉ có 1 buổi mỗi tuần thì buổi kế tiếp là tuần sau', dashNextSessionDay(1, [1 => [$row(1)]]) === ['day' => 1, 'nextWeek' => true]);
check('Lịch rỗng thì không có buổi kế tiếp', dashNextSessionDay(1, []) === null);

// ===== Tóm tắt buổi =====
$s = dashSessionSummary([$row(1, 4), $row(2, 3)], 'Đẩy');
check('Buổi 7 hiệp: 2 bài, làm tròn lên 20 phút', $s['count'] === 2 && $s['sets'] === 7 && $s['minutes'] === 20);
check('Nhóm cơ trùng chỉ hiện 1 lần', $s['muscles'] === 'Ngực');
check('Buổi 8 hiệp đúng 20 phút', dashSessionSummary([$row(1, 8)])['minutes'] === 20);

// ===== Nút chính =====
check('Thiếu hồ sơ được ưu tiên trước mọi thứ', dashPrimaryAction(3, 'ok', 5, 0)['href'] === '/profile.php');
check('Chưa có lịch thì tạo lịch', dashPrimaryAction(0, 'none', 0, 0)['label'] === 'Tạo lịch tập');
check('Lỗi đọc lịch thì mở trang lịch', dashPrimaryAction(0, 'error', 0, 0)['href'] === '/workout-plan.php');
check('Có buổi, chưa ghi thì bắt đầu', dashPrimaryAction(0, 'ok', 5, 0)['label'] === 'Bắt đầu buổi tập hôm nay');
check('Ghi dở thì tiếp tục', dashPrimaryAction(0, 'ok', 5, 2)['label'] === 'Tiếp tục buổi tập hôm nay');
check('Ghi đủ thì xem tiến trình', dashPrimaryAction(0, 'ok', 5, 5)['href'] === '/progress.php');
check('Ngày nghỉ thì xem tiến trình', dashPrimaryAction(0, 'ok', 0, 0)['href'] === '/progress.php');

// ===== Xu hướng cân nặng =====
$m = fn(string $d, float $kg) => ['recorded_date' => $d, 'weight_kg' => $kg];
check('Một lần đo chưa có xu hướng', dashWeightTrend([$m('2026-10-01', 70)], 'LOSE_FAT') === null);
$t = dashWeightTrend([$m('2026-10-01', 70), $m('2026-10-08', 69)], 'LOSE_FAT');
check('Giảm cân khi mục tiêu giảm mỡ là đúng hướng', $t['delta'] === -1.0 && $t['matches'] === true);
check('Giảm cân khi mục tiêu tăng cơ là chưa đúng', dashWeightTrend([$m('2026-10-01', 70), $m('2026-10-08', 69)], 'GAIN_MUSCLE')['matches'] === false);
check('Duy trì: lệch 0,5 kg vẫn đạt', dashWeightTrend([$m('2026-10-01', 70), $m('2026-10-08', 70.5)], 'MAINTAIN')['matches'] === true);
check('Duy trì: lệch 1 kg là chưa đạt', dashWeightTrend([$m('2026-10-01', 70), $m('2026-10-08', 71)], 'MAINTAIN')['matches'] === false);
check('Chưa chọn mục tiêu thì không đánh giá', dashWeightTrend([$m('2026-10-01', 70), $m('2026-10-08', 71)], null)['matches'] === null);

// ===== Sparkline =====
check('Dưới 2 lần đo không vẽ', dashSparklinePoints([$m('2026-10-01', 70)], 100, 40) === '');
$p = explode(' ', dashSparklinePoints([$m('2026-10-01', 70), $m('2026-10-11', 72)], 100, 40));
check('Cân nặng tăng thì đường đi lên (y giảm)', $p[0] === '0,36' && $p[1] === '100,4');
$p = explode(' ', dashSparklinePoints([$m('2026-10-01', 70), $m('2026-10-02', 70), $m('2026-10-11', 70)], 100, 40));
check('Cân nặng không đổi là đường ngang ở giữa', $p[0] === '0,20' && $p[2] === '100,20');
check('Trục x theo ngày, không theo số lần đo', $p[1] === '10,20');

// ===== Hiển thị =====
check('Nhãn ngày tiếng Việt', dashDateLabel('2026-11-01') === 'Chủ nhật, 01/11/2026');
check('Số thập phân dùng dấu phẩy', dashNumber(68.5) === '68,5');

// ===== Lỗi DB: từng thẻ tự báo lỗi, không làm hỏng trang =====
class BrokenPdo extends PDO
{
    public function __construct()
    {
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        throw new PDOException('giả lập mất kết nối');
    }
}
ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'); // không in nhật ký lỗi của phần giả lập ra màn hình
$d = loadDashboard(new BrokenPdo(), 1, '2026-10-28', 'MAINTAIN', 0, ['a' => ['title' => 'A']]);
check('DB lỗi: lịch tập báo error', $d['plan']['status'] === 'error');
check('DB lỗi: tuần này báo error', $d['week']['status'] === 'error');
check('DB lỗi: dinh dưỡng báo error', $d['nutrition']['status'] === 'error');
check('DB lỗi: tiến trình báo error', $d['progress']['status'] === 'error');
check('DB lỗi: bài chưa đọc vẫn hiện đủ', isset($d['unreadArticles']['a']));

// ===== Lớp đọc DB trên SQLite in-memory: chỉ đọc đúng dữ liệu của user đang đăng nhập =====
if (extension_loaded('pdo_sqlite')) {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE workout_logs (id INTEGER PRIMARY KEY, user_id INT, exercise_id INT, workout_plan_exercise_id INT, log_date TEXT, weight_kg_used REAL)');
    $db->exec('CREATE TABLE nutrition_targets (id INTEGER PRIMARY KEY, user_id INT, tdee_kcal REAL, target_kcal REAL, protein_g REAL, carbs_g REAL, fat_g REAL, calculated_date TEXT)');
    $db->exec('CREATE TABLE body_metrics (id INTEGER PRIMARY KEY, user_id INT, recorded_date TEXT, weight_kg REAL)');
    $db->exec('CREATE TABLE article_reads (user_id INT, slug TEXT)');
    $db->exec("INSERT INTO workout_logs (user_id, exercise_id, workout_plan_exercise_id, log_date, weight_kg_used) VALUES
        (1, 5, 50, '2026-10-20', 20), (1, 5, 50, '2026-10-27', 25), (2, 5, 90, '2026-10-28', 99), (1, 6, 60, '2026-10-19', NULL), (1, 7, 70, '2026-10-19', 30), (1, 7, 70, '2026-10-20', 0)");
    $db->exec("INSERT INTO nutrition_targets (user_id, tdee_kcal, target_kcal, protein_g, carbs_g, fat_g, calculated_date) VALUES
        (1, 2000, 1800, 100, 200, 50, '2026-09-01'), (1, 2500, 2300, 150, 250, 60, '2026-10-01'), (2, 9, 9, 9, 9, 9, '2026-10-30')");
    $db->exec("INSERT INTO body_metrics (user_id, recorded_date, weight_kg) VALUES (1, '2026-10-08', 71), (1, '2026-10-01', 70), (2, '2026-10-20', 90)");
    $db->exec("INSERT INTO article_reads (user_id, slug) VALUES (1, 'da-doc'), (2, 'nguoi-khac-doc')");

    check('Mức tạ: lấy lần gần nhất của đúng user', dashLoadLastWeights($db, 1, [5, 6]) === [5 => 25.0]);
    check('Mức tạ: bỏ qua lần ghi để trống (0 kg)', dashLoadLastWeights($db, 1, [7]) === [7 => 30.0]);
    check('Mức tạ: không có bài nào thì không truy vấn', dashLoadLastWeights(new BrokenPdo(), 1, []) === []);
    $logs = dashLoadWeekLogs($db, 1, '2026-10-28');
    check('Nhật ký tuần: chỉ của user này, đúng tuần', count($logs) === 1 && $logs[0]['log_date'] === '2026-10-27');
    $n = dashLoadNutrition($db, 1);
    check('Dinh dưỡng: lấy lần tính gần nhất của user', $n['status'] === 'ok' && (float)$n['tdee_kcal'] === 2500.0);
    check('Dinh dưỡng: user chưa tính báo none', dashLoadNutrition($db, 3)['status'] === 'none');
    $p = dashLoadMetrics($db, 1, 'GAIN_MUSCLE');
    check('Số đo: sắp cũ đến mới, mới nhất là 71 kg', $p['metrics'][0]['recorded_date'] === '2026-10-01' && (float)$p['latest']['weight_kg'] === 71.0);
    check('Số đo: xu hướng theo mục tiêu', $p['trend']['delta'] === 1.0 && $p['trend']['matches'] === true);
    check('Số đo: user chưa đo báo none', dashLoadMetrics($db, 3, null)['status'] === 'none');
    check('Bài đã đọc: chỉ của user này', dashLoadReadSlugs($db, 1) === ['da-doc']);
} else {
    echo "[BỎ QUA] Không có pdo_sqlite nên không chạy phần DB\n";
}

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
