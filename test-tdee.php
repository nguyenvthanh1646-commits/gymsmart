<?php
// test-tdee.php — kiểm tra BMR (Mifflin-St Jeor), TDEE và chia macro trong includes/tdee.php bằng số tính tay (G3).
// Chạy ở gốc repo: php test-tdee.php   (không cần DB)

require __DIR__ . '/public/includes/tdee.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}
/** Hàm phải ném InvalidArgumentException với dữ liệu sai. */
function throws(callable $fn): bool
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return true;
    }
    return false;
}
$near = fn(float $a, float $b, float $eps = 0.01) => abs($a - $b) < $eps;

// ===== BMR = 10 × kg + 6,25 × cm − 5 × tuổi + hằng số giới tính =====
// Nam 70 kg, 175 cm, 25 tuổi: 700 + 1093,75 − 125 + 5 = 1673,75
check('BMR nam 70 kg / 175 cm / 25 tuổi = 1673,75', $near(calculateBMR(70, 175, 25, 'MALE'), 1673.75));
// Nữ 55 kg, 160 cm, 30 tuổi: 550 + 1000 − 150 − 161 = 1239
check('BMR nữ 55 kg / 160 cm / 30 tuổi = 1239', $near(calculateBMR(55, 160, 30, 'FEMALE'), 1239.0));
// Khác: hằng số trung bình (5 − 161) / 2 = −78 → 550 + 1000 − 150 − 78 = 1322
check('BMR giới tính khác dùng −78', $near(calculateBMR(55, 160, 30, 'OTHER'), 1322.0));
check('Nam hơn nữ đúng 166 kcal khi cùng số đo', $near(calculateBMR(60, 165, 28, 'MALE') - calculateBMR(60, 165, 28, 'FEMALE'), 166.0));
check('Chuỗi số "70" vẫn tính được', $near(calculateBMR('70', '175', '25', 'MALE'), 1673.75));

check('Giới tính lạ bị chặn', throws(fn() => calculateBMR(70, 175, 25, 'male')));
check('Cân nặng 0 bị chặn', throws(fn() => calculateBMR(0, 175, 25, 'MALE')));
check('Chiều cao âm bị chặn', throws(fn() => calculateBMR(70, -1, 25, 'MALE')));
check('Chữ "70kg" bị chặn', throws(fn() => calculateBMR('70kg', 175, 25, 'MALE')));
check('NAN bị chặn', throws(fn() => calculateBMR(NAN, 175, 25, 'MALE')));
check('Tuổi 121 vượt giới hạn', throws(fn() => calculateBMR(70, 175, 121, 'MALE')));
check('Số đo vô lý cho BMR <= 0 bị chặn', throws(fn() => calculateBMR(1, 1, 120, 'FEMALE')));

// ===== TDEE = BMR × hệ số vận động =====
$bmr = 1673.75;
check('TDEE ít vận động ×1,2 = 2008,5', $near(calculateTDEE($bmr, 'SEDENTARY'), 2008.5));
check('TDEE vận động vừa ×1,55 = 2594,3', $near(calculateTDEE($bmr, 'MODERATE'), 2594.3125));
check('TDEE rất nhiều ×1,9 = 3180,1', $near(calculateTDEE($bmr, 'EXTRA_ACTIVE'), 3180.125));
check('Mức vận động lạ bị chặn', throws(fn() => calculateTDEE($bmr, 'VERY')));
check('BMR âm bị chặn', throws(fn() => calculateTDEE(-5, 'LIGHT')));

// ===== Macro =====
// Giảm mỡ, 70 kg, người mới, TDEE 2500: kcal 2000; đạm 1,6×70 = 112 g; béo 0,8×70 = 56 g; carb = (2000 − 448 − 504) / 4 = 262 g
$t = calculateTargets(2500, 70, 'LOSE_FAT', 'BEGINNER');
check('Giảm mỡ: 2000 kcal, đạm 112 g, béo 56 g, carb 262 g',
    $t['target_kcal'] == 2000.0 && $t['protein_g'] == 112.0 && $t['fat_g'] == 56.0 && $t['carbs_g'] == 262.0);
check('Tổng 4P + 4C + 9F bằng calo mục tiêu', $near($t['protein_g'] * 4 + $t['carbs_g'] * 4 + $t['fat_g'] * 9, $t['target_kcal'], 0.5));
$t = calculateTargets(2500, 70, 'GAIN_MUSCLE', 'ADVANCED');
check('Tăng cơ, đã có kinh nghiệm: 2750 kcal, đạm 1,8 g/kg = 126 g, béo 1,0 g/kg = 70 g',
    $t['target_kcal'] == 2750.0 && $t['protein_g'] == 126.0 && $t['fat_g'] == 70.0);
check('Không chọn trình độ thì dùng mức người mới 1,6 g/kg', calculateTargets(2500, 70, 'MAINTAIN', null)['protein_g'] == 112.0);
// Calo quá thấp so với cân nặng: carb âm thì về 0, không âm
$t = calculateTargets(1200, 150, 'LOSE_FAT', 'BEGINNER');
check('Carb không bao giờ âm', $t['carbs_g'] == 0.0);
check('Mục tiêu lạ bị chặn', throws(fn() => calculateTargets(2500, 70, 'BULK')));
check('Trình độ lạ bị chặn', throws(fn() => calculateTargets(2500, 70, 'MAINTAIN', 'PRO')));
check('TDEE 0 bị chặn', throws(fn() => calculateTargets(0, 70, 'MAINTAIN')));

// ===== Cả chuỗi khớp trang chủ khách (js/home-guest.js dùng cùng công thức) =====
// Nam 25 tuổi 170 cm 65 kg vận động vừa, giảm mỡ: BMR = 650 + 1062,5 − 125 + 5 = 1592,5; TDEE = 2468,4; mục tiêu = 1974,7
$tdee = calculateTDEE(calculateBMR(65, 170, 25, 'MALE'), 'MODERATE');
check('Chuỗi BMR → TDEE → mục tiêu khớp số tính tay', $near($tdee, 2468.375) && calculateTargets($tdee, 65, 'LOSE_FAT')['target_kcal'] == 1974.7);

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
