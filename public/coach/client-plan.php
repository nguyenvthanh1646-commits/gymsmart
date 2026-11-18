<?php
/**
 * coach/client-plan.php?id=...[&view=draft] — HLV giao và chỉnh lịch tập cho học viên.
 *
 * Hai chế độ:
 *   - "Lịch đang giao" (mặc định): sửa trực tiếp lịch ACTIVE, học viên thấy ngay. Dùng lại nguyên các hàm của lịch tập
 *     (updatePlanExerciseVolume / replacePlanExercise / removePlanExercise / addPlanExercise trong includes/workout-plan-db.php);
 *     các hàm này tự kiểm tra dòng thuộc lịch ACTIVE của $userId, ở đây truyền $clientId đã qua requireCoachClient().
 *   - "Bản nháp" (view=draft, cần sql/coach_extras.sql): HLV tạo từ thuật toán gợi ý hoặc chép lịch hiện tại, sửa thoải mái,
 *     học viên KHÔNG thấy; bấm "Giao bản nháp" thì lịch cũ lưu trữ và bản nháp thành lịch đang dùng (coachPublishDraft).
 *     Chưa import coach_extras.sql thì "Tạo từ gợi ý" giao lịch ngay như bản cũ.
 *   - "Tiếp quản": học viên đang có lịch tự tạo → ghi tên HLV vào lịch đó.
 * Mỗi thao tác là 1 form POST có CSRF, xong thì chuyển hướng (Post/Redirect/Get). Giao lịch / tiếp quản ghi nhật ký coach.assign_plan.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';
require_once __DIR__ . '/../includes/workout-plan-db.php';
require_once __DIR__ . '/../includes/audit-log.php';
require_once __DIR__ . '/../includes/coach-templates.php';   // "Lưu làm mẫu" (A-48)

requireCoach();
[$clientId, $relationId] = requireCoachClient($pdo, $_GET['id'] ?? null);
$coachId = (int)$_SESSION['user_id'];
$client  = coachClientBasics($pdo, $clientId);
$drafts  = coachExtrasReady($pdo);   // có hỗ trợ bản nháp chưa
$view    = ($drafts && ($_GET['view'] ?? '') === 'draft') ? 'draft' : 'active';
$base    = '/coach/client-plan.php?id=' . $clientId;
$self    = $base . ($view === 'draft' ? '&view=draft' : '');

/** Số nguyên từ $_POST; sai kiểu → 0 (các hàm lịch tập sẽ từ chối 0). */
function postNum(string $key): int
{
    $v = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    return $v === false ? 0 : $v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $action = (string)($_POST['action'] ?? '');
    $go = $self;
    $editParams = ['row_id' => postNum('row_id'), 'sets' => postNum('sets'), 'reps' => postNum('reps'),
                   'new_exercise_id' => postNum('new_exercise_id'), 'day_of_week' => postNum('day_of_week'), 'exercise_id' => postNum('exercise_id')];
    try {
        switch ($action) {
            case 'generate':
                $split = (string)($_POST['split_type'] ?? '');
                $split = $split === '' ? null : $split;
                if ($drafts) {
                    coachDraftFromGenerator($pdo, $coachId, $clientId, postNum('days_per_week'), (string)($_POST['level'] ?? ''), $split);
                    coachFlash('ok', 'Đã tạo bản nháp từ gợi ý. Học viên chưa thấy; chỉnh xong thì bấm "Giao bản nháp".');
                    $go = $base . '&view=draft';
                } else {
                    createWorkoutPlanForUser($pdo, $clientId, postNum('days_per_week'), (string)($_POST['level'] ?? ''), $split, $coachId);
                    auditLog($pdo, 'coach.assign_plan', 'client', $clientId, 'Giao lịch mới cho "' . $client['full_name'] . '"');
                    coachFlash('ok', 'Đã tạo lịch từ gợi ý và giao cho học viên.');
                }
                break;
            case 'draft_copy':
                coachDraftFromActive($pdo, $coachId, $clientId);
                coachFlash('ok', 'Đã chép lịch hiện tại thành bản nháp.');
                $go = $base . '&view=draft';
                break;
            case 'draft_publish':
                coachPublishDraft($pdo, $coachId, $clientId);
                auditLog($pdo, 'coach.assign_plan', 'client', $clientId, 'Giao bản nháp lịch tập cho "' . $client['full_name'] . '"');
                coachFlash('ok', 'Đã giao bản nháp. Học viên thấy lịch mới ngay; lịch cũ được lưu trữ.');
                $go = $base;
                break;
            case 'draft_discard':
                coachDeleteDrafts($pdo, $coachId, $clientId);
                coachFlash('ok', 'Đã bỏ bản nháp.');
                $go = $base;
                break;
            case 'draft_update': case 'draft_replace': case 'draft_remove': case 'draft_add':
                coachDraftEdit($pdo, $coachId, $clientId, substr($action, 6), $editParams);
                coachFlash('ok', 'Đã cập nhật bản nháp (học viên chưa thấy).');
                $go = $base . '&view=draft';
                break;
            case 'save_template':
                // Lưu lịch đang xem (bản nháp hoặc lịch đang dùng) thành mẫu để giao cho học viên khác ở trang Mẫu lịch
                coachTemplateSaveFromClient($pdo, $coachId, $clientId, (string)($_POST['template_name'] ?? ''), $view === 'draft');
                coachFlash('ok', 'Đã lưu làm mẫu. Vào Mẫu lịch để giao cho học viên khác.');
                break;
            case 'adopt':
                if (coachMarkPlanAssigned($pdo, $coachId, $clientId) === 0) {
                    throw new CoachException('Học viên chưa có lịch để tiếp quản.');
                }
                auditLog($pdo, 'coach.assign_plan', 'client', $clientId, 'Tiếp quản lịch hiện tại của "' . $client['full_name'] . '"');
                coachFlash('ok', 'Đã tiếp quản lịch hiện tại. Học viên thấy nhãn "Do HLV giao".');
                break;
            case 'update':
                updatePlanExerciseVolume($pdo, $clientId, $editParams['row_id'], $editParams['sets'], $editParams['reps']);
                break;
            case 'replace':
                replacePlanExercise($pdo, $clientId, $editParams['row_id'], $editParams['new_exercise_id']);
                break;
            case 'remove':
                removePlanExercise($pdo, $clientId, $editParams['row_id']);
                break;
            case 'add':
                addPlanExercise($pdo, $clientId, $editParams['day_of_week'], $editParams['exercise_id'], $editParams['sets'], $editParams['reps']);
                break;
            default:
                throw new CoachException('Thao tác không hợp lệ.');
        }
        if (in_array($action, ['update', 'replace', 'remove', 'add'], true)) {
            coachMarkPlanAssigned($pdo, $coachId, $clientId);   // HLV đã sửa thì lịch tính là do HLV giao
            coachFlash('ok', 'Đã cập nhật lịch, học viên thấy ngay.');
        }
    } catch (PDOException $e) {
        throw $e;   // lỗi DB: KHÔNG in câu lỗi SQL ra trang (PDOException cũng là RuntimeException nên phải bắt riêng trước)
    } catch (InvalidArgumentException | RuntimeException $e) {
        // Lỗi nghiệp vụ có câu tiếng Việt: CoachException, PlanNotFoundException, "Bài này đã có trong ngày đó"...
        coachFlash('error', $e->getMessage());
    }
    $day = postNum('day_of_week') ?: postNum('day');
    header('Location: ' . $go . ($day >= 1 && $day <= 7 ? '#day-' . $day : ''));
    exit;
}

