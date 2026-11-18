<?php
/**
 * chat-context.php — dữ liệu tập luyện THẬT của người dùng để GymSmart AI trả lời cụ thể (D1).
 * Chỉ dùng khi người dùng bật công tắc "Dùng hồ sơ của tôi" trong khung chat (api/chatbot.php kiểm tra).
 *
 * Tách 2 phần để dễ hiểu và test được:
 *   - loadTrainingContext(): đọc DB (lịch ACTIVE, buổi hôm nay + gợi ý mức tạ, nhật ký 7 ngày, số đo 30 ngày).
 *   - formatTrainingContext(): hàm THUẦN, biến mảng dữ liệu thành vài dòng chữ ngắn để nhét vào prompt.
 * Không đưa tên, email hay ghi chú tự do của người dùng vào prompt (ghi chú có thể chứa chữ bất kỳ, kể cả câu lệnh lừa AI).
 */
require_once __DIR__ . '/workout-plan-db.php';
require_once __DIR__ . '/progression.php';

const CHAT_CONTEXT_DAY_NAMES = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'];

/**
 * @param string $today ngày hôm nay Y-m-d (truyền vào để test được)
 * @return array ['plan' => ?array, 'today' => [...], 'next' => ?array, 'week' => [...], 'metrics' => ?array]
 */
function loadTrainingContext(PDO $pdo, int $userId, string $today): array
{
    $out = ['plan' => null, 'today' => [], 'todayDow' => (int)date('N', strtotime($today)), 'next' => null, 'week' => null, 'metrics' => null];

    $plan = fetchActivePlan($pdo, $userId);
    if ($plan !== null) {
        $labels = workoutSessionLabels($plan['split_type'], (int)$plan['days_per_week']);
        $out['plan'] = ['split' => WORKOUT_SPLIT_LABELS[$plan['split_type']] ?? $plan['split_type'], 'days' => (int)$plan['days_per_week'],
            'trainingDays' => array_keys(array_filter($plan['days']))];
        $dow = $out['todayDow'];
        $rows = $plan['days'][$dow] ?? [];
        $recent = fetchRecentLogsByExercise($pdo, $userId, array_column($rows, 'exercise_id'));
        foreach ($rows as $r) {
            $tip = suggestProgression($recent[(int)$r['exercise_id']] ?? [], (int)$r['sets'], WORKOUT_REP_RANGE[0], WORKOUT_REP_RANGE[1], (string)$r['muscle_group']);
            $out['today'][] = ['name' => $r['name'], 'sets' => (int)$r['sets'], 'reps' => (int)$r['reps'], 'tip' => $tip];
        }
        $out['todayLabel'] = $labels[$dow] ?? '';
        // Buổi kế tiếp sau hôm nay (vòng sang tuần sau nếu cần)
        for ($k = 1; $k <= 7; $k++) {
            $d = ($dow + $k - 1) % 7 + 1;
            if (!empty($plan['days'][$d])) {
                $out['next'] = ['day' => $d, 'label' => $labels[$d] ?? '', 'count' => count($plan['days'][$d])];
                break;
            }
        }
    }

    // Nhật ký 7 ngày gần nhất (tính cả hôm nay)
    $from = date('Y-m-d', strtotime($today . ' -6 days'));
    $st = $pdo->prepare(
        'SELECT l.log_date, e.name, l.sets_completed, l.reps_completed, l.weight_kg_used
         FROM workout_logs l JOIN exercises e ON e.id = l.exercise_id
         WHERE l.user_id = ? AND l.log_date BETWEEN ? AND ? ORDER BY l.log_date DESC, l.id DESC'
    );
    $st->execute([$userId, $from, $today]);
    $logs = $st->fetchAll(PDO::FETCH_ASSOC);
    $out['week'] = ['days' => count(array_unique(array_column($logs, 'log_date'))),
        'sets' => array_sum(array_map('intval', array_column($logs, 'sets_completed'))), 'recent' => []];
    foreach ($logs as $l) {
        if (count($out['week']['recent']) >= 6) {
            break;
        }
        $out['week']['recent'][] = ['date' => (string)$l['log_date'], 'name' => $l['name'], 'sets' => (int)$l['sets_completed'],
            'reps' => (int)$l['reps_completed'], 'weight' => (float)$l['weight_kg_used']];
    }

    // Số đo: mới nhất và lần đo cũ nhất trong 30 ngày để thấy xu hướng
    $st = $pdo->prepare('SELECT recorded_date, weight_kg, waist_cm, body_fat_percent FROM body_metrics WHERE user_id = ? AND recorded_date >= ? ORDER BY recorded_date ASC, id ASC');
    $st->execute([$userId, date('Y-m-d', strtotime($today . ' -30 days'))]);
    $m = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($m) {
        $first = $m[0];
        $last = $m[count($m) - 1];
        $out['metrics'] = ['date' => (string)$last['recorded_date'], 'weight' => (float)$last['weight_kg'],
            'waist' => $last['waist_cm'] !== null ? (float)$last['waist_cm'] : null,
            'fat' => $last['body_fat_percent'] !== null ? (float)$last['body_fat_percent'] : null,
            'change' => count($m) > 1 ? round((float)$last['weight_kg'] - (float)$first['weight_kg'], 1) : null,
            'since' => (string)$first['recorded_date'], 'count' => count($m)];
    }
    return $out;
}

