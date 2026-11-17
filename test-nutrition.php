<?php
/**
 * test-nutrition.php — kiểm tra phần tính toán của trang Dinh dưỡng.
 * Chạy: php test-nutrition.php. Phần thuần không cần DB, phần lưu lựa chọn dùng SQLite in-memory (cần pdo_sqlite).
 */

require_once __DIR__ . '/public/includes/tdee.php';
require_once __DIR__ . '/public/includes/meal-plan.php';
require_once __DIR__ . '/public/includes/nutrition-db.php';

$failed = 0;
function check(string $label, bool $ok): void
{
    global $failed;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

/** Mọi tổ hợp của 6 mã loại trừ. */
function allExclusionSets(): array
{
    $codes = array_keys(foodExclusions());
    $sets = [];
    for ($mask = 0; $mask < (1 << count($codes)); $mask++) {
        $set = [];
        foreach ($codes as $i => $code) {
            if ($mask & (1 << $i)) {
                $set[] = $code;
            }
        }
        $sets[] = $set;
    }
    return $sets;
}

/** Tổng cả ngày có khớp mục tiêu không (cùng tiêu chí với nutrition.php). */
function planMatches(array $plan): bool
{
    $ok = abs($plan['total']['kcal'] - $plan['target']['kcal']) <= $plan['target']['kcal'] * 0.01;
    foreach (['p', 'c', 'f'] as $k) {
        $ok = $ok && abs($plan['total'][$k] - $plan['target'][$k]) <= 1;
    }
    return $ok;
}

$macros = ['target_kcal' => 2200.0, 'protein_g' => 130.0, 'carbs_g' => 270.0, 'fat_g' => 66.0];

echo "--- calculateTargets và nhịp mục tiêu ---" . PHP_EOL;
$old = calculateTargets(2500, 70, 'LOSE_FAT', 'BEGINNER');
check('Không truyền nhịp thì giữ hệ số cũ 0,80', $old['target_kcal'] == 2000.0);
check('Nhịp standard bằng khi không truyền', calculateTargets(2500, 70, 'LOSE_FAT', 'BEGINNER', 'standard') === $old);
check('Tăng cơ mặc định vẫn 1,10', calculateTargets(2500, 70, 'GAIN_MUSCLE')['target_kcal'] == 2750.0);
check('Giảm mỡ nhịp chậm 0,90', calculateTargets(2500, 70, 'LOSE_FAT', null, 'gentle')['target_kcal'] == 2250.0);
check('Giảm mỡ nhịp nhanh 0,75', calculateTargets(2500, 70, 'LOSE_FAT', null, 'fast')['target_kcal'] == 1875.0);
check('Duy trì thì nhịp nào cũng bằng TDEE', calculateTargets(2500, 70, 'MAINTAIN', null, 'fast')['target_kcal'] == 2500.0);
$threw = false;
try {
    calculateTargets(2500, 70, 'LOSE_FAT', null, 'bay');
} catch (InvalidArgumentException $e) {
    $threw = true;
}
check('Nhịp lạ bị từ chối', $threw);
check('Thâm hụt 1100 kcal/ngày là −1 kg/tuần', abs(expectedWeeklyChange(1400, 2500) + 1.0) < 0.001);

echo "--- Ngày tập và ngày nghỉ ---" . PHP_EOL;
foreach ([1, 3, 4, 6] as $n) {
    $split = splitTrainingRestDays($macros, $n);
    $week = $n * $split['train']['target_kcal'] + (7 - $n) * $split['rest']['target_kcal'];
    check("$n ngày tập: tổng calo tuần không đổi", abs($week - 7 * $macros['target_kcal']) < 1);
    check("$n ngày tập: ngày tập nhiều hơn ngày nghỉ", $split['train']['target_kcal'] > $split['rest']['target_kcal']);
    check("$n ngày tập: protein, chất béo giữ nguyên", $split['train']['protein_g'] == 130.0 && $split['rest']['fat_g'] == 66.0);
    $carbKcal = ($split['train']['carbs_g'] - $split['rest']['carbs_g']) * 4;
    check("$n ngày tập: phần chênh chỉ nằm ở tinh bột", abs($carbKcal - ($split['train']['target_kcal'] - $split['rest']['target_kcal'])) < 1);
}
check('0 ngày tập thì không chia', splitTrainingRestDays($macros, 0) === null);
check('7 ngày tập thì không chia', splitTrainingRestDays($macros, 7) === null);
check('Không có tinh bột thì không chia', splitTrainingRestDays(['carbs_g' => 0.0] + $macros, 3) === null);
$low = splitTrainingRestDays(['carbs_g' => 40.0] + $macros, 6);
check('Ngày nghỉ không giảm quá 40% tinh bột', $low['rest']['carbs_g'] >= 24.0);

echo "--- Loại trừ thực phẩm ---" . PHP_EOL;
check('Mã lạ và phần tử không phải chuỗi bị bỏ', normalizeExclusions(['egg', 'xx', ['dairy'], 5]) === ['egg']);
check('Đọc được chuỗi từ DB, xếp đúng thứ tự', normalizeExclusions('dairy,vegetarian') === ['vegetarian', 'dairy']);
check('Giá trị không phải mảng hay chuỗi', normalizeExclusions(null) === []);

$minOptions = 99;
$leaked = 0;
foreach (allExclusionSets() as $set) {
    $banned = [];
    foreach ($set as $code) {
        $banned = array_merge($banned, foodExclusions()[$code]['foods']);
    }
    foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $kind) {
        $minOptions = min($minOptions, count(allowedMealOptions($kind, $set)));
    }
    foreach ([3, 4, 5] as $meals) {
        foreach ([0, 1, 2, 3, 4, 5, 6] as $v) {
            foreach (buildMealPlan($macros, $meals, $v, $set)['meals'] as $meal) {
                foreach ($meal['suggestion']['items'] as $item) {
                    $leaked += in_array($item['key'], $banned, true) ? 1 : 0;
                }
            }
        }
    }
}
check("Mọi tổ hợp loại trừ còn ít nhất 2 món mỗi loại bữa (ít nhất: $minOptions)", $minOptions >= 2);
check('Không tổ hợp nào lọt thực phẩm bị loại', $leaked === 0);