$flash   = coachTakeFlash();
$active  = fetchActivePlan($pdo, $clientId);
$draft   = $drafts ? coachDraftPlan($pdo, $coachId, $clientId) : null;
$planBy  = coachPlanAssigner($pdo, $clientId);
$mine    = $planBy !== null && (int)$planBy['id'] === $coachId;
$plan    = $view === 'draft' ? $draft : $active;   // lịch đang hiện trong khung sửa
$prefix  = $view === 'draft' ? 'draft_' : '';       // tên thao tác gửi lên máy chủ
$library = [];
foreach (fetchExerciseLibrary($pdo) as $ex) {
    $library[$ex['muscle_group']][] = $ex;
}
$dayNames = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];
$levelDefault = in_array($client['experience_level'], WORKOUT_LEVELS, true) ? $client['experience_level'] : 'BEGINNER';
$splitOptions = workoutSplitOptions();

$pageTitle = 'Giao lịch tập';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$coachNavActive = 'clients';
include __DIR__ . '/../includes/coach-nav.php';
?>
<div class="adm-wrap">
    <a class="adm-back" href="/coach/client.php?id=<?= (int)$clientId ?>">&larr; Hồ sơ <?= htmlspecialchars($client['full_name']) ?></a>
    <div class="adm-head">
        <div>
            <h1>Lịch tập của <?= htmlspecialchars($client['full_name']) ?></h1>
            <p class="adm-sub">Mục tiêu: <?= htmlspecialchars(COACH_GOAL_LABELS[$client['goal']] ?? 'chưa đặt') ?> · trình độ <?= htmlspecialchars(mb_strtolower(COACH_LEVEL_LABELS[$client['experience_level']] ?? 'chưa chọn')) ?>.
                <?= $drafts ? 'Sửa ở "Lịch đang giao" thì học viên thấy ngay; soạn ở "Bản nháp" thì học viên chỉ thấy khi bạn bấm Giao.' : 'Học viên thấy thay đổi ngay.' /* audit:ok chữ viết sẵn */ ?></p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <?php if ($drafts): ?>
        <nav class="ch-tabs" aria-label="Chế độ">
            <a href="<?= htmlspecialchars($base) ?>"<?= $view === 'active' ? ' class="is-active" aria-current="page"' : '' ?>>Lịch đang giao<?php if ($active): ?> <span class="ch-badge ch-badge--plain"><?= $mine ? 'do bạn giao' : 'học viên tự tạo' ?></span><?php endif; ?></a>
            <a href="<?= htmlspecialchars($base . '&view=draft') ?>"<?= $view === 'draft' ? ' class="is-active" aria-current="page"' : '' ?>>Bản nháp<?php if ($draft): ?> <span class="ch-dot" aria-label="có bản nháp">1</span><?php endif; ?></a>
        </nav>
    <?php endif; ?>

    <div class="adm-split">
        <div style="display:grid;gap:16px;min-width:0">
            <?php if ($view === 'draft' && $draft !== null): ?>
                <div class="adm-panel adm-card-pad cp-draftbar">
                    <div><b><?= htmlspecialchars($draft['name']) ?></b><br><small class="adm-sub">Học viên chưa thấy bản nháp. Sửa lần cuối <?= htmlspecialchars(coachTimeAgo((string)$draft['updated_at'])) ?>.</small></div>
                    <div class="adm-actions">
                        <form method="post" action="<?= htmlspecialchars($self) ?>" data-confirm="Bỏ bản nháp này?"><?= csrfField() ?><input type="hidden" name="action" value="draft_discard">
                            <button type="submit" class="adm-btn adm-btn-ghost adm-btn-sm">Bỏ bản nháp</button></form>
                        <form method="post" action="<?= htmlspecialchars($self) ?>" data-confirm="Giao bản nháp cho học viên? Lịch đang dùng sẽ được lưu trữ."><?= csrfField() ?><input type="hidden" name="action" value="draft_publish">
                            <button type="submit" class="adm-btn adm-btn-sm" data-busy-text="Đang giao…">Giao bản nháp</button></form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($plan === null): ?>
                <div class="adm-panel ch-empty" style="border-style:solid">
                    <div class="ch-empty__ico" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
                    </div>
                    <?php if ($view === 'draft'): ?>
                        <h2>Chưa có bản nháp</h2>
                        <p>Tạo bản nháp từ gợi ý ở cột bên phải<?= $active ? ', hoặc chép lịch hiện tại để sửa nhiều chỗ rồi giao một lần' : '' ?>.</p>
                        <?php if ($active): ?>
                            <form method="post" action="<?= htmlspecialchars($self) ?>"><?= csrfField() ?><input type="hidden" name="action" value="draft_copy">
                                <button type="submit" class="adm-btn">Chép lịch hiện tại thành bản nháp</button></form>
                        <?php endif; ?>
                    <?php else: ?>
                        <h2>Học viên chưa có lịch</h2>
                        <p>Tạo lịch từ gợi ý ở cột bên phải (thuật toán chia buổi theo mục tiêu và trình độ), rồi chỉnh từng bài cho hợp với học viên.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php if ($view === 'active' && !$mine): ?>
                    <div class="adm-hint-box">Đây là lịch học viên tự tạo. Bấm <b>Tiếp quản</b> để giữ lịch này và chỉnh tiếp<?= $drafts ? ', hoặc soạn lịch mới ở tab Bản nháp' : '' ?>. Mọi chỉnh sửa của bạn cũng tự động ghi lịch là do bạn giao.</div>
                <?php endif; ?>
                <?php for ($d = 1; $d <= 7; $d++): $rows = $plan['days'][$d] ?? []; ?>
                    <section class="adm-panel adm-card-pad cp-day" id="day-<?= (int)$d ?>" aria-labelledby="h-day-<?= (int)$d ?>">
                        <h2 id="h-day-<?= (int)$d ?>"><?= htmlspecialchars($dayNames[$d]) ?> <small class="adm-sub"><?= $rows ? count($rows) . ' bài · ' . array_sum(array_column($rows, 'sets')) . ' set' : 'Nghỉ' ?></small></h2>
                        <?php foreach ($rows as $row): ?>
                            <div class="cp-row">
                                <div class="cp-row__name"><b><?= htmlspecialchars($row['name']) ?></b><small><?= htmlspecialchars(WORKOUT_MUSCLE_LABELS[$row['muscle_group']] ?? $row['muscle_group']) ?></small></div>
                                <form method="post" action="<?= htmlspecialchars($self) ?>" class="cp-form">
                                    <?= csrfField() ?><input type="hidden" name="action" value="<?= $prefix ?>update"><input type="hidden" name="row_id" value="<?= (int)$row['row_id'] ?>"><input type="hidden" name="day" value="<?= (int)$d ?>">
                                    <label>Set <input class="adm-input" type="number" name="sets" min="1" max="<?= PLAN_EDIT_MAX_SETS ?>" value="<?= (int)$row['sets'] ?>" inputmode="numeric"></label>
                                    <label>Rep <input class="adm-input" type="number" name="reps" min="1" max="<?= PLAN_EDIT_MAX_REPS ?>" value="<?= (int)$row['reps'] ?>" inputmode="numeric"></label>
                                    <button type="submit" class="adm-btn adm-btn-ghost adm-btn-sm">Lưu</button>
                                </form>
                                <form method="post" action="<?= htmlspecialchars($self) ?>" class="cp-form">
                                    <?= csrfField() ?><input type="hidden" name="action" value="<?= $prefix ?>replace"><input type="hidden" name="row_id" value="<?= (int)$row['row_id'] ?>"><input type="hidden" name="day" value="<?= (int)$d ?>">
                                    <select class="adm-select" name="new_exercise_id" aria-label="Đổi <?= htmlspecialchars($row['name']) ?> sang bài khác" required>
                                        <option value="">Đổi sang bài…</option>
                                        <?php foreach ($library[$row['muscle_group']] ?? [] as $ex): if ((int)$ex['id'] === (int)$row['exercise_id']) continue; ?>
                                            <option value="<?= (int)$ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="adm-btn adm-btn-ghost adm-btn-sm">Đổi</button>
                                </form>
                                <form method="post" action="<?= htmlspecialchars($self) ?>" class="cp-form" data-confirm="Xóa <?= htmlspecialchars($row['name']) ?> khỏi <?= htmlspecialchars($dayNames[$d]) ?>?">
                                    <?= csrfField() ?><input type="hidden" name="action" value="<?= $prefix ?>remove"><input type="hidden" name="row_id" value="<?= (int)$row['row_id'] ?>"><input type="hidden" name="day" value="<?= (int)$d ?>">
                                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">Xóa</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endfor; ?>
            <?php endif; ?>
        </div>

        <div class="adm-side" style="display:grid;gap:16px">
            <?php if ($plan !== null): ?>
                <section class="adm-panel adm-card-pad" aria-labelledby="h-add">
                    <h2 id="h-add">Thêm bài<?= $view === 'draft' ? ' vào bản nháp' : '' ?></h2>
                    <form method="post" action="<?= htmlspecialchars($self) ?>">
                        <?= csrfField() ?><input type="hidden" name="action" value="<?= $prefix ?>add">
                        <div class="adm-field"><label for="add-day">Ngày</label>
                            <select class="adm-select" id="add-day" name="day_of_week"><?php foreach ($dayNames as $n => $label): ?><option value="<?= (int)$n ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
                        <div class="adm-field"><label for="add-ex">Bài tập</label>
                            <select class="adm-select" id="add-ex" name="exercise_id" required>
                                <option value="">Chọn bài tập…</option>
                                <?php foreach ($library as $group => $list): ?>
                                    <optgroup label="<?= htmlspecialchars(WORKOUT_MUSCLE_LABELS[$group] ?? $group) ?>">
                                        <?php foreach ($list as $ex): ?><option value="<?= (int)$ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?></option><?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="adm-grid" style="grid-template-columns:1fr 1fr">
                            <div class="adm-field"><label for="add-sets">Set</label><input class="adm-input" id="add-sets" type="number" name="sets" min="1" max="<?= PLAN_EDIT_MAX_SETS ?>" value="<?= WORKOUT_SETS_PER_EXERCISE ?>" inputmode="numeric"></div>
                            <div class="adm-field"><label for="add-reps">Rep</label><input class="adm-input" id="add-reps" type="number" name="reps" min="1" max="<?= PLAN_EDIT_MAX_REPS ?>" value="<?= WORKOUT_TARGET_REPS ?>" inputmode="numeric"></div>
                        </div>
                        <button type="submit" class="adm-btn" style="width:100%">Thêm</button>
                    </form>
                </section>
            <?php endif; ?>
            <?php if ($plan !== null && coachTemplatesReady($pdo)): ?>
                <section class="adm-panel adm-card-pad" aria-labelledby="h-tpl">
                    <h2 id="h-tpl">Lưu làm mẫu</h2>
                    <p class="adm-sub">Lưu <?= $view === 'draft' ? 'bản nháp' : 'lịch' ?> này để giao nhanh cho học viên khác ở trang <a href="/coach/templates.php">Mẫu lịch</a>.</p>
                    <form method="post" action="<?= htmlspecialchars($self) ?>">
                        <?= csrfField() ?><input type="hidden" name="action" value="save_template">
                        <div class="adm-field"><label for="tpl-name">Tên mẫu</label>
                            <input class="adm-input" id="tpl-name" name="template_name" required minlength="3" maxlength="80" placeholder="Ví dụ: Toàn thân 3 buổi cho người mới"></div>
                        <button type="submit" class="adm-btn adm-btn-ghost" style="width:100%">Lưu làm mẫu</button>
                    </form>
                </section>
            <?php endif; ?>
            <?php if ($view === 'active' && $active !== null && !$mine): ?>
                <section class="adm-panel adm-card-pad">
                    <h2>Tiếp quản lịch hiện tại</h2>
                    <p class="adm-sub">Giữ nguyên lịch học viên đang tập, ghi tên bạn là người giao.</p>
                    <form method="post" action="<?= htmlspecialchars($self) ?>"><?= csrfField() ?><input type="hidden" name="action" value="adopt">
                        <button type="submit" class="adm-btn adm-btn-ghost" style="width:100%">Tiếp quản</button></form>
                </section>
            <?php endif; ?>

            <section class="adm-panel adm-card-pad" aria-labelledby="h-gen">
                <h2 id="h-gen"><?= $drafts ? 'Tạo bản nháp từ gợi ý' : 'Tạo lịch từ gợi ý' ?></h2>
                <p class="adm-sub">Dùng thuật toán sinh lịch của GymSmart với mục tiêu của học viên làm bản đầu, rồi chỉnh tay.
                    <?= $drafts ? ($draft ? 'Bản nháp hiện tại sẽ bị thay.' : 'Học viên chưa thấy cho tới khi bạn giao.') : ($active ? 'Lịch hiện tại sẽ được lưu trữ.' : '') /* audit:ok chữ viết sẵn */ ?></p>
                <?php if (!$client['goal']): ?>
                    <div class="adm-hint-box">Học viên chưa chọn mục tiêu trong hồ sơ nên chưa tạo được lịch gợi ý. Nhắn tin nhờ học viên cập nhật.</div>
                <?php else: ?>
                <form method="post" action="<?= htmlspecialchars($self) ?>"<?= (!$drafts && $active) || ($drafts && $draft) ? ' data-confirm="' . ($drafts ? 'Thay bản nháp hiện tại bằng bản mới?' : 'Tạo lịch mới sẽ thay lịch hiện tại của học viên. Tiếp tục?') . '"' : '' ?>>
                    <?= csrfField() ?><input type="hidden" name="action" value="generate">
                    <div class="adm-field"><label for="gen-days">Số buổi mỗi tuần</label>
                        <select class="adm-select" id="gen-days" name="days_per_week"><?php foreach (array_keys($splitOptions) as $n): ?><option value="<?= (int)$n ?>"<?= $n === 3 ? ' selected' : '' ?>><?= (int)$n ?> buổi</option><?php endforeach; ?></select></div>
                    <div class="adm-field"><label for="gen-level">Trình độ</label>
                        <select class="adm-select" id="gen-level" name="level"><?php foreach (WORKOUT_LEVELS as $lv): ?><option value="<?= htmlspecialchars($lv) ?>"<?= $lv === $levelDefault ? ' selected' : '' ?>><?= htmlspecialchars(COACH_LEVEL_LABELS[$lv]) ?></option><?php endforeach; ?></select></div>
                    <div class="adm-field"><label for="gen-split">Kiểu chia</label>
                        <select class="adm-select" id="gen-split" name="split_type">
                            <option value="">Mặc định theo số buổi</option>
                            <?php foreach (WORKOUT_SPLIT_LABELS as $key => $label): if (!isset(WORKOUT_SPLITS[$key])) continue; ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?> (<?= htmlspecialchars(implode(', ', array_keys(WORKOUT_SPLITS[$key]))) ?> buổi)</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="adm-hint">Kiểu chia không hợp với số buổi thì máy chủ báo lỗi.</p></div>
                    <button type="submit" class="adm-btn" style="width:100%" data-busy-text="Đang tạo…"><?= $drafts ? 'Tạo bản nháp' : 'Tạo và giao lịch' ?></button>
                </form>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
