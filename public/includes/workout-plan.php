<?php
// public/includes/workout_plan.php
// Hàm THUẦN: không truy vấn DB, không đọc $_SESSION/$_GET. Cùng đầu vào -> cùng đầu ra.
// Lịch được chia theo các mẫu buổi tập (template): PPL, Upper/Lower, Anterior/Posterior, Toàn thân.
// Mỗi buổi ghi rõ cần bao nhiêu bài cho từng "vai trò" (ngực ngang, ngực trên, vai sau, đùi sau...).

// ===== WHITELIST ĐẦU VÀO =====
const WORKOUT_GOALS  = ['LOSE_FAT', 'MAINTAIN', 'GAIN_MUSCLE'];
const WORKOUT_LEVELS = ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'];

// Nhãn tiếng Việt để hiển thị (cùng nội dung với $muscleLabels của exercises.php)
const WORKOUT_MUSCLE_LABELS = ['CHEST' => 'Ngực', 'BACK' => 'Lưng', 'SHOULDERS' => 'Vai', 'LEGS' => 'Chân', 'ARMS' => 'Tay', 'ABS' => 'Bụng', 'FULL_BODY' => 'Toàn thân'];

// ===== VAI TRÒ CỦA TỪNG BÀI =====
// id theo sql/seed_exercises_v2.sql. Bài không có trong bảng này (VD admin thêm sau) sẽ KHÔNG được
// hàm sinh lịch chọn, cho tới khi thêm id của nó vào đây. Người dùng vẫn tự thêm bài đó vào lịch được.
const WORKOUT_EXERCISE_ROLES = [
    // Ngực
    1 => 'CHEST_FLAT', 2 => 'CHEST_FLAT', 3 => 'CHEST_FLAT', 4 => 'CHEST_INCLINE', 5 => 'CHEST_FLAT', 6 => 'CHEST_FLAT',
    // Lưng (11 nhún vai tính là cầu vai -> đi cùng vai sau; 12 siêu nhân chưa dùng trong template)
    7 => 'BACK', 8 => 'BACK', 9 => 'BACK', 10 => 'BACK', 11 => 'DELT_REAR', 12 => 'LOWER_BACK', 13 => 'HINGE',
    // Vai
    14 => 'DELT_FRONT', 15 => 'DELT_SIDE', 16 => 'DELT_REAR', 17 => 'DELT_FRONT',
    // Chân (25 bước ngang dây kháng lực là dạng háng -> xếp vào mông; thư viện CHƯA có bài khép háng và RDL)
    18 => 'SQUAT', 19 => 'SQUAT', 20 => 'SQUAT', 21 => 'LEG_EXTENSION', 22 => 'LEG_CURL', 23 => 'GLUTE', 24 => 'CALF', 25 => 'GLUTE',
    // Tay (31 cuốn cổ tay là cẳng tay, chưa dùng)
    26 => 'BICEPS', 27 => 'BICEPS', 28 => 'TRICEPS', 29 => 'TRICEPS', 30 => 'TRICEPS', 31 => 'FOREARM',
    // Bụng: thẳng bụng / liên sườn
    32 => 'ABS', 33 => 'ABS', 34 => 'ABS', 35 => 'OBLIQUE', 36 => 'OBLIQUE',
];

// Vai trò -> cơ được tính khối lượng (để kiểm tra số set/tuần). Đùi trước, đùi sau, mông... tính riêng,
// không gộp hết vào "Chân", vì một buổi chân có tới 7 bài cho 5 cơ khác nhau.
const WORKOUT_ROLE_MUSCLE = [
    'CHEST_FLAT' => 'CHEST', 'CHEST_INCLINE' => 'CHEST', 'BACK' => 'BACK', 'LOWER_BACK' => 'BACK',
    'DELT_FRONT' => 'DELT_FRONT', 'DELT_SIDE' => 'DELT_SIDE', 'DELT_REAR' => 'DELT_REAR',
    'BICEPS' => 'BICEPS', 'TRICEPS' => 'TRICEPS', 'FOREARM' => 'FOREARM',
    'SQUAT' => 'QUADS', 'LEG_EXTENSION' => 'QUADS', 'HINGE' => 'HAMSTRINGS', 'LEG_CURL' => 'HAMSTRINGS',
    'GLUTE' => 'GLUTES', 'ADDUCTOR' => 'ADDUCTORS', 'CALF' => 'CALVES', 'ABS' => 'ABS', 'OBLIQUE' => 'ABS',
];

