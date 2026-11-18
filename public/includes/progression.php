<?php
/**
 * progression.php — gợi ý mức tạ buổi sau từ nhật ký tập (B2). Hàm THUẦN: không đụng DB, không đọc session, test được.
 *
 * Cách làm: "tăng tải kép" (double progression), cùng khoảng lặp mà lịch tập đang dùng (WORKOUT_REP_RANGE = 6-8):
 *   1. Giữ một mức tạ, mỗi buổi cố thêm lặp cho tới khi MỌI set đạt mức lặp trên (8).
 *   2. Đạt mức lặp trên ở HAI buổi liên tiếp với CÙNG mức tạ thì mới tăng tạ (một buổi đạt có thể do hôm đó khỏe bất thường).
 *   3. Hai buổi liên tiếp cùng mức tạ mà vẫn dưới mức lặp dưới (6) thì tạ đang quá nặng: gợi ý giảm khoảng 10%.
 * Bước tăng: chân và lưng (bài đa khớp lớn, tạ nặng) 2,5 kg; các nhóm khác 1 kg (tạ đơn, máy thường có bước nhỏ).
 * Bài không dùng tạ (ghi 0 kg) thì gợi ý thêm lặp thay cho thêm tạ.
 *
 * Mỗi dòng nhật ký (bảng workout_logs) chỉ có MỘT giá trị lặp cho mọi set, nên "đạt" = số set >= số set của lịch
 * VÀ số lặp >= mức trên. Trang "Đang tập" ghi số lặp thấp nhất trong các set, nên "đạt" nghĩa là set yếu nhất cũng đạt.
 */

const PROGRESSION_STEP_KG = ['LEGS' => 2.5, 'BACK' => 2.5];
const PROGRESSION_DEFAULT_STEP_KG = 1.0;
const PROGRESSION_DECREASE = 0.9;   // giảm còn 90% khi tạ quá nặng

/** Làm tròn về bội số 0,5 kg (tạ thường có bước nhỏ nhất 0,5 kg). */
function progressionRound(float $kg): float
{
    return round($kg * 2) / 2;
}

/**
 * Gộp nhật ký theo ngày: một ngày ghi nhiều lần cùng một bài thì giữ lần ghi sau cùng (mảng vào đã xếp mới nhất trước).
 * @param array $logs [['date' => 'Y-m-d', 'weight' => float, 'sets' => int, 'reps' => int], ...] mới nhất trước
 */
function progressionSessions(array $logs): array
{
    $seen = [];
    $out = [];
    foreach ($logs as $log) {
        $date = (string)($log['date'] ?? '');
        if (isset($seen[$date])) {
            continue;
        }
        $seen[$date] = true;
        $out[] = ['date' => $date, 'weight' => (float)$log['weight'], 'sets' => (int)$log['sets'], 'reps' => (int)$log['reps']];
    }
    return $out;
}

/**
 * @param array  $logs        nhật ký của MỘT bài, mới nhất trước (định dạng như progressionSessions)
 * @param int    $targetSets  số set của bài trong lịch
 * @param int    $repLow      mức lặp dưới (6)
 * @param int    $repHigh     mức lặp trên (8)
 * @param string $muscleGroup CHEST | BACK | LEGS | ... (chọn bước tăng)
 * @return array ['action' => start|increase|keep|decrease|reps, 'weight' => ?float (mức tạ gợi ý), 'text' => câu giải thích]
 */
function suggestProgression(array $logs, int $targetSets, int $repLow, int $repHigh, string $muscleGroup): array
{
    $sessions = progressionSessions($logs);
    if ($sessions === []) {
        return ['action' => 'start', 'weight' => null,
            'text' => "Chọn mức tạ làm được $repLow-$repHigh lặp mà vẫn đúng kỹ thuật."];
    }

    $last = $sessions[0];
    $prev = $sessions[1] ?? null;
    $hit  = fn(array $s): bool => $s['sets'] >= $targetSets && $s['reps'] >= $repHigh;
    $miss = fn(array $s): bool => $s['reps'] < $repLow;
    $sameWeight = $prev !== null && abs($prev['weight'] - $last['weight']) < 0.01;
    $w = $last['weight'];
    $kg = fn(float $v): string => rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',');

    if ($w <= 0) {
        // Bài dùng trọng lượng cơ thể: không có tạ để tăng, nên tăng lặp
        if ($hit($last)) {
            return ['action' => 'reps', 'weight' => 0.0,
                'text' => "Đã đạt $repHigh lặp: thêm 1-2 lặp mỗi set, hoặc cầm thêm tạ nhỏ nếu bài cho phép."];
        }
        return ['action' => 'keep', 'weight' => 0.0, 'text' => "Cố thêm 1 lặp mỗi set cho tới khi đạt $repHigh lặp."];
    }

    if ($hit($last) && $sameWeight && $hit($prev)) {
        $step = PROGRESSION_STEP_KG[$muscleGroup] ?? PROGRESSION_DEFAULT_STEP_KG;
        $next = progressionRound($w + $step);
        return ['action' => 'increase', 'weight' => $next,
            'text' => "2 buổi liên tiếp đạt $repHigh lặp với {$kg($w)} kg: tăng lên {$kg($next)} kg (bắt đầu lại từ $repLow lặp)."];
    }
    if ($hit($last)) {
        return ['action' => 'keep', 'weight' => $w,
            'text' => "Đạt $repHigh lặp 1 buổi với {$kg($w)} kg. Giữ mức này, buổi sau vẫn đạt thì tăng tạ."];
    }
    if ($miss($last) && $sameWeight && $miss($prev)) {
        $next = progressionRound($w * PROGRESSION_DECREASE);
        return ['action' => 'decrease', 'weight' => $next,
            'text' => "2 buổi liên tiếp dưới $repLow lặp với {$kg($w)} kg: giảm còn khoảng {$kg($next)} kg để tập đủ lặp, đúng kỹ thuật."];
    }
    return ['action' => 'keep', 'weight' => $w,
        'text' => "Giữ {$kg($w)} kg, cố thêm 1 lặp mỗi set cho tới khi mọi set đạt $repHigh lặp."];
}

/**
 * Gộp nhiều set của một bài (chế độ Đang tập) thành MỘT dòng nhật ký theo cấu trúc bảng workout_logs:
 * mức tạ = tạ nặng nhất đã dùng, số lặp = số lặp THẤP NHẤT trong các set ở mức tạ đó, số set = số set ở mức tạ đó.
 * Set khởi động (tạ nhẹ hơn) không làm sai việc đánh giá đạt/chưa đạt ở mức tạ chính.
 * @param array $sets [['weight' => float, 'reps' => int], ...] các set đã đánh dấu xong
 * @return ?array ['weight' => float, 'sets' => int, 'reps' => int] hoặc null nếu không có set hợp lệ
 */
function summarizeSessionSets(array $sets): ?array
{
    $valid = array_values(array_filter($sets, fn($s) => (int)($s['reps'] ?? 0) > 0 && (float)($s['weight'] ?? -1) >= 0));
    if ($valid === []) {
        return null;
    }
    $top = max(array_map(fn($s) => (float)$s['weight'], $valid));
    $atTop = array_values(array_filter($valid, fn($s) => abs((float)$s['weight'] - $top) < 0.01));
    return ['weight' => $top, 'sets' => count($atTop), 'reps' => min(array_map(fn($s) => (int)$s['reps'], $atTop))];
}
