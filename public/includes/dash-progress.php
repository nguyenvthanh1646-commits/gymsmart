<?php
/**
 * dash-progress.php — thẻ tiến trình: cân nặng mới nhất, xu hướng và biểu đồ nhỏ vẽ bằng SVG từ body_metrics
 * (tự vẽ nên trang chủ không phải tải Chart.js). Biểu đồ đầy đủ nằm ở trang Tiến trình.
 */
$progress = $dash['progress'];
?>
<section class="du-card du-card--progress" aria-labelledby="du-progress-title">
    <div class="du-card__head"><h2 id="du-progress-title">Tiến trình</h2></div>

    <?php if ($progress['status'] === 'error'): ?>
        <?php duError('số đo cơ thể'); ?>
    <?php elseif ($progress['status'] === 'none'): ?>
        <?php duState('Chưa có số đo', 'Ghi cân nặng đầu tiên, sau vài lần đo bạn sẽ thấy xu hướng thay đổi ở đây.', '/progress.php', 'Ghi cân nặng'); ?>
    <?php else:
        $latest = $progress['latest'];
        $trend = $progress['trend'];
        $dateOf = fn(string $d) => (new DateTimeImmutable($d))->format('d/m');
    ?>
        <p class="du-weight">
            <b><?= htmlspecialchars(dashNumber((float)$latest['weight_kg'])) ?></b> kg
            <small>đo ngày <?= htmlspecialchars($dateOf($latest['recorded_date'])) ?></small>
        </p>
        <?php if ($trend === null): ?>
            <p class="du-note">Cần thêm ít nhất 1 lần đo để thấy xu hướng.</p>
        <?php else:
            $sign = $trend['delta'] > 0 ? '+' : ($trend['delta'] < 0 ? '−' : '');
            $profileGoal = $row['goal'] ?? '';
        ?>
            <p class="du-trend"><b><?= $sign ?><?= htmlspecialchars(dashNumber(abs($trend['delta']))) ?> kg</b> so với <?= htmlspecialchars($dateOf($trend['from'])) ?></p>
            <?php if ($trend['matches'] !== null): ?>
                <p class="du-note">
                    <?php if ($profileGoal === 'MAINTAIN'): ?>
                        <?= $trend['matches'] ? 'Đang giữ được cân nặng theo mục tiêu duy trì.' : 'Cân nặng đang lệch khỏi mục tiêu duy trì.' ?>
                    <?php else: ?>
                        <?= $trend['matches'] ? 'Đúng hướng mục tiêu ' : 'Chưa theo hướng mục tiêu ' ?><?= htmlspecialchars(DASH_GOAL_LABELS[$profileGoal]) ?>.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <svg class="du-spark" viewBox="0 0 240 56" preserveAspectRatio="none" role="img"
                 aria-label="Biểu đồ cân nặng từ <?= htmlspecialchars(dashNumber((float)$progress['metrics'][0]['weight_kg'])) ?> kg ngày <?= htmlspecialchars($dateOf($trend['from'])) ?> đến <?= htmlspecialchars(dashNumber((float)$latest['weight_kg'])) ?> kg ngày <?= htmlspecialchars($dateOf($latest['recorded_date'])) ?>">
                <polyline points="<?= htmlspecialchars(dashSparklinePoints($progress['metrics'], 240, 56)) ?>"/>
            </svg>
        <?php endif; ?>
        <a class="du-link" href="/progress.php">Xem tiến trình đầy đủ</a>
    <?php endif; ?>
</section>