// Nhãn hiển thị cho cơ (theo WORKOUT_ROLE_MUSCLE) và cho vai trò, dùng ở trang lịch tập
const WORKOUT_MUSCLE_DETAIL_LABELS = [
    'CHEST' => 'Ngực', 'BACK' => 'Lưng', 'DELT_FRONT' => 'Vai trước', 'DELT_SIDE' => 'Vai giữa', 'DELT_REAR' => 'Vai sau',
    'BICEPS' => 'Tay trước', 'TRICEPS' => 'Tay sau', 'FOREARM' => 'Cẳng tay', 'QUADS' => 'Đùi trước', 'HAMSTRINGS' => 'Đùi sau',
    'GLUTES' => 'Mông', 'ADDUCTORS' => 'Khép háng', 'CALVES' => 'Bắp chân', 'ABS' => 'Bụng',
];
const WORKOUT_ROLE_LABELS = [
    'CHEST_FLAT' => 'Ngực', 'CHEST_INCLINE' => 'Ngực trên', 'BACK' => 'Lưng', 'LOWER_BACK' => 'Lưng dưới',
    'DELT_FRONT' => 'Vai trước', 'DELT_SIDE' => 'Vai giữa', 'DELT_REAR' => 'Vai sau · cầu vai',
    'BICEPS' => 'Tay trước', 'TRICEPS' => 'Tay sau', 'FOREARM' => 'Cẳng tay',
    'SQUAT' => 'Đùi trước · squat', 'LEG_EXTENSION' => 'Đùi trước', 'HINGE' => 'Đùi sau · gập hông', 'LEG_CURL' => 'Đùi sau',
    'GLUTE' => 'Mông', 'ADDUCTOR' => 'Khép háng', 'CALF' => 'Bắp chân', 'ABS' => 'Bụng', 'OBLIQUE' => 'Liên sườn',
];

// ===== MẪU BUỔI TẬP =====
// Mỗi dòng: [danh sách vai trò, số bài tối thiểu, số bài tối đa]. Số bài như "1-2" thì
// người mới / trung bình lấy số nhỏ, nâng cao lấy số lớn. Nhiều vai trò trong một dòng = chọn xoay vòng trong đó.
const WORKOUT_SESSION_TEMPLATES = [
    'PUSH' => ['label' => 'Đẩy', 'slots' => [
        [['CHEST_FLAT'], 1, 2], [['CHEST_INCLINE'], 1, 1],          // 2-3 ngực
        [['DELT_FRONT'], 1, 1], [['DELT_SIDE'], 1, 1],              // 2 vai (trước, giữa)
        [['TRICEPS'], 1, 2],                                        // 1-2 tay sau
    ]],
    'PULL' => ['label' => 'Kéo', 'slots' => [
        [['BACK'], 3, 3],                                           // 3 lưng
        [['DELT_REAR'], 1, 2],                                      // 1-2 vai sau (tính cả cầu vai)
        [['BICEPS'], 1, 2],                                         // 1-2 tay trước
    ]],
    'LEGS' => ['label' => 'Chân', 'slots' => [
        [['SQUAT'], 1, 1], [['LEG_EXTENSION'], 1, 1],               // 2 đùi trước
        [['HINGE'], 1, 1], [['LEG_CURL'], 1, 1],                    // 2 đùi sau
        [['GLUTE'], 1, 2], [['ADDUCTOR'], 1, 1], [['CALF'], 1, 1],  // 1-2 mông, 1 khép háng, 1 bắp chân
    ]],
    'UPPER' => ['label' => 'Thân trên', 'slots' => [
        [['CHEST_FLAT'], 1, 1], [['CHEST_INCLINE'], 1, 1],
        [['BACK'], 2, 2],
        [['DELT_SIDE'], 1, 1], [['DELT_REAR'], 1, 1],
        [['BICEPS'], 1, 1], [['TRICEPS'], 1, 1],                    // 1 tay trước, 1 tay sau
    ]],
    'LOWER' => ['label' => 'Thân dưới', 'slots' => [
        [['SQUAT'], 1, 1], [['LEG_EXTENSION'], 1, 1],               // 1 squat, 1 leg extension
        [['HINGE'], 1, 1], [['LEG_CURL'], 1, 1],                    // 1 SLDL/RDL, 1 leg curl
        [['GLUTE'], 1, 2], [['ADDUCTOR'], 1, 1], [['CALF'], 1, 1],  // 1-2 mông, 1 khép háng, 1 calf raise
    ]],
    'ANTERIOR' => ['label' => 'Mặt trước', 'slots' => [
        [['CHEST_FLAT'], 1, 2], [['CHEST_INCLINE'], 1, 1],          // 2-3 ngực
        [['DELT_FRONT'], 1, 1], [['DELT_SIDE'], 1, 1],              // 2 vai (trước, giữa)
        [['TRICEPS'], 1, 2],                                        // 1-2 tay sau
        [['SQUAT'], 1, 1], [['LEG_EXTENSION'], 1, 1],               // 2 đùi trước
        [['ABS'], 1, 1], [['OBLIQUE'], 1, 1],                       // bụng: thẳng bụng + liên sườn
    ]],
    'POSTERIOR' => ['label' => 'Mặt sau', 'slots' => [
        [['BACK'], 3, 3],                                           // 3 lưng
        [['DELT_REAR'], 1, 1],                                      // 1 vai sau
        [['BICEPS'], 1, 2],                                         // 1-2 tay trước
        [['HINGE'], 1, 1], [['LEG_CURL'], 1, 1],                    // 2 đùi sau
        [['GLUTE'], 1, 2], [['ADDUCTOR'], 1, 1], [['CALF'], 1, 1],  // 1-2 mông, 1 khép háng, 1 bắp chân
    ]],
    'FULL_BODY' => ['label' => 'Toàn thân', 'slots' => [
        [['CHEST_FLAT', 'CHEST_INCLINE'], 1, 1],
        [['BACK'], 1, 1],
        [['DELT_FRONT', 'DELT_SIDE', 'DELT_REAR'], 1, 1],
        [['SQUAT', 'LEG_EXTENSION'], 1, 1],
        [['HINGE', 'LEG_CURL'], 1, 1],                             // đùi sau mỗi buổi (không xoay sang mông, kẻo đùi sau chỉ còn 1 buổi/tuần)
        [['BICEPS'], 1, 1], [['TRICEPS'], 1, 1],
    ]],
];

