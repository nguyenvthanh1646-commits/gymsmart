<?php
/**
 * dash-week.php — thẻ "Tuần này": vòng tiến độ và dải 7 ngày.
 * Mỗi ngày có ký hiệu và chữ mô tả, không chỉ phân biệt bằng màu.
 */
$week = $dash['week'];

// status => [class, ký hiệu, chữ hiện trên ô, chữ cho trình đọc màn hình]
$duDayStates = [
    'done'     => ['is-done', '✓', 'Đã tập', 'đã tập'],
    'today'    => ['is-today-pending', '•', 'Hôm nay', 'có buổi hôm nay, chưa tập'],
    'missed'   => ['is-missed', '✕', 'Chưa tập', 'chưa tập'],
    'upcoming' => ['is-upcoming', '○', 'Sắp tới', 'sắp tới'],
    'rest'     => ['is-rest', '–', 'Nghỉ', 'nghỉ'],
    'extra'    => ['is-extra', '+', 'Tập thêm', 'tập thêm ngoài lịch'],
];
?>
<section class="du-week" aria-labelledby="du-week-title">
    <?php if ($week['status'] === 'ok'): ?>
        <div class="du-week__summary" data-week data-done="<?= (int)$week['done'] ?>" data-planned="<?= (int)$week['planned'] ?>">
            <div class="du-ring" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$week['percent'] ?>"
                 aria-label="Đã tập <?= (int)$week['done'] ?> trên <?= (int)$week['planned'] ?> buổi trong tuần" style="--du-value: <?= (int)$week['percent'] ?>%">
                <span class="du-ring__text"><b data-week-done><?= (int)$week['done'] ?></b>/<?= (int)$week['planned'] ?></span>
            </div>
            <div>
                <h2 class="du-week__title" id="du-week-title">Tuần này</h2>
                <p class="du-week__sub">
                    <?= $week['done'] === 0 ? 'Ghi bài đầu tiên để bắt đầu tính tiến độ' : 'buổi đã tập theo lịch' ?>
                </p>
                <a class="du-link du-link--inline" href="/workout-plan.php">Xem lịch tập</a>
            </div>
        </div>
        <ol class="du-days">
            <?php foreach ($week['days'] as $day):
                [$dayClass, $dayMark, $dayLabel, $dayText] = $duDayStates[$day['status']];
            ?>
                <li class="du-day <?= htmlspecialchars($dayClass) ?><?= $day['isToday'] ? ' is-current' : '' ?>"<?= $day['isToday'] ? ' data-week-today' : '' ?>>
                    <span class="du-day__name" aria-hidden="true"><?= htmlspecialchars(DASH_DAY_SHORT[$day['day']]) ?></span>
                    <span class="du-day__mark" aria-hidden="true" data-week-mark><?= htmlspecialchars($dayMark) ?></span>
                    <span class="du-day__label" aria-hidden="true" data-week-label><?= htmlspecialchars($dayLabel) ?></span>
                    <span class="du-sr"><?= htmlspecialchars(DASH_DAY_NAMES[$day['day']]) ?><?= $day['isToday'] ? ' (hôm nay)' : '' ?>: <span data-week-state><?= htmlspecialchars($dayText) ?></span><?php if ($day['total'] > 0): ?>, <span data-week-logged><?= (int)$day['logged'] ?></span>/<?= (int)$day['total'] ?> bài<?php endif; ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <div class="du-week__empty">
            <h2 class="du-week__title" id="du-week-title">Tuần này</h2>
            <?php if ($week['status'] === 'error'): ?>
                <?php duError('tiến độ tuần này'); ?>
            <?php elseif (!empty($missingFields)): ?>
                <?php duState('calendar', 'Chưa có buổi nào để theo dõi', 'Hoàn thiện hồ sơ rồi tạo lịch tập, tiến độ từng tuần sẽ hiện ở đây.', '/profile.php', 'Hoàn thiện hồ sơ'); ?>
            <?php else: ?>
                <?php duState('calendar', 'Chưa có buổi nào để theo dõi', 'Sau khi tạo lịch tập, các buổi đã tập và chưa tập trong tuần sẽ hiện ở đây.', '/workout-plan.php', 'Tạo lịch tập'); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