echo "--- Đổi món từng bữa ---" . PHP_EOL;
check('Đọc được chuỗi picks', parseMealPicks('1,0,2,0', 4) === [1, 0, 2, 0]);
check('Sai số bữa thì coi như chưa đổi', parseMealPicks('1,0', 4) === [0, 0, 0, 0]);
check('Sai định dạng thì coi như chưa đổi', parseMealPicks('1,a,2,0', 4) === [0, 0, 0, 0]);
check('Mảng trên URL thì coi như chưa đổi', parseMealPicks(['1'], 3) === [0, 0, 0]);

$base = buildMealPlan($macros, 4, 0);
$noSwapMismatch = 0;
$sameDish = 0;
foreach ([0, 1, 2, 3] as $slot) {
    $picks = [0, 0, 0, 0];
    $picks[$slot] = 1;
    $swapped = buildMealPlan($macros, 4, 0, [], $picks);
    $sameDish += $swapped['meals'][$slot]['suggestion']['option'] === $base['meals'][$slot]['suggestion']['option'] ? 1 : 0;
    $noSwapMismatch += planMatches($swapped) ? 0 : 1;
}
check('Bấm đổi món thì bữa đó đổi sang món khác', $sameDish === 0);
check('Đổi món ở bất kỳ bữa nào tổng ngày vẫn khớp', $noSwapMismatch === 0);

$matched = $tried = 0;
foreach (range(0, 9) as $v) {
    foreach ([0, 1, 2, 3, 4] as $pick) {
        foreach ([3, 4, 5] as $meals) {
            $tried++;
            $matched += planMatches(buildMealPlan($macros, $meals, $v, [], array_fill(0, $meals, $pick))) ? 1 : 0;
        }
    }
}
check("Không loại trừ: mọi cách đổi món đều khớp ($matched/$tried)", $matched === $tried);

// Thống kê để biết tổ hợp nào không khớp được. Không đặt ngưỡng cho thuần chay vì đạm thực vật kéo theo chất béo.
foreach (['vegetarian' => ['vegetarian'], 'thuần chay' => ['vegetarian', 'egg', 'dairy'], 'không hải sản' => ['seafood']] as $label => $set) {
    $ok = $n = 0;
    foreach (range(0, 13) as $v) {
        foreach ([3, 4, 5] as $meals) {
            $n++;
            $ok += planMatches(buildMealPlan($macros, $meals, $v, $set)) ? 1 : 0;
        }
    }
    echo "       (thống kê) $label: $ok/$n ngày khớp mục tiêu" . PHP_EOL;
    check("$label: phần lớn số ngày khớp mục tiêu", $ok >= $n * 0.8);
}

echo "--- Lịch 7 ngày và đi chợ ---" . PHP_EOL;
$dayTargets = array_fill(1, 7, $macros);
$repeat = 0;
foreach (allExclusionSets() as $set) {
    foreach ([3, 4, 5] as $meals) {
        foreach ([0, 5] as $v) {
            $week = buildWeekPlan($dayTargets, $meals, $v, $set);
            for ($d = 2; $d <= 7; $d++) {
                foreach ($week[$d]['meals'] as $i => $meal) {
                    $repeat += $meal['suggestion']['option'] === $week[$d - 1]['meals'][$i]['suggestion']['option'] ? 1 : 0;
                }
            }
        }
    }
}
check('Không bữa nào lặp món hai ngày liền nhau, kể cả khi loại trừ nhiều', $repeat === 0);