// ===== KIỂU CHIA THEO SỐ NGÀY/TUẦN =====
// Mỗi cơ được tập >= 2 buổi/tuần. Lịch 3, 4, 5 ngày có nhiều kiểu để chọn (kể cả lịch kết hợp).
const WORKOUT_SPLITS = [
    'FULL_BODY' => [
        2 => [[1, 'FULL_BODY'], [4, 'FULL_BODY']],
        3 => [[1, 'FULL_BODY'], [3, 'FULL_BODY'], [5, 'FULL_BODY']],
        4 => [[1, 'FULL_BODY'], [3, 'FULL_BODY'], [5, 'FULL_BODY'], [7, 'FULL_BODY']], // cách ngày, mỗi buổi xoay bài khác
    ],
    'UPPER_LOWER' => [
        4 => [[1, 'UPPER'], [2, 'LOWER'], [4, 'UPPER'], [5, 'LOWER']],
    ],
    'ANTERIOR_POSTERIOR' => [
        4 => [[1, 'ANTERIOR'], [2, 'POSTERIOR'], [4, 'ANTERIOR'], [5, 'POSTERIOR']],
    ],
    'PPL' => [
        6 => [[1, 'PUSH'], [2, 'PULL'], [3, 'LEGS'], [4, 'PUSH'], [5, 'PULL'], [6, 'LEGS']],
    ],
    // ----- Lịch kết hợp: hết vòng kiểu chia thứ nhất rồi chuyển sang kiểu thứ hai -----
    'PPL_UL' => [                                                     // Đẩy, Kéo, Chân rồi Trên, Dưới
        5 => [[1, 'PUSH'], [2, 'PULL'], [3, 'LEGS'], [5, 'UPPER'], [6, 'LOWER']],
    ],
    'UL_FULL_BODY' => [                                               // Trên, Dưới rồi Toàn thân
        3 => [[1, 'UPPER'], [3, 'LOWER'], [5, 'FULL_BODY']],
        5 => [[1, 'UPPER'], [2, 'LOWER'], [4, 'FULL_BODY'], [5, 'UPPER'], [6, 'LOWER']],
    ],
    'AP_FULL_BODY' => [                                               // Mặt trước, Mặt sau rồi Toàn thân
        3 => [[1, 'ANTERIOR'], [3, 'POSTERIOR'], [5, 'FULL_BODY']],
        // Không có bản 5 ngày: Mặt trước/Mặt sau x2 + Toàn thân vượt 18 set/tuần cho ngực và lưng (nâng cao)
    ],
];
// Kiểu chia mặc định khi người dùng không chọn
const WORKOUT_DEFAULT_SPLIT = [2 => 'FULL_BODY', 3 => 'FULL_BODY', 4 => 'UPPER_LOWER', 5 => 'PPL_UL', 6 => 'PPL'];
const WORKOUT_SPLIT_TYPES = ['UPPER_LOWER', 'ANTERIOR_POSTERIOR', 'FULL_BODY', 'PPL', 'PPL_UL', 'UL_FULL_BODY', 'AP_FULL_BODY']; // thứ tự hiện trên form: kiểu đầu tiên dùng được là mặc định
const WORKOUT_SPLIT_LABELS = [
    'FULL_BODY' => 'Toàn thân', 'UPPER_LOWER' => 'Trên / Dưới',
    'ANTERIOR_POSTERIOR' => 'Mặt trước / Mặt sau', 'PPL' => 'Đẩy / Kéo / Chân',
    'PPL_UL' => 'PPL + Trên / Dưới', 'UL_FULL_BODY' => 'Trên / Dưới + Toàn thân', 'AP_FULL_BODY' => 'Mặt trước / Mặt sau + Toàn thân',
];

