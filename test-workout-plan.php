<?php
// Chạy: C:\xampp\php\php.exe test-workout-plan.php
require __DIR__ . '/public/includes/workout-plan.php';

$fails = 0;
function check(string $name, bool $ok): void {
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $fails++;
}

// Dữ liệu thật: 40 bài của seed_exercises_v2.sql, nhóm theo muscle_group giống fetchExercisesGroupedByMuscle()
$fixture = require __DIR__ . '/test-exercises-fixture.php';
$full = [];
$byId = [];
foreach ($fixture as $ex) {
    $byId[$ex['id']] = $ex;
    if ($ex['type'] === 'RESISTANCE') {
        $full[$ex['muscle_group']][] = ['id' => $ex['id'], 'difficulty' => $ex['difficulty']];
    }
}

// Mọi tổ hợp (số ngày, kiểu chia) hợp lệ
$combos = [];
foreach (WORKOUT_SPLITS as $split => $byDays) foreach ($byDays as $d => $_) $combos[] = [$d, $split];

// 0. Bảng vai trò khớp seed: mỗi id có thật, là bài kháng lực
$badRole = false;
foreach (WORKOUT_EXERCISE_ROLES as $id => $role) {
    if (!isset($byId[$id]) || $byId[$id]['type'] !== 'RESISTANCE' || !isset(WORKOUT_ROLE_MUSCLE[$role])) $badRole = true;
}
check('Bảng vai trò: mọi id là bài kháng lực có trong seed', !$badRole);

// 1. Mọi tổ hợp hợp lệ chạy được và đúng số buổi
$before = $fails;
foreach ($combos as [$d, $split]) {
    foreach (WORKOUT_GOALS as $g) {
        foreach (WORKOUT_LEVELS as $l) {
            $plan = generateWorkoutPlan($d, $g, $l, $full, $split);
            if (count($plan['days']) !== $d) { check("$d/$split/$g/$l đúng số buổi", false); }
        }
    }
}
check(count($combos) * 9 . ' tổ hợp hợp lệ: đúng số buổi', $fails === $before);

// 2. Kiểu chia mặc định theo số ngày
check('3 ngày -> FULL_BODY', generateWorkoutPlan(3, 'MAINTAIN', 'BEGINNER', $full)['split_type'] === 'FULL_BODY');
check('4 ngày -> UPPER_LOWER', generateWorkoutPlan(4, 'MAINTAIN', 'BEGINNER', $full)['split_type'] === 'UPPER_LOWER');
check('4 ngày chọn AP -> ANTERIOR_POSTERIOR', generateWorkoutPlan(4, 'MAINTAIN', 'BEGINNER', $full, 'ANTERIOR_POSTERIOR')['split_type'] === 'ANTERIOR_POSTERIOR');
$fb4 = generateWorkoutPlan(4, 'MAINTAIN', 'INTERMEDIATE', $full, 'FULL_BODY');
check('4 ngày chọn Toàn thân -> 4 buổi toàn thân, cách ngày', $fb4['split_type'] === 'FULL_BODY' && array_column($fb4['days'], 'day_of_week') === [1, 3, 5, 7]);
check('workoutSplitOptions: 4 ngày có 3 kiểu chia', count(workoutSplitOptions()[4]) === 3);
check('workoutSessionLabels: PPL 6 ngày thứ 2 là Đẩy', (workoutSessionLabels('PPL', 6)[1] ?? '') === 'Đẩy');
check('5 ngày -> PPL + Trên/Dưới', generateWorkoutPlan(5, 'MAINTAIN', 'BEGINNER', $full)['split_type'] === 'PPL_UL');
$sess = fn($d, $split) => array_column(generateWorkoutPlan($d, 'MAINTAIN', 'INTERMEDIATE', $full, $split)['days'], 'session');
check('PPL_UL 5 ngày: Đẩy, Kéo, Chân rồi Trên, Dưới', $sess(5, 'PPL_UL') === ['PUSH', 'PULL', 'LEGS', 'UPPER', 'LOWER']);
check('UL_FULL_BODY 3 ngày: Trên, Dưới rồi Toàn thân', $sess(3, 'UL_FULL_BODY') === ['UPPER', 'LOWER', 'FULL_BODY']);
check('AP_FULL_BODY 3 ngày: Mặt trước, Mặt sau rồi Toàn thân', $sess(3, 'AP_FULL_BODY') === ['ANTERIOR', 'POSTERIOR', 'FULL_BODY']);
check('Lịch 3 ngày có 3 kiểu để chọn', count(workoutSplitOptions()[3]) === 3);
check('6 ngày -> PPL', generateWorkoutPlan(6, 'MAINTAIN', 'BEGINNER', $full)['split_type'] === 'PPL');
$sessions = array_column(generateWorkoutPlan(6, 'MAINTAIN', 'ADVANCED', $full)['days'], 'session');
check('6 ngày: Đẩy, Kéo, Chân x2', $sessions === ['PUSH', 'PULL', 'LEGS', 'PUSH', 'PULL', 'LEGS']);

