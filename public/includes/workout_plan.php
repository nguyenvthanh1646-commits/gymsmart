<?php
// public/includes/workout_plan.php
// Hàm THUẦN: không truy vấn DB, không đọc $_SESSION/$_GET. Cùng đầu vào -> cùng đầu ra.

// ===== WHITELIST ĐẦU VÀO =====
const WORKOUT_GOALS  = ['LOSE_FAT', 'MAINTAIN', 'GAIN_MUSCLE'];
const WORKOUT_LEVELS = ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'];

// ===== QUY TẮC (B7 - DỰ THẢO, cần B + A chốt) =====
// Tên nhóm cơ PHẢI trùng giá trị cột exercises.muscle_group trong seed.sql
const WORKOUT_SPLIT_RULES = [
    2 => ['split_type' => 'FULL_BODY', 'days' => [
        ['day_of_week' => 1, 'groups' => ['Chân', 'Ngực', 'Lưng', 'Vai']],
        ['day_of_week' => 4, 'groups' => ['Chân', 'Lưng', 'Ngực', 'Tay']],
    ]],
    3 => ['split_type' => 'FULL_BODY', 'days' => [
        ['day_of_week' => 1, 'groups' => ['Chân', 'Ngực', 'Lưng', 'Vai']],
        ['day_of_week' => 3, 'groups' => ['Chân', 'Lưng', 'Ngực', 'Tay']],
        ['day_of_week' => 5, 'groups' => ['Chân', 'Vai', 'Lưng', 'Ngực']],
    ]],
    4 => ['split_type' => 'UPPER_LOWER', 'days' => [
        ['day_of_week' => 1, 'groups' => ['Ngực', 'Lưng', 'Vai', 'Tay']],
        ['day_of_week' => 2, 'groups' => ['Chân']],
        ['day_of_week' => 4, 'groups' => ['Ngực', 'Lưng', 'Vai', 'Tay']],
        ['day_of_week' => 5, 'groups' => ['Chân']],
    ]],
    5 => ['split_type' => 'BRO_SPLIT', 'days' => [
        ['day_of_week' => 1, 'groups' => ['Ngực']],
        ['day_of_week' => 2, 'groups' => ['Lưng']],
        ['day_of_week' => 3, 'groups' => ['Vai']],
        ['day_of_week' => 4, 'groups' => ['Chân']],
        ['day_of_week' => 5, 'groups' => ['Tay']],
    ]],
    6 => ['split_type' => 'PPL', 'days' => [
        ['day_of_week' => 1, 'groups' => ['Ngực', 'Vai', 'Tay']],  // Push
        ['day_of_week' => 2, 'groups' => ['Lưng', 'Tay']],         // Pull
        ['day_of_week' => 3, 'groups' => ['Chân']],                // Legs
        ['day_of_week' => 4, 'groups' => ['Ngực', 'Vai', 'Tay']],
        ['day_of_week' => 5, 'groups' => ['Lưng', 'Tay']],
        ['day_of_week' => 6, 'groups' => ['Chân']],
    ]],
];

// Trình độ -> độ khó bài được phép chọn
const WORKOUT_LEVEL_DIFFICULTIES = [
    'BEGINNER'     => ['BEGINNER'],
    'INTERMEDIATE' => ['BEGINNER', 'INTERMEDIATE'],
    'ADVANCED'     => ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'],
];
// Trình độ -> số bài mỗi buổi, số set mỗi bài
const WORKOUT_EXERCISES_PER_SESSION = ['BEGINNER' => 4, 'INTERMEDIATE' => 5, 'ADVANCED' => 6];
const WORKOUT_SETS_BY_LEVEL         = ['BEGINNER' => 3, 'INTERMEDIATE' => 3, 'ADVANCED' => 4];
// Mục tiêu -> số lặp
const WORKOUT_REPS_BY_GOAL = ['LOSE_FAT' => 15, 'MAINTAIN' => 12, 'GAIN_MUSCLE' => 8];

/**
 * @param int    $daysPerWeek      2..6
 * @param string $goal             LOSE_FAT | MAINTAIN | GAIN_MUSCLE
 * @param string $level            BEGINNER | INTERMEDIATE | ADVANCED
 * @param array  $exercisesByGroup ['Ngực' => [['id'=>1,'difficulty'=>'BEGINNER'], ...], ...]
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

    $rule    = WORKOUT_SPLIT_RULES[$daysPerWeek];
    $allowed = WORKOUT_LEVEL_DIFFICULTIES[$level];
    $limit   = WORKOUT_EXERCISES_PER_SESSION[$level];
    $sets    = WORKOUT_SETS_BY_LEVEL[$level];
    $reps    = WORKOUT_REPS_BY_GOAL[$goal];

    $days = [];
    foreach ($rule['days'] as $dayIndex => $day) {
        $picked = pickExercisesForDay($day['groups'], $limit, $allowed, $exercisesByGroup, $dayIndex);

        $rows = [];
        foreach ($picked as $i => $ex) {
            $rows[] = [
                'exercise_id' => (int)$ex['id'],
                'sets'        => $sets,
                'reps'        => $reps,
                'order_index' => $i + 1,
            ];
        }
        // Thiếu bài thì buổi đó ít bài hơn (có thể rỗng), KHÔNG báo lỗi
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

/**
 * Chọn bài cho 1 buổi: lọc theo độ khó, xoay vòng giữa các nhóm cơ
 * (mỗi vòng lấy 1 bài/nhóm) cho tới khi đủ $limit hoặc hết bài.
 * $dayIndex xoay điểm bắt đầu để các buổi cùng nhóm cơ không trùng bài.
 * Không dùng random -> kết quả ổn định, dễ test.
 */
function pickExercisesForDay(array $groups, int $limit, array $allowedDifficulties, array $exercisesByGroup, int $dayIndex): array
{
    $pools = [];
    foreach ($groups as $group) {
        $pool = [];
        foreach ($exercisesByGroup[$group] ?? [] as $ex) {
            if (in_array($ex['difficulty'] ?? '', $allowedDifficulties, true)) {
                $pool[] = $ex;
            }
        }
        if (count($pool) > 0) {
            $offset = $dayIndex % count($pool);
            $pools[] = array_merge(array_slice($pool, $offset), array_slice($pool, 0, $offset));
        }
    }
    if (count($pools) === 0) {
        return [];
    }

    $picked  = [];
    $used    = [];
    $cursors = array_fill(0, count($pools), 0);

    while (count($picked) < $limit) {
        $addedThisRound = false;
        foreach ($pools as $i => $pool) {
            if (count($picked) >= $limit) {
                break;
            }
            while ($cursors[$i] < count($pool)) {
                $ex = $pool[$cursors[$i]++];
                if (!isset($used[$ex['id']])) {
                    $picked[] = $ex;
                    $used[$ex['id']] = true;
                    $addedThisRound = true;
                    break;
                }
            }
        }
        if (!$addedThisRound) {
            break; // hết bài ở mọi nhóm
        }
    }
    return $picked;
}