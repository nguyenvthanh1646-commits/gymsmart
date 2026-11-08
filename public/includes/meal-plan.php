<?php
/**
 * includes/meal-plan.php — phần mở rộng cho trang dinh dưỡng (nutrition.php).
 *
 * File này chỉ chứa hàm (không có HTML, không đụng DB):
 *   - tdee.php          : BMR, TDEE, calo mục tiêu, nhịp mục tiêu, ngày tập/nghỉ, tiến độ cân nặng (việc của C)
 *   - meal-plan.php     : kiểu ăn (cân bằng, giàu protein, ít tinh bột, tùy chỉnh), macro theo %,
 *                         chia macro theo bữa, gợi ý món kèm khối lượng, loại trừ thực phẩm,
 *                         lịch 7 ngày và danh sách đi chợ
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
        'micang'  => ['name' => 'Mì căn (seitan)',         'p' => 24,  'c' => 5,  'f' => 1.5], // đạm chay ít béo, giúp người ăn thuần chay đủ đạm
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
 * Món gợi ý theo loại bữa. Mỗi phương án có tên món và 'foods' = [nguồn đạm, nguồn tinh bột, nguồn đạm bổ sung].
 * Nguồn đạm bổ sung chỉ dùng khi nguồn đạm chính bị giới hạn khối lượng (ví dụ trứng tối đa 3 quả/bữa).
 * Bữa tối luôn là bữa cuối ngày nên không dùng trứng (bữa cuối cần khối lượng tính được chính xác, xem suggestMeal).
 */
function mealOptions(): array
{
    return [
        'breakfast' => [
            ['name' => 'Yến mạch, trứng và sữa chua',      'foods' => ['trung', 'yenmach', 'suachua']],
            ['name' => 'Bún gà',                           'foods' => ['ucga', 'bun']],
            ['name' => 'Bánh mì trứng, sữa chua',          'foods' => ['trung', 'banhmi', 'suachua']],
            ['name' => 'Yến mạch trộn sữa chua',           'foods' => ['suachua', 'yenmach']],
            ['name' => 'Bún đậu hũ',                       'foods' => ['dauhu', 'bun']],
            ['name' => 'Bánh mì kẹp mì căn',               'foods' => ['micang', 'banhmi']],
            ['name' => 'Bún mì căn',                       'foods' => ['micang', 'bun']],
        ],
        'lunch' => [
            ['name' => 'Cơm ức gà',                        'foods' => ['ucga', 'com']],
            ['name' => 'Cơm gạo lứt, bò nạc',              'foods' => ['bo', 'comlut']],
            ['name' => 'Cơm cá basa hấp',                  'foods' => ['cabasa', 'com']],
            ['name' => 'Thịt heo nạc, khoai tây luộc',     'foods' => ['heo', 'khoaitay']],
            ['name' => 'Cơm đậu hũ sốt cà',                'foods' => ['dauhu', 'com']],
            ['name' => 'Cơm gạo lứt, mì căn kho',          'foods' => ['micang', 'comlut']],
            ['name' => 'Cơm mì căn rim',                   'foods' => ['micang', 'com']],
        ],
        'dinner' => [
            ['name' => 'Cá hồi nướng, khoai lang',         'foods' => ['cahoi', 'khoailang']],
            ['name' => 'Cơm tôm luộc',                     'foods' => ['tom', 'com']],
            ['name' => 'Cá basa hấp, khoai tây',           'foods' => ['cabasa', 'khoaitay']],
            ['name' => 'Thịt heo nạc, khoai lang',         'foods' => ['heo', 'khoailang']],
            ['name' => 'Ức gà áp chảo, cơm gạo lứt',       'foods' => ['ucga', 'comlut']],
            ['name' => 'Mì căn xào, khoai lang',           'foods' => ['micang', 'khoailang']],
            ['name' => 'Mì căn kho, cơm trắng',            'foods' => ['micang', 'com']],
            ['name' => 'Đậu hũ, cơm gạo lứt',              'foods' => ['dauhu', 'comlut']],
        ],
        'snack' => [
            ['name' => 'Sữa chua chuối',                   'foods' => ['suachua', 'chuoi']],
            ['name' => 'Trứng luộc, khoai lang, sữa chua', 'foods' => ['trung', 'khoailang', 'suachua']],
            ['name' => 'Sữa chua yến mạch',                'foods' => ['suachua', 'yenmach']],
            ['name' => 'Trứng luộc, chuối, sữa chua',      'foods' => ['trung', 'chuoi', 'suachua']],
            ['name' => 'Đậu hũ, chuối',                    'foods' => ['dauhu', 'chuoi']],
            ['name' => 'Đậu hũ, khoai lang',               'foods' => ['dauhu', 'khoailang']],
            ['name' => 'Mì căn áp chảo, chuối',            'foods' => ['micang', 'chuoi']],
            ['name' => 'Mì căn áp chảo, khoai lang',       'foods' => ['micang', 'khoailang']],
        ],
    ];
}

