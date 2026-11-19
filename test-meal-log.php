<?php
// test-meal-log.php — kiểm tra nhật ký ăn (includes/meal-log.php): hàm thuần và luồng DB trên SQLite.
// Chạy ở gốc repo: php test-meal-log.php

require __DIR__ . '/public/includes/meal-log.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}
function errOf(callable $fn): ?string
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return $e->getMessage();
    }
    return null;
}

// ===== Hàm thuần =====
// Ức gà 31 g đạm, 0 tinh bột, 3,6 g béo / 100 g; 150 g → đạm 46,5; béo 5,4; kcal = (31×4 + 3,6×9) × 1,5 = 234,6
$v = mealPortion(31, 0, 3.6, 150);
check('150 g ức gà: 46,5 g đạm, 5,4 g béo, 234,6 kcal (4P + 4C + 9F)', $v === ['kcal' => 234.6, 'protein' => 46.5, 'carbs' => 0.0, 'fat' => 5.4]);
check('0 g thì mọi số bằng 0', mealPortion(10, 10, 10, 0)['kcal'] == 0);
check('Tên quá ngắn bị chặn', userFoodError('a', 10, 10, 10) !== null);
check('Đạm âm bị chặn', userFoodError('Sữa', -1, 5, 3) !== null);
check('Tổng P + C + F > 100 g bị chặn', userFoodError('Bánh', 30, 60, 20) !== null);
check('Chữ thay số bị chặn', userFoodError('Bánh', 'abc', 10, 10) !== null);
check('Thực phẩm theo nhãn hợp lệ', userFoodError('Sữa tươi không đường', 3.2, 4.8, 3.3) === null);

$rows = [
    ['meal_kind' => 'breakfast', 'kcal' => 500, 'protein_g' => 30, 'carbs_g' => 60, 'fat_g' => 15],
    ['meal_kind' => 'lunch', 'kcal' => 700, 'protein_g' => 45, 'carbs_g' => 80, 'fat_g' => 20],
    ['meal_kind' => 'lunch', 'kcal' => 100, 'protein_g' => 5, 'carbs_g' => 10, 'fat_g' => 4],
];
$s = mealDaySummary($rows, ['target_kcal' => 2000, 'protein_g' => 140, 'carbs_g' => 220, 'fat_g' => 60]);
check('Tổng ngày: 1300 kcal, 80 g đạm', $s['total']['kcal'] == 1300 && $s['total']['protein'] == 80);
check('Theo bữa: trưa 800 kcal, tối 0', $s['byMeal']['lunch'] == 800 && $s['byMeal']['dinner'] == 0);
check('Còn lại 700 kcal, 60 g đạm; đạt 65% calo', $s['left']['kcal'] == 700 && $s['left']['protein'] == 60 && $s['percent']['kcal'] === 65);
$s = mealDaySummary($rows, null);
check('Chưa có mục tiêu: không tính phần còn lại', $s['left'] === null && $s['percent'] === null);

// ===== Luồng DB =====
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "[BỎ QUA] Không có pdo_sqlite nên không chạy phần DB\n";
} else {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE user_foods (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, name TEXT, protein_100g REAL, carbs_100g REAL, fat_100g REAL, created_at TEXT)');
    $pdo->exec('CREATE TABLE meal_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, log_date TEXT, meal_kind TEXT, food_key TEXT, user_food_id INT,
        food_name TEXT, grams REAL, kcal REAL, protein_g REAL, carbs_g REAL, fat_g REAL, created_at TEXT)');
    $today = date('Y-m-d');

    check('Ghi món từ bảng thực phẩm (ức gà 150 g)', errOf(fn() => mealLogAdd($pdo, 1, $today, 'lunch', 'c:ucga', 150)) === null);
    $r = mealLogDay($pdo, 1, $today)[0];
    check('Máy chủ tự tính và chụp lại số liệu: 234,6 kcal', (float)$r['kcal'] == 234.6 && $r['food_name'] === 'Ức gà luộc/nướng');
    check('Mã thực phẩm không có trong bảng bị chặn', errOf(fn() => mealLogAdd($pdo, 1, $today, 'lunch', 'c:phobo', 100)) !== null);
    check('Bữa sai bị chặn', errOf(fn() => mealLogAdd($pdo, 1, $today, 'brunch', 'c:com', 100)) !== null);
    check('Ngày tương lai bị chặn', errOf(fn() => mealLogAdd($pdo, 1, date('Y-m-d', strtotime('+1 day')), 'lunch', 'c:com', 100)) !== null);
    check('Ngày sai định dạng bị chặn', errOf(fn() => mealLogAdd($pdo, 1, '2026-02-31', 'lunch', 'c:com', 100)) !== null);
    check('Khối lượng 0 hoặc quá lớn bị chặn', errOf(fn() => mealLogAdd($pdo, 1, $today, 'lunch', 'c:com', 0)) !== null && errOf(fn() => mealLogAdd($pdo, 1, $today, 'lunch', 'c:com', 9999)) !== null);

    $fid = userFoodAdd($pdo, 1, 'Sữa tươi không đường (theo nhãn)', 3.2, 4.8, 3.3);
    check('Thêm thực phẩm theo nhãn', $fid > 0 && count(userFoods($pdo, 1)) === 1);
    check('Ghi món từ thực phẩm tự thêm (250 g)', errOf(fn() => mealLogAdd($pdo, 1, $today, 'breakfast', 'u:' . $fid, 250)) === null);
    check('Người khác không dùng được thực phẩm của mình', errOf(fn() => mealLogAdd($pdo, 2, $today, 'breakfast', 'u:' . $fid, 100)) !== null);
    check('Người khác không xóa được món trong nhật ký của mình', errOf(fn() => mealLogDelete($pdo, 2, 1)) !== null);
    check('Người khác không thấy nhật ký của mình', mealLogDay($pdo, 2, $today) === []);
    userFoodDelete($pdo, 1, $fid);
    check('Xóa thực phẩm tự thêm: dòng đã ghi vẫn giữ tên và số liệu', count(mealLogDay($pdo, 1, $today)) === 2);
    mealLogDelete($pdo, 1, 1);
    check('Xóa món trong nhật ký của mình', count(mealLogDay($pdo, 1, $today)) === 1);
}

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
