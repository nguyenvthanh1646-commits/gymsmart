<?php
// public/workout-plan.php (B10 & B11) - Cập nhật Sprint 2.1 (Ghi log)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/workout-plan-db.php';

$userId = (int)$_SESSION['user_id'];
$goal   = fetchUserGoal($pdo, $userId);
$plan   = fetchActivePlan($pdo, $userId);

$libraryByGroup = [];
if ($plan !== null) {
    foreach (fetchExerciseLibrary($pdo) as $lib) {
        $libraryByGroup[$lib['muscle_group']][] = $lib;
    }
}

$dayNames    = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];
$goalLabels  = ['LOSE_FAT' => 'Giảm mỡ', 'MAINTAIN' => 'Duy trì', 'GAIN_MUSCLE' => 'Tăng cơ'];
$levelLabels = ['BEGINNER' => 'Người mới', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Nâng cao'];

// Thống kê volume
$totalExercises = 0;
$totalSets = 0;
$volumeByMuscle = [];
$daysHaveWorkout = array_fill(1, 7, 0);

if ($plan !== null) {
    foreach ($plan['days'] as $dow => $rows) {
        $daysHaveWorkout[$dow] = count($rows);
        $totalExercises += count($rows);
        foreach ($rows as $row) {
            $totalSets += $row['sets'];
            $mg = $row['muscle_group'];
            $volumeByMuscle[$mg] = ($volumeByMuscle[$mg] ?? 0) + $row['sets'];
        }
    }
}

$pageTitle = 'Lịch tập của bạn';
include __DIR__ . '/includes/header.php';
?>

<style>
/* CSS Core (giữ nguyên) */
.wp-container { max-width: 1000px; margin: 0 auto; padding: 20px; font-family: sans-serif; background-color: #f8fafc; border-radius: 12px; }
.wp-header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
.wp-header-top h1 { margin: 0; font-size: 24px; color: #111; }
.wp-actions { display: flex; gap: 10px; }
.wp-btn { padding: 8px 16px; border-radius: 20px; border: 1px solid #ccc; background: #fff; cursor: pointer; text-decoration: none; color: #333; font-weight: 500; font-size: 14px; }
.wp-btn--primary { background: #e04a1f; color: #fff; border-color: #e04a1f; }

.wp-overview-card { background: #fff; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
.wp-overview-card h3 { font-size: 12px; color: #666; text-transform: uppercase; margin: 0 0 5px 0; }
.wp-overview-card h2 { margin: 0 0 15px 0; font-size: 22px; color: #222; }
.wp-tags { display: flex; gap: 10px; flex-wrap: wrap; }
.wp-tag { padding: 5px 12px; background: #fdefeb; color: #b7411e; border-radius: 15px; font-size: 13px; font-weight: 500; }

.wp-tabs { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
.wp-tab { flex: 1; min-width: 80px; padding: 12px 5px; text-align: center; background: #fff; border: 1px solid #eee; border-radius: 12px; cursor: pointer; color: #888; transition: all 0.2s; }
.wp-tab span { display: block; font-size: 11px; margin-top: 4px; }
.wp-tab.active { background: #e04a1f; color: #fff; border-color: #e04a1f; font-weight: bold; }
.wp-tab.active span { color: rgba(255,255,255,0.9); }

.wp-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
@media (max-width: 768px) { .wp-grid { grid-template-columns: 1fr; } }

.wp-day-content { display: none; background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
.wp-day-content.active { display: block; }
.wp-day-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
.wp-day-header h3 { margin: 0; font-size: 16px; }
.wp-day-header span { font-size: 13px; color: #666; }

.wp-exercise-list { list-style: none; padding: 0; margin: 0; }
.wp-exercise-item { padding: 12px 0; border-bottom: 1px solid #f5f5f5; display: flex; flex-direction: column; }
.wp-exercise-item:last-child { border-bottom: none; }
.wp-ex-row { display: flex; align-items: center; width: 100%; }
.wp-ex-number { width: 24px; height: 24px; background: #fdefeb; color: #e04a1f; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; margin-right: 15px; flex-shrink: 0; }
.wp-ex-muscle { font-size: 11px; font-weight: 600; color: #666; width: 48px; height: 48px; background-color: #f1f5f9; border-radius: 10px; display: flex; align-items: center; justify-content: center; text-transform: uppercase; flex-shrink: 0; margin-right: 15px; }
.wp-ex-info { flex: 1; min-width: 0; }
.wp-ex-name { font-weight: 600; color: #222; font-size: 15px; display: block; margin-bottom: 2px; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wp-ex-name:hover { text-decoration: underline; }
.wp-ex-muscle-sub { font-size: 12px; color: #888; }
.wp-ex-sets { background: #111; color: #fff; padding: 5px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; margin-right: 15px; display: flex; align-items: center; gap: 4px; white-space: nowrap; }
.wp-ex-play { width: 28px; height: 28px; background: #f5f5f5; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #333; font-size: 12px; margin-right: 10px; }

/* Nút Ghi Log */
.wp-ex-log-btn { background: #fdefeb; border: none; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; color: #e04a1f; cursor: pointer; transition: 0.2s; white-space: nowrap; }
.wp-ex-log-btn:hover { background: #fad8d0; }

.wp-volume-card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); align-self: start; }
.wp-volume-card h3 { margin: 0 0 5px 0; font-size: 16px; }
.wp-volume-card p { font-size: 12px; color: #666; margin: 0 0 20px 0; }
.wp-vol-item { display: flex; align-items: center; margin-bottom: 12px; }
.wp-vol-label { width: 60px; font-size: 13px; font-weight: 500; color: #333; }
.wp-vol-bar-bg { flex: 1; height: 8px; background: #eee; border-radius: 4px; margin: 0 10px; position: relative; overflow: hidden; }
.wp-vol-bar-fill { position: absolute; left: 0; top: 0; height: 100%; background: #e04a1f; border-radius: 4px; }
.wp-vol-value { font-size: 13px; font-weight: bold; width: 20px; text-align: right; }
.wp-vol-note { font-size: 11px; color: #888; margin-top: 15px; line-height: 1.4; }

/* CSS Form Ghi log (Ép phẳng margin tuyệt đối) */
.wp-log-section { display: none; margin-top: 15px; padding: 15px; background: #fdfdfd; border-radius: 10px; border: 1px solid #eaeaea; margin-left: 39px; }
.wp-log-history { font-size: 13px; color: #666; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.wp-log-history-badge { background: #e04a1f; color: #fff; padding: 3px 8px; border-radius: 6px; font-weight: bold; font-size: 11px; }
.wp-log-inputs { display: flex; gap: 10px; align-items: flex-end !important; flex-wrap: wrap; margin: 0 !important; }
.wp-log-input-group { display: flex; flex-direction: column; gap: 4px; margin: 0 !important; padding: 0 !important; }
.wp-log-input-group label { font-size: 11px; color: #888; font-weight: 600; text-transform: uppercase; margin: 0 !important; line-height: 1; }
.wp-log-input-group input { width: 80px; height: 36px !important; box-sizing: border-box !important; padding: 0 8px !important; border: 1px solid #ddd; border-radius: 6px; text-align: center; outline: none; margin: 0 !important; font-size: 14px; }
.wp-log-submit { background: #e04a1f; color: #fff; border: none; padding: 0 20px; height: 36px !important; box-sizing: border-box !important; border-radius: 6px; font-weight: 600; cursor: pointer; margin: 0 !important; align-self: flex-end !important; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.wp-log-submit:hover { background: #c0392b; }

/* CSS Form Edit B11 */
.workout-plan__message { margin: 10px 0; font-size: 14px; }
.workout-plan__edit, .workout-plan__edit-only { display: none; }
.workout-plan--editing .workout-plan__edit { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 8px; width: 100%; border: 1px dashed #ccc; }
.workout-plan--editing .workout-plan__edit-only { display: block; margin-top: 20px; }
.workout-plan__edit input, .workout-plan__edit select { padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
.workout-plan__edit input[type="number"] { width: 60px; }

/* CSS Form Create */
.wp-form-container { background: #fcfcfc; padding: 20px; border-radius: 12px; border: 1px solid #eee; display: grid; grid-template-columns: 1fr 1fr auto; gap: 20px; align-items: end; }
@media (max-width: 600px) { .wp-form-container { grid-template-columns: 1fr; } }
.wp-form-group { display: flex; flex-direction: column; gap: 8px; margin: 0; }
.wp-form-label { font-size: 13px; font-weight: 600; color: #444; margin: 0; line-height: 1; }
.wp-form-control, .wp-btn-submit { height: 44px !important; box-sizing: border-box !important; margin: 0 !important; }
.wp-form-control { padding: 0 16px !important; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; color: #333; background-color: #fff; outline: none; transition: 0.2s; width: 100%; }
.wp-btn-submit { padding: 0 28px !important; border-radius: 8px; font-weight: 600; display: flex; align-items: center; justify-content: center; }
.wp-alert { background: #fdefeb; color: #c0392b; padding: 12px 16px; border-radius: 8px; margin-top: 15px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
</style>

<main class="workout-plan wp-container" id="workout-plan-root">
    
    <div class="wp-header-top">
        <h1>Lịch tập của bạn</h1>
        <div class="wp-actions">
            <?php if ($plan !== null): ?>
                <button type="button" class="wp-btn" id="workout-plan-toggle-create">Tạo lịch mới</button>
                <a href="api/export-workout-plan.php" class="wp-btn">Xuất lịch tập</a>
                <button type="button" class="wp-btn wp-btn--primary" id="workout-plan-toggle-edit">Chỉnh sửa lịch</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- FORM TẠO LỊCH -->
    <section class="wp-overview-card" id="create-plan-section" style="<?= $plan !== null ? 'display: none;' : '' ?>">
        <div style="margin-bottom: 20px;">
            <h2 style="font-size: 20px; margin: 0 0 6px 0; color: #111;">Khởi tạo lịch tập</h2>
            <p style="margin: 0; font-size: 14px; color: #666;">Thiết lập thông số để hệ thống gợi ý cấu trúc lịch tập phù hợp nhất với bạn.</p>
        </div>

        <form id="workout-plan-form" data-has-plan="<?= $plan ? '1' : '0' ?>" class="wp-form-container">
            <div class="wp-form-group">
                <label class="wp-form-label">Số ngày tập / tuần</label>
                <select name="days_per_week" class="wp-form-control">
                    <?php for ($d = 2; $d <= 6; $d++): ?>
                        <option value="<?= $d ?>" <?= $d === 3 ? 'selected' : '' ?>><?= $d ?> ngày</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="wp-form-group">
                <label class="wp-form-label">Trình độ</label>
                <select name="level" class="wp-form-control">
                    <?php foreach (WORKOUT_LEVELS as $lv): ?>
                        <option value="<?= htmlspecialchars($lv) ?>"><?= htmlspecialchars($levelLabels[$lv] ?? $lv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="wp-btn wp-btn--primary wp-btn-submit" <?= $goal === null ? 'disabled' : '' ?>>Tạo lịch ngay</button>
        </form>

        <?php if ($goal === null): ?>
            <div class="wp-alert">
                <strong>Chú ý:</strong> Bạn chưa chọn mục tiêu. Hãy cập nhật trong <a href="profile.php" style="color: #c0392b; text-decoration: underline;">hồ sơ</a> trước khi tạo lịch.
            </div>
        <?php endif; ?>
        <p id="workout-plan-message" class="workout-plan__message" role="status"></p>
    </section>

    <?php if ($plan !== null): ?>
        
        <section class="wp-overview-card">
            <h3>Lịch đang dùng</h3>
            <h2><?= htmlspecialchars($plan['split_type']) ?> · <?= (int)$plan['days_per_week'] ?> ngày/tuần</h2>
            <div class="wp-tags">
                <span class="wp-tag">Mục tiêu: <?= htmlspecialchars($goalLabels[$goal] ?? $goal) ?></span>
                <span class="wp-tag">Trình độ: Trung bình</span>
                <span class="wp-tag"><?= $totalExercises ?> bài · <?= $totalSets ?> set / tuần</span>
            </div>
        </section>

        <?php 
        $firstActiveDay = 1;
        for ($i = 1; $i <= 7; $i++) {
            if ($daysHaveWorkout[$i] > 0) { $firstActiveDay = $i; break; }
        }
        ?>
        <nav class="wp-tabs" id="day-tabs">
            <?php for ($i = 1; $i <= 7; $i++): 
                $count = $daysHaveWorkout[$i];
                $isActive = ($i === $firstActiveDay) ? 'active' : '';
            ?>
                <div class="wp-tab <?= $isActive ?>" data-day="<?= $i ?>">
                    <?= $i === 7 ? 'CN' : 'T' . ($i + 1) ?>
                    <span><?= $count > 0 ? $count . ' bài' : 'Nghỉ' ?></span>
                </div>
            <?php endfor; ?>
        </nav>

        <div class="wp-grid">
            <div class="wp-daily-lists">
                <?php for ($i = 1; $i <= 7; $i++): 
                    $count = $daysHaveWorkout[$i];
                    $isActive = ($i === $firstActiveDay) ? 'active' : '';
                ?>
                    <div class="wp-day-content <?= $isActive ?>" id="day-content-<?= $i ?>">
                        <?php if ($count === 0): ?>
                            <div style="text-align: center; padding: 40px 0; color: #888;">Hôm nay là ngày nghỉ ngơi phục hồi cơ bắp.</div>
                        <?php else: ?>
                            <div class="wp-day-header">
                                <h3><?= htmlspecialchars($dayNames[$i] ?? ('Ngày ' . $i)) ?></h3>
                                <span><?= $count ?> bài · <?= array_sum(array_column($plan['days'][$i], 'sets')) ?> set</span>
                            </div>
                            <ul class="wp-exercise-list">
                                <?php 
                                $index = 1;
                                foreach ($plan['days'][$i] as $row): 
                                    $muscleName = WORKOUT_MUSCLE_LABELS[$row['muscle_group']] ?? $row['muscle_group'];
                                    
                                    // GỌI DB LẤY LỊCH SỬ TẬP
                                    $lastLog = fetchLastWorkoutLog($pdo, $userId, $row['exercise_id']);
                                ?>
                                    <li class="wp-exercise-item" data-row-id="<?= (int)$row['row_id'] ?>">
                                        <div class="wp-ex-row">
                                            <div class="wp-ex-number"><?= $index++ ?></div>
                                            <div class="wp-ex-muscle"><?= mb_substr($muscleName, 0, 4) ?></div>
                                            <div class="wp-ex-info">
                                                <a href="exercise-detail.php?id=<?= (int)$row['exercise_id'] ?>" class="wp-ex-name"><?= htmlspecialchars($row['name']) ?></a>
                                                <span class="wp-ex-muscle-sub"><?= htmlspecialchars($muscleName) ?></span>
                                            </div>
                                            <div class="wp-ex-sets">
                                                <?= (int)$row['sets'] ?> <span style="font-size:10px">×</span> <?= (int)$row['reps'] ?>
                                            </div>
                                            
                                            <div class="wp-ex-play">▶</div>
                                            <!-- Nút mở form Ghi Log -->
                                            <button class="wp-ex-log-btn" onclick="toggleLogForm(this)">Ghi Log</button>
                                        </div>
                                        
                                        <!-- FORM GHI LOG TỪNG BÀI (Progressive Overload) -->
                                        <div class="wp-log-section">
                                            <div class="wp-log-history">
                                                <?php if ($lastLog): ?>
                                                    <span class="wp-log-history-badge">Lần tập trước</span> 
                                                    <?= date('d/m/Y', strtotime($lastLog['log_date'])) ?>: 
                                                    <strong><?= $lastLog['weight_kg_used'] ?>kg</strong> × <?= $lastLog['sets_completed'] ?> sets × <?= $lastLog['reps_completed'] ?> reps
                                                <?php else: ?>
                                                    <span>Chưa có dữ liệu tập luyện trước đó. Lần này hãy khởi đầu nhé!</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <form class="wp-log-inputs" onsubmit="submitLog(event, this)">
                                                <input type="hidden" name="row_id" value="<?= $row['row_id'] ?>">
                                                <input type="hidden" name="exercise_id" value="<?= $row['exercise_id'] ?>">
                                                
                                                <div class="wp-log-input-group">
                                                    <label>Mức tạ (kg)</label>
                                                    <input type="number" name="weight" step="0.5" placeholder="VD: 50" value="<?= $lastLog ? $lastLog['weight_kg_used'] : '' ?>" required>
                                                </div>
                                                <div class="wp-log-input-group">
                                                    <label>Số Set</label>
                                                    <input type="number" name="sets" value="<?= $row['sets'] ?>" required>
                                                </div>
                                                <div class="wp-log-input-group">
                                                    <label>Số Rep</label>
                                                    <input type="number" name="reps" value="<?= $row['reps'] ?>" required>
                                                </div>
                                                <button type="submit" class="wp-log-submit">Lưu lại</button>
                                            </form>
                                        </div>

                                        <!-- UI chỉnh sửa B11 ẩn mặc định -->
                                        <div class="workout-plan__edit">
                                            <label>Set <input type="number" name="sets" min="1" max="<?= PLAN_EDIT_MAX_SETS ?>" value="<?= (int)$row['sets'] ?>"></label>
                                            <label>Lặp <input type="number" name="reps" min="1" max="<?= PLAN_EDIT_MAX_REPS ?>" value="<?= (int)$row['reps'] ?>"></label>
                                            <button type="button" class="wp-btn" data-action="update">Lưu</button>
                                            <select name="new_exercise_id">
                                                <option value="">Đổi sang bài khác...</option>
                                                <?php foreach ($libraryByGroup[$row['muscle_group']] ?? [] as $lib): ?>
                                                    <?php if ((int)$lib['id'] === (int)$row['exercise_id']) continue; ?>
                                                    <option value="<?= (int)$lib['id'] ?>"><?= htmlspecialchars($lib['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="wp-btn" data-action="replace">Đổi</button>
                                            <button type="button" class="wp-btn" style="color:#c0392b;" data-action="remove">Xóa</button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
                
                <!-- Thêm bài vào bất kỳ ngày nào -->
                <div class="wp-overview-card workout-plan__add workout-plan__edit-only">
                    <h3>Thêm bài tập mới</h3>
                    <div class="workout-plan__edit workout-plan__edit--add" style="display:flex; border:none; padding:0; margin-top:10px;">
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
                        <label>Lặp <input type="number" name="reps" min="1" max="<?= PLAN_EDIT_MAX_REPS ?>" value="10"></label>
                        <button type="button" class="wp-btn wp-btn--primary" data-action="add">Thêm vào lịch</button>
                    </div>
                </div>
            </div>

            <aside class="wp-volume-card">
                <h3>Khối lượng tuần</h3>
                <p>Set mỗi nhóm cơ</p>
                <?php 
                $muscles = ['LEGS'=>'Chân', 'CHEST'=>'Ngực', 'BACK'=>'Lưng', 'SHOULDERS'=>'Vai', 'ARMS'=>'Tay'];
                foreach ($muscles as $key => $label): 
                    $sets = $volumeByMuscle[$key] ?? 0;
                    $percent = min(100, ($sets / 15) * 100);
                ?>
                    <div class="wp-vol-item">
                        <div class="wp-vol-label"><?= $label ?></div>
                        <div class="wp-vol-bar-bg">
                            <div class="wp-vol-bar-fill" style="width: <?= $percent ?>%;"></div>
                        </div>
                        <div class="wp-vol-value"><?= $sets ?></div>
                    </div>
                <?php endforeach; ?>
                
                <div class="wp-vol-note">
                    Mốc tham chiếu: khoảng 10-15 set/nhóm cơ/tuần để tăng cơ (ACSM 2026).
                </div>
            </aside>
        </div>
    <?php endif; ?>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const tabs = document.querySelectorAll('.wp-tab');
    const contents = document.querySelectorAll('.wp-day-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const day = this.dataset.day;
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('day-content-' + day).classList.add('active');
        });
    });

    const toggleCreateBtn = document.getElementById('workout-plan-toggle-create');
    if (toggleCreateBtn) {
        toggleCreateBtn.addEventListener('click', function() {
            const createSec = document.getElementById('create-plan-section');
            if (createSec.style.display === 'none') {
                createSec.style.display = 'block';
                createSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                createSec.style.display = 'none';
            }
        });
    }
});

// JS xử lý form Ghi Log (Vanilla JS, dùng fetch AJAX)
function toggleLogForm(btn) {
    const item = btn.closest('.wp-exercise-item');
    const logSection = item.querySelector('.wp-log-section');
    if (logSection.style.display === 'none' || logSection.style.display === '') {
        logSection.style.display = 'block';
    } else {
        logSection.style.display = 'none';
    }
}

async function submitLog(e, form) {
    e.preventDefault();
    const btn = form.querySelector('.wp-log-submit');
    const originalText = btn.innerText;
    
    btn.disabled = true;
    btn.innerText = 'Đang lưu...';
    
    try {
        const formData = new FormData(form);
        const res = await fetch('api/log-workout.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if (data.success) {
            btn.innerText = 'Lưu thành công!';
            btn.style.background = '#27ae60';
            
            setTimeout(() => {
                const item = btn.closest('.wp-exercise-item');
                item.querySelector('.wp-log-section').style.display = 'none'; // Tự động đóng lại
                btn.innerText = originalText;
                btn.disabled = false;
                btn.style.background = '#111';
                
                // Mẹo: Cập nhật lại DOM "Lần trước" bằng JS ngay lập tức để UX tốt hơn (nếu có thời gian làm sau)
            }, 1000);
        } else {
            alert(data.message || 'Lỗi lưu trữ');
            btn.disabled = false;
            btn.innerText = originalText;
        }
    } catch (err) {
        alert('Lỗi kết nối máy chủ');
        btn.disabled = false;
        btn.innerText = originalText;
    }
}
</script>

<script src="js/workout-plan.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>