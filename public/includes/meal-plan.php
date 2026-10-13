<?php
/**
 * includes/meal-plan.php — phần mở rộng cho trang dinh dưỡng (nutrition.php).
 *
 * File này chỉ chứa hàm (không có HTML), KHÔNG sửa tdee.php:
 *   - tdee.php          : BMR, TDEE, calo mục tiêu và macro mặc định theo mục tiêu (việc của C)
 *   - meal-plan.php     : kiểu ăn (cân bằng, giàu protein, ít tinh bột, tùy chỉnh), macro theo %,
 *                         chia macro theo bữa và gợi ý món ăn kèm khối lượng
 *
 * Quy đổi năng lượng dùng chung: protein 4 kcal/g, carb 4 kcal/g, chất béo 9 kcal/g.
 * Các con số món ăn là ƯỚC LƯỢNG theo bảng thành phần thực phẩm phổ biến, chỉ để tham khảo.
 */

/**
 * Các kiểu ăn. 'pct' = [protein %, carb %, fat %] của calo mục tiêu; null = dùng cách tính theo cân nặng của tdee.php.
 */
function dietStyles(): array
{
    return [
        'goal' => [
            'label' => 'Theo mục tiêu',
            'desc'  => 'Protein 1,6 g/kg (người mới) hoặc 1,8 g/kg (có kinh nghiệm), chất béo tính theo cân nặng, phần calo còn lại dành cho tinh bột.',
            'pct'   => null,
        ],
        'balanced' => [
            'label' => 'Cân bằng',
            'desc'  => '20% protein, 50% tinh bột, 30% chất béo. Dễ theo, hợp người mới.',
            'pct'   => [20, 50, 30],
        ],
        'highprotein' => [
            'label' => 'Giàu protein',
            'desc'  => '30% protein, 40% tinh bột, 30% chất béo. Nhiều đạm hơn, hợp khi giảm mỡ.',
            'pct'   => [30, 40, 30],
        ],
        'lowcarb' => [
            'label' => 'Ít tinh bột',
            'desc'  => '25% protein, 30% tinh bột, 45% chất béo. Giảm cơm, bún, bánh mì.',
            'pct'   => [25, 30, 45],
        ],
        'custom' => [
            'label' => 'Tùy chỉnh',
            'desc'  => 'Tự chọn tỉ lệ % protein, tinh bột, chất béo (tổng 100%).',
            'pct'   => null, // lấy từ ô nhập của người dùng
        ],
    ];
}

/** Giới hạn cho phép khi tự chỉnh tỉ lệ (để không tạo ra khẩu phần phi lý) */
function customPercentLimits(): array
{
    return ['protein' => [10, 40], 'carbs' => [5, 70], 'fat' => [15, 60]];
}

/**
 * Kiểm tra tỉ lệ tùy chỉnh. Trả về thông báo lỗi (chuỗi) hoặc null nếu hợp lệ.
 */
function validateCustomPercent(int $p, int $c, int $f): ?string
{
    $limits = customPercentLimits();
    if ($p < $limits['protein'][0] || $p > $limits['protein'][1]) {
        return "Protein phải từ {$limits['protein'][0]}% đến {$limits['protein'][1]}%.";
    }
    if ($c < $limits['carbs'][0] || $c > $limits['carbs'][1]) {
        return "Tinh bột phải từ {$limits['carbs'][0]}% đến {$limits['carbs'][1]}%.";
    }
    if ($f < $limits['fat'][0] || $f > $limits['fat'][1]) {
        return "Chất béo phải từ {$limits['fat'][0]}% đến {$limits['fat'][1]}%.";
    }
    if ($p + $c + $f !== 100) {
        return 'Tổng ba tỉ lệ phải đúng 100% (hiện là ' . ($p + $c + $f) . '%).';
    }
    return null;
}

/**
 * Đổi tỉ lệ % calo thành gram.
 * Ví dụ: 2000 kcal, protein 30% → 2000 * 0.30 / 4 = 150 g.
 * Tên khóa khớp với calculateTargets() để dùng chung code hiển thị và lưu DB.
 */
function macrosFromPercent(float $targetKcal, int $pPct, int $cPct, int $fPct): array
{
    return [
        'target_kcal' => round($targetKcal, 1),
        'protein_g'   => round($targetKcal * $pPct / 100 / 4, 1),
        'carbs_g'     => round($targetKcal * $cPct / 100 / 4, 1),
        'fat_g'       => round($targetKcal * $fPct / 100 / 9, 1),
    ];
}

