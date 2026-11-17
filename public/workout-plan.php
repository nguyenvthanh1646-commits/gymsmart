<?php
// public/workout-plan.php (B10 & B11) - trang Lịch tập: tạo lịch, xem theo tuần, ghi log, chỉnh sửa.
// Giao diện: css/workout-plan.css (class bắt đầu bằng "wp-"). Hành vi: js/workout-plan.js.
require_once __DIR__ . '/includes/auth.php'; // tự bật session với cookie an toàn
require_once __DIR__ . '/includes/csrf.php';
requireLogin();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/workout-plan-db.php';
require_once __DIR__ . '/includes/coach.php';

$userId = (int)$_SESSION['user_id'];
$goal   = fetchUserGoal($pdo, $userId);
$plan   = fetchActivePlan($pdo, $userId);
// HLV: đang có HLV thì khóa Tạo lịch mới / Chỉnh sửa (API cũng chặn ở máy chủ); ghi log tập vẫn bình thường
$planLock = coachSelfEditState($pdo, $userId, 'PLAN');
$planBy   = coachPlanAssigner($pdo, $userId);
$canEdit  = !$planLock['locked'];

$libraryByGroup = [];
if ($plan !== null) {
    foreach (fetchExerciseLibrary($pdo) as $lib) {
        $libraryByGroup[$lib['muscle_group']][] = $lib;
    }
}