// ===== KHỐI LƯỢNG =====
// Theo mẫu: 1-3 set mỗi bài, 6-8 lặp, nghỉ 3-4 phút giữa các set (tập nặng, ít set).
const WORKOUT_SETS_BY_LEVEL = ['BEGINNER' => 2, 'INTERMEDIATE' => 2, 'ADVANCED' => 3];
const WORKOUT_REP_RANGE     = [6, 8];
const WORKOUT_REST_MINUTES  = [3, 4];
// Số lặp lưu vào DB là mức trên của khoảng: làm được 8 lặp thì lần sau tăng tạ.
const WORKOUT_TARGET_REPS   = WORKOUT_REP_RANGE[1];
// Mặc định cho ô "Set" khi người dùng tự thêm bài
const WORKOUT_SETS_PER_EXERCISE = 2;
// Trình độ -> độ khó bài được phép chọn
const WORKOUT_LEVEL_DIFFICULTIES = [
    'BEGINNER'     => ['BEGINNER'],
    'INTERMEDIATE' => ['BEGINNER', 'INTERMEDIATE'],
    'ADVANCED'     => ['BEGINNER', 'INTERMEDIATE', 'ADVANCED'],
];

/**
 * @param int         $daysPerWeek      2..6
 * @param string      $goal             LOSE_FAT | MAINTAIN | GAIN_MUSCLE
 * @param string      $level            BEGINNER | INTERMEDIATE | ADVANCED
 * @param array       $exercisesByGroup ['CHEST' => [['id'=>1,'difficulty'=>'BEGINNER'], ...], ...]
 * @param string|null $splitType        null = mặc định theo số ngày; các kiểu dùng được xem WORKOUT_SPLITS
 * @return array ['split_type','days_per_week','days'=>[['day_of_week','groups','session','exercises'=>[...]]]]
 * @throws InvalidArgumentException khi đầu vào ngoài whitelist
 */
