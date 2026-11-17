<?php
// Test B9 bằng SQLite in-memory (không cần MySQL). Chạy: php test_workout_plan_db.php
require __DIR__ . '/public/includes/workout-plan-db.php';

$fails = 0;
function check(string $name, bool $ok): void {
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $fails++;
}

function makeDb(): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, goal TEXT)');
    $pdo->exec('CREATE TABLE exercises (id INTEGER PRIMARY KEY, name TEXT, muscle_group TEXT, type TEXT, difficulty TEXT)');
    $pdo->exec('CREATE TABLE workout_plans (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, name TEXT, split_type TEXT, days_per_week INTEGER, status TEXT)');
    $pdo->exec('CREATE TABLE workout_plan_exercises (id INTEGER PRIMARY KEY AUTOINCREMENT, workout_plan_id INTEGER, exercise_id INTEGER NOT NULL, day_of_week INTEGER, sets INTEGER, reps INTEGER, order_index INTEGER)');
    $pdo->exec("INSERT INTO users (id, goal) VALUES (1,'GAIN_MUSCLE'), (2,'LOSE_FAT'), (3,NULL)");
    // 40 bài thật của seed_exercises_v2.sql (id 37-39 là cardio)
    $insert = $pdo->prepare('INSERT INTO exercises (id, name, muscle_group, type, difficulty) VALUES (?, ?, ?, ?, ?)');
    foreach (require __DIR__ . '/test-exercises-fixture.php' as $ex) {
        $insert->execute([$ex['id'], $ex['name'], $ex['muscle_group'], $ex['type'], $ex['difficulty']]);
    }
    return $pdo;
}
function count1(PDO $pdo, string $sql): int { return (int)$pdo->query($sql)->fetchColumn(); }

$pdo = makeDb();

// 1. Tạo lịch lần đầu
$p1 = createWorkoutPlanForUser($pdo, 1, 3, 'BEGINNER');
check('Tạo lịch lần 1: trả về id', $p1 > 0);
check('Có đúng 1 lịch ACTIVE', count1($pdo, "SELECT COUNT(*) FROM workout_plans WHERE user_id=1 AND status='ACTIVE'") === 1);
check('Có dòng bài tập cho lịch', count1($pdo, "SELECT COUNT(*) FROM workout_plan_exercises WHERE workout_plan_id=$p1") > 0);
check('Không lấy bài CARDIO', count1($pdo, "SELECT COUNT(*) FROM workout_plan_exercises WHERE exercise_id IN (37, 38, 39)") === 0);

// 2. Tạo lịch lần 2: lịch cũ -> ARCHIVED, vẫn chỉ 1 ACTIVE
$p2 = createWorkoutPlanForUser($pdo, 1, 4, 'INTERMEDIATE');
check('Lần 2: vẫn đúng 1 ACTIVE', count1($pdo, "SELECT COUNT(*) FROM workout_plans WHERE user_id=1 AND status='ACTIVE'") === 1);
check('Lần 2: lịch cũ thành ARCHIVED', count1($pdo, "SELECT COUNT(*) FROM workout_plans WHERE id=$p1 AND status='ARCHIVED'") === 1);
check('Lần 2: lịch mới là ACTIVE', count1($pdo, "SELECT COUNT(*) FROM workout_plans WHERE id=$p2 AND status='ACTIVE'") === 1);

// 3. Không ảnh hưởng lịch của user khác
createWorkoutPlanForUser($pdo, 2, 3, 'BEGINNER');
check('User 1 vẫn còn 1 ACTIVE sau khi user 2 tạo', count1($pdo, "SELECT COUNT(*) FROM workout_plans WHERE user_id=1 AND status='ACTIVE'") === 1);

// 4. ROLLBACK: ép lỗi giữa chừng (exercise_id NULL vi phạm NOT NULL) -> lịch cũ phải còn ACTIVE
$bad = ['split_type' => 'FULL_BODY', 'days_per_week' => 3, 'days' => [
    ['day_of_week' => 1, 'groups' => [], 'exercises' => [['exercise_id' => null, 'sets' => 3, 'reps' => 10, 'order_index' => 1]]],
]];
$activeBefore = count1($pdo, "SELECT COUNT(*) FROM workout_plans WHERE user_id=1 AND status='ACTIVE'");
$plansBefore  = count1($pdo, 'SELECT COUNT(*) FROM workout_plans');
$thrown = false;
try { saveWorkoutPlan($pdo, 1, $bad); } catch (Throwable $e) { $thrown = true; }
check('Lỗi giữa chừng: có ném exception', $thrown);
check('Rollback: lịch cũ vẫn ACTIVE', count1($pdo, "SELECT COUNT(*) FROM workout_plans WHERE user_id=1 AND status='ACTIVE'") === $activeBefore);
check('Rollback: không để lại lịch dở dang', count1($pdo, 'SELECT COUNT(*) FROM workout_plans') === $plansBefore);
check('Rollback: transaction đã đóng', !$pdo->inTransaction());