// 3. Khối lượng theo mẫu: 1-3 set, 6-8 lặp (lưu mức 8)
$okVolume = true;
foreach (WORKOUT_LEVELS as $l) {
    foreach (generateWorkoutPlan(6, 'LOSE_FAT', $l, $full)['days'] as $day) {
        foreach ($day['exercises'] as $row) {
            if ($row['sets'] < 1 || $row['sets'] > 3 || $row['reps'] < 6 || $row['reps'] > 8) $okVolume = false;
        }
    }
}
check('Mọi bài: 1-3 set, 6-8 lặp', $okVolume);
$e = generateWorkoutPlan(3, 'GAIN_MUSCLE', 'ADVANCED', $full)['days'][0]['exercises'][0];
check('ADVANCED -> 3 set x 8 lặp', $e['sets'] === 3 && $e['reps'] === 8);

// 4. Số bài từng vai trò đúng mẫu (thư viện đủ bài cho nâng cao)
$countRoles = function (array $day): array {
    $c = [];
    foreach ($day['exercises'] as $row) { $r = WORKOUT_EXERCISE_ROLES[$row['exercise_id']]; $c[$r] = ($c[$r] ?? 0) + 1; }
    return $c;
};
$pull = $countRoles(generateWorkoutPlan(6, 'MAINTAIN', 'ADVANCED', $full)['days'][1]);
check('Buổi Kéo nâng cao: 3 lưng, 2 vai sau, 2 tay trước', ($pull['BACK'] ?? 0) === 3 && ($pull['DELT_REAR'] ?? 0) === 2 && ($pull['BICEPS'] ?? 0) === 2);
$push = $countRoles(generateWorkoutPlan(6, 'MAINTAIN', 'ADVANCED', $full)['days'][0]);
check('Buổi Đẩy nâng cao: 3 ngực (có ngực trên), 2 vai, 2 tay sau',
    ($push['CHEST_FLAT'] ?? 0) + ($push['CHEST_INCLINE'] ?? 0) === 3 && ($push['CHEST_INCLINE'] ?? 0) === 1
    && ($push['DELT_FRONT'] ?? 0) === 1 && ($push['DELT_SIDE'] ?? 0) === 1 && ($push['TRICEPS'] ?? 0) === 2);
$lower = $countRoles(generateWorkoutPlan(4, 'MAINTAIN', 'ADVANCED', $full)['days'][1]);
check('Buổi Thân dưới: squat, leg extension, RDL, leg curl, 2 mông, bắp chân',
    ($lower['SQUAT'] ?? 0) === 1 && ($lower['LEG_EXTENSION'] ?? 0) === 1 && ($lower['HINGE'] ?? 0) === 1
    && ($lower['LEG_CURL'] ?? 0) === 1 && ($lower['GLUTE'] ?? 0) === 2 && ($lower['CALF'] ?? 0) === 1);
$post = $countRoles(generateWorkoutPlan(4, 'MAINTAIN', 'ADVANCED', $full, 'ANTERIOR_POSTERIOR')['days'][1]);
check('Buổi Mặt sau: 3 lưng, 1 vai sau, 2 đùi sau', ($post['BACK'] ?? 0) === 3 && ($post['DELT_REAR'] ?? 0) === 1
    && ($post['HINGE'] ?? 0) + ($post['LEG_CURL'] ?? 0) === 2);
$ant = $countRoles(generateWorkoutPlan(4, 'MAINTAIN', 'INTERMEDIATE', $full, 'ANTERIOR_POSTERIOR')['days'][0]);
check('Buổi Mặt trước: có bụng thẳng và liên sườn', ($ant['ABS'] ?? 0) === 1 && ($ant['OBLIQUE'] ?? 0) === 1);

// 5. Trình độ chỉ nhận bài không khó hơn
$rank = ['BEGINNER' => 0, 'INTERMEDIATE' => 1, 'ADVANCED' => 2];
$tooHard = false;
foreach ($combos as [$d, $split]) foreach (WORKOUT_LEVELS as $l) {
    foreach (generateWorkoutPlan($d, 'MAINTAIN', $l, $full, $split)['days'] as $day) foreach ($day['exercises'] as $row) {
        if ($rank[$byId[$row['exercise_id']]['difficulty']] > $rank[$l]) $tooHard = true;
    }
}
check('Không có bài khó hơn trình độ', !$tooHard);