function generateWorkoutPlan(int $daysPerWeek, string $goal, string $level, array $exercisesByGroup, ?string $splitType = null): array
{
    if (!isset(WORKOUT_DEFAULT_SPLIT[$daysPerWeek])) {
        throw new InvalidArgumentException('Số ngày/tuần phải từ 2 đến 6.');
    }
    if (!in_array($goal, WORKOUT_GOALS, true)) {
        throw new InvalidArgumentException('Mục tiêu không hợp lệ.');
    }
    if (!in_array($level, WORKOUT_LEVELS, true)) {
        throw new InvalidArgumentException('Trình độ không hợp lệ.');
    }
    $splitType = $splitType ?? WORKOUT_DEFAULT_SPLIT[$daysPerWeek];
    if (!isset(WORKOUT_SPLITS[$splitType][$daysPerWeek])) {
        throw new InvalidArgumentException('Kiểu chia lịch này không dùng được với số ngày đã chọn.');
    }

    $sets  = WORKOUT_SETS_BY_LEVEL[$level];
    $pools = buildRolePools($exercisesByGroup, WORKOUT_LEVEL_DIFFICULTIES[$level]);

    $cursors = []; // con trỏ xoay vòng theo vai trò, giữ qua các ngày để các buổi dùng bài khác nhau
    $days = [];
    foreach (WORKOUT_SPLITS[$splitType][$daysPerWeek] as [$dayOfWeek, $sessionKey]) {
        $session = WORKOUT_SESSION_TEMPLATES[$sessionKey];
        $rows = [];
        $usedToday = [];
        foreach ($session['slots'] as [$roles, $min, $max]) {
            $count = $level === 'ADVANCED' ? $max : $min;
            $pool  = mergeRolePools($pools, $roles);
            if (empty($pool)) {
                continue; // thư viện chưa có bài cho vai trò này -> bỏ qua, không lỗi
            }
            $key = implode('|', $roles);
            $cursors[$key] = $cursors[$key] ?? 0;
            foreach (takeExercises($pool, $cursors[$key], $count, $usedToday) as $ex) {
                $usedToday[$ex['id']] = true;
                $rows[] = [
                    'exercise_id' => (int)$ex['id'],
                    'sets'        => $sets,
                    'reps'        => WORKOUT_TARGET_REPS,
                    'order_index' => count($rows) + 1,
                ];
            }
        }
        $days[] = [
            'day_of_week' => $dayOfWeek,
            'session'     => $sessionKey,
            'groups'      => [$session['label']],
            'exercises'   => $rows,
        ];
    }

    return [
        'split_type'    => $splitType,
        'days_per_week' => $daysPerWeek,
        'days'          => $days,
    ];
}

/**
 * Tên buổi theo ngày cho một lịch đã lưu, VD [1 => 'Đẩy', 2 => 'Kéo', ...] (để trang lịch tập hiển thị).
 * Lịch cũ / kiểu chia không còn trong WORKOUT_SPLITS thì trả mảng rỗng.
 */
function workoutSessionLabels(string $splitType, int $daysPerWeek): array
{
    $labels = [];
    foreach (WORKOUT_SPLITS[$splitType][$daysPerWeek] ?? [] as [$dayOfWeek, $sessionKey]) {
        $labels[$dayOfWeek] = WORKOUT_SESSION_TEMPLATES[$sessionKey]['label'];
    }
    return $labels;
}

/** Các kiểu chia dùng được với từng số ngày, VD [4 => ['UPPER_LOWER', 'ANTERIOR_POSTERIOR', 'FULL_BODY'], ...] */
function workoutSplitOptions(): array
{
    $options = [];
    foreach (WORKOUT_SPLITS as $splitType => $byDays) {
        foreach ($byDays as $days => $_) {
            $options[$days][] = $splitType;
        }
    }
    ksort($options);
    return $options;
}

/** Lọc bài theo độ khó rồi chia theo vai trò. Bài không có trong WORKOUT_EXERCISE_ROLES bị bỏ qua. */
function buildRolePools(array $exercisesByGroup, array $allowedDifficulties): array
{
    $pools = [];
    foreach ($exercisesByGroup as $list) {
        foreach ($list as $ex) {
            $role = WORKOUT_EXERCISE_ROLES[(int)$ex['id']] ?? null;
            if ($role !== null && in_array($ex['difficulty'] ?? '', $allowedDifficulties, true)) {
                $pools[$role][] = $ex;
            }
        }
    }
    return $pools;
}

/** Gộp bài của nhiều vai trò thành một danh sách, sắp theo id để kết quả ổn định. */
function mergeRolePools(array $pools, array $roles): array
{
    $merged = [];
    foreach ($roles as $role) {
        foreach ($pools[$role] ?? [] as $ex) {
            $merged[] = $ex;
        }
    }
    usort($merged, fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);
    return $merged;
}

/**
 * Lấy $count bài kế tiếp từ $pool theo con trỏ xoay vòng (không random -> ổn định, dễ test).
 * Không lấy bài đã có trong buổi ($usedToday); pool nhỏ hơn $count thì trả ít hơn.
 */
function takeExercises(array $pool, int &$cursor, int $count, array $usedToday = []): array
{
    $n = count($pool);
    $picked = [];
    for ($tries = 0; $tries < $n && count($picked) < $count; $tries++) {
        $ex = $pool[$cursor % $n];
        $cursor++;
        if (!isset($usedToday[$ex['id']])) {
            $usedToday[$ex['id']] = true;
            $picked[] = $ex;
        }
    }
    return $picked;
}
