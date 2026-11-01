<?php
/**
 * dash-week.php — thẻ "Tuần này": thanh tiến độ và dải 7 ngày.
 * Mỗi ngày có ký hiệu và chữ cho trình đọc màn hình, không chỉ phân biệt bằng màu.
 */
$week = $dash['week'];

// status => [class, ký hiệu, chữ mô tả]
$duDayStates = [
    'done'     => ['is-done', '✓', 'đã tập'],
    'today'    => ['is-today-pending', '•', 'có buổi hôm nay, chưa tập'],
    'missed'   => ['is-missed', '✕', 'chưa tập'],
    'upcoming' => ['is-upcoming', '○', 'sắp tới'],
    'rest'     => ['is-rest', '–', 'nghỉ'],
    'extra'    => ['is-extra', '+', 'tập thêm ngoài lịch'],
];
?>
<section class="du-card du-card--week" aria-labelledby="du-week-title">
    <?php if ($week['status'] === 'ok'): ?>
        <div data-week data-done="<?= (int)$week['done'] ?>" data-planned="<?= (int)$week['planned'] ?>">
            <div class="du-card__head">
                <h2 id="du-week-title">Tuần này</h2>
                <span class="du-card__meta"><b data-week-done><?= (int)$week['done'] ?></b>/<?= (int)$week['planned'] ?> buổi</span>
            </div>
            <div class="du-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$week['percent'] ?>"
                 aria-label="Đã tập <?= (int)$week['done'] ?> trên <?= (int)$week['planned'] ?> buổi trong tuần"><i style="--du-value: <?= (int)$week['percent'] ?>%"></i></div>
        </div>
        <ol class="du-days">
            <?php foreach ($week['days'] as $day):
                [$dayClass, $dayMark, $dayText] = $duDayStates[$day['status']];
            ?>
                <li class="du-day <?= $dayClass ?><?= $day['isToday'] ? ' is-current' : '' ?>"<?= $day['isToday'] ? ' data-week-today' : '' ?>>
                    <span class="du-day__name" aria-hidden="true"><?= DASH_DAY_SHORT[$day['day']] ?></span>
                    <span class="du-day__mark" aria-hidden="true" data-week-mark><?= htmlspecialchars($dayMark) ?></span>
                    <span class="du-sr"><?= DASH_DAY_NAMES[$day['day']] ?><?= $day['isToday'] ? ' (hôm nay)' : '' ?>: <span data-week-state><?= $dayText ?></span><?php if ($day['total'] > 0): ?>, <span data-week-logged><?= (int)$day['logged'] ?></span>/<?= (int)$day['total'] ?> bài<?php endif; ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
        <p class="du-legend" aria-hidden="true">✓ đã tập · ○ sắp tới · ✕ chưa tập · – nghỉ · + tập thêm</p>
        <?php if ($week['done'] === 0): ?>
            <p class="du-note">Ghi bài đầu tiên của một buổi để buổi đó được tính là đã tập.</p>
        <?php endif; ?>
        <a class="du-link" href="/workout-plan.php">Xem lịch tập</a>
    <?php else: ?>
        <div class="du-card__head"><h2 id="du-week-title">Tuần này</h2></div>
        <?php if ($week['status'] === 'error'): ?>
            <?php duError('tiến độ tuần này'); ?>
        <?php elseif (!empty($missingFields)): ?>
            <?php duState('Chưa có buổi nào để theo dõi', 'Hoàn thiện hồ sơ rồi tạo lịch tập, tiến độ từng tuần sẽ hiện ở đây.', '/profile.php', 'Hoàn thiện hồ sơ'); ?>
        <?php else: ?>
            <?php duState('Chưa có buổi nào để theo dõi', 'Sau khi tạo lịch tập, các buổi đã tập và chưa tập trong tuần sẽ hiện ở đây.', '/workout-plan.php', 'Tạo lịch tập'); ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