/**
 * Tỉ lệ % calo thực tế của từng macro (tính từ gram). Dùng để vẽ vòng tròn.
 * Trả về ['protein' => %, 'carbs' => %, 'fat' => %], tổng = 100.
 */
function macroShares(float $proteinG, float $carbsG, float $fatG): array
{
    $pk = $proteinG * 4;
    $ck = $carbsG * 4;
    $fk = $fatG * 9;
    $total = $pk + $ck + $fk;
    if ($total <= 0) {
        return ['protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
    }
    return [
        'protein' => $pk / $total * 100,
        'carbs'   => $ck / $total * 100,
        'fat'     => $fk / $total * 100,
    ];
}

/** Các bữa trong ngày theo số bữa. 'share' = phần trăm khẩu phần cả ngày. */
function mealSlots(int $meals): array
{
    if ($meals === 4) {
        return [
            ['kind' => 'breakfast', 'name' => 'Bữa sáng', 'time' => '07:00', 'share' => 25],
            ['kind' => 'lunch',     'name' => 'Bữa trưa', 'time' => '12:00', 'share' => 35],
            ['kind' => 'snack',     'name' => 'Bữa phụ',  'time' => '15:30', 'share' => 10],
            ['kind' => 'dinner',    'name' => 'Bữa tối',  'time' => '18:30', 'share' => 30],
        ];
    }
    if ($meals === 5) {
        return [
            ['kind' => 'breakfast', 'name' => 'Bữa sáng',     'time' => '07:00', 'share' => 25],
            ['kind' => 'snack',     'name' => 'Phụ buổi sáng', 'time' => '10:00', 'share' => 10],
            ['kind' => 'lunch',     'name' => 'Bữa trưa',     'time' => '12:00', 'share' => 30],
            ['kind' => 'snack',     'name' => 'Phụ buổi chiều', 'time' => '15:30', 'share' => 10],
            ['kind' => 'dinner',    'name' => 'Bữa tối',      'time' => '18:30', 'share' => 25],
        ];
    }
    return [
        ['kind' => 'breakfast', 'name' => 'Bữa sáng', 'time' => '07:00', 'share' => 30],
        ['kind' => 'lunch',     'name' => 'Bữa trưa', 'time' => '12:00', 'share' => 40],
        ['kind' => 'dinner',    'name' => 'Bữa tối',  'time' => '18:30', 'share' => 30],
    ];
}

/**
 * Bảng thực phẩm: giá trị trên 100 g (đã nấu chín, trừ yến mạch tính khô).
 * p = protein, c = tinh bột, f = chất béo (gram). 'unit' = [tên đơn vị, gram mỗi đơn vị] để quy đổi cho dễ hình dung.
 */
function foodCatalog(): array
{
    return [
        // Nguồn đạm
        'ucga'    => ['name' => 'Ức gà luộc/nướng',        'p' => 31,  'c' => 0,  'f' => 3.6],
        'bo'      => ['name' => 'Thịt bò nạc',             'p' => 26,  'c' => 0,  'f' => 8],
        'heo'     => ['name' => 'Thịt heo nạc (thăn)',     'p' => 27,  'c' => 0,  'f' => 6],
        'cabasa'  => ['name' => 'Cá phi lê (basa, tra) hấp', 'p' => 20, 'c' => 0,  'f' => 3],
        'cahoi'   => ['name' => 'Cá hồi nướng',            'p' => 22,  'c' => 0,  'f' => 12],
        'tom'     => ['name' => 'Tôm luộc',                'p' => 24,  'c' => 0.2, 'f' => 0.3],
        'trung'   => ['name' => 'Trứng gà',                'p' => 13,  'c' => 1,  'f' => 10, 'unit' => ['quả', 55], 'max' => 165], // tối đa 3 quả mỗi bữa
        'dauhu'   => ['name' => 'Đậu hũ trắng',            'p' => 8,   'c' => 2,  'f' => 5],
        'suachua' => ['name' => 'Sữa chua Hy Lạp không đường', 'p' => 10, 'c' => 4, 'f' => 0.4],
        // Nguồn tinh bột
        'com'     => ['name' => 'Cơm trắng',               'p' => 2.7, 'c' => 28, 'f' => 0.3],
        'comlut'  => ['name' => 'Cơm gạo lứt',             'p' => 2.6, 'c' => 23, 'f' => 0.9],
        'khoailang' => ['name' => 'Khoai lang luộc',       'p' => 1.6, 'c' => 20, 'f' => 0.1],
        'khoaitay' => ['name' => 'Khoai tây luộc',         'p' => 2,   'c' => 17, 'f' => 0.1],
        'bun'     => ['name' => 'Bún tươi',                'p' => 1.7, 'c' => 24, 'f' => 0.2],
        'banhmi'  => ['name' => 'Bánh mì',                 'p' => 9,   'c' => 49, 'f' => 3.2],
        'yenmach' => ['name' => 'Yến mạch (khô)',          'p' => 13,  'c' => 66, 'f' => 7],
        'chuoi'   => ['name' => 'Chuối',                   'p' => 1.1, 'c' => 23, 'f' => 0.3, 'unit' => ['quả', 100]],
        // Chất béo bổ sung
        'dau'     => ['name' => 'Dầu ăn / dầu ô liu',      'p' => 0,   'c' => 0,  'f' => 100, 'unit' => ['thìa cà phê', 5]],
        'dauphong' => ['name' => 'Đậu phộng rang',         'p' => 26,  'c' => 16, 'f' => 49, 'unit' => ['nắm nhỏ', 20]],
    ];
}

/**
 * Món gợi ý theo loại bữa: mỗi phương án là [nguồn đạm, nguồn tinh bột, nguồn đạm bổ sung].
 * Nguồn đạm bổ sung chỉ dùng khi nguồn đạm chính bị giới hạn khối lượng (ví dụ trứng tối đa 3 quả/bữa).
 */
function mealOptions(): array
{
    return [
        'breakfast' => [['trung', 'yenmach', 'suachua'], ['ucga', 'bun'], ['trung', 'banhmi', 'suachua'], ['suachua', 'yenmach']],
        'lunch'     => [['ucga', 'com'], ['bo', 'comlut'], ['cabasa', 'com'], ['heo', 'khoaitay']],
        'dinner'    => [['cahoi', 'khoailang'], ['tom', 'com'], ['cabasa', 'khoaitay'], ['heo', 'khoailang']],
        'snack'     => [['suachua', 'chuoi'], ['trung', 'khoailang', 'suachua'], ['suachua', 'yenmach'], ['trung', 'chuoi', 'suachua']],
    ];
}

/** Làm tròn gram về bội số của 5 cho dễ cân đo (tối thiểu 5 g nếu > 0). */
function roundPortion(float $grams): int
{
    if ($grams <= 0) {
        return 0;
    }
    return max(5, (int)(round($grams / 5) * 5));
}

/**
 * Gợi ý một bữa ăn đạt gần đúng macro mục tiêu của bữa.
 *
 * Cách tính (đơn giản, từng bước):
 *   1. Khối lượng nguồn tinh bột = carb cần / carb trong 100 g của món đó.
 *   2. Món tinh bột cũng có chút protein → trừ phần đó khỏi protein cần, phần còn lại lấy từ nguồn đạm.
 *   3. Chất béo đã có sẵn trong 2 món trên; còn thiếu bao nhiêu thì bổ sung bằng dầu ăn (hoặc đậu phộng ở bữa phụ).
 *   4. Làm tròn khối lượng, rồi cộng lại macro THỰC TẾ của khẩu phần để hiển thị trung thực.
 *
 * @param array $slot   một phần tử của mealSlots()
 * @param array $target ['p' => g, 'c' => g, 'f' => g] mục tiêu của bữa
 * @param int   $variant số thứ tự phương án (đổi món)
 * @return array ['items' => [[name, grams, hint]], 'p','c','f','kcal', 'option_count']
 */
function suggestMeal(array $slot, array $target, int $variant): array
{
    $foods = foodCatalog();
    $options = mealOptions()[$slot['kind']];
    $count = count($options);
    $choice = $options[(($variant % $count) + $count) % $count];
    $protein = $foods[$choice[0]];
    $carb = $foods[$choice[1]];
    $topUp = isset($choice[2]) ? $foods[$choice[2]] : null;

    // 1. Nguồn tinh bột (bỏ qua nếu cần quá ít, ví dụ kiểu ăn ít tinh bột)
    $gCarb = $carb['c'] > 0 ? $target['c'] / ($carb['c'] / 100) : 0;
    if ($gCarb < 15) {
        $gCarb = 0;
    }
    $carbProtein = $gCarb * $carb['p'] / 100;

    // 2. Nguồn đạm (có giới hạn khối lượng thì phần thiếu lấy từ nguồn đạm bổ sung)
    $needProt = max(0, $target['p'] - $carbProtein);
    $gProt = $needProt / ($protein['p'] / 100);
    $gTopUp = 0;
    if (isset($protein['max']) && $gProt > $protein['max']) {
        $needProt -= $protein['max'] * $protein['p'] / 100;
        $gProt = $protein['max'];
        if ($topUp !== null) {
            $gTopUp = $needProt / ($topUp['p'] / 100);
        }
    }

    // Làm tròn: trứng tính theo quả, còn lại bội số của 5 g
    $items = [];
    $totals = ['p' => 0.0, 'c' => 0.0, 'f' => 0.0];
    $add = function (array $food, float $grams) use (&$items, &$totals) {
        $hint = '';
        if (isset($food['unit'])) {
            $units = max(1, (int)round($grams / $food['unit'][1]));
            $grams = $units * $food['unit'][1];
            $hint = '≈ ' . $units . ' ' . $food['unit'][0];
        }
        $items[] = ['name' => $food['name'], 'grams' => (int)$grams, 'hint' => $hint];
        $totals['p'] += $grams * $food['p'] / 100;
        $totals['c'] += $grams * $food['c'] / 100;
        $totals['f'] += $grams * $food['f'] / 100;
    };

    $isEgg = isset($protein['unit']);
    $add($protein, $isEgg ? $gProt : roundPortion($gProt));
    if ($gTopUp >= 20) {
        $add($topUp, roundPortion($gTopUp));
    }
    if ($gCarb > 0) {
        $add($carb, isset($carb['unit']) ? $gCarb : roundPortion($gCarb));
    }

    // 3. Bù chất béo còn thiếu
    $fatLeft = $target['f'] - $totals['f'];
    // Bữa phụ dùng đậu phộng (1 nắm nhỏ ≈ 10 g béo) nên chỉ thêm khi còn thiếu đủ nhiều
    $minFat = $slot['kind'] === 'snack' ? 8 : 3;
    if ($fatLeft >= $minFat) {
        $fatFood = $slot['kind'] === 'snack' ? $foods['dauphong'] : $foods['dau'];
        $gFat = $fatFood['f'] > 0 ? $fatLeft / ($fatFood['f'] / 100) : 0;
        $step = isset($fatFood['unit']) ? $fatFood['unit'][1] : 5;
        $units = max(1, (int)round($gFat / $step));
        $add($fatFood, $units * $step);
    }

    $kcal = $totals['p'] * 4 + $totals['c'] * 4 + $totals['f'] * 9;
    return [
        'items'        => $items,
        'p'            => round($totals['p']),
        'c'            => round($totals['c']),
        'f'            => round($totals['f']),
        'kcal'         => (int)round($kcal),
        'option_count' => $count,
    ];
}

/**
 * Lịch ăn cả ngày: chia macro theo từng bữa rồi gợi ý món.
 *
 * @param array $targets ['target_kcal','protein_g','carbs_g','fat_g'] của cả ngày
 * @return array danh sách bữa: slot + target + suggestion
 */
function buildMealPlan(array $targets, int $meals, int $variant): array
{
    $plan = [];
    $snackCount = 0;
    foreach (mealSlots($meals) as $slot) {
        $ratio = $slot['share'] / 100;
        $target = [
            'p' => $targets['protein_g'] * $ratio,
            'c' => $targets['carbs_g'] * $ratio,
            'f' => $targets['fat_g'] * $ratio,
        ];
        // Hai bữa phụ cùng loại thì lệch nhau 1 phương án để đỡ trùng món
        $offset = 0;
        if ($slot['kind'] === 'snack') {
            $offset = $snackCount;
            $snackCount++;
        }
        $plan[] = [
            'slot'       => $slot,
            'target'     => $target,
            'suggestion' => suggestMeal($slot, $target, $variant + $offset),
        ];
    }
    return $plan;
}

/** Vài lời khuyên ngắn theo mục tiêu (hiện dưới lịch ăn). */
function goalTips(string $goal): array
{
    $tips = [
        'LOSE_FAT' => [
            'Mỗi bữa có nguồn đạm để no lâu và giữ cơ khi thâm hụt calo.',
            'Ăn nhiều rau xanh và uống đủ nước; hạn chế nước ngọt, đồ chiên.',
            'Giảm cân vừa phải, khoảng 0,5 đến 1 kg mỗi tuần là hợp lý.',
        ],
        'MAINTAIN' => [
            'Giữ cân nặng ổn định, theo dõi mỗi tuần để điều chỉnh nếu lệch.',
            'Phân bổ đạm đều giữa các bữa thay vì dồn vào bữa tối.',
        ],
        'GAIN_MUSCLE' => [
            'Ăn đủ calo mỗi ngày và tập kháng lực đều, thiếu một trong hai thì khó lên cơ.',
            'Bữa trước và sau tập nên có cả đạm và tinh bột.',
            'Tăng cân chậm (khoảng 0,25 đến 0,5 kg mỗi tuần) để hạn chế tăng mỡ.',
        ],
    ];
    return $tips[$goal] ?? [];
}
