<?php
/**
 * dash-today.php — thẻ "Hôm nay": buổi tập có ảnh từng bài và ghi nhật ký nhanh, hoặc ngày nghỉ và buổi kế tiếp.
 * Form ghi nhanh gửi tới api/log-workout.php (xử lý trong js/home-user.js). Thiếu JS thì vẫn có liên kết sang trang Lịch tập.
 */

/** Ảnh nhỏ của bài tập. Tên bài đã có ngay bên cạnh nên alt rỗng. */
function duExerciseThumb(?string $imageFile): void
{
    $src = exerciseImageSrc($imageFile);
    if ($src === null) {
        echo '<span class="du-thumb du-thumb--none">' . hmIcon('dumbbell', 22) . '</span>';
        return;
    }
    echo '<img class="du-thumb" src="' . htmlspecialchars($src) . '" alt="" width="800" height="448" loading="lazy" decoding="async">';
}
?>
<section class="du-card du-card--today" id="hom-nay" aria-labelledby="du-today-title">
    <div class="du-card__head">
        <?php duCardTitle('dumbbell', 'du-today-title', 'Hôm nay'); ?>
        <?php if ($session['summary'] !== null): ?>
            <span class="du-card__meta"><b data-today-count data-total="<?= (int)$session['summary']['count'] ?>"><?= (int)$session['logged'] ?></b>/<?= (int)$session['summary']['count'] ?> bài đã ghi</span>
        <?php endif; ?>
    </div>

    <?php if ($planStatus === 'error'): ?>
        <?php duError('buổi tập hôm nay'); ?>
    <?php elseif ($planStatus === 'none' && !empty($missingFields)): ?>
        <?php duState('user', 'Chưa có lịch tập', 'Lịch tập dựa trên trình độ và mục tiêu của bạn, nên cần hoàn thiện hồ sơ trước.', '/profile.php', 'Hoàn thiện hồ sơ'); ?>
    <?php elseif ($planStatus === 'none'): ?>
        <?php duState('calendar', 'Chưa có lịch tập', 'Chọn số buổi mỗi tuần, GymSmart sẽ gợi ý lịch theo trình độ và mục tiêu của bạn.', '/workout-plan.php', 'Tạo lịch tập'); ?>
    <?php elseif ($session['summary'] === null): ?>
        <div class="du-rest">
            <span class="du-rest__icon"><?= hmIcon('moon', 28) ?></span>
            <div>
                <p class="du-rest__title">Ngày nghỉ</p>
                <p class="du-rest__text">Hôm nay không có buổi tập theo lịch.</p>
            </div>
        </div>
        <?php if ($session['next'] !== null): $next = $session['next']; ?>
            <div class="du-next">
                <p class="du-next__head">Buổi kế tiếp: <b><?= htmlspecialchars(DASH_DAY_NAMES[$next['day']]) ?><?= $next['nextWeek'] ? ' tuần sau' : '' ?></b></p>
                <p class="du-next__meta"><?= htmlspecialchars($next['muscles']) ?> · <?= (int)$next['count'] ?> bài · khoảng <?= (int)$next['minutes'] ?> phút</p>
                <ul class="du-next__list">
                    <?php foreach (array_slice($next['rows'], 0, 4) as $nextRow): ?>
                        <li>
                            <?php duExerciseThumb($nextRow['image']); ?>
                            <span><?= htmlspecialchars($nextRow['name']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <a class="du-link" href="/workout-plan.php">Xem lịch tập</a>
    <?php else: $summary = $session['summary']; ?>
        <p class="du-session">
            <?php if ($summary['label'] !== ''): ?><b>Buổi <?= htmlspecialchars($summary['label']) ?></b> · <?php endif; ?>
            <?= htmlspecialchars($summary['muscles']) ?> · <?= (int)$summary['count'] ?> bài · khoảng <?= (int)$summary['minutes'] ?> phút
        </p>
        <ul class="du-exercises">
            <?php foreach ($session['rows'] as $row):
                $rowId = (int)$row['row_id'];
                $lastWeight = $session['weights'][$row['exercise_id']] ?? null;
            ?>
                <li class="du-ex<?= $row['logged'] ? ' is-logged' : '' ?>" data-log-row>
                    <div class="du-ex__line">
                        <?php duExerciseThumb($row['image']); ?>
                        <div class="du-ex__info">
                            <a href="/exercise-detail.php?id=<?= (int)$row['exercise_id'] ?>"><?= htmlspecialchars($row['name']) ?></a>
                            <small><?= (int)$row['sets'] ?> hiệp × <?= (int)$row['reps'] ?> lần<?= $lastWeight !== null ? ' · lần trước ' . htmlspecialchars(dashNumber($lastWeight)) . ' kg' : '' ?></small>
                        </div>
                        <?php if ($row['logged']): ?>
                            <span class="du-ex__done">Đã ghi</span>
                        <?php else: ?>
                            <button type="button" class="du-ex__toggle" data-log-toggle aria-expanded="false" aria-controls="du-log-<?= (int)$rowId ?>">Ghi nhanh</button>
                        <?php endif; ?>
                    </div>
                    <?php if (!$row['logged']): ?>
                        <form class="du-log" id="du-log-<?= (int)$rowId ?>" method="post" action="/api/log-workout.php" data-log-form data-no-guard hidden>
                            <?= csrfField() ?>
                            <input type="hidden" name="row_id" value="<?= (int)$rowId ?>">
                            <input type="hidden" name="exercise_id" value="<?= (int)$row['exercise_id'] ?>">
                            <label>Hiệp
                                <input type="number" name="sets" min="1" max="<?= (int)PLAN_EDIT_MAX_SETS ?>" value="<?= (int)$row['sets'] ?>" required>
                            </label>
                            <label>Lần
                                <input type="number" name="reps" min="1" max="<?= (int)PLAN_EDIT_MAX_REPS ?>" value="<?= (int)$row['reps'] ?>" required>
                            </label>
                            <label>Tạ (kg)
                                <input type="number" name="weight" min="0" max="500" step="0.5" value="<?= $lastWeight !== null ? htmlspecialchars((string)$lastWeight) : '' ?>">
                            </label>
                            <button type="submit" class="du-log__save">Lưu</button>
                            <p class="du-log__msg" role="status" data-log-msg></p>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <a class="du-link" href="/workout-plan.php">Mở lịch tập đầy đủ</a>
    <?php endif; ?>
</section>
