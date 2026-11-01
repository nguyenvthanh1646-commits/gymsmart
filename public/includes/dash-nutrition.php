<?php
/**
 * dash-nutrition.php — thẻ dinh dưỡng: vòng macro quanh calo mục tiêu, TDEE và 3 macro từ lần tính gần nhất.
 * Dự án chưa ghi món đã ăn, nên vòng là tỉ lệ calo của MỤC TIÊU, không phải lượng đã ăn trong ngày.
 */
$nutrition = $dash['nutrition'];
?>
<section class="du-card du-card--nutrition" aria-labelledby="du-nutrition-title">
    <div class="du-card__head"><?php duCardTitle('flame', 'du-nutrition-title', 'Dinh dưỡng'); ?></div>

    <?php if ($nutrition['status'] === 'error'): ?>
        <?php duError('mục tiêu dinh dưỡng'); ?>
    <?php elseif ($nutrition['status'] === 'none' && !empty($missingFields)): ?>
        <?php duState('user', 'Chưa tính TDEE', 'TDEE cần tuổi, chiều cao, cân nặng, giới tính và mức vận động trong hồ sơ của bạn.', '/profile.php', 'Hoàn thiện hồ sơ'); ?>
    <?php elseif ($nutrition['status'] === 'none'): ?>
        <?php duState('flame', 'Chưa tính TDEE', 'Tính nhu cầu năng lượng để biết mỗi ngày nên ăn bao nhiêu calo, đạm, tinh bột và béo.', '/nutrition.php', 'Tính TDEE'); ?>
    <?php else:
        $shares = roundToSum($nutrition['shares']);
        $macros = [
            ['Đạm', $nutrition['protein_g'], $shares['protein'], 'protein'],
            ['Tinh bột', $nutrition['carbs_g'], $shares['carbs'], 'carbs'],
            ['Béo', $nutrition['fat_g'], $shares['fat'], 'fat'],
        ];
    ?>
        <div class="du-macro">
            <!-- Số đã hiện đủ bằng chữ bên cạnh nên vòng chỉ để minh họa -->
            <div class="du-donut" aria-hidden="true" style="--du-protein: <?= (int)$shares['protein'] ?>%; --du-carbs: <?= (int)$shares['carbs'] ?>%"></div>
            <div class="du-donut__center">
                <b><?= htmlspecialchars(dashNumber((float)$nutrition['target_kcal'], 0)) ?></b>
                <small>kcal mục tiêu</small>
            </div>
            <ul class="du-macros">
                <?php foreach ($macros as [$macroName, $grams, $share, $macroKey]): ?>
                    <li class="du-macros__item du-macros__item--<?= htmlspecialchars($macroKey) ?>">
                        <span><?= htmlspecialchars($macroName) ?></span>
                        <b><?= htmlspecialchars(dashNumber((float)$grams, 0)) ?> g</b>
                        <small><?= (int)$share ?>%</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <p class="du-note">
            TDEE <b><?= htmlspecialchars(dashNumber((float)$nutrition['tdee_kcal'], 0)) ?> kcal</b>. % là tỉ lệ calo của mục tiêu mỗi ngày, tính ngày <?= htmlspecialchars((new DateTimeImmutable($nutrition['calculated_date']))->format('d/m/Y')) ?>.
        </p>
        <a class="du-link" href="/nutrition.php#lich-an">Xem lịch ăn gợi ý</a>
    <?php endif; ?>
</section>