/**
 * Các loại thực phẩm người dùng có thể loại khỏi lịch ăn.
 * "Ăn chay" ở đây bỏ thịt, cá, hải sản; muốn thuần chay thì chọn thêm "Không trứng" và "Không sữa".
 */
function foodExclusions(): array
{
    $seafood = ['cabasa', 'cahoi', 'tom'];
    return [
        'vegetarian' => ['label' => 'Ăn chay',       'hint' => 'Bỏ thịt, cá, hải sản', 'foods' => array_merge(['ucga', 'bo', 'heo'], $seafood)],
        'seafood'    => ['label' => 'Không hải sản', 'hint' => 'Bỏ cá và tôm',         'foods' => $seafood],
        'beef'       => ['label' => 'Không thịt bò', 'hint' => '',                     'foods' => ['bo']],
        'pork'       => ['label' => 'Không thịt heo', 'hint' => '',                    'foods' => ['heo']],
        'egg'        => ['label' => 'Không trứng',   'hint' => '',                     'foods' => ['trung']],
        'dairy'      => ['label' => 'Không sữa',     'hint' => 'Bỏ sữa chua',          'foods' => ['suachua']],
    ];
}

/**
 * Làm sạch danh sách loại trừ: nhận mảng từ form hoặc chuỗi "egg,dairy" từ DB, chỉ giữ mã hợp lệ, theo thứ tự cố định.
 * Phần tử không phải chuỗi (ví dụ form gửi lên mảng lồng nhau) bị bỏ qua.
 */
function normalizeExclusions($raw): array
{
    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }
    if (!is_array($raw)) {
        return [];
    }
    $picked = array_filter($raw, 'is_string');
    return array_values(array_intersect(array_keys(foodExclusions()), $picked));
}

/** Các phương án món của một loại bữa sau khi bỏ món chứa thực phẩm bị loại trừ. */
function allowedMealOptions(string $kind, array $exclusions): array
{
    $banned = [];
    foreach (normalizeExclusions($exclusions) as $code) {
        $banned = array_merge($banned, foodExclusions()[$code]['foods']);
    }
    return array_values(array_filter(
        mealOptions()[$kind],
        fn(array $option): bool => !array_intersect($option['foods'], $banned)
    ));
}

/**
 * Đọc lựa chọn "đổi món" từ URL, dạng "0,2,1,0": mỗi số là số lần đã bấm đổi ở bữa tương ứng.
 * Sai định dạng hoặc sai số bữa thì coi như chưa đổi bữa nào.
 */