// 6. Không trùng bài trong buổi, các buổi cùng mẫu xoay bài khác nhau
$dup = false;
foreach ($combos as [$d, $split]) foreach (WORKOUT_LEVELS as $l) {
    foreach (generateWorkoutPlan($d, 'MAINTAIN', $l, $full, $split)['days'] as $day) {
        $ids = array_column($day['exercises'], 'exercise_id');
        if (count($ids) !== count(array_unique($ids))) $dup = true;
    }
}
check('Không trùng bài trong buổi', !$dup);
$fb = generateWorkoutPlan(3, 'MAINTAIN', 'ADVANCED', $full)['days'];
check('Toàn thân 3 buổi: buổi 1 và buổi 2 khác bài', array_column($fb[0]['exercises'], 'exercise_id') !== array_column($fb[1]['exercises'], 'exercise_id'));

// 7. Khối lượng tuần tính theo TỪNG CƠ (đùi trước, đùi sau, mông... riêng), không gộp chung "Chân"
//    và tần suất: từ 3 ngày, mỗi cơ chính tập >= 2 buổi/tuần
$overVolume = []; $lowFreq = [];
foreach ($combos as [$d, $split]) {
    foreach (WORKOUT_LEVELS as $l) {
        $plan = generateWorkoutPlan($d, 'MAINTAIN', $l, $full, $split);
        $sets = []; $freq = [];
        foreach ($plan['days'] as $day) {
            $seen = [];
            foreach ($day['exercises'] as $row) {
                $m = WORKOUT_ROLE_MUSCLE[WORKOUT_EXERCISE_ROLES[$row['exercise_id']]];
                $sets[$m] = ($sets[$m] ?? 0) + $row['sets'];
                $seen[$m] = true;
            }
            foreach ($seen as $m => $_) $freq[$m] = ($freq[$m] ?? 0) + 1;
        }
        foreach ($sets as $m => $n) if ($n > 18) $overVolume[] = "$d/$split/$l/$m=$n";
        if ($d >= 3) foreach (['CHEST', 'BACK', 'QUADS', 'HAMSTRINGS'] as $m) if (($freq[$m] ?? 0) < 2) $lowFreq[] = "$d/$split/$l/$m";
    }
}
check('Không cơ nào quá 18 set/tuần' . ($overVolume ? ' ' . implode(', ', $overVolume) : ''), !$overVolume);
check('Từ 3 ngày: ngực, lưng, đùi trước, đùi sau >= 2 buổi/tuần' . ($lowFreq ? ' ' . implode(', ', $lowFreq) : ''), !$lowFreq);

// 8. Dữ liệu ít / bài lạ: không lỗi
$sparse = ['CHEST' => [['id' => 1, 'difficulty' => 'BEGINNER']], 'ARMS' => [['id' => 28, 'difficulty' => 'BEGINNER']]];
$p = generateWorkoutPlan(4, 'LOSE_FAT', 'BEGINNER', $sparse);
check('Thiếu nhóm cơ: vẫn trả đủ 4 buổi', count($p['days']) === 4);
check('Buổi Thân dưới rỗng (không lỗi)', count($p['days'][1]['exercises']) === 0);
check('Mảng bài rỗng hoàn toàn: không lỗi', count(generateWorkoutPlan(3, 'MAINTAIN', 'BEGINNER', [])['days']) === 3);
$unknown = ['CHEST' => [['id' => 999, 'difficulty' => 'BEGINNER']]];
check('Bài chưa có vai trò: không được chọn', count(generateWorkoutPlan(3, 'MAINTAIN', 'BEGINNER', $unknown)['days'][0]['exercises']) === 0);

// 9. Đầu vào sai phải ném InvalidArgumentException
$cases = [[1, 'MAINTAIN', 'BEGINNER', null], [7, 'MAINTAIN', 'BEGINNER', null], [3, 'BULK', 'BEGINNER', null],
          [3, 'MAINTAIN', 'PRO', null], [3, "' OR '1'='1", 'BEGINNER', null],
          [3, 'MAINTAIN', 'BEGINNER', 'ANTERIOR_POSTERIOR'], [4, 'MAINTAIN', 'BEGINNER', 'BRO_SPLIT']];
foreach ($cases as $c) {
    $thrown = false;
    try { generateWorkoutPlan($c[0], $c[1], $c[2], $full, $c[3]); } catch (InvalidArgumentException $ex) { $thrown = true; }
    check('Từ chối: ' . json_encode($c, JSON_UNESCAPED_UNICODE), $thrown);
}

echo PHP_EOL . ($fails === 0 ? 'TẤT CẢ ĐỀU PASS' : "$fails TEST FAIL") . PHP_EOL;