$week = buildWeekPlan($dayTargets, 4, 0);
check('Có đủ 7 ngày, Thứ Hai là ngày 1', array_keys($week) === [1, 2, 3, 4, 5, 6, 7]);
$list = buildShoppingList($week);
$sum = 0;
foreach ($week as $plan) {
    foreach ($plan['meals'] as $meal) {
        $sum += array_sum(array_column($meal['suggestion']['items'], 'grams'));
    }
}
check('Đi chợ: tổng khối lượng bằng đúng tổng trong lịch', array_sum(array_column($list, 'grams')) === $sum);
check('Đi chợ: mỗi thực phẩm chỉ một dòng', count(array_unique(array_column($list, 'key'))) === count($list));
$picksWeek = buildWeekPlan($dayTargets, 4, 0, [], [3 => [1, 0, 0, 0]]);
check('Đổi món ở một ngày chỉ làm đổi ngày đó trở đi', $picksWeek[2] === $week[2] && $picksWeek[3]['meals'][0]['suggestion']['option'] !== $week[3]['meals'][0]['suggestion']['option']);

echo "--- Tiến độ cân nặng ---" . PHP_EOL;
function series(array $pairs): array
{
    return array_map(fn($p) => ['recorded_date' => $p[0], 'weight_kg' => $p[1]], $pairs);
}
$today = '2026-11-08';
check('Không có số đo', analyzeWeightProgress([], 2000, 2500, 1500, $today)['status'] === 'need_more');
$two = analyzeWeightProgress(series([['2026-10-20', 70], ['2026-11-08', 69]]), 2000, 2500, 1500, $today);
check('Mới 2 lần đo thì chưa kết luận', $two['status'] === 'need_more' && $two['count'] === 2);
$short = analyzeWeightProgress(series([['2026-11-01', 70], ['2026-11-04', 69.8], ['2026-11-08', 69.5]]), 2000, 2500, 1500, $today);
check('Chưa đủ 14 ngày thì chưa kết luận', $short['status'] === 'need_more' && $short['span_days'] === 7);
$stale = analyzeWeightProgress(series([['2026-09-01', 70], ['2026-09-08', 69.5], ['2026-09-15', 69]]), 2000, 2500, 1500, $today);
check('Số đo quá cũ thì báo cần cân lại', $stale['status'] === 'stale');

// Kế hoạch 2000/2500: −500 kcal/ngày ≈ −0,4545 kg/tuần
$onTrack = analyzeWeightProgress(series([['2026-10-18', 70.0], ['2026-10-25', 69.55], ['2026-11-01', 69.1], ['2026-11-08', 68.65]]), 2000, 2500, 1500, $today);
check('Đúng kế hoạch thì không chỉnh', $onTrack['status'] === 'on_track' && $onTrack['adjust_kcal'] === 0);
check('Độ dốc đúng −0,45 kg/tuần', abs($onTrack['actual_per_week'] + 0.45) < 0.001);
check('TDEE thực tế gần TDEE lý thuyết khi đúng kế hoạch', abs($onTrack['estimated_tdee'] - 2495) < 1);

$flat = analyzeWeightProgress(series([['2026-10-18', 70.0], ['2026-10-25', 70.0], ['2026-11-01', 70.0], ['2026-11-08', 70.0]]), 2000, 2500, 1500, $today);
check('Giảm mỡ mà cân đứng yên: gợi ý bớt calo', $flat['status'] === 'above_plan' && $flat['adjust_kcal'] < 0);
check('Mỗi lần chỉnh tối đa 250 kcal, bội số 50', abs($flat['adjust_kcal']) <= 250 && $flat['adjust_kcal'] % 50 === 0);
check('TDEE thực tế khi cân đứng yên bằng lượng đang ăn', abs($flat['estimated_tdee'] - 2000) < 1);

$fast = analyzeWeightProgress(series([['2026-10-18', 70.0], ['2026-10-25', 69.0], ['2026-11-01', 68.0], ['2026-11-08', 67.0]]), 2000, 2500, 1500, $today);
check('Giảm nhanh quá kế hoạch: gợi ý tăng calo', $fast['status'] === 'below_plan' && $fast['adjust_kcal'] > 0);