function parseMealPicks($raw, int $meals): array
{
    $none = array_fill(0, $meals, 0);
    if (!is_string($raw) || !preg_match('/^\d{1,2}(,\d{1,2})*$/', $raw)) {
        return $none;
    }
    $picks = array_map('intval', explode(',', $raw));
    return count($picks) === $meals ? $picks : $none;
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
 * Bữa cuối ngày ($exact = true) tính chính xác hơn để tổng cả ngày khớp mục tiêu:
 *   - Giải cùng lúc khối lượng nguồn đạm và nguồn tinh bột (hệ 2 phương trình), vì món nào cũng có chút đạm lẫn tinh bột.
 *   - Làm tròn tới 1 g (không phải 5 g), dầu ăn tính theo gram thay vì theo thìa.
 *
 * @param array $slot    một phần tử của mealSlots()
 * @param array $target  ['p' => g, 'c' => g, 'f' => g] mục tiêu của bữa
 * @param int   $variant số thứ tự phương án (đổi món)
 * @param bool  $exact   true = bữa cuối, cân cho khớp phần còn lại của ngày
 * @param array $exclusions mã thực phẩm loại trừ (xem foodExclusions)
 * @return array ['dish', 'option' (tên phương án, dùng để so trùng), 'items' => [[key, name, grams, hint]], 'raw' => ['p','c','f','kcal'] chưa làm tròn, 'option_count']
 */
function suggestMeal(array $slot, array $target, int $variant, bool $exact = false, array $exclusions = []): array
{
    $foods = foodCatalog();
    foreach ($foods as $key => $food) {
        $foods[$key]['key'] = $key;       // để danh sách đi chợ cộng dồn theo từng thực phẩm
    }
    $options = allowedMealOptions($slot['kind'], $exclusions);
    $count = count($options);
    $option = $options[(($variant % $count) + $count) % $count];
    $choice = $option['foods'];
    $protein = $foods[$choice[0]];
    $carb = $foods[$choice[1]];
    $topUp = isset($choice[2]) ? $foods[$choice[2]] : null;
    // Chỉ tính chính xác được khi món không có giới hạn đơn vị (trứng theo quả, chuối theo quả)
    $exact = $exact && !isset($protein['unit']) && !isset($carb['unit']);
    $portion = fn(float $g): int => $exact ? (int)round(max(0, $g)) : roundPortion($g);

    if ($exact) {
        // Hệ 2 phương trình (đơn vị: gram macro trên 1 g thực phẩm):
        //   đạm:      gProt * pP + gCarb * cP = target p
        //   tinh bột: gProt * pC + gCarb * cC = target c
        $pP = $protein['p'] / 100; $pC = $protein['c'] / 100;
        $cP = $carb['p'] / 100;    $cC = $carb['c'] / 100;
        $det = $pP * $cC - $cP * $pC;
        $gCarb = ($pP * $target['c'] - $pC * $target['p']) / $det;
        $gProt = ($target['p'] - $cP * $gCarb) / $pP;
        if ($gCarb < 0) {                     // tinh bột đã đủ từ bữa trước: chỉ còn nguồn đạm
            $gCarb = 0;
            $gProt = $target['p'] / $pP;
        } elseif ($gProt < 0) {               // đạm trong nguồn tinh bột đã vượt phần đạm còn lại
            $gProt = 0;
            $gCarb = $target['c'] / $cC;
        }
        $gTopUp = 0;
    } else {
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
        if (isset($protein['max'])) {
            // Trứng: tối đa 3 quả, và không quá phần chất béo của bữa (3 quả ≈ 16,5 g béo); ít nhất 1 quả
            $cap = min($protein['max'], max($protein['unit'][1], $target['f'] / ($protein['f'] / 100)));
            if ($gProt > $cap) {
                $units = max(1, (int)floor($cap / $protein['unit'][1]));
                $gProt = $units * $protein['unit'][1];
                $needProt -= $gProt * $protein['p'] / 100;
                if ($topUp !== null) {
                    $gTopUp = $needProt / ($topUp['p'] / 100);
                }
            }
        }
    }

    // Làm tròn: trứng tính theo quả, còn lại bội số của 5 g (bữa cuối: 1 g)
    $items = [];
    $totals = ['p' => 0.0, 'c' => 0.0, 'f' => 0.0];
    $add = function (array $food, float $grams, bool $byUnit = true) use (&$items, &$totals) {
        $hint = '';
        if (isset($food['unit'])) {
            if ($byUnit) {
                $units = (int)round($grams / $food['unit'][1]);
                if ($units < 1) {
                    return; // cần chưa tới nửa quả: bỏ món này thay vì ép lên 1 quả
                }
                $grams = $units * $food['unit'][1];
                $hint = '≈ ' . $units . ' ' . $food['unit'][0];
            } else {
                $hint = '≈ ' . str_replace('.', ',', (string)round($grams / $food['unit'][1], 1)) . ' ' . $food['unit'][0];
            }
        }
        $items[] = ['key' => $food['key'], 'name' => $food['name'], 'grams' => (int)$grams, 'hint' => $hint];
        $totals['p'] += $grams * $food['p'] / 100;
        $totals['c'] += $grams * $food['c'] / 100;
        $totals['f'] += $grams * $food['f'] / 100;
    };

    $isEgg = isset($protein['unit']);
    $gProtPortion = $isEgg ? max($gProt, $protein['unit'][1]) : $portion($gProt);   // trứng: ít nhất 1 quả
    if ($gProtPortion > 0) {
        $add($protein, $gProtPortion);
    }
    if ($gTopUp >= 20) {
        $add($topUp, roundPortion($gTopUp));
    }
    $carbCount = count($items);
    if ($gCarb > 0 && $portion($gCarb) > 0) {
        $add($carb, isset($carb['unit']) ? $gCarb : $portion($gCarb));
    }
    // Không có nguồn tinh bột (kiểu ăn ít tinh bột, hoặc bữa trước đã đủ) thì tên món chỉ còn nguồn đạm
    $dish = count($items) > $carbCount ? $option['name'] : $protein['name'];

    // 3. Bù chất béo còn thiếu
    $fatLeft = $target['f'] - $totals['f'];
    if ($exact) {
        // Bữa cuối: dầu ăn tính theo gram để tổng chất béo trong ngày khớp
        $gOil = (int)round($fatLeft);
        if ($gOil >= 1) {
            $add($foods['dau'], $gOil, false);
        }
    } else {
        // Bữa phụ dùng đậu phộng (1 nắm nhỏ ≈ 10 g béo) nên chỉ thêm khi còn thiếu đủ nhiều
        $minFat = $slot['kind'] === 'snack' ? 8 : 3;
        if ($fatLeft >= $minFat) {
            $fatFood = $slot['kind'] === 'snack' ? $foods['dauphong'] : $foods['dau'];
            $gFat = $fatFood['f'] > 0 ? $fatLeft / ($fatFood['f'] / 100) : 0;
            $step = isset($fatFood['unit']) ? $fatFood['unit'][1] : 5;
            $units = max(1, (int)round($gFat / $step));
            $add($fatFood, $units * $step);
        }
    }

    return [
        'dish'         => $dish,
        'option'       => $option['name'],
        'items'        => $items,
        'raw'          => $totals + ['kcal' => $totals['p'] * 4 + $totals['c'] * 4 + $totals['f'] * 9],
        'option_count' => $count,
    ];
}

/**
 * Làm tròn một dãy số sao cho tổng các số đã làm tròn bằng đúng tổng gốc làm tròn
 * (cách "phần dư lớn nhất"). Ví dụ [1.4, 1.4, 1.2] (tổng 4) → [2, 1, 1] thay vì [1, 1, 1] (tổng 3).
 * Dùng để số liệu từng bữa cộng lại đúng bằng dòng tổng.
 */
function roundToSum(array $values): array
{
    $floors = array_map('floor', $values);
    $missing = (int)round(array_sum($values) - array_sum($floors));
    $remainders = [];
    foreach ($values as $i => $v) {
        $remainders[$i] = $v - $floors[$i];
    }
    arsort($remainders);
    foreach (array_slice(array_keys($remainders), 0, max(0, $missing)) as $i) {
        $floors[$i]++;
    }
    return array_map('intval', $floors);
}

/**
 * Lịch ăn cả ngày: chia macro theo từng bữa rồi gợi ý món, tổng cả ngày khớp mục tiêu.
 *
 * Cách giữ tổng khớp:
 *   1. Mỗi bữa nhận phần còn lại của ngày (mục tiêu − những gì các bữa trước đã thực sự cho), chia theo tỉ lệ
 *      của bữa đó trong số các bữa còn lại. Bữa trước lệch do làm tròn thì bữa sau tự bù.
 *   2. Bữa cuối (bữa tối) nhận toàn bộ phần còn lại và được tính chính xác tới 1 g (xem suggestMeal).
 *   3. Số liệu từng bữa làm tròn bằng roundToSum() nên cộng lại đúng bằng dòng tổng.
 * Nhờ cách này, đổi món ở một bữa thì các bữa sau tự cân lại và tổng ngày vẫn khớp.
 *
 * @param array $targets ['target_kcal','protein_g','carbs_g','fat_g'] của cả ngày
 * @param array $exclusions mã thực phẩm loại trừ
 * @param array $picks  [số thứ tự bữa => số lần bấm đổi món]
 * @param array $avoid  [số thứ tự bữa => tên phương án của hôm trước], để hai ngày liền nhau không trùng món
 * @return array ['meals' => [slot, target, suggestion + p,c,f,kcal đã làm tròn], 'total' => [...], 'target' => [...]]
 */
function buildMealPlan(array $targets, int $meals, int $variant, array $exclusions = [], array $picks = [], array $avoid = []): array
{
    $slots = mealSlots($meals);
    $want = ['p' => $targets['protein_g'], 'c' => $targets['carbs_g'], 'f' => $targets['fat_g']];
    $done = ['p' => 0.0, 'c' => 0.0, 'f' => 0.0];
    $shareLeft = array_sum(array_column($slots, 'share'));
    $plan = [];
    $snackCount = 0;
    $usedToday = [];
    foreach ($slots as $i => $slot) {
        $ratio = $slot['share'] / $shareLeft;
        $target = [];
        foreach ($want as $k => $v) {
            $target[$k] = max(0, ($v - $done[$k]) * $ratio);
        }
        // Hai bữa phụ cùng loại thì lệch nhau 1 phương án để đỡ trùng món
        $offset = 0;
        if ($slot['kind'] === 'snack') {
            $offset = $snackCount;
            $snackCount++;
        }
        $isLast = $i === count($slots) - 1;
        $pick = (int)($picks[$i] ?? 0);
        $position = $variant + $offset + $pick;

        // Các phương án được phép dùng ở bữa này: bỏ món trùng hôm trước và món đã có trong ngày
        // (giữ lại số thứ tự gốc để truyền cho suggestMeal). Hết lựa chọn thì đành chấp nhận trùng.
        $names = array_column(allowedMealOptions($slot['kind'], $exclusions), 'name');
        $blocked = array_merge($usedToday, isset($avoid[$i]) ? [$avoid[$i]] : []);
        $usable = array_keys(array_diff($names, $blocked)) ?: array_keys($names);

        // Chỉ chọn trong các món không làm dư chất béo của bữa (ví dụ cá hồi, đậu hũ khi béo còn ít), vì chất béo đã dư thì
        // bữa sau không bù lại được. Bữa cuối tính chính xác tới 1 g nên ngưỡng chặt hơn các bữa đã làm tròn 5 g.
        // Không món nào vừa thì mặc định lấy món dư ít nhất, người dùng bấm đổi món sẽ chuyển sang món kế tiếp.
        $candidates = array_map(fn(int $index) => suggestMeal($slot, $target, $index, $isLast, $exclusions), $usable);
        $fatLimit = $target['f'] + ($isLast ? 0.5 : 2);
        $fit = array_values(array_filter($candidates, fn(array $c) => $c['raw']['f'] <= $fatLimit));
        if ($fit) {
            $suggestion = $fit[$position % count($fit)];
            $suggestion['can_swap'] = count($fit) > 1;
        } else {
            usort($candidates, fn(array $a, array $b) => $a['raw']['f'] <=> $b['raw']['f']);
            $suggestion = $candidates[$pick % count($candidates)];
            $suggestion['can_swap'] = count($candidates) > 1;
        }
        $usedToday[] = $suggestion['option'];
        foreach ($done as $k => $_) {
            $done[$k] += $suggestion['raw'][$k];
        }
        $shareLeft -= $slot['share'];
        $plan[] = ['slot' => $slot, 'target' => $target, 'suggestion' => $suggestion];
    }

    // Làm tròn từng bữa sao cho cộng lại đúng bằng tổng
    $total = [];
    foreach (['p', 'c', 'f', 'kcal'] as $k) {
        $rounded = roundToSum(array_map(fn($m) => $m['suggestion']['raw'][$k], $plan));
        foreach ($rounded as $i => $v) {
            $plan[$i]['suggestion'][$k] = $v;
        }
        $total[$k] = array_sum($rounded);
    }

    return [
        'meals'  => $plan,
        'total'  => $total,
        'target' => [
            'p'    => (int)round($targets['protein_g']),
            'c'    => (int)round($targets['carbs_g']),
            'f'    => (int)round($targets['fat_g']),
            'kcal' => (int)round($targets['target_kcal']),
        ],
    ];
}

/**
 * Lịch ăn 7 ngày, ngày 1 là Thứ Hai (cùng cách đánh số day_of_week của lịch tập).
 * Mỗi ngày truyền tên món của hôm trước vào buildMealPlan nên cùng một bữa không lặp món hai ngày liền nhau.
 *
 * @param array $dayTargets [1..7 => macro của ngày đó] (ngày tập và ngày nghỉ có thể khác nhau)
 * @param array $picksByDay [thứ trong tuần => picks] khi người dùng đổi món ở một ngày
 * @return array [1..7 => kết quả của buildMealPlan]
 */
function buildWeekPlan(array $dayTargets, int $meals, int $variant, array $exclusions = [], array $picksByDay = []): array
{
    $week = [];
    $previous = [];
    for ($day = 1; $day <= 7; $day++) {
        $plan = buildMealPlan($dayTargets[$day], $meals, $variant + $day, $exclusions, $picksByDay[$day] ?? [], $previous);
        $previous = array_map(fn(array $meal) => $meal['suggestion']['option'], $plan['meals']);
        $week[$day] = $plan;
    }
    return $week;
}

/**
 * Danh sách đi chợ: cộng dồn khối lượng từng thực phẩm trong cả tuần, xếp theo nhóm.
 * Khối lượng lấy nguyên từ lịch ăn nên là khối lượng sau khi nấu chín (yến mạch tính khô).
 *
 * @return array [['key','name','grams','group','hint']] theo thứ tự của foodCatalog()
 */
function buildShoppingList(array $week): array
{
    $grams = [];
    foreach ($week as $plan) {
        foreach ($plan['meals'] as $meal) {
            foreach ($meal['suggestion']['items'] as $item) {
                $grams[$item['key']] = ($grams[$item['key']] ?? 0) + $item['grams'];
            }
        }
    }

    $carbFoods = ['com', 'comlut', 'khoailang', 'khoaitay', 'bun', 'banhmi', 'yenmach', 'chuoi'];
    $fatFoods = ['dau', 'dauphong'];
    $list = [];
    foreach (foodCatalog() as $key => $food) {
        if (!isset($grams[$key])) {
            continue;
        }
        $hint = '';
        if (isset($food['unit']) && $food['unit'][0] === 'quả') {   // trứng, chuối: mua theo quả, làm tròn lên
            $hint = '≈ ' . (int)ceil($grams[$key] / $food['unit'][1]) . ' quả';
        }
        $list[] = [
            'key'   => $key,
            'name'  => $food['name'],
            'grams' => $grams[$key],
            'group' => in_array($key, $fatFoods, true) ? 'Chất béo' : (in_array($key, $carbFoods, true) ? 'Tinh bột và trái cây' : 'Nguồn đạm'),
            'hint'  => $hint,
        ];
    }
    return $list;
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