/** Hàm THUẦN: mảng từ loadTrainingContext() → vài dòng chữ (tối đa khoảng 1.200 ký tự). */
function formatTrainingContext(array $c): string
{
    $kg = fn(float $v): string => rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',');
    $dm = fn(string $d): string => date('d/m', strtotime($d));
    $lines = [];

    if ($c['plan'] === null) {
        $lines[] = '- Lịch tập: chưa có lịch (có thể gợi ý vào trang Lịch tập để tạo).';
    } else {
        $p = $c['plan'];
        $lines[] = '- Lịch tập: ' . $p['split'] . ', ' . $p['days'] . ' buổi/tuần.';
        $dayName = CHAT_CONTEXT_DAY_NAMES[$c['todayDow']] ?? '';
        if ($c['today']) {
            $items = array_map(function ($t) use ($kg) {
                $s = $t['name'] . ' ' . $t['sets'] . 'x' . ($t['reps'] === WORKOUT_TARGET_REPS ? WORKOUT_REP_RANGE[0] . '-' . WORKOUT_REP_RANGE[1] : $t['reps']);
                if ($t['tip']['weight'] !== null && $t['tip']['weight'] > 0) {
                    $s .= ' (gợi ý ' . $kg($t['tip']['weight']) . ' kg, ' . ['start' => 'buổi đầu', 'increase' => 'tăng tạ', 'keep' => 'giữ mức', 'decrease' => 'giảm tạ', 'reps' => 'thêm lặp'][$t['tip']['action']] . ')';
                }
                return $s;
            }, array_slice($c['today'], 0, 8));
            $lines[] = '- Hôm nay (' . $dayName . ', buổi ' . ($c['todayLabel'] ?? '') . '): ' . implode('; ', $items) . '.';
        } else {
            $next = $c['next'];
            $lines[] = '- Hôm nay (' . $dayName . ') là ngày nghỉ' . ($next ? '; buổi kế tiếp: ' . (CHAT_CONTEXT_DAY_NAMES[$next['day']] ?? '') . ' (' . $next['label'] . ', ' . $next['count'] . ' bài)' : '') . '.';
        }
    }

    $w = $c['week'];
    if ($w === null || $w['days'] === 0) {
        $lines[] = '- 7 ngày qua: chưa ghi nhật ký buổi tập nào.';
    } else {
        $recent = array_map(fn($r) => $dm($r['date']) . ' ' . $r['name'] . ' ' . $kg($r['weight']) . ' kg ' . $r['sets'] . 'x' . $r['reps'], $w['recent']);
        $lines[] = '- 7 ngày qua: tập ' . $w['days'] . ' ngày, tổng ' . $w['sets'] . ' set. Gần nhất: ' . implode('; ', $recent) . '.';
    }

    $m = $c['metrics'];
    if ($m === null) {
        $lines[] = '- Số đo: chưa ghi trong 30 ngày qua.';
    } else {
        $s = '- Số đo mới nhất (' . $dm($m['date']) . '): ' . $kg($m['weight']) . ' kg';
        if ($m['waist'] !== null) { $s .= ', vòng eo ' . $kg($m['waist']) . ' cm'; }
        if ($m['fat'] !== null) { $s .= ', mỡ ' . $kg($m['fat']) . '%'; }
        if ($m['change'] !== null) {
            $s .= '; so với ' . $dm($m['since']) . ': ' . ($m['change'] > 0 ? '+' : '') . $kg($m['change']) . ' kg (' . $m['count'] . ' lần đo)';
        }
        $lines[] = $s . '.';
    }
    return implode("\n", $lines) . "\n";
}