// 5. Thư viện rỗng: không lưu, không archive lịch cũ
$empty = makeDb();
$empty->exec('DELETE FROM exercises');
$empty->exec("INSERT INTO workout_plans (user_id,name,split_type,days_per_week,status) VALUES (1,'cũ','PPL',6,'ACTIVE')");
$thrown = false;
try { createWorkoutPlanForUser($empty, 1, 3, 'BEGINNER'); } catch (RuntimeException $e) { $thrown = true; }
check('Thư viện rỗng: ném RuntimeException', $thrown);
check('Thư viện rỗng: lịch cũ không bị archive', count1($empty, "SELECT COUNT(*) FROM workout_plans WHERE status='ACTIVE'") === 1);

// 6. Đầu vào sai / user chưa có mục tiêu
foreach ([[1, 9, 'BEGINNER'], [1, 3, 'PRO'], [3, 3, 'BEGINNER'], [999, 3, 'BEGINNER']] as $c) {
    $thrown = false;
    try { createWorkoutPlanForUser($pdo, $c[0], $c[1], $c[2]); } catch (InvalidArgumentException $e) { $thrown = true; }
    check('Từ chối: ' . json_encode($c), $thrown);
}

// 7. B10: fetchActivePlan chỉ trả lịch của đúng user
$mine = fetchActivePlan($pdo, 1);
check('fetchActivePlan: có lịch của user 1', $mine !== null && $mine['id'] === $p2);
check('fetchActivePlan: nhóm theo ngày, có tên bài', !empty($mine['days']) && isset(current($mine['days'])[0]['name']));
$other = fetchActivePlan($pdo, 2);
check('fetchActivePlan: user 2 không thấy lịch của user 1', $other !== null && $other['id'] !== $p2);
check('fetchActivePlan: user chưa có lịch -> null', fetchActivePlan($pdo, 3) === null);

// 8. B11: chỉnh sửa lịch
function expectThrow(callable $fn, string $class): bool {
    try { $fn(); } catch (Throwable $e) { return $e instanceof $class; }
    return false;
}
$edit = makeDb();
$planA = createWorkoutPlanForUser($edit, 1, 3, 'BEGINNER');           // lịch của user 1
$planB = createWorkoutPlanForUser($edit, 2, 3, 'BEGINNER');           // lịch của user 2
$rowA = (int)$edit->query("SELECT id FROM workout_plan_exercises WHERE workout_plan_id=$planA ORDER BY id LIMIT 1")->fetchColumn();
$rowB = (int)$edit->query("SELECT id FROM workout_plan_exercises WHERE workout_plan_id=$planB ORDER BY id LIMIT 1")->fetchColumn();

// 8a. sửa set/lặp
updatePlanExerciseVolume($edit, 1, $rowA, 4, 8);
$r = $edit->query("SELECT sets, reps FROM workout_plan_exercises WHERE id=$rowA")->fetch(PDO::FETCH_ASSOC);
check('Sửa set/lặp thành công', (int)$r['sets'] === 4 && (int)$r['reps'] === 8);
check('Từ chối set = 0', expectThrow(fn() => updatePlanExerciseVolume($edit, 1, $rowA, 0, 8), InvalidArgumentException::class));
check('Từ chối lặp quá lớn', expectThrow(fn() => updatePlanExerciseVolume($edit, 1, $rowA, 3, 999), InvalidArgumentException::class));

// 8b. KHÔNG sửa/xóa được dòng của người khác
check('User 1 không sửa được dòng của user 2', expectThrow(fn() => updatePlanExerciseVolume($edit, 1, $rowB, 5, 5), PlanNotFoundException::class));
check('User 1 không xóa được dòng của user 2', expectThrow(fn() => removePlanExercise($edit, 1, $rowB), PlanNotFoundException::class));
check('User 1 không đổi được bài của user 2', expectThrow(fn() => replacePlanExercise($edit, 1, $rowB, 1), PlanNotFoundException::class));
check('Dòng của user 2 vẫn còn nguyên', count1($edit, "SELECT COUNT(*) FROM workout_plan_exercises WHERE id=$rowB") === 1);

