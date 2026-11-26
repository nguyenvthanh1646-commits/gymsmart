<?php
// test-progression.php — kiểm tra gợi ý tăng tạ (includes/progression.php) và lớp đọc nhật ký theo bài (SQLite trong bộ nhớ).
// Chạy ở gốc repo: php test-progression.php

require __DIR__ . '/public/includes/progression.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}
$log = fn(string $date, float $w, int $reps, int $sets = 3) => ['date' => $date, 'weight' => $w, 'sets' => $sets, 'reps' => $reps];

// ===== suggestProgression =====
$s = suggestProgression([], 3, 6, 8, 'CHEST');
check('Chưa có nhật ký: start, không đoán mức tạ', $s['action'] === 'start' && $s['weight'] === null);

$s = suggestProgression([$log('2026-11-18', 40, 8)], 3, 6, 8, 'CHEST');
check('Đạt 8 lặp 1 buổi: giữ nguyên, chưa tăng', $s['action'] === 'keep' && $s['weight'] === 40.0);

$s = suggestProgression([$log('2026-11-18', 40, 8), $log('2026-11-15', 40, 8)], 3, 6, 8, 'CHEST');
check('Đạt 8 lặp 2 buổi liền cùng mức tạ (ngực): tăng 1 kg', $s['action'] === 'increase' && $s['weight'] === 41.0);

$s = suggestProgression([$log('2026-11-18', 60, 8), $log('2026-11-15', 60, 9)], 3, 6, 8, 'LEGS');
check('Chân: bước tăng 2,5 kg', $s['action'] === 'increase' && $s['weight'] === 62.5);

$s = suggestProgression([$log('2026-11-18', 40, 8), $log('2026-11-15', 37.5, 8)], 3, 6, 8, 'CHEST');
check('Buổi trước mức tạ khác: chưa tính là 2 buổi liền', $s['action'] === 'keep');

$s = suggestProgression([$log('2026-11-18', 40, 8, 2), $log('2026-11-15', 40, 8, 2)], 3, 6, 8, 'CHEST');
check('Thiếu set so với lịch thì chưa tính là đạt', $s['action'] === 'keep');

$s = suggestProgression([$log('2026-11-18', 40, 7), $log('2026-11-15', 40, 8)], 3, 6, 8, 'CHEST');
check('Buổi mới nhất chưa đạt: giữ, cố thêm lặp', $s['action'] === 'keep' && str_contains($s['text'], 'thêm 1 lặp'));

$s = suggestProgression([$log('2026-11-18', 50, 5), $log('2026-11-15', 50, 4)], 3, 6, 8, 'CHEST');
check('2 buổi liền dưới 6 lặp: giảm còn 45 kg (90%, tròn 0,5)', $s['action'] === 'decrease' && $s['weight'] === 45.0);

$s = suggestProgression([$log('2026-11-18', 50, 5)], 3, 6, 8, 'CHEST');
check('Mới 1 buổi dưới 6 lặp: chưa giảm', $s['action'] === 'keep');

$s = suggestProgression([$log('2026-11-18', 0, 10), $log('2026-11-15', 0, 10)], 3, 6, 8, 'ABS');
check('Bài không tạ đạt mức: gợi ý thêm lặp, không tăng tạ', $s['action'] === 'reps' && $s['weight'] === 0.0);

// Cùng ngày ghi 2 lần: chỉ tính lần sau cùng (đầu mảng), không biến 1 buổi thành 2 buổi
$s = suggestProgression([$log('2026-11-18', 40, 8), $log('2026-11-18', 40, 8)], 3, 6, 8, 'CHEST');
check('Hai lần ghi cùng ngày chỉ là 1 buổi: chưa tăng', $s['action'] === 'keep');

check('Làm tròn 0,5 kg', progressionRound(41.3) === 41.5 && progressionRound(40.2) === 40.0);

// ===== summarizeSessionSets =====
$sum = summarizeSessionSets([['weight' => 20, 'reps' => 10], ['weight' => 40, 'reps' => 8], ['weight' => 40, 'reps' => 7]]);
check('Gộp set: tạ nặng nhất, lặp thấp nhất ở mức đó, set khởi động không tính',
    $sum === ['weight' => 40.0, 'sets' => 2, 'reps' => 7]);
check('Gộp set: không có set hợp lệ thì null', summarizeSessionSets([['weight' => 40, 'reps' => 0]]) === null);
check('Gộp set: bài không tạ (0 kg) vẫn hợp lệ', summarizeSessionSets([['weight' => 0, 'reps' => 12]]) === ['weight' => 0.0, 'sets' => 1, 'reps' => 12]);

// ===== Lớp DB: nhật ký gần đây theo nhiều bài trong một truy vấn =====
if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    require __DIR__ . '/public/includes/workout-plan-db.php';
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('CREATE TABLE workout_logs (id INTEGER PRIMARY KEY, user_id INT, workout_plan_exercise_id INT NULL, exercise_id INT,
        log_date TEXT, sets_completed INT, reps_completed INT, weight_kg_used REAL, note TEXT, created_at TEXT)');
    $ins = $db->prepare('INSERT INTO workout_logs (user_id, exercise_id, log_date, sets_completed, reps_completed, weight_kg_used) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ([[1, 5, '2026-11-10', 3, 8, 40], [1, 5, '2026-11-14', 3, 8, 40], [1, 6, '2026-11-14', 2, 6, 20], [2, 5, '2026-11-15', 3, 8, 99]] as $r) {
        $ins->execute($r);
    }
    $byEx = fetchRecentLogsByExercise($db, 1, [5, 6, 7]);
    check('Nhật ký theo bài: mới nhất trước', $byEx[5][0]['date'] === '2026-11-14' && $byEx[5][1]['date'] === '2026-11-10');
    check('Nhật ký theo bài: không lẫn nhật ký người khác', count($byEx[5]) === 2 && $byEx[5][0]['weight'] === 40.0);
    check('Nhật ký theo bài: bài chưa tập là mảng rỗng', $byEx[7] === []);
    check('Nhật ký theo bài: danh sách rỗng không truy vấn', fetchRecentLogsByExercise($db, 1, []) === []);
    check('Nhật ký theo bài + gợi ý: 2 buổi 8 lặp ngực -> tăng', suggestProgression($byEx[5], 3, 6, 8, 'CHEST')['action'] === 'increase');
} else {
    echo "[BỎ QUA] Không có pdo_sqlite nên không chạy phần DB\n";
}

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