$floored = analyzeWeightProgress(series([['2026-10-18', 70.0], ['2026-10-25', 70.0], ['2026-11-01', 70.0], ['2026-11-08', 70.0]]), 1550, 2050, 1500, $today);
check('Không gợi ý thấp hơn mức sàn', $floored['suggested_kcal'] >= 1500 && $floored['limited'] === true);
$underFloor = analyzeWeightProgress(series([['2026-10-18', 70.0], ['2026-10-25', 70.0], ['2026-11-01', 70.0], ['2026-11-08', 70.0]]), 1400, 1900, 1500, $today);
check('Đang dưới sàn thì không gợi ý bớt thêm', $underFloor['adjust_kcal'] === 0);

$noisy = analyzeWeightProgress(series([['2026-10-18', 70.0], ['2026-10-25', 69.0], ['2026-11-01', 70.1], ['2026-11-08', 68.9]]), 2000, 2500, 1500, $today);
check('Một lần cân lệch không làm kết quả nhảy như so hai đầu mút', abs($noisy['actual_per_week']) < abs(68.9 - 70.0) / 3);
$old2 = analyzeWeightProgress(series([['2026-06-01', 90.0], ['2026-10-18', 70.0], ['2026-10-25', 69.55], ['2026-11-01', 69.1], ['2026-11-08', 68.65]]), 2000, 2500, 1500, $today);
check('Bỏ số đo ngoài 28 ngày', $old2['status'] === 'on_track' && $old2['count'] === 4);

echo "--- Lựa chọn người dùng ---" . PHP_EOL;
check('Session kiểu cũ vẫn dùng được', normalizeNutritionPrefs(['diet' => 'lowcarb', 'p' => 25, 'c' => 45, 'f' => 30])['diet'] === 'lowcarb');
check('Kiểu ăn lạ về mặc định', normalizeNutritionPrefs(['diet' => 'xyz'])['diet'] === 'goal');
check('Kiểu ăn dạng mảng về mặc định', normalizeNutritionPrefs(['diet' => ['a']])['diet'] === 'goal');
check('Số bữa lạ về 4', normalizeNutritionPrefs(['meals' => 9])['meals'] === 4);
check('Nhịp lạ về standard', normalizeNutritionPrefs(['pace' => 'x'])['pace'] === 'standard');
check('Tùy chỉnh sai tổng về mặc định', normalizeNutritionPrefs(['diet' => 'custom', 'p' => 40, 'c' => 40, 'f' => 40])['diet'] === 'goal');

if (extension_loaded('pdo_sqlite')) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tắt ghi log trong lúc giả lập chưa import bảng để kết quả test dễ đọc
    $oldLog = ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
    check('Chưa import bảng: đọc trả về null', loadNutritionPrefs($pdo, 1) === null);
    check('Chưa import bảng: ghi trả về false', saveNutritionPrefs($pdo, 1, nutritionPrefsDefaults()) === false);
    ini_set('error_log', (string)$oldLog);

    $pdo->exec("CREATE TABLE nutrition_preferences (
        user_id INTEGER PRIMARY KEY, diet_style TEXT NOT NULL, custom_protein_pct INT NOT NULL, custom_carbs_pct INT NOT NULL, custom_fat_pct INT NOT NULL,
        meals_per_day INT NOT NULL, excluded_foods TEXT NOT NULL, weight_pace TEXT NOT NULL, cycle_calories INT NOT NULL)");
    check('Chưa có dòng nào thì đọc ra null', loadNutritionPrefs($pdo, 1) === null);
    $saved = ['diet' => 'custom', 'p' => 30, 'c' => 40, 'f' => 30, 'meals' => 5, 'exclusions' => ['vegetarian', 'egg'], 'pace' => 'fast', 'cycle' => true];
    check('Lưu dòng mới', saveNutritionPrefs($pdo, 1, $saved));
    check('Đọc lại đúng từng giá trị', loadNutritionPrefs($pdo, 1) === $saved);
    check('Lưu lần 2 là cập nhật, không thêm dòng', saveNutritionPrefs($pdo, 1, ['meals' => 3] + $saved) && (int)$pdo->query('SELECT COUNT(*) FROM nutrition_preferences')->fetchColumn() === 1);
    check('Giá trị mới đã được ghi', loadNutritionPrefs($pdo, 1)['meals'] === 3);
    check('Người khác không thấy lựa chọn này', loadNutritionPrefs($pdo, 2) === null);
} else {
    echo "[BỎ QUA] Chưa bật pdo_sqlite nên chưa test phần lưu lựa chọn" . PHP_EOL;
}

echo PHP_EOL . ($failed === 0 ? 'TẤT CẢ ĐỀU PASS' : "CÓ $failed KIỂM TRA THẤT BẠI") . PHP_EOL;
exit($failed === 0 ? 0 : 1);
