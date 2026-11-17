<?php
// public/includes/workout_plan_db.php
// B9: phần làm việc với DB cho lịch tập. Mọi truy vấn dùng PDO prepared statement.
// Phụ thuộc: workout_plan.php (hàm generateWorkoutPlan của B8).

require_once __DIR__ . '/workout-plan.php';

/** Lấy mục tiêu của user từ bảng users (null nếu không có user hoặc chưa đặt mục tiêu). */
function fetchUserGoal(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare('SELECT goal FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $goal = $stmt->fetchColumn();
    return $goal === false || $goal === null ? null : (string)$goal;
}

/**
 * Lấy bài kháng lực và nhóm theo muscle_group, đúng định dạng B8 cần:
 * ['Ngực' => [['id'=>1,'difficulty'=>'BEGINNER'], ...], ...]
 */
function fetchExercisesGroupedByMuscle(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT id, muscle_group, difficulty FROM exercises WHERE type = ? ORDER BY id');
    $stmt->execute(['RESISTANCE']);

    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grouped[$row['muscle_group']][] = [
            'id'         => (int)$row['id'],
            'difficulty' => $row['difficulty'],
        ];
    }
    return $grouped;
}

/**
 * Lưu lịch trong MỘT transaction:
 *   1. chuyển lịch ACTIVE cũ của user sang ARCHIVED
 *   2. tạo workout_plans mới (ACTIVE)
 *   3. tạo các dòng workout_plan_exercises
 * Lỗi ở bất kỳ bước nào -> rollBack, lịch cũ giữ nguyên ACTIVE.
 * $assignedBy: id HLV giao lịch (phân hệ HLV, cột workout_plans.assigned_by); null = người dùng tự tạo.
 * Khi null thì câu INSERT giống hệt bản cũ, nên DB chưa chạy sql/coach_features.sql vẫn dùng được.
 * $status: 'ACTIVE' (mặc định) hoặc 'DRAFT' = bản nháp HLV soạn (sql/coach_extras.sql): KHÔNG lưu trữ lịch đang dùng,
 * học viên chưa thấy cho tới khi HLV giao (coachPublishDraft). Bản nháp bắt buộc có $assignedBy.
 *
 * @return int id của lịch mới
 * @throws RuntimeException nếu lịch không có bài nào (không đụng tới lịch cũ)
 */
