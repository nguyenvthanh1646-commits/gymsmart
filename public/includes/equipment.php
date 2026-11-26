<?php
/**
 * equipment.php — dụng cụ của bài tập, bài tương đương khi thiếu dụng cụ, kỷ lục cá nhân (B3, A-47).
 * Hàm THUẦN, không đụng DB, test ở test-equipment.php.
 *
 * "Tương đương" = cùng VAI TRÒ trong WORKOUT_EXERCISE_ROLES (includes/workout-plan.php), tức cùng tác động một cơ theo
 * cùng kiểu chuyển động (vd ép ngực máy và hít đất đều là CHEST_FLAT), chỉ khác dụng cụ. Bài chưa có vai trò thì
 * lấy bài cùng nhóm cơ (muscle_group) làm phương án dự phòng, có ghi rõ là "cùng nhóm cơ".
 */

const EQUIPMENT_LABELS = [
    'NONE' => 'Không dụng cụ', 'BENCH' => 'Ghế', 'BARS' => 'Xà song song', 'BAND' => 'Dây kháng lực', 'ROPE' => 'Dây nhảy',
    'DUMBBELL' => 'Tạ đơn', 'BARBELL' => 'Tạ đòn', 'MACHINE' => 'Máy tập', 'CABLE' => 'Máy cáp',
];
// Mức "dễ có" của dụng cụ: tập ở nhà thường chỉ có 0-2; phòng gym có đủ. Dùng để xếp bài tương đương dễ làm lên trước.
const EQUIPMENT_ACCESS = ['NONE' => 0, 'BENCH' => 1, 'BAND' => 1, 'ROPE' => 1, 'DUMBBELL' => 2, 'BARS' => 3, 'BARBELL' => 3, 'CABLE' => 3, 'MACHINE' => 3];

function equipmentLabel(?string $code): string
{
    return EQUIPMENT_LABELS[$code ?? 'NONE'] ?? 'Không rõ';
}

/**
 * Bài tương đương với bài $exerciseId.
 * @param array $library [['id','name','muscle_group','equipment'], ...] toàn bộ bài tập
 * @param array $roles   bảng id => vai trò (WORKOUT_EXERCISE_ROLES)
 * @param array|null $have chỉ lấy bài dùng các dụng cụ này (null = không lọc). NONE luôn được tính là có.
 * @return array [['id','name','equipment','label','same' => 'role'|'muscle'], ...] dễ làm trước, tối đa $limit bài
 */
function equivalentExercises(int $exerciseId, array $library, array $roles, ?array $have = null, int $limit = 4): array
{
    $byId = array_column($library, null, 'id');
    if (!isset($byId[$exerciseId])) {
        return [];
    }
    $me = $byId[$exerciseId];
    $role = $roles[$exerciseId] ?? null;
    if ($have !== null) {
        $have[] = 'NONE';
    }
    $out = [];
    foreach ($library as $ex) {
        $id = (int)$ex['id'];
        if ($id === $exerciseId) {
            continue;
        }
        $same = null;
        if ($role !== null && ($roles[$id] ?? null) === $role) {
            $same = 'role';
        } elseif ($role === null && $ex['muscle_group'] === $me['muscle_group']) {
            $same = 'muscle';
        }
        $eq = (string)($ex['equipment'] ?? 'NONE');
        if ($same === null || ($have !== null && !in_array($eq, $have, true))) {
            continue;
        }
        $out[] = ['id' => $id, 'name' => (string)$ex['name'], 'equipment' => $eq, 'label' => equipmentLabel($eq), 'same' => $same];
    }
    // Khác dụng cụ với bài gốc lên trước (mục đích là thay khi thiếu dụng cụ), rồi dụng cụ dễ có trước
    usort($out, function ($a, $b) use ($me) {
        $da = $a['equipment'] === ($me['equipment'] ?? '') ? 1 : 0;
        $db = $b['equipment'] === ($me['equipment'] ?? '') ? 1 : 0;
        return [$da, EQUIPMENT_ACCESS[$a['equipment']] ?? 9, $a['id']] <=> [$db, EQUIPMENT_ACCESS[$b['equipment']] ?? 9, $b['id']];
    });
    return array_slice($out, 0, $limit);
}

/**
 * Kỷ lục cá nhân theo từng bài: mức tạ nặng nhất từng ghi (bài có tạ), hoặc số lặp nhiều nhất (bài không tạ, ghi 0 kg).
 * @param array $logs [['exercise_id','name','date','weight','reps'], ...] thứ tự bất kỳ
 * @return array [exercise_id => ['name','weight','reps','date','kind' => 'weight'|'reps']], ngày là lần ĐẦU đạt mức đó
 */
function personalRecords(array $logs): array
{
    usort($logs, fn($a, $b) => [$a['date'], (int)($a['id'] ?? 0)] <=> [$b['date'], (int)($b['id'] ?? 0)]);   // cũ đến mới
    $pr = [];
    foreach ($logs as $l) {
        $id = (int)$l['exercise_id'];
        $w = (float)$l['weight'];
        $r = (int)$l['reps'];
        $cur = $pr[$id] ?? null;
        $better = $cur === null
            || ($w > $cur['weight'])
            || ($w == $cur['weight'] && $r > $cur['reps']);
        if ($better && ($w > 0 || $r > 0)) {
            $pr[$id] = ['name' => (string)$l['name'], 'weight' => $w, 'reps' => $r, 'date' => (string)$l['date'], 'kind' => $w > 0 ? 'weight' : 'reps'];
        }
    }
    return $pr;
}

/**
 * Kỷ lục MỚI sau một buổi: so kỷ lục trước buổi ($before) với các dòng vừa lưu ($new, cùng định dạng personalRecords).
 * Bài lần đầu tập không tính là kỷ lục (chưa có gì để phá). Trả [['name','weight','reps','oldWeight','oldReps'], ...]
 */
function newRecords(array $before, array $new): array
{
    $out = [];
    foreach (personalRecords($new) as $id => $now) {
        $old = $before[$id] ?? null;
        if ($old === null) {
            continue;
        }
        if ($now['weight'] > $old['weight'] || ($now['weight'] == $old['weight'] && $now['reps'] > $old['reps'])) {
            $out[] = ['name' => $now['name'], 'weight' => $now['weight'], 'reps' => $now['reps'], 'oldWeight' => $old['weight'], 'oldReps' => $old['reps']];
        }
    }
    return $out;
}

/**
 * So kỷ lục TRƯỚC và SAU một buổi (cả hai là kết quả personalRecords()): bài nào có kỷ lục tốt hơn và đã từng có kỷ lục trước đó.
 * Dùng ở api/log-session.php để báo "Kỷ lục mới" ngay sau khi lưu. Trả cùng định dạng newRecords().
 */
function recordsImproved(array $before, array $after): array
{
    $out = [];
    foreach ($after as $id => $now) {
        $old = $before[$id] ?? null;
        if ($old !== null && ($now['weight'] > $old['weight'] || ($now['weight'] == $old['weight'] && $now['reps'] > $old['reps']))) {
            $out[] = ['name' => $now['name'], 'weight' => $now['weight'], 'reps' => $now['reps'], 'oldWeight' => $old['weight'], 'oldReps' => $old['reps']];
        }
    }
    return $out;
}
