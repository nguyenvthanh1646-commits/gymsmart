<?php
/**
 * dash-nutrition.php — thẻ dinh dưỡng: TDEE, calo mục tiêu và 3 macro từ lần tính gần nhất.
 * Dự án chưa ghi món đã ăn, nên các thanh là tỉ lệ calo của MỤC TIÊU, không phải lượng đã ăn trong ngày.
 */
$nutrition = $dash['nutrition'];
?>
<section class="du-card du-card--nutrition" aria-labelledby="du-nutrition-title">
    <div class="du-card__head"><h2 id="du-nutrition-title">Dinh dưỡng</h2></div>

    <?php if ($nutrition['status'] === 'error'): ?>
        <?php duError('mục tiêu dinh dưỡng'); ?>
    <?php elseif ($nutrition['status'] === 'none' && !empty($missingFields)): ?>
        <?php duState('Chưa tính TDEE', 'TDEE cần tuổi, chiều cao, cân nặng, giới tính và mức vận động trong hồ sơ của bạn.', '/profile.php', 'Hoàn thiện hồ sơ'); ?>
    <?php elseif ($nutrition['status'] === 'none'): ?>
        <?php duState('Chưa tính TDEE', 'Tính nhu cầu năng lượng để biết mỗi ngày nên ăn bao nhiêu calo, đạm, tinh bột và béo.', '/nutrition.php', 'Tính TDEE'); ?>
    <?php else:
        $shares = roundToSum($nutrition['shares']);
        $macros = [
            ['Đạm', $nutrition['protein_g'], $shares['protein'], 'protein'],
            ['Tinh bột', $nutrition['carbs_g'], $shares['carbs'], 'carbs'],
            ['Béo', $nutrition['fat_g'], $shares['fat'], 'fat'],
        ];
    ?>
        <dl class="du-stats">
            <div><dt>TDEE</dt><dd><?= htmlspecialchars(dashNumber((float)$nutrition['tdee_kcal'], 0)) ?> <small>kcal</small></dd></div>
            <div><dt>Calo mục tiêu</dt><dd><?= htmlspecialchars(dashNumber((float)$nutrition['target_kcal'], 0)) ?> <small>kcal</small></dd></div>
        </dl>
        <ul class="du-macros">
            <?php foreach ($macros as [$macroName, $grams, $share, $macroKey]): ?>
                <li>
                    <span class="du-macros__line"><span><?= $macroName ?></span><b><?= htmlspecialchars(dashNumber((float)$grams, 0)) ?> g</b><span class="du-macros__share"><?= (int)$share ?>%</span></span>
                    <span class="du-bar du-bar--<?= $macroKey ?>" aria-hidden="true"><i style="--du-value: <?= (int)$share ?>%"></i></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="du-note">Mục tiêu mỗi ngày, % là tỉ lệ calo. Tính ngày <?= htmlspecialchars((new DateTimeImmutable($nutrition['calculated_date']))->format('d/m/Y')) ?>.</p>
        <a class="du-link" href="/nutrition.php#lich-an">Xem lịch ăn gợi ý</a>
    <?php endif; ?>
</section>