// 8c. đổi bài
$cur = (int)$edit->query("SELECT exercise_id FROM workout_plan_exercises WHERE id=$rowA")->fetchColumn();
$day = (int)$edit->query("SELECT day_of_week FROM workout_plan_exercises WHERE id=$rowA")->fetchColumn();
$taken = array_map('intval', $edit->query("SELECT exercise_id FROM workout_plan_exercises WHERE workout_plan_id=$planA AND day_of_week=$day")->fetchAll(PDO::FETCH_COLUMN));
$free = (int)$edit->query('SELECT id FROM exercises WHERE type=\'RESISTANCE\' AND id NOT IN (' . implode(',', $taken) . ') ORDER BY id LIMIT 1')->fetchColumn();
replacePlanExercise($edit, 1, $rowA, $free);
check('Đổi bài thành công', count1($edit, "SELECT exercise_id FROM workout_plan_exercises WHERE id=$rowA") === $free);
check('Từ chối đổi sang bài cardio', expectThrow(fn() => replacePlanExercise($edit, 1, $rowA, 37), InvalidArgumentException::class));
check('Từ chối đổi sang bài không tồn tại', expectThrow(fn() => replacePlanExercise($edit, 1, $rowA, 99999), InvalidArgumentException::class));
$otherInDay = (int)$edit->query("SELECT exercise_id FROM workout_plan_exercises WHERE workout_plan_id=$planA AND day_of_week=$day AND id<>$rowA LIMIT 1")->fetchColumn();
check('Từ chối đổi thành bài đã có trong ngày', expectThrow(fn() => replacePlanExercise($edit, 1, $rowA, $otherInDay), RuntimeException::class));

// 8d. thêm bài (kể cả vào ngày chưa có bài)
$freeAdd = (int)$edit->query("SELECT id FROM exercises WHERE type='RESISTANCE' ORDER BY id DESC LIMIT 1")->fetchColumn();
$newId = addPlanExercise($edit, 1, 7, $freeAdd, 3, 10);
check('Thêm bài vào ngày trống: có id', $newId > 0);
check('Bài mới có order_index = 1 ở ngày trống', count1($edit, "SELECT order_index FROM workout_plan_exercises WHERE id=$newId") === 1);
$freeAdd2 = (int)$edit->query("SELECT id FROM exercises WHERE type='RESISTANCE' AND id <> $freeAdd ORDER BY id DESC LIMIT 1")->fetchColumn();
$newId2 = addPlanExercise($edit, 1, 7, $freeAdd2, 3, 10);
check('Bài thứ hai cùng ngày có order_index = 2', count1($edit, "SELECT order_index FROM workout_plan_exercises WHERE id=$newId2") === 2);
check('Từ chối thêm bài trùng trong ngày', expectThrow(fn() => addPlanExercise($edit, 1, 7, $freeAdd, 3, 10), RuntimeException::class));
check('Từ chối ngày = 8', expectThrow(fn() => addPlanExercise($edit, 1, 8, $freeAdd, 3, 10), InvalidArgumentException::class));
check('Từ chối thêm khi chưa có lịch', expectThrow(fn() => addPlanExercise($edit, 3, 1, $freeAdd, 3, 10), PlanNotFoundException::class));

// 8e. xóa bài
removePlanExercise($edit, 1, $newId2);
check('Xóa bài thành công', count1($edit, "SELECT COUNT(*) FROM workout_plan_exercises WHERE id=$newId2") === 0);
check('Xóa dòng không tồn tại -> PlanNotFoundException', expectThrow(fn() => removePlanExercise($edit, 1, 99999), PlanNotFoundException::class));

// 8f. lịch đã ARCHIVED thì không sửa được
createWorkoutPlanForUser($edit, 1, 4, 'BEGINNER');                    // lịch cũ ($planA) thành ARCHIVED
check('Dòng của lịch ARCHIVED không sửa được', expectThrow(fn() => updatePlanExerciseVolume($edit, 1, $rowA, 3, 10), PlanNotFoundException::class));

// 8g. fetchActivePlan trả row_id; thư viện bài
$act = fetchActivePlan($edit, 1);
check('fetchActivePlan có row_id', isset(current($act['days'])[0]['row_id']));
check('fetchExerciseLibrary chỉ trả bài kháng lực', count(fetchExerciseLibrary($edit)) === 37); // 40 bài - 3 cardio

// 9. Phân hệ HLV: HLV giao lịch thì ghi assigned_by (cột thêm bởi sql/coach_features.sql); tự tạo thì không đụng cột này
$coachDb = makeDb();
$coachDb->exec('ALTER TABLE workout_plans ADD COLUMN assigned_by INTEGER');
$pSelf  = createWorkoutPlanForUser($coachDb, 1, 3, 'BEGINNER');
$pCoach = createWorkoutPlanForUser($coachDb, 1, 4, 'BEGINNER', null, 77);
check('Tự tạo lịch: assigned_by = NULL', $coachDb->query("SELECT assigned_by FROM workout_plans WHERE id = $pSelf")->fetchColumn() === null);
check('HLV giao lịch: assigned_by = id HLV', (int)$coachDb->query("SELECT assigned_by FROM workout_plans WHERE id = $pCoach")->fetchColumn() === 77);
check('HLV giao lịch: lịch cũ của học viên thành ARCHIVED', count1($coachDb, "SELECT COUNT(*) FROM workout_plans WHERE id = $pSelf AND status = 'ARCHIVED'") === 1);

echo PHP_EOL . ($fails === 0 ? 'TẤT CẢ ĐỀU PASS' : "$fails TEST FAIL") . PHP_EOL;
exit($fails === 0 ? 0 : 1);