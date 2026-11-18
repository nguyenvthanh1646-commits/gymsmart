<?php
/**
 * workout-session.php — chế độ "Đang tập" (B1): mở buổi của một ngày trong lịch, nhập kg/lặp và đánh dấu từng set,
 * đồng hồ nghỉ giữa set, cuối buổi lưu nhật ký MỘT lần (api/log-session.php).
 * Mức tạ gợi ý lấy từ nhật ký cũ qua suggestProgression() (includes/progression.php, B2).
 * GET ?day=1..7 (thứ Hai = 1); bỏ trống thì là hôm nay. Chỉ đọc lịch ACTIVE của chính người đăng nhập.
 * Giao diện: css/workout-session.css (class "ws-"), hành vi: js/workout-session.js.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireLogin();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/workout-plan-db.php';
require_once __DIR__ . '/includes/progression.php';
require_once __DIR__ . '/includes/equipment-db.php';   // bài tương đương khi thiếu dụng cụ (A-47)

$userId = (int)$_SESSION['user_id'];
$todayDow = (int)date('N');
$day = filter_var($_GET['day'] ?? $todayDow, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
if ($day === false) {
    $day = $todayDow;
}
$isToday = $day === $todayDow;

$dayNames = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'];
$dayShort = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];

$plan = null;
$loadError = false;
try {
    $plan = fetchActivePlan($pdo, $userId);
} catch (PDOException $e) {
    error_log('workout-session: ' . $e->getMessage());
    $loadError = true;
}

$rows = $plan['days'][$day] ?? [];
$sessionLabel = '';
$recent = [];
$loggedToday = [];
if ($plan !== null) {
    $sessionLabel = workoutSessionLabels($plan['split_type'], (int)$plan['days_per_week'])[$day] ?? '';
    $recent = fetchRecentLogsByExercise($pdo, $userId, array_column($rows, 'exercise_id'));
    $loggedToday = $isToday ? fetchLoggedRowsOn($pdo, $userId, date('Y-m-d')) : [];
    $library = $rows ? exerciseLibraryWithEquipment($pdo) : [];
}
$totalSets = array_sum(array_column($rows, 'sets'));
$restSeconds = WORKOUT_REST_MINUTES[0] * 60;
// Ước tính: ~45 giây mỗi set + thời gian nghỉ ở mức thấp của khoảng nghỉ
$minutes = (int)round($totalSets * (0.75 + WORKOUT_REST_MINUTES[0]));
$kg = fn(float $v): string => rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',');

$pageTitle = 'Đang tập';
$pageCss = ['/css/workout-session.css'];
include __DIR__ . '/includes/header.php';
?>
<div class="ws"
     data-session
     data-key="<?= htmlspecialchars('gs-ws-' . $userId . '-' . date('Y-m-d') . '-' . (int)($plan['id'] ?? 0) . '-' . $day) ?>"
     data-rest="<?= (int)$restSeconds ?>">
    <a class="ws-back" href="/workout-plan.php">&larr; Lịch tập</a>
    <header class="ws-head">
        <div>
            <p class="ws-eyebrow"><?= $isToday ? 'Hôm nay' : 'Tập bù / tập trước' ?> · <?= htmlspecialchars($dayNames[$day]) ?></p>
            <h1 class="ws-title">Đang tập<?= $sessionLabel !== '' ? ': ' . htmlspecialchars($sessionLabel) : '' ?></h1>
            <?php if ($rows !== []): ?>
                <p class="ws-sub"><?= count($rows) ?> bài · <?= (int)$totalSets ?> set · khoảng <?= $minutes ?> phút · nghỉ <?= WORKOUT_REST_MINUTES[0] ?>-<?= WORKOUT_REST_MINUTES[1] ?> phút giữa các set</p>
            <?php endif; ?>
        </div>
        <?php if ($plan !== null): ?>
            <nav class="ws-days" aria-label="Chọn buổi khác">
                <?php for ($i = 1; $i <= 7; $i++): ?>
                    <?php $has = !empty($plan['days'][$i]); ?>
                    <?php if ($has): ?>
                        <a class="ws-day<?= $i === $day ? ' is-current' : '' ?>" href="/workout-session.php?day=<?= $i ?>"<?= $i === $day ? ' aria-current="page"' : '' ?>><?= $dayShort[$i] ?></a>
                    <?php else: ?>
                        <span class="ws-day is-rest" title="Ngày nghỉ"><?= htmlspecialchars($dayShort[$i]) ?></span>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </header>

    <?php if ($loadError): ?>
        <div class="ws-state">
            <p class="ws-state__title">Chưa tải được lịch tập</p>
            <p>Có lỗi khi đọc dữ liệu. Bạn thử tải lại trang.</p>
            <a class="btn" href="/workout-session.php?day=<?= (int)$day ?>">Tải lại</a>
        </div>
    <?php elseif ($plan === null): ?>
        <div class="ws-state">
            <p class="ws-state__title">Bạn chưa có lịch tập</p>
            <p>Tạo lịch trước, sau đó mở chế độ Đang tập để ghi từng set.</p>
            <a class="btn" href="/workout-plan.php">Tạo lịch tập</a>
        </div>
    <?php elseif ($rows === []): ?>
        <div class="ws-state">
            <p class="ws-state__title"><?= htmlspecialchars($dayNames[$day]) ?> là ngày nghỉ</p>
            <p>Nghỉ cũng là một phần của tập luyện: cơ phục hồi và lớn lên trong lúc nghỉ. Chọn buổi khác ở trên nếu bạn muốn tập bù.</p>
            <a class="btn btn--ghost" href="/workout-plan.php">Xem lịch tuần</a>
        </div>
    <?php else: ?>
        <noscript><p class="ws-note">Chế độ Đang tập cần JavaScript. Bạn vẫn ghi được từng bài ở <a href="/workout-plan.php">trang Lịch tập</a>.</p></noscript>
        <p class="ws-progress" data-progress aria-live="polite">0/<?= (int)$totalSets ?> set xong</p>

        <ol class="ws-list">
            <?php foreach ($rows as $n => $row): ?>
                <?php
                $exId = (int)$row['exercise_id'];
                $role = WORKOUT_EXERCISE_ROLES[$exId] ?? null;
                $roleLabel = $role !== null ? WORKOUT_ROLE_LABELS[$role] : (WORKOUT_MUSCLE_LABELS[$row['muscle_group']] ?? $row['muscle_group']);
                $tip = suggestProgression($recent[$exId] ?? [], (int)$row['sets'], WORKOUT_REP_RANGE[0], WORKOUT_REP_RANGE[1], (string)$row['muscle_group']);
                $startWeight = $tip['weight'];
                $reps = (int)$row['reps'] === WORKOUT_TARGET_REPS ? WORKOUT_REP_RANGE[0] . '-' . WORKOUT_REP_RANGE[1] : (string)(int)$row['reps'];
                ?>
                <li class="ws-ex" data-ex data-row-id="<?= (int)$row['row_id'] ?>">
                    <div class="ws-ex__head">
                        <span class="ws-ex__num"><?= (int)$n + 1 ?></span>
                        <div class="ws-ex__info">
                            <h2 class="ws-ex__name"><a href="/exercise-detail.php?id=<?= $exId ?>" target="_blank" rel="noopener"><?= htmlspecialchars($row['name']) ?></a></h2>
                            <p class="ws-ex__meta"><?= htmlspecialchars($roleLabel) ?> · <?= (int)$row['sets'] ?> set × <?= htmlspecialchars($reps) ?> lặp
                                <?php if (isset($loggedToday[(int)$row['row_id']])): ?><span class="ws-badge ws-badge--ok">Đã ghi hôm nay</span><?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <p class="ws-tip ws-tip--<?= htmlspecialchars($tip['action']) ?>">
                        <b><?= ['start' => 'Buổi đầu', 'increase' => 'Tăng tạ', 'keep' => 'Giữ mức', 'decrease' => 'Giảm tạ', 'reps' => 'Thêm lặp'][$tip['action']] ?></b>
                        <?= htmlspecialchars($tip['text']) ?>
                    </p>
                    <?php $alts = equivalentExercises($exId, $library ?? [], WORKOUT_EXERCISE_ROLES, null, 3); ?>
                    <?php if ($alts): ?>
                        <p class="ws-alt">Thiếu dụng cụ? Thay bằng:
                            <?php foreach ($alts as $k => $alt): ?><?= $k > 0 ? ' · ' : '' ?><a href="/exercise-detail.php?id=<?= (int)$alt['id'] ?>" target="_blank" rel="noopener"><?= htmlspecialchars($alt['name']) ?></a> <span>(<?= htmlspecialchars($alt['label']) ?>)</span><?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                    <table class="ws-sets">
                        <thead><tr><th scope="col">Set</th><th scope="col">Tạ (kg)</th><th scope="col">Lặp</th><th scope="col"><span class="ws-sr">Trạng thái</span></th></tr></thead>
                        <tbody data-sets>
                            <?php for ($s = 1; $s <= (int)$row['sets']; $s++): ?>
                                <tr class="ws-set" data-set>
                                    <th scope="row" class="ws-set__no"><?= (int)$s ?></th>
                                    <td><input class="ws-in" type="number" inputmode="decimal" step="0.5" min="0" max="500" name="weight"
                                               value="<?= $startWeight !== null ? htmlspecialchars($kg($startWeight)) : '' ?>"
                                               placeholder="kg" aria-label="Tạ set <?= $s ?> (kg), <?= htmlspecialchars($row['name']) ?>"></td>
                                    <td><input class="ws-in" type="number" inputmode="numeric" min="1" max="100" name="reps" value=""
                                               placeholder="<?= htmlspecialchars($reps) ?>" aria-label="Số lặp set <?= $s ?>, <?= htmlspecialchars($row['name']) ?>"></td>
                                    <td><button type="button" class="ws-done" data-done aria-pressed="false">Xong</button></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                    <button type="button" class="ws-add" data-add-set>+ Thêm set</button>
                </li>
            <?php endforeach; ?>
        </ol>

        <form class="ws-form" id="ws-form" action="/api/log-session.php" method="post">
            <?= csrfField() ?>
        </form>

        <div class="ws-bar" data-bar>
            <div class="ws-timer" data-timer hidden role="timer" aria-live="off">
                <span class="ws-timer__label" data-timer-label>Nghỉ</span>
                <b class="ws-timer__time" data-timer-time>0:00</b>
                <button type="button" class="ws-timer__btn" data-timer-add="-30" aria-label="Bớt 30 giây">−30s</button>
                <button type="button" class="ws-timer__btn" data-timer-add="30" aria-label="Thêm 30 giây">+30s</button>
                <button type="button" class="ws-timer__btn" data-timer-skip>Bỏ qua</button>
            </div>
            <div class="ws-bar__main">
                <p class="ws-msg" data-msg role="status"></p>
                <button type="button" class="btn ws-save" data-save disabled>Lưu buổi tập</button>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="/js/workout-session.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