$dayNames    = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];
$dayShort    = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];
$goalLabels  = ['LOSE_FAT' => 'Giảm mỡ', 'MAINTAIN' => 'Duy trì', 'GAIN_MUSCLE' => 'Tăng cơ'];
$levelLabels = ['BEGINNER' => 'Người mới', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Nâng cao'];
$splitOptions = workoutSplitOptions(); // [số ngày => [kiểu chia...]] -> JS đánh dấu kiểu hợp với số buổi
// Ô "Kiểu chia": kiểu cơ bản và kiểu kết hợp (hết vòng kiểu này rồi sang kiểu kia)
$splitGroups = [
    'Kiểu cơ bản'   => ['FULL_BODY', 'UPPER_LOWER', 'ANTERIOR_POSTERIOR', 'PPL'],
    'Kiểu kết hợp'  => ['PPL_UL', 'UL_FULL_BODY', 'AP_FULL_BODY'],
];

// Thời lượng ước tính một set: ~45 giây tập + thời gian nghỉ trung bình
$minutesPerSet = 0.75 + array_sum(WORKOUT_REST_MINUTES) / 2;

// Chuỗi hiển thị số lặp: lịch sinh tự động lưu mức trên (8) -> hiện cả khoảng "6-8"
function repsText(int $reps): string
{
    return $reps === WORKOUT_TARGET_REPS ? WORKOUT_REP_RANGE[0] . '-' . WORKOUT_REP_RANGE[1] : (string)$reps;
}

// Thống kê tuần
$totalExercises  = 0;
$totalSets       = 0;
$setsByMuscle    = [];   // theo cơ chi tiết (Đùi trước, Đùi sau...), không gộp chung "Chân"
$daysHaveWorkout = array_fill(1, 7, 0);
$sessionLabels   = [];
$firstActiveDay  = 1;

if ($plan !== null) {
    $sessionLabels = workoutSessionLabels($plan['split_type'], (int)$plan['days_per_week']);
    foreach ($plan['days'] as $dow => $rows) {
        $daysHaveWorkout[$dow] = count($rows);
        $totalExercises += count($rows);
        foreach ($rows as $row) {
            $totalSets += $row['sets'];
            $role   = WORKOUT_EXERCISE_ROLES[$row['exercise_id']] ?? null;
            $muscle = $role !== null ? WORKOUT_ROLE_MUSCLE[$role] : $row['muscle_group'];
            $setsByMuscle[$muscle] = ($setsByMuscle[$muscle] ?? 0) + $row['sets'];
        }
    }
    for ($i = 1; $i <= 7; $i++) {
        if ($daysHaveWorkout[$i] > 0) { $firstActiveDay = $i; break; }
    }
}
$trainingDays = count(array_filter($daysHaveWorkout));
$volumeMax    = 18;  // thanh khối lượng đầy ở 18 set/tuần
$volumeTarget = 10;  // mốc tham chiếu ~10 set/cơ/tuần

$pageTitle = 'Lịch tập của bạn';
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/workout-plan.css">
<link rel="stylesheet" href="/css/coach.css">

<div class="wp workout-plan" id="workout-plan-root" data-csrf="<?= htmlspecialchars(csrfToken()) ?>">

    <header class="wp-head">
        <div>
            <p class="wp-eyebrow">Lịch tập</p>
            <h1 class="wp-title">
                <?php if ($plan !== null): ?>
                    <?= htmlspecialchars(WORKOUT_SPLIT_LABELS[$plan['split_type']] ?? $plan['split_type']) ?>
                <?php else: ?>
                    Tạo lịch tập đầu tiên
                <?php endif; ?>
            </h1>
            <?php if ($plan !== null): ?>
                <p class="wp-sub"><?= (int)$plan['days_per_week'] ?> buổi mỗi tuần · Mục tiêu: <?= $goal !== null ? htmlspecialchars($goalLabels[$goal] ?? $goal) : 'chưa đặt' ?></p>
            <?php endif; ?>
        </div>
        <?php if ($plan !== null): ?>
            <div class="wp-head__actions">
                <?php if ($canEdit): ?><button type="button" class="btn btn--ghost" id="workout-plan-toggle-create" aria-expanded="false" aria-controls="create-plan-section">Tạo lịch mới</button><?php endif; ?>
                <a href="api/export-workout-plan.php" class="btn btn--ghost">Xuất lịch</a>
                <?php if ($canEdit): ?><button type="button" class="btn" id="workout-plan-toggle-edit">Chỉnh sửa lịch</button><?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($planLock['coach_id'] !== null): ?>
        <!-- Nhãn "Do HLV ... giao" và trạng thái khóa (UI_UX_PROMPT.md mục 7, khu HLV) -->
        <div class="ch-lock<?= $canEdit ? ' is-open' : '' ?>" role="note">
            <div class="ch-lock__txt">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0<?= $canEdit ? '' : 'v3' ?>"/></svg>
                <div>
                    <b><?php if ($plan !== null && $planBy !== null): ?>Do HLV <?= htmlspecialchars($planBy['full_name']) ?> giao<?php elseif ($plan !== null): ?>HLV <?= htmlspecialchars((string)$planLock['coach_name']) ?> đang quản lý lịch tập của bạn<?php else: ?>HLV <?= htmlspecialchars((string)$planLock['coach_name']) ?> chưa giao lịch tập<?php endif; ?></b>
                    <span><?php if ($canEdit): ?>HLV cho phép bạn tự sửa tới <?= htmlspecialchars(date('H:i d/m', strtotime((string)$planLock['unlock_until']))) ?>.<?php elseif ($planLock['pending']): ?>Bạn đã xin sửa lịch, đang chờ HLV trả lời. Vẫn ghi log buổi tập bình thường.<?php else: ?>Bạn vẫn ghi log từng buổi. Muốn đổi bài, set hay ngày tập thì gửi yêu cầu cho HLV.<?php endif; ?></span>
                </div>
            </div>
            <?php if (!$canEdit && !$planLock['pending']): ?>
                <button type="button" class="btn btn--ghost" data-toggle="wp-change">Xin HLV cho sửa lịch</button>
                <form id="wp-change" class="ch-req" method="post" action="/my-coach.php" hidden>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change">
                    <input type="hidden" name="area" value="PLAN">
                    <label class="ch-label" for="wp-reason">Lý do</label>
                    <textarea id="wp-reason" name="reason" maxlength="<?= COACH_NOTE_MAX ?>" required placeholder="Ví dụ: tuần này đau vai, muốn bỏ bài đẩy qua đầu"></textarea>
                    <div class="ch-req__row"><button type="submit" class="btn" data-busy-text="Đang gửi…">Gửi yêu cầu</button></div>
                </form>
            <?php else: ?>
                <a class="btn btn--ghost" href="/my-coach.php">HLV của tôi</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- FORM TẠO LỊCH: hiện sẵn khi chưa có lịch, ẩn khi đã có (bấm "Tạo lịch mới" để mở) -->
    <?php if ($canEdit): ?>
    <section class="wp-card wp-create" id="create-plan-section" <?= $plan !== null ? 'hidden' : '' ?>>
        <h2 class="wp-card__title">Gợi ý lịch tập cho bạn</h2>
        <p class="wp-card__sub">Chọn số buổi và trình độ, hệ thống chia buổi theo mẫu và chọn bài phù hợp.</p>

        <form id="workout-plan-form" class="wp-create__form" data-has-plan="<?= $plan ? '1' : '0' ?>"
              data-split-options="<?= htmlspecialchars(json_encode($splitOptions)) ?>"
              data-split-default="<?= htmlspecialchars(json_encode(WORKOUT_DEFAULT_SPLIT)) ?>">
            <fieldset class="wp-field">
                <legend class="wp-field__label">Số buổi mỗi tuần</legend>
                <div class="wp-seg">
                    <?php foreach (array_keys($splitOptions) as $d): ?>
                        <label class="wp-seg__item">
                            <input type="radio" name="days_per_week" value="<?= (int)$d ?>" <?= $d === 4 ? 'checked' : '' ?>>
                            <span><?= (int)$d ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="wp-field">
                <legend class="wp-field__label">Trình độ</legend>
                <div class="wp-seg">
                    <?php foreach (WORKOUT_LEVELS as $lv): ?>
                        <label class="wp-seg__item">
                            <input type="radio" name="level" value="<?= htmlspecialchars($lv) ?>" <?= $lv === 'BEGINNER' ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($levelLabels[$lv] ?? $lv) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="wp-field" id="wp-split-field">
                <legend class="wp-field__label">Kiểu chia</legend>
                <?php foreach ($splitGroups as $groupLabel => $types): ?>
                    <p class="wp-field__group"><?= htmlspecialchars($groupLabel) ?></p>
                    <div class="wp-seg">
                        <?php foreach ($types as $type): ?>
                            <label class="wp-seg__item wp-seg__item--split" data-split="<?= htmlspecialchars($type) ?>"
                                   data-days="<?= htmlspecialchars(implode(',', array_keys(WORKOUT_SPLITS[$type]))) ?>">
                                <input type="radio" name="split_type" value="<?= htmlspecialchars($type) ?>">
                                <span>
                                    <?= htmlspecialchars(WORKOUT_SPLIT_LABELS[$type]) ?>
                                    <small><?= htmlspecialchars(implode(' · ', array_keys(WORKOUT_SPLITS[$type]))) ?> buổi</small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <p class="wp-field__hint">Chọn kiểu chưa hợp với số buổi đang chọn thì số buổi tự đổi theo.</p>
            </fieldset>

            <div class="wp-create__foot">
                <p class="wp-create__hint">
                    Mỗi bài <?= WORKOUT_SETS_BY_LEVEL['BEGINNER'] ?>-<?= WORKOUT_SETS_BY_LEVEL['ADVANCED'] ?> set ·
                    <?= WORKOUT_REP_RANGE[0] ?>-<?= WORKOUT_REP_RANGE[1] ?> reps · nghỉ <?= WORKOUT_REST_MINUTES[0] ?>-<?= WORKOUT_REST_MINUTES[1] ?> phút
                </p>
                <button type="submit" class="btn" <?= $goal === null ? 'disabled' : '' ?>>Tạo lịch ngay</button>
            </div>
        </form>

        <?php if ($goal === null): ?>
            <p class="wp-alert">Bạn chưa chọn mục tiêu. Hãy cập nhật trong <a href="profile.php">hồ sơ</a> trước khi tạo lịch.</p>
        <?php endif; ?>
        <p id="workout-plan-message" class="workout-plan__message" role="status"></p>
    </section>
    <?php elseif ($plan === null): ?>
        <!-- Đang có HLV mà chưa có lịch: không tự tạo, chờ HLV giao -->
        <div class="ch-empty">
            <div class="ch-empty__ico" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
            </div>
            <h2>Chờ HLV giao lịch tập</h2>
            <p>HLV sẽ xây lịch theo hồ sơ của bạn. Nhắn tin cho HLV nếu bạn có lịch rảnh hoặc chấn thương cần lưu ý.</p>
            <a class="btn" href="/my-coach.php">HLV của tôi</a>
        </div>
    <?php endif; ?>

    <?php if ($plan !== null): ?>
        <p id="workout-plan-edit-message" class="workout-plan__message" role="status"></p>

        <!-- THỐNG KÊ TUẦN -->
        <section class="wp-stats" aria-label="Tổng quan tuần">
            <div class="wp-stat wp-stat--main">
                <span class="wp-stat__label">Buổi tập / tuần</span>
                <span class="wp-stat__value"><?= $trainingDays ?><small> buổi</small></span>
                <span class="wp-stat__sub"><?= 7 - $trainingDays ?> ngày nghỉ để phục hồi</span>
            </div>
            <div class="wp-stat">
                <span class="wp-stat__label">Bài tập</span>
                <span class="wp-stat__value"><?= $totalExercises ?></span>
                <span class="wp-stat__sub">trong cả tuần</span>
            </div>
            <div class="wp-stat">
                <span class="wp-stat__label">Tổng set</span>
                <span class="wp-stat__value"><?= $totalSets ?></span>
                <span class="wp-stat__sub">≈ <?= (int)round($totalSets * $minutesPerSet / max(1, $trainingDays)) ?> phút mỗi buổi</span>
            </div>
            <div class="wp-stat">
                <span class="wp-stat__label">Mỗi set</span>
                <span class="wp-stat__value"><?= WORKOUT_REP_RANGE[0] ?>-<?= WORKOUT_REP_RANGE[1] ?><small> lặp</small></span>
                <span class="wp-stat__sub">nghỉ <?= WORKOUT_REST_MINUTES[0] ?>-<?= WORKOUT_REST_MINUTES[1] ?> phút giữa các set</span>
            </div>
        </section>

        <!-- THANH 7 NGÀY -->
        <nav class="wp-week" id="day-tabs" aria-label="Các ngày trong tuần">
            <?php for ($i = 1; $i <= 7; $i++):
                $count = $daysHaveWorkout[$i];
                $label = $count > 0 ? ($sessionLabels[$i] ?? 'Buổi tập') : 'Nghỉ';
            ?>
                <button type="button" class="wp-day<?= $count === 0 ? ' wp-day--rest' : '' ?><?= $i === $firstActiveDay ? ' is-active' : '' ?>"
                        data-day="<?= $i ?>" aria-pressed="<?= $i === $firstActiveDay ? 'true' : 'false' ?>">
                    <span class="wp-day__name"><?= $dayShort[$i] ?></span>
                    <span class="wp-day__session"><?= htmlspecialchars($label) ?></span>
                    <span class="wp-day__count"><?= $count > 0 ? $count . ' bài' : '—' ?></span>
                </button>
            <?php endfor; ?>
        </nav>

        <div class="wp-layout">
            <div class="wp-main">
                <?php for ($i = 1; $i <= 7; $i++):
                    $count = $daysHaveWorkout[$i];
                    $daySets = $count > 0 ? array_sum(array_column($plan['days'][$i], 'sets')) : 0;
                ?>
                    <section class="wp-card wp-panel<?= $i === $firstActiveDay ? ' is-active' : '' ?>" id="day-content-<?= $i ?>">
                        <?php if ($count === 0): ?>
                            <div class="wp-rest">
                                <p class="wp-rest__title"><?= htmlspecialchars($dayNames[$i]) ?> · Ngày nghỉ</p>
                                <p class="wp-rest__text">Cơ bắp lớn lên trong lúc nghỉ. Đi bộ nhẹ, ngủ đủ và ăn đủ đạm.</p>
                            </div>
                        <?php else: ?>
                            <div class="wp-panel__head">
                                <div>
                                    <h2 class="wp-panel__title"><?= htmlspecialchars($dayNames[$i]) ?> · <?= htmlspecialchars($sessionLabels[$i] ?? 'Buổi tập') ?></h2>
                                    <p class="wp-panel__meta"><?= $count ?> bài · <?= $daySets ?> set · ≈ <?= (int)round($daySets * $minutesPerSet) ?> phút</p>
                                </div>
                            </div>
                            <ol class="wp-list">
                                <?php
                                $index = 1;
                                foreach ($plan['days'][$i] as $row):
                                    $role      = WORKOUT_EXERCISE_ROLES[$row['exercise_id']] ?? null;
                                    $roleLabel = $role !== null ? WORKOUT_ROLE_LABELS[$role] : (WORKOUT_MUSCLE_LABELS[$row['muscle_group']] ?? $row['muscle_group']);
                                    $lastLog   = fetchLastWorkoutLog($pdo, $userId, $row['exercise_id']);
                                ?>
                                    <li class="wp-ex" data-row-id="<?= (int)$row['row_id'] ?>">
                                        <div class="wp-ex__row">
                                            <span class="wp-ex__num"><?= $index++ ?></span>
                                            <div class="wp-ex__info">
                                                <a href="exercise-detail.php?id=<?= (int)$row['exercise_id'] ?>" class="wp-ex__name"><?= htmlspecialchars($row['name']) ?></a>
                                                <span class="wp-ex__role"><?= htmlspecialchars($roleLabel) ?></span>
                                            </div>
                                            <span class="wp-ex__dose" title="set × lặp">
                                                <b><?= (int)$row['sets'] ?></b> × <?= htmlspecialchars(repsText((int)$row['reps'])) ?>
                                            </span>
                                            <div class="wp-ex__actions">
                                                <a class="wp-icon-btn" href="exercise-detail.php?id=<?= (int)$row['exercise_id'] ?>" aria-label="Xem video <?= htmlspecialchars($row['name']) ?>">▶</a>
                                                <button type="button" class="wp-log-btn" data-log-toggle aria-expanded="false">Ghi log</button>
                                            </div>
                                        </div>

                                        <!-- FORM GHI LOG (Progressive Overload) -->
                                        <div class="wp-log" hidden>
                                            <p class="wp-log__last">
                                                <?php if ($lastLog): ?>
                                                    <span class="wp-log__badge">Lần trước</span>
                                                    <?= date('d/m', strtotime($lastLog['log_date'])) ?>:
                                                    <strong><?= htmlspecialchars((string)$lastLog['weight_kg_used']) ?> kg</strong>
                                                    × <?= (int)$lastLog['sets_completed'] ?> set × <?= (int)$lastLog['reps_completed'] ?> lặp
                                                    <?php if ((int)$lastLog['reps_completed'] >= WORKOUT_REP_RANGE[1]): ?>
                                                        <span class="wp-log__up">Đạt <?= WORKOUT_REP_RANGE[1] ?> lặp: lần này tăng tạ</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    Chưa có lần tập nào. Chọn mức tạ làm được <?= WORKOUT_REP_RANGE[0] ?>-<?= WORKOUT_REP_RANGE[1] ?> lặp.
                                                <?php endif; ?>
                                            </p>
                                            <form class="wp-log__form" data-log-form>
                                                <input type="hidden" name="row_id" value="<?= (int)$row['row_id'] ?>">
                                                <input type="hidden" name="exercise_id" value="<?= (int)$row['exercise_id'] ?>">
                                                <label class="wp-log__field">Mức tạ (kg)
                                                    <input type="number" name="weight" step="0.5" min="0" placeholder="VD: 50" value="<?= $lastLog ? htmlspecialchars((string)$lastLog['weight_kg_used']) : '' ?>" required>
                                                </label>
                                                <label class="wp-log__field">Set
                                                    <input type="number" name="sets" min="1" value="<?= (int)$row['sets'] ?>" required>
                                                </label>
                                                <label class="wp-log__field">Lặp
                                                    <input type="number" name="reps" min="1" value="<?= (int)$row['reps'] ?>" required>
                                                </label>
                                                <button type="submit" class="btn wp-log__submit">Lưu</button>
                                            </form>
                                        </div>

                                        <!-- B11: chỉnh sửa (chỉ hiện ở chế độ chỉnh sửa) -->
                                        <div class="workout-plan__edit wp-edit">
                                            <label>Set <input type="number" name="sets" min="1" max="<?= PLAN_EDIT_MAX_SETS ?>" value="<?= (int)$row['sets'] ?>"></label>
                                            <label>Lặp <input type="number" name="reps" min="1" max="<?= PLAN_EDIT_MAX_REPS ?>" value="<?= (int)$row['reps'] ?>"></label>
                                            <button type="button" class="btn btn--ghost" data-action="update">Lưu</button>
                                            <select name="new_exercise_id" aria-label="Đổi sang bài khác">
                                                <option value="">Đổi sang bài khác...</option>
                                                <?php foreach ($libraryByGroup[$row['muscle_group']] ?? [] as $lib): ?>
                                                    <?php if ((int)$lib['id'] === (int)$row['exercise_id']) continue; ?>
                                                    <option value="<?= (int)$lib['id'] ?>"><?= htmlspecialchars($lib['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn--ghost" data-action="replace">Đổi</button>
                                            <button type="button" class="btn btn--ghost wp-edit__remove" data-action="remove">Xóa</button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </section>
                <?php endfor; ?>

                <!-- Thêm bài vào bất kỳ ngày nào (chế độ chỉnh sửa) -->
                <section class="wp-card workout-plan__edit-only">
                    <h2 class="wp-card__title">Thêm bài tập</h2>
                    <div class="workout-plan__edit wp-edit wp-edit--add">
                        <select name="day_of_week" aria-label="Ngày">
                            <?php foreach ($dayNames as $num => $dayName): ?>
                                <option value="<?= (int)$num ?>"><?= htmlspecialchars($dayName) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="exercise_id" aria-label="Bài tập">
                            <option value="">Chọn bài tập...</option>
                            <?php foreach ($libraryByGroup as $group => $list): ?>
                                <optgroup label="<?= htmlspecialchars(WORKOUT_MUSCLE_LABELS[$group] ?? $group) ?>">
                                    <?php foreach ($list as $lib): ?>
                                        <option value="<?= (int)$lib['id'] ?>"><?= htmlspecialchars($lib['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <label>Set <input type="number" name="sets" min="1" max="<?= PLAN_EDIT_MAX_SETS ?>" value="<?= WORKOUT_SETS_PER_EXERCISE ?>"></label>
                        <label>Lặp <input type="number" name="reps" min="1" max="<?= PLAN_EDIT_MAX_REPS ?>" value="<?= WORKOUT_TARGET_REPS ?>"></label>
                        <button type="button" class="btn" data-action="add">Thêm vào lịch</button>
                    </div>
                </section>
            </div>

            <aside class="wp-side">
                <section class="wp-card">
                    <h2 class="wp-card__title">Khối lượng tuần</h2>
                    <p class="wp-card__sub">Số set mỗi cơ. Vạch dọc là mốc khoảng <?= $volumeTarget ?> set/tuần để tăng cơ.</p>
                    <ul class="wp-vol">
                        <?php foreach (WORKOUT_MUSCLE_DETAIL_LABELS as $key => $label):
                            $sets = $setsByMuscle[$key] ?? 0;
                            if ($sets === 0) continue;
                        ?>
                            <li class="wp-vol__item">
                                <span class="wp-vol__label"><?= htmlspecialchars($label) ?></span>
                                <span class="wp-vol__bar" style="--fill: <?= min(100, round($sets / $volumeMax * 100)) ?>%; --mark: <?= round($volumeTarget / $volumeMax * 100) ?>%;"></span>
                                <span class="wp-vol__value"><?= $sets ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="wp-card wp-rules">
                    <h2 class="wp-card__title">Cách tập theo lịch này</h2>
                    <ul>
                        <li>Khởi động 1-2 set nhẹ trước bài đầu tiên.</li>
                        <li>Chọn mức tạ chỉ làm được <?= WORKOUT_REP_RANGE[0] ?>-<?= WORKOUT_REP_RANGE[1] ?> reps, set cuối gần hết sức.</li>
                        <li>Nghỉ đủ <?= WORKOUT_REST_MINUTES[0] ?>-<?= WORKOUT_REST_MINUTES[1] ?> phút để set sau vẫn nặng.</li>
                        <li>Làm được <?= WORKOUT_REP_RANGE[1] ?> reps ở mọi set thì buổi sau tăng tạ.</li>
                    </ul>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</div>

<script src="js/workout-plan.js"></script>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
