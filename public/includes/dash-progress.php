<?php
/**
 * dash-progress.php — thẻ tiến trình: cân nặng mới nhất, xu hướng và biểu đồ nhỏ vẽ bằng SVG từ body_metrics
 * (tự vẽ nên trang chủ không phải tải Chart.js). Biểu đồ đầy đủ nằm ở trang Tiến trình.
 */
$progress = $dash['progress'];
?>
<section class="du-card du-card--progress" aria-labelledby="du-progress-title">
    <div class="du-card__head"><?php duCardTitle('chart', 'du-progress-title', 'Tiến trình'); ?></div>

    <?php if ($progress['status'] === 'error'): ?>
        <?php duError('số đo cơ thể'); ?>
    <?php elseif ($progress['status'] === 'none'): ?>
        <?php duState('chart', 'Chưa có số đo', 'Ghi cân nặng đầu tiên, sau vài lần đo bạn sẽ thấy xu hướng thay đổi ở đây.', '/progress.php', 'Ghi cân nặng'); ?>
    <?php else:
        $latest = $progress['latest'];
        $trend = $progress['trend'];
        $dateOf = fn(string $d) => (new DateTimeImmutable($d))->format('d/m');
    ?>
        <div class="du-weight">
            <p class="du-weight__value"><b><?= htmlspecialchars(dashNumber((float)$latest['weight_kg'])) ?></b> kg</p>
            <?php if ($trend !== null):
                $sign = $trend['delta'] > 0 ? '+' : ($trend['delta'] < 0 ? '−' : '');
            ?>
                <span class="du-pill"><?= htmlspecialchars($sign . dashNumber(abs($trend['delta']))) ?> kg</span>
            <?php endif; ?>
        </div>
        <?php if ($trend === null): ?>
            <p class="du-note">Đo ngày <?= htmlspecialchars($dateOf($latest['recorded_date'])) ?>. Cần thêm ít nhất 1 lần đo để thấy xu hướng.</p>
        <?php else:
            $profileGoal = $row['goal'] ?? '';
            $sparkPoints = dashSparklinePoints($progress['metrics'], 240, 64);
        ?>
            <?php if ($trend['matches'] !== null): ?>
                <p class="du-note du-note--tight">
                    <?php if ($profileGoal === 'MAINTAIN'): ?>
                        <?= $trend['matches'] ? 'Đang giữ được cân nặng theo mục tiêu duy trì.' : 'Cân nặng đang lệch khỏi mục tiêu duy trì.' ?>
                    <?php else: ?>
                        <?= $trend['matches'] ? 'Đúng hướng mục tiêu ' : 'Chưa theo hướng mục tiêu ' ?><?= htmlspecialchars(DASH_GOAL_LABELS[$profileGoal]) ?>.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <svg class="du-spark" viewBox="0 0 240 64" preserveAspectRatio="none" role="img"
                 aria-label="Biểu đồ cân nặng từ <?= htmlspecialchars(dashNumber((float)$progress['metrics'][0]['weight_kg'])) ?> kg ngày <?= htmlspecialchars($dateOf($trend['from'])) ?> đến <?= htmlspecialchars(dashNumber((float)$latest['weight_kg'])) ?> kg ngày <?= htmlspecialchars($dateOf($latest['recorded_date'])) ?>">
                <defs>
                    <linearGradient id="du-spark-fill" x1="0" y1="0" x2="0" y2="1">
                        <stop class="du-spark__stop" offset="0" stop-opacity=".28"/>
                        <stop class="du-spark__stop" offset="1" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                <polygon class="du-spark__area" points="0,64 <?= htmlspecialchars($sparkPoints) ?> 240,64"/>
                <polyline class="du-spark__line" points="<?= htmlspecialchars($sparkPoints) ?>"/>
            </svg>
            <p class="du-spark__axis" aria-hidden="true">
                <span><?= htmlspecialchars($dateOf($trend['from'])) ?></span>
                <span><?= htmlspecialchars($dateOf($latest['recorded_date'])) ?></span>
            </p>
        <?php endif; ?>
        <a class="du-link" href="/progress.php">Xem tiến trình đầy đủ</a>
    <?php endif; ?>
</section>
