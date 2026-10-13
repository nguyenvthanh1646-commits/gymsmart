<?php
// Chạy: C:\xampp\php\php.exe test_workout_plan.php
require __DIR__ . '/public/includes/workout-plan.php';

$fails = 0;
function check(string $name, bool $ok): void {
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $fails++;
}

// Dữ liệu giả: mỗi nhóm 6 bài, độ khó xen kẽ
function fakeData(array $groups, int $perGroup = 6): array {
    $data = []; $id = 1; $diff = ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'];
    foreach ($groups as $g) {
        for ($i = 0; $i < $perGroup; $i++) {
            $data[$g][] = ['id' => $id++, 'difficulty' => $diff[$i % 3]];
        }
    }
    return $data;
}
$full = fakeData(['CHEST', 'BACK', 'SHOULDERS', 'LEGS', 'ARMS']);

// 1. Mọi tổ hợp hợp lệ chạy được và đúng số buổi
foreach ([2, 3, 4, 5, 6] as $d) {
    foreach (WORKOUT_GOALS as $g) {
        foreach (WORKOUT_LEVELS as $l) {
            $plan = generateWorkoutPlan($d, $g, $l, $full);
            if (count($plan['days']) !== $d) { check("$d/$g/$l đúng số buổi", false); }
        }
    }
}
check('45 tổ hợp hợp lệ: đúng số buổi', $fails === 0);

// 2. Split đúng theo số ngày
check('3 ngày -> FULL_BODY', generateWorkoutPlan(3, 'MAINTAIN', 'BEGINNER', $full)['split_type'] === 'FULL_BODY');
check('4 ngày -> UPPER_LOWER', generateWorkoutPlan(4, 'MAINTAIN', 'BEGINNER', $full)['split_type'] === 'UPPER_LOWER');
check('5 ngày -> PPL', generateWorkoutPlan(5, 'MAINTAIN', 'BEGINNER', $full)['split_type'] === 'PPL');
check('6 ngày -> PPL', generateWorkoutPlan(6, 'MAINTAIN', 'BEGINNER', $full)['split_type'] === 'PPL');

// 2b. Mọi tổ hợp chỉ sinh split_type nằm trong danh sách cho phép (không có BRO_SPLIT)
$badSplit = false;
foreach ([2, 3, 4, 5, 6] as $d) {
    if (!in_array(generateWorkoutPlan($d, 'MAINTAIN', 'BEGINNER', $full)['split_type'], WORKOUT_SPLIT_TYPES, true)) $badSplit = true;
}
check('split_type luôn thuộc FULL_BODY/UPPER_LOWER/PPL', !$badSplit);

// 3. Sets/reps theo mục tiêu + trình độ
$p = generateWorkoutPlan(3, 'GAIN_MUSCLE', 'ADVANCED', $full);
$e = $p['days'][0]['exercises'][0];
check('GAIN_MUSCLE -> 3 set x 10 lặp', $e['sets'] === 3 && $e['reps'] === 10);

// 4. BEGINNER chỉ nhận bài BEGINNER
$beginnerIds = [];
foreach ($full as $list) foreach ($list as $ex) if ($ex['difficulty'] === 'BEGINNER') $beginnerIds[] = $ex['id'];
$p = generateWorkoutPlan(5, 'MAINTAIN', 'BEGINNER', $full);
$bad = false;
foreach ($p['days'] as $day) foreach ($day['exercises'] as $row) if (!in_array($row['exercise_id'], $beginnerIds, true)) $bad = true;
check('BEGINNER không có bài khó hơn', !$bad);

// 5. Không trùng bài trong cùng 1 buổi
$p = generateWorkoutPlan(3, 'MAINTAIN', 'ADVANCED', $full);
$ids = array_column($p['days'][0]['exercises'], 'exercise_id');
check('Không trùng bài trong buổi', count($ids) === count(array_unique($ids)));

// 5b. Kiểm tra mỏi + tần suất trên mọi tổ hợp (dữ liệu đầy đủ)
$groupOf = [];
foreach ($full as $g => $list) foreach ($list as $ex) $groupOf[$ex['id']] = $g;
$overCap = false; $overVolume = false; $lowFreq = false;
foreach ([2, 3, 4, 5, 6] as $d) {
    foreach (WORKOUT_LEVELS as $l) {
        $plan = generateWorkoutPlan($d, 'MAINTAIN', $l, $full);
        $sets = []; $freq = [];
        foreach ($plan['days'] as $day) {
            if (count($day['exercises']) > WORKOUT_MAX_EXERCISES_PER_SESSION[$l]) $overCap = true;
            $seen = [];
            foreach ($day['exercises'] as $row) {
                $g = $groupOf[$row['exercise_id']];
                $sets[$g] = ($sets[$g] ?? 0) + $row['sets'];
                $seen[$g] = true;
            }
            foreach ($seen as $g => $_) $freq[$g] = ($freq[$g] ?? 0) + 1;
        }
        foreach ($sets as $g => $n) if ($n > 18) $overVolume = true;
        // tần suất >= 2 buổi/tuần cho mọi nhóm cơ ở lịch từ 3 ngày trở lên
        if ($d >= 3) foreach (['LEGS', 'CHEST', 'BACK', 'SHOULDERS', 'ARMS'] as $g) if (($freq[$g] ?? 0) < 2) $lowFreq = true;
    }
}
check('Không buổi nào vượt trần số bài (mỏi)', !$overCap);
check('Không nhóm cơ nào quá 18 set/tuần', !$overVolume);
check('Từ 3 ngày: mọi nhóm cơ lớn tập >= 2 buổi/tuần', !$lowFreq);

// 6. Dữ liệu ít: thiếu hẳn nhóm 'LEGS', nhóm 'ARMS' chỉ có 1 bài -> không lỗi
$sparse = ['CHEST' => [['id' => 1, 'difficulty' => 'BEGINNER']], 'ARMS' => [['id' => 2, 'difficulty' => 'BEGINNER']]];
$p = generateWorkoutPlan(4, 'LOSE_FAT', 'BEGINNER', $sparse);
check('Thiếu nhóm cơ: vẫn trả đủ 4 buổi', count($p['days']) === 4);
check('Buổi Chân rỗng (không lỗi)', count($p['days'][1]['exercises']) === 0);
check('Mảng bài rỗng hoàn toàn: không lỗi', count(generateWorkoutPlan(3, 'MAINTAIN', 'BEGINNER', [])['days']) === 3);

// 7. Đầu vào sai phải ném InvalidArgumentException
$cases = [[1, 'MAINTAIN', 'BEGINNER'], [7, 'MAINTAIN', 'BEGINNER'], [3, 'BULK', 'BEGINNER'], [3, 'MAINTAIN', 'PRO'], [3, "' OR '1'='1", 'BEGINNER']];
foreach ($cases as $c) {
    $thrown = false;
    try { generateWorkoutPlan($c[0], $c[1], $c[2], $full); } catch (InvalidArgumentException $ex) { $thrown = true; }
    check('Từ chối: ' . json_encode($c, JSON_UNESCAPED_UNICODE), $thrown);
}

echo PHP_EOL . ($fails === 0 ? 'TẤT CẢ ĐỀU PASS' : "$fails TEST FAIL") . PHP_EOL;
exit($fails === 0 ? 0 : 1);