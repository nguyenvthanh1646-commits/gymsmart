<?php
// public/includes/workout_plan.php
// Hàm THUẦN: không truy vấn DB, không đọc $_SESSION/$_GET. Cùng đầu vào -> cùng đầu ra.
// Quy tắc được giải thích trong docs/workout-split-rules.md (B7).

// ===== WHITELIST ĐẦU VÀO =====
const WORKOUT_GOALS  = ['LOSE_FAT', 'MAINTAIN', 'GAIN_MUSCLE'];
const WORKOUT_LEVELS = ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'];

// ===== QUY TẮC SPLIT (theo số ngày/tuần) =====
// Tên nhóm cơ là MÃ lưu trong cột exercises.muscle_group (xem $muscleLabels trong exercises.php): CHEST, BACK, SHOULDERS, LEGS, ARMS.
// ABS và FULL_BODY có trong thư viện nhưng planner chưa dùng.
// Nguyên tắc: mỗi nhóm cơ lớn xuất hiện >= 2 buổi/tuần; không xếp cùng nhóm ở 2 ngày liên tiếp.
const WORKOUT_SPLIT_RULES = [
    2 => ['split_type' => 'FULL_BODY', 'days' => [
        ['day_of_week' => 1, 'groups' => ['LEGS', 'CHEST', 'BACK', 'SHOULDERS', 'ARMS']],
        ['day_of_week' => 4, 'groups' => ['LEGS', 'CHEST', 'BACK', 'SHOULDERS', 'ARMS']],
    ]],
    3 => ['split_type' => 'FULL_BODY', 'days' => [
        ['day_of_week' => 1, 'groups' => ['LEGS', 'CHEST', 'BACK', 'SHOULDERS', 'ARMS']],
        ['day_of_week' => 3, 'groups' => ['LEGS', 'CHEST', 'BACK', 'SHOULDERS', 'ARMS']],
        ['day_of_week' => 5, 'groups' => ['LEGS', 'CHEST', 'BACK', 'SHOULDERS', 'ARMS']],
    ]],
    4 => ['split_type' => 'UPPER_LOWER', 'days' => [
        ['day_of_week' => 1, 'groups' => ['CHEST', 'BACK', 'SHOULDERS', 'ARMS']], // Upper
        ['day_of_week' => 2, 'groups' => ['LEGS']],                      // Lower
        ['day_of_week' => 4, 'groups' => ['CHEST', 'BACK', 'SHOULDERS', 'ARMS']],
        ['day_of_week' => 5, 'groups' => ['LEGS']],
    ]],
    5 => ['split_type' => 'PPL', 'days' => [                             // PPL + Upper/Lower
        ['day_of_week' => 1, 'groups' => ['CHEST', 'SHOULDERS']],               // Push
        ['day_of_week' => 2, 'groups' => ['BACK', 'ARMS']],               // Pull
        ['day_of_week' => 3, 'groups' => ['LEGS']],                      // Legs
        ['day_of_week' => 5, 'groups' => ['CHEST', 'BACK', 'SHOULDERS', 'ARMS']], // Upper
        ['day_of_week' => 6, 'groups' => ['LEGS']],                      // Lower
    ]],
    6 => ['split_type' => 'PPL', 'days' => [
        ['day_of_week' => 1, 'groups' => ['CHEST', 'SHOULDERS']],
        ['day_of_week' => 2, 'groups' => ['BACK', 'ARMS']],
        ['day_of_week' => 3, 'groups' => ['LEGS']],
        ['day_of_week' => 4, 'groups' => ['CHEST', 'SHOULDERS']],
        ['day_of_week' => 5, 'groups' => ['BACK', 'ARMS']],
        ['day_of_week' => 6, 'groups' => ['LEGS']],
    ]],
];
// Chỉ dùng 3 kiểu split (đã thống nhất với A: bỏ BRO_SPLIT, lịch 5 ngày dùng PPL).
const WORKOUT_SPLIT_TYPES = ['FULL_BODY', 'UPPER_LOWER', 'PPL'];

// ===== KHỐI LƯỢNG & MỎI =====
// Thứ tự ưu tiên nhóm cơ: nhóm lớn tập trước; khi buổi quá dài thì cắt từ cuối danh sách
// Nhãn tiếng Việt để hiển thị (cùng nội dung với $muscleLabels của exercises.php)
const WORKOUT_MUSCLE_LABELS = ['CHEST' => 'Ngực', 'BACK' => 'Lưng', 'SHOULDERS' => 'Vai', 'LEGS' => 'Chân', 'ARMS' => 'Tay', 'ABS' => 'Bụng', 'FULL_BODY' => 'Toàn thân'];
const WORKOUT_GROUP_PRIORITY = ['LEGS', 'CHEST', 'BACK', 'SHOULDERS', 'ARMS'];
// Ngân sách: số bài mỗi nhóm cơ mỗi TUẦN (x 3 set = 9 / 12 / 15 set/nhóm/tuần)
const WORKOUT_WEEKLY_EXERCISES_PER_GROUP = ['BEGINNER' => 3, 'INTERMEDIATE' => 4, 'ADVANCED' => 5];
// Trần số bài mỗi buổi (giới hạn mỏi / thời lượng)
const WORKOUT_MAX_EXERCISES_PER_SESSION = ['BEGINNER' => 5, 'INTERMEDIATE' => 6, 'ADVANCED' => 8];
const WORKOUT_SETS_PER_EXERCISE = 3;
// Mục tiêu -> số lặp
const WORKOUT_REPS_BY_GOAL = ['LOSE_FAT' => 15, 'MAINTAIN' => 12, 'GAIN_MUSCLE' => 10];
// Trình độ -> độ khó bài được phép chọn
const WORKOUT_LEVEL_DIFFICULTIES = [
    'BEGINNER'     => ['BEGINNER'],
    'INTERMEDIATE' => ['BEGINNER', 'INTERMEDIATE'],
    'ADVANCED'     => ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'],
];

/**
 * @param int    $daysPerWeek      2..6
 * @param string $goal             LOSE_FAT | MAINTAIN | GAIN_MUSCLE
 * @param string $level            BEGINNER | INTERMEDIATE | ADVANCED
 * @param array  $exercisesByGroup ['CHEST' => [['id'=>1,'difficulty'=>'BEGINNER'], ...], ...]
 * @return array ['split_type','days_per_week','days'=>[['day_of_week','groups','exercises'=>[...]]]]
 * @throws InvalidArgumentException khi đầu vào ngoài whitelist
 */
function generateWorkoutPlan(int $daysPerWeek, string $goal, string $level, array $exercisesByGroup): array
{
    if (!isset(WORKOUT_SPLIT_RULES[$daysPerWeek])) {
        throw new InvalidArgumentException('Số ngày/tuần phải từ 2 đến 6.');
    }
    if (!in_array($goal, WORKOUT_GOALS, true)) {
        throw new InvalidArgumentException('Mục tiêu không hợp lệ.');
    }
    if (!in_array($level, WORKOUT_LEVELS, true)) {
        throw new InvalidArgumentException('Trình độ không hợp lệ.');
    }

    $rule  = WORKOUT_SPLIT_RULES[$daysPerWeek];
    $reps  = WORKOUT_REPS_BY_GOAL[$goal];
    $pools = buildExercisePools($exercisesByGroup, WORKOUT_LEVEL_DIFFICULTIES[$level]);
    $slots = planSessionSlots(
        $rule['days'],
        WORKOUT_WEEKLY_EXERCISES_PER_GROUP[$level],
        WORKOUT_MAX_EXERCISES_PER_SESSION[$level]
    );

    $cursors = []; // con trỏ xoay vòng theo nhóm cơ, giữ qua các ngày để các buổi dùng bài khác nhau
    $days = [];
    foreach ($rule['days'] as $dayIndex => $day) {
        $rows = [];
        foreach (WORKOUT_GROUP_PRIORITY as $group) {
            $count = $slots[$dayIndex][$group] ?? 0;
            if ($count === 0 || empty($pools[$group])) {
                continue; // thiếu bài của nhóm này -> bỏ qua, không lỗi
            }
            $cursors[$group] = $cursors[$group] ?? 0;
            foreach (takeExercises($pools[$group], $cursors[$group], $count) as $ex) {
                $rows[] = [
                    'exercise_id' => (int)$ex['id'],
                    'sets'        => WORKOUT_SETS_PER_EXERCISE,
                    'reps'        => $reps,
                    'order_index' => count($rows) + 1,
                ];
            }
        }
        $days[] = [
            'day_of_week' => $day['day_of_week'],
            'groups'      => $day['groups'],
            'exercises'   => $rows,
        ];
    }

    return [
        'split_type'    => $rule['split_type'],
        'days_per_week' => $daysPerWeek,
        'days'          => $days,
    ];
}

/** Lọc bài theo độ khó cho từng nhóm cơ. */
function buildExercisePools(array $exercisesByGroup, array $allowedDifficulties): array
{
    $pools = [];
    foreach ($exercisesByGroup as $group => $list) {
        foreach ($list as $ex) {
            if (in_array($ex['difficulty'] ?? '', $allowedDifficulties, true)) {
                $pools[$group][] = $ex;
            }
        }
    }
    return $pools;
}

/**
 * Chia ngân sách tuần của mỗi nhóm cơ cho các buổi có nhóm đó, rồi cắt theo trần mỗi buổi.
 * Ví dụ: ngân sách 4 bài/tuần, nhóm xuất hiện 3 buổi -> 2 + 1 + 1.
 * @return array [dayIndex => ['LEGS' => 2, 'CHEST' => 1, ...]]
 */
function planSessionSlots(array $days, int $weeklyPerGroup, int $maxPerSession): array
{
    $sessionsOfGroup = [];
    foreach ($days as $i => $day) {
        foreach ($day['groups'] as $group) {
            $sessionsOfGroup[$group][] = $i;
        }
    }

    $slots = array_fill(0, count($days), []);
    $groupNo = 0;
    foreach ($sessionsOfGroup as $group => $dayIdxs) {
        $f    = count($dayIdxs);
        $base = intdiv($weeklyPerGroup, $f);
        $rem  = $weeklyPerGroup % $f;
        foreach ($dayIdxs as $k => $dayIdx) {
            // phần dư rải bắt đầu từ vị trí khác nhau theo từng nhóm để các buổi không lệch tải
            $extra = ((($k - $groupNo) % $f + $f) % $f) < $rem ? 1 : 0;
            $slots[$dayIdx][$group] = $base + $extra;
        }
        $groupNo++;
    }

    foreach ($slots as $i => $s) {
        $slots[$i] = trimSessionSlots($s, $maxPerSession);
    }
    return $slots;
}

/**
 * Buổi quá dài thì giảm dần: trước hết bớt nhóm đang có nhiều bài nhất (hòa thì bớt nhóm ưu tiên thấp),
 * khi mọi nhóm chỉ còn 1 bài mà vẫn vượt trần thì bỏ nhóm ưu tiên thấp nhất.
 */
function trimSessionSlots(array $slots, int $max): array
{
    $lowToHigh = array_reverse(WORKOUT_GROUP_PRIORITY);
    while (array_sum($slots) > $max) {
        $target = null;
        $best = 1;
        foreach ($lowToHigh as $g) {
            if (($slots[$g] ?? 0) > $best) {
                $best = $slots[$g];
                $target = $g;
            }
        }
        if ($target === null) {
            foreach ($lowToHigh as $g) {
                if (($slots[$g] ?? 0) > 0) {
                    $target = $g;
                    break;
                }
            }
        }
        if ($target === null) {
            break;
        }
        $slots[$target]--;
    }
    return $slots;
}

/**
 * Lấy $count bài kế tiếp từ $pool theo con trỏ xoay vòng (không random -> ổn định, dễ test).
 * Không trả trùng bài trong cùng một lần gọi; pool nhỏ hơn $count thì trả ít hơn.
 */
function takeExercises(array $pool, int &$cursor, int $count): array
{
    $n = count($pool);
    $picked = [];
    $used = [];
    for ($tries = 0; $tries < $n && count($picked) < $count; $tries++) {
        $ex = $pool[$cursor % $n];
        $cursor++;
        if (!isset($used[$ex['id']])) {
            $used[$ex['id']] = true;
            $picked[] = $ex;
        }
    }
    return $picked;
}