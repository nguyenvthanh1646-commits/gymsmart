<?php
// test-equipment.php — kiểm tra bài tương đương khi thiếu dụng cụ và kỷ lục cá nhân (includes/equipment.php).
// Chạy ở gốc repo: php test-equipment.php   (không cần DB; dùng vai trò thật trong includes/workout-plan.php)

require __DIR__ . '/public/includes/workout-plan.php';
require __DIR__ . '/public/includes/equipment.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}

// Một phần thư viện thật (id, tên, nhóm cơ, dụng cụ đúng như sql/exercise_equipment.sql)
$lib = [
    ['id' => 1, 'name' => 'Hít đất', 'muscle_group' => 'CHEST', 'equipment' => 'NONE'],
    ['id' => 2, 'name' => 'Ép ngực máy Pec Deck', 'muscle_group' => 'CHEST', 'equipment' => 'MACHINE'],
    ['id' => 3, 'name' => 'Đẩy ngực tạ đòn', 'muscle_group' => 'CHEST', 'equipment' => 'BARBELL'],
    ['id' => 4, 'name' => 'Đẩy ngực tạ đơn ghế nghiêng', 'muscle_group' => 'CHEST', 'equipment' => 'DUMBBELL'],
    ['id' => 5, 'name' => 'Pullover tạ đơn', 'muscle_group' => 'CHEST', 'equipment' => 'DUMBBELL'],
    ['id' => 6, 'name' => 'Dips ngực song song', 'muscle_group' => 'CHEST', 'equipment' => 'BARS'],
    ['id' => 26, 'name' => 'Cuốn tạ đòn bắp tay', 'muscle_group' => 'ARMS', 'equipment' => 'BARBELL'],
    ['id' => 27, 'name' => 'Cuốn tạ đơn kiểu búa', 'muscle_group' => 'ARMS', 'equipment' => 'DUMBBELL'],
    ['id' => 99, 'name' => 'Bài admin mới', 'muscle_group' => 'ARMS', 'equipment' => 'NONE'],
];

$eq = equivalentExercises(2, $lib, WORKOUT_EXERCISE_ROLES);
check('Ép ngực máy: chỉ lấy bài cùng vai trò ngực ngang (không lấy ngực trên id 4)', !in_array(4, array_column($eq, 'id'), true) && in_array(1, array_column($eq, 'id'), true));
check('Không dụng cụ (hít đất) xếp đầu vì dễ có nhất', $eq[0]['id'] === 1 && $eq[0]['label'] === 'Không dụng cụ');
check('Đánh dấu là cùng vai trò', $eq[0]['same'] === 'role');
$eq = equivalentExercises(2, $lib, WORKOUT_EXERCISE_ROLES, ['DUMBBELL']);
check('Chỉ có tạ đơn: còn hít đất (không dụng cụ) và pullover tạ đơn', array_column($eq, 'id') === [1, 5]);
check('Không trả lại chính bài gốc', !in_array(2, array_column(equivalentExercises(2, $lib, WORKOUT_EXERCISE_ROLES), 'id'), true));
check('Tạ đòn bắp tay: tương đương là tạ đơn kiểu búa', array_column(equivalentExercises(26, $lib, WORKOUT_EXERCISE_ROLES), 'id') === [27]);
$eq = equivalentExercises(99, $lib, WORKOUT_EXERCISE_ROLES);
check('Bài chưa có vai trò: dùng bài cùng nhóm cơ, ghi rõ "muscle"', array_column($eq, 'id') === [27, 26] && $eq[0]['same'] === 'muscle');
check('Id không có trong thư viện: rỗng', equivalentExercises(12345, $lib, WORKOUT_EXERCISE_ROLES) === []);
check('Giới hạn số bài trả về', count(equivalentExercises(2, $lib, WORKOUT_EXERCISE_ROLES, null, 2)) === 2);
check('Nhãn dụng cụ lạ', equipmentLabel('XYZ') === 'Không rõ' && equipmentLabel(null) === 'Không dụng cụ');

// ===== Kỷ lục cá nhân =====
$logs = [
    ['exercise_id' => 18, 'name' => 'Squat', 'date' => '2026-11-02', 'weight' => 40, 'reps' => 8],
    ['exercise_id' => 18, 'name' => 'Squat', 'date' => '2026-11-09', 'weight' => 42.5, 'reps' => 6],
    ['exercise_id' => 18, 'name' => 'Squat', 'date' => '2026-11-12', 'weight' => 42.5, 'reps' => 8],
    ['exercise_id' => 18, 'name' => 'Squat', 'date' => '2026-11-16', 'weight' => 42.5, 'reps' => 7],
    ['exercise_id' => 1, 'name' => 'Hít đất', 'date' => '2026-11-02', 'weight' => 0, 'reps' => 12],
    ['exercise_id' => 1, 'name' => 'Hít đất', 'date' => '2026-11-09', 'weight' => 0, 'reps' => 15],
];
$pr = personalRecords($logs);
check('Kỷ lục squat: 42,5 kg × 8 lặp, đạt ngày 12/11', $pr[18]['weight'] == 42.5 && $pr[18]['reps'] === 8 && $pr[18]['date'] === '2026-11-12' && $pr[18]['kind'] === 'weight');
check('Bài không tạ: kỷ lục theo số lặp (15)', $pr[1]['reps'] === 15 && $pr[1]['kind'] === 'reps');
check('Thứ tự nhật ký đầu vào không ảnh hưởng', personalRecords(array_reverse($logs)) == $pr);

$before = personalRecords($logs);
$new = newRecords($before, [['exercise_id' => 18, 'name' => 'Squat', 'date' => '2026-11-18', 'weight' => 45, 'reps' => 6],
                            ['exercise_id' => 1, 'name' => 'Hít đất', 'date' => '2026-11-18', 'weight' => 0, 'reps' => 14],
                            ['exercise_id' => 7, 'name' => 'Kéo cáp', 'date' => '2026-11-18', 'weight' => 30, 'reps' => 8]]);
check('Kỷ lục mới: squat 45 kg (cũ 42,5)', count($new) === 1 && $new[0]['name'] === 'Squat' && $new[0]['weight'] == 45 && $new[0]['oldWeight'] == 42.5);
check('Bài lần đầu tập (kéo cáp) không tính là kỷ lục mới', !in_array('Kéo cáp', array_column($new, 'name'), true));
check('Không vượt kỷ lục cũ (hít đất 14 < 15) thì không báo', !in_array('Hít đất', array_column($new, 'name'), true));
check('Cùng mức tạ nhưng nhiều lặp hơn cũng là kỷ lục', count(newRecords($before, [['exercise_id' => 18, 'name' => 'Squat', 'date' => '2026-11-18', 'weight' => 42.5, 'reps' => 9]])) === 1);

$after = personalRecords(array_merge($logs, [['exercise_id' => 18, 'name' => 'Squat', 'date' => '2026-11-18', 'weight' => 45, 'reps' => 6]]));
check('So trước/sau buổi: báo squat 45 kg là kỷ lục mới', recordsImproved($before, $after) === [['name' => 'Squat', 'weight' => 45.0, 'reps' => 6, 'oldWeight' => 42.5, 'oldReps' => 8]]);
check('So trước/sau buổi: không đổi thì không báo', recordsImproved($before, $before) === []);

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