function saveWorkoutPlan(PDO $pdo, int $userId, array $plan, ?int $assignedBy = null, string $status = 'ACTIVE'): int
{
    $total = 0;
    foreach ($plan['days'] as $day) {
        $total += count($day['exercises']);
    }
    if ($total === 0) {
        throw new RuntimeException('Chưa đủ bài tập trong thư viện để tạo lịch.');
    }

    $name = 'Lịch ' . (WORKOUT_SPLIT_LABELS[$plan['split_type']] ?? $plan['split_type']) . ' ' . $plan['days_per_week'] . ' ngày/tuần';

    if ($status !== 'ACTIVE' && ($status !== 'DRAFT' || $assignedBy === null)) {
        throw new InvalidArgumentException('Trạng thái lịch không hợp lệ.');
    }
    if ($status === 'DRAFT') {
        $name = 'Bản nháp: ' . $name;
    }

    $pdo->beginTransaction();
    try {
        if ($status === 'ACTIVE') {   // bản nháp thì giữ nguyên lịch học viên đang tập
            $archive = $pdo->prepare("UPDATE workout_plans SET status = 'ARCHIVED' WHERE user_id = ? AND status = 'ACTIVE'");
            $archive->execute([$userId]);
        }

        if ($assignedBy === null) {
            $insertPlan = $pdo->prepare(
                "INSERT INTO workout_plans (user_id, name, split_type, days_per_week, status) VALUES (?, ?, ?, ?, 'ACTIVE')"
            );
            $insertPlan->execute([$userId, $name, $plan['split_type'], $plan['days_per_week']]);
        } else {
            $insertPlan = $pdo->prepare(
                "INSERT INTO workout_plans (user_id, name, split_type, days_per_week, status, assigned_by) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insertPlan->execute([$userId, $name, $plan['split_type'], $plan['days_per_week'], $status, $assignedBy]);
        }
        $planId = (int)$pdo->lastInsertId();

        $insertRow = $pdo->prepare(
            'INSERT INTO workout_plan_exercises (workout_plan_id, exercise_id, day_of_week, sets, reps, order_index)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($plan['days'] as $day) {
            foreach ($day['exercises'] as $ex) {
                $insertRow->execute([
                    $planId, $ex['exercise_id'], $day['day_of_week'],
                    $ex['sets'], $ex['reps'], $ex['order_index'],
                ]);
            }
        }

        $pdo->commit();
        return $planId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Luồng đầy đủ: lấy goal + bài tập từ DB -> B8 sinh lịch -> lưu.
 * $userId PHẢI lấy từ $_SESSION ở nơi gọi, không nhận từ form/URL
 * (riêng HLV giao lịch: $userId là học viên đã qua requireCoachClient(), $assignedBy là HLV).
 */
function createWorkoutPlanForUser(PDO $pdo, int $userId, int $daysPerWeek, string $level, ?string $splitType = null, ?int $assignedBy = null): int
{
    $goal = fetchUserGoal($pdo, $userId);
    if ($goal === null) {
        throw new InvalidArgumentException('Bạn chưa chọn mục tiêu trong hồ sơ.');
    }
    $exercises = fetchExercisesGroupedByMuscle($pdo);
    $plan = generateWorkoutPlan($daysPerWeek, $goal, $level, $exercises, $splitType); // ném InvalidArgumentException nếu sai đầu vào
    return saveWorkoutPlan($pdo, $userId, $plan, $assignedBy);
}

/**
 * B10: lấy lịch ACTIVE của CHÍNH user này (user_id nằm trong WHERE nên không xem được lịch người khác).
 * Trả về null nếu chưa có lịch, ngược lại:
 * ['id','name','split_type','days_per_week','days'=>[ day_of_week => [ ['row_id','exercise_id','name','muscle_group','sets','reps'], ... ] ]]
 */
function fetchActivePlan(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, name, split_type, days_per_week FROM workout_plans
         WHERE user_id = ? AND status = 'ACTIVE' ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$userId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($plan === false) {
        return null;
    }

    $rowsStmt = $pdo->prepare(
        'SELECT wpe.id AS row_id, wpe.day_of_week, wpe.sets, wpe.reps, e.id AS exercise_id, e.name, e.muscle_group
         FROM workout_plan_exercises wpe
         JOIN exercises e ON e.id = wpe.exercise_id
         WHERE wpe.workout_plan_id = ?
         ORDER BY wpe.day_of_week, wpe.order_index'
    );
    $rowsStmt->execute([$plan['id']]);

    $days = [];
    foreach ($rowsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $days[(int)$row['day_of_week']][] = [
            'row_id'       => (int)$row['row_id'],
            'exercise_id'  => (int)$row['exercise_id'],
            'name'         => $row['name'],
            'muscle_group' => $row['muscle_group'],
            'sets'         => (int)$row['sets'],
            'reps'         => (int)$row['reps'],
        ];
    }
    $plan['days'] = $days;
    return $plan;
}


// =====================================================================
// B11: chỉnh sửa lịch ACTIVE của chính user (thêm / xóa / đổi bài / sửa set-lặp)
// Mọi hàm đều kiểm tra dòng thuộc lịch ACTIVE của đúng user_id (lấy từ session ở nơi gọi).
// =====================================================================

const PLAN_EDIT_MAX_SETS = 10;
const PLAN_EDIT_MAX_REPS = 50;

/** Không tìm thấy dòng/lịch, hoặc dòng không thuộc user (không phân biệt để khỏi lộ thông tin). */
class PlanNotFoundException extends RuntimeException
{
}

/** Danh sách bài kháng lực để người dùng chọn khi đổi/thêm bài. */
function fetchExerciseLibrary(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT id, name, muscle_group, difficulty FROM exercises WHERE type = ? ORDER BY name');
    $stmt->execute(['RESISTANCE']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function assertValidSetsReps(int $sets, int $reps): void
{
    if ($sets < 1 || $sets > PLAN_EDIT_MAX_SETS) {
        throw new InvalidArgumentException('Số set phải từ 1 đến ' . PLAN_EDIT_MAX_SETS . '.');
    }
    if ($reps < 1 || $reps > PLAN_EDIT_MAX_REPS) {
        throw new InvalidArgumentException('Số lặp phải từ 1 đến ' . PLAN_EDIT_MAX_REPS . '.');
    }
}

function assertResistanceExerciseExists(PDO $pdo, int $exerciseId): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM exercises WHERE id = ? AND type = ?');
    $stmt->execute([$exerciseId, 'RESISTANCE']);
    if ((int)$stmt->fetchColumn() === 0) {
        throw new InvalidArgumentException('Bài tập không tồn tại.');
    }
}

/** Lấy dòng workout_plan_exercises nếu thuộc lịch ACTIVE của user; ngược lại ném PlanNotFoundException. */
function findOwnedPlanRow(PDO $pdo, int $userId, int $rowId): array
{
    $stmt = $pdo->prepare(
        "SELECT wpe.id, wpe.workout_plan_id, wpe.day_of_week, wpe.exercise_id
         FROM workout_plan_exercises wpe
         JOIN workout_plans wp ON wp.id = wpe.workout_plan_id
         WHERE wpe.id = ? AND wp.user_id = ? AND wp.status = 'ACTIVE'"
    );
    $stmt->execute([$rowId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        throw new PlanNotFoundException('Không tìm thấy bài này trong lịch của bạn.');
    }
    return $row;
}

/** Bài này đã có trong cùng ngày của cùng lịch chưa (bỏ qua dòng $exceptRowId). */
function planDayHasExercise(PDO $pdo, int $planId, int $day, int $exerciseId, int $exceptRowId = 0): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM workout_plan_exercises
         WHERE workout_plan_id = ? AND day_of_week = ? AND exercise_id = ? AND id <> ?'
    );
    $stmt->execute([$planId, $day, $exerciseId, $exceptRowId]);
    return (int)$stmt->fetchColumn() > 0;
}

function updatePlanExerciseVolume(PDO $pdo, int $userId, int $rowId, int $sets, int $reps): void
{
    assertValidSetsReps($sets, $reps);
    findOwnedPlanRow($pdo, $userId, $rowId);
    $stmt = $pdo->prepare('UPDATE workout_plan_exercises SET sets = ?, reps = ? WHERE id = ?');
    $stmt->execute([$sets, $reps, $rowId]);
}

function replacePlanExercise(PDO $pdo, int $userId, int $rowId, int $newExerciseId): void
{
    assertResistanceExerciseExists($pdo, $newExerciseId);
    $row = findOwnedPlanRow($pdo, $userId, $rowId);
    if (planDayHasExercise($pdo, (int)$row['workout_plan_id'], (int)$row['day_of_week'], $newExerciseId, $rowId)) {
        throw new RuntimeException('Bài này đã có trong ngày đó.');
    }
    $stmt = $pdo->prepare('UPDATE workout_plan_exercises SET exercise_id = ? WHERE id = ?');
    $stmt->execute([$newExerciseId, $rowId]);
}

function removePlanExercise(PDO $pdo, int $userId, int $rowId): void
{
    findOwnedPlanRow($pdo, $userId, $rowId);
    $stmt = $pdo->prepare('DELETE FROM workout_plan_exercises WHERE id = ?');
    $stmt->execute([$rowId]);
}

/** Thêm bài vào một ngày (1-7) của lịch ACTIVE; xếp cuối ngày đó. Trả về id dòng mới. */
function addPlanExercise(PDO $pdo, int $userId, int $dayOfWeek, int $exerciseId, int $sets, int $reps): int
{
    if ($dayOfWeek < 1 || $dayOfWeek > 7) {
        throw new InvalidArgumentException('Ngày trong tuần không hợp lệ.');
    }
    assertValidSetsReps($sets, $reps);
    assertResistanceExerciseExists($pdo, $exerciseId);

    $planStmt = $pdo->prepare("SELECT id FROM workout_plans WHERE user_id = ? AND status = 'ACTIVE' ORDER BY id DESC LIMIT 1");
    $planStmt->execute([$userId]);
    $planId = $planStmt->fetchColumn();
    if ($planId === false) {
        throw new PlanNotFoundException('Bạn chưa có lịch tập.');
    }
    $planId = (int)$planId;

    if (planDayHasExercise($pdo, $planId, $dayOfWeek, $exerciseId)) {
        throw new RuntimeException('Bài này đã có trong ngày đó.');
    }

    $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(order_index), 0) FROM workout_plan_exercises WHERE workout_plan_id = ? AND day_of_week = ?');
    $maxStmt->execute([$planId, $dayOfWeek]);
    $nextOrder = (int)$maxStmt->fetchColumn() + 1;

    $insert = $pdo->prepare(
        'INSERT INTO workout_plan_exercises (workout_plan_id, exercise_id, day_of_week, sets, reps, order_index) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$planId, $exerciseId, $dayOfWeek, $sets, $reps, $nextOrder]);
    return (int)$pdo->lastInsertId();
}
/** Lấy kết quả tập gần nhất của bài tập này để làm mốc Progressive Overload */
function fetchLastWorkoutLog(PDO $pdo, int $userId, int $exerciseId): ?array {
    $stmt = $pdo->prepare(
        "SELECT weight_kg_used, sets_completed, reps_completed, log_date 
         FROM workout_logs 
         WHERE user_id = ? AND exercise_id = ? 
         ORDER BY log_date DESC, id DESC LIMIT 1"
    );
    $stmt->execute([$userId, $exerciseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Lưu kết quả tập thực tế vào DB.
 * $planExerciseId > 0: dòng đó phải thuộc lịch ACTIVE của chính user (chống sửa row_id để ghi vào lịch người khác),
 * và exercise_id lấy luôn từ dòng đó thay vì tin giá trị client gửi. $planExerciseId = 0: tập ngoài lịch, lưu NULL.
 */
function saveWorkoutLog(PDO $pdo, int $userId, int $planExerciseId, int $exerciseId, int $sets, int $reps, float $weight, string $note = ''): int {
    assertValidSetsReps($sets, $reps);
    if (!is_finite($weight) || $weight < 0 || $weight > 500) {
        throw new InvalidArgumentException('Mức tạ phải từ 0 đến 500 kg.');
    }
    if ($planExerciseId > 0) {
        $row = findOwnedPlanRow($pdo, $userId, $planExerciseId);
        $exerciseId = (int)$row['exercise_id'];
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM exercises WHERE id = ?');
        $stmt->execute([$exerciseId]);
        if ((int)$stmt->fetchColumn() === 0) {
            throw new InvalidArgumentException('Bài tập không tồn tại.');
        }
    }
    $stmt = $pdo->prepare(
        "INSERT INTO workout_logs (user_id, workout_plan_exercise_id, exercise_id, log_date, sets_completed, reps_completed, weight_kg_used, note, created_at) 
         VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$userId, $planExerciseId > 0 ? $planExerciseId : null, $exerciseId, $sets, $reps, $weight, $note]);
    return (int)$pdo->lastInsertId();
}