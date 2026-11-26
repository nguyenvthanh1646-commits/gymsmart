<?php
/**
 * achievements.php — chuỗi tuần tập đều, huy hiệu cột mốc và báo cáo tháng (F1). Hàm THUẦN, không đụng DB, test được.
 *
 * Mọi con số tính từ dữ liệu THẬT trong nhật ký (workout_logs) và số đo (body_metrics), không có huy hiệu "tặng".
 *  - "Tuần đạt": tuần (thứ Hai - Chủ nhật) có số NGÀY tập >= số buổi trong lịch của người dùng (chưa có lịch thì 2 buổi).
 *  - Chuỗi hiện tại: số tuần đạt liên tiếp tính lùi từ tuần này. Tuần này CHƯA đạt thì chưa tính là đứt chuỗi
 *    (tuần còn chưa hết), chuỗi được đếm từ tuần trước.
 *  - Huy hiệu: số buổi tập (1, 10, 25, 50, 100), chuỗi tuần (2, 4, 8, 12), số lần ghi số đo (1, 10). Ngày đạt là ngày thật
 *    lần đầu chạm mốc.
 */

const ACH_SESSION_MILESTONES = [1, 10, 25, 50, 100];
const ACH_STREAK_MILESTONES = [2, 4, 8, 12];
const ACH_METRIC_MILESTONES = [1, 10];
const ACH_DEFAULT_WEEK_TARGET = 2;

/** Thứ Hai của tuần chứa ngày $date (Y-m-d). */
function achWeekStart(string $date): string
{
    $ts = strtotime($date);
    return date('Y-m-d', strtotime('-' . ((int)date('N', $ts) - 1) . ' days', $ts));
}

/**
 * @param array  $dates  các ngày đã tập (Y-m-d, có thể trùng, thứ tự bất kỳ)
 * @param string $today  hôm nay (Y-m-d)
 * @param int    $target số ngày tập tối thiểu để một tuần là "tuần đạt"
 * @return array ['current' => int, 'best' => int, 'thisWeek' => int (số ngày đã tập tuần này), 'target' => int,
 *                'firstReach' => [độ dài chuỗi => ngày Chủ nhật của tuần lần đầu đạt độ dài đó]]
 */
function achWeeklyStreak(array $dates, string $today, int $target): array
{
    $target = max(1, $target);
    $perWeek = [];
    foreach (array_unique($dates) as $d) {
        if ($d > $today) {
            continue;   // bỏ ngày trong tương lai (dữ liệu nhập sai)
        }
        $w = achWeekStart($d);
        $perWeek[$w] = ($perWeek[$w] ?? 0) + 1;
    }
    $thisWeek = achWeekStart($today);
    $out = ['current' => 0, 'best' => 0, 'thisWeek' => $perWeek[$thisWeek] ?? 0, 'target' => $target, 'firstReach' => []];
    if ($perWeek === []) {
        return $out;
    }

    // Chuỗi dài nhất: đi qua từng tuần liên tiếp từ tuần đầu tiên có tập tới tuần này
    $run = 0;
    for ($w = min(array_keys($perWeek)); $w <= $thisWeek; $w = date('Y-m-d', strtotime($w . ' +7 days'))) {
        if (($perWeek[$w] ?? 0) >= $target) {
            $run++;
            $out['best'] = max($out['best'], $run);
            if (!isset($out['firstReach'][$run])) {
                $out['firstReach'][$run] = date('Y-m-d', strtotime($w . ' +6 days'));
            }
        } elseif ($w !== $thisWeek) {
            $run = 0;   // tuần đã qua mà không đạt thì đứt chuỗi; tuần này chưa hết thì chưa tính
        }
    }

    // Chuỗi hiện tại: lùi từ tuần này (nếu đã đạt) hoặc từ tuần trước
    $w = ($perWeek[$thisWeek] ?? 0) >= $target ? $thisWeek : date('Y-m-d', strtotime($thisWeek . ' -7 days'));
    while (($perWeek[$w] ?? 0) >= $target) {
        $out['current']++;
        $w = date('Y-m-d', strtotime($w . ' -7 days'));
    }
    return $out;
}

/**
 * Danh sách huy hiệu (đã đạt và chưa đạt).
 * @param array $sessionDates ngày đã tập (Y-m-d, không trùng, cũ đến mới)
 * @param array $metricDates  ngày đã ghi số đo (Y-m-d, không trùng, cũ đến mới)
 * @param array $streak       kết quả achWeeklyStreak()
 * @return array [['id','title','desc','achieved' => bool,'date' => ?string,'have' => int,'need' => int], ...]
 */
function achMilestones(array $sessionDates, array $metricDates, array $streak): array
{
    $list = [];
    $sessions = count($sessionDates);
    foreach (ACH_SESSION_MILESTONES as $n) {
        $list[] = ['id' => "sessions-$n", 'title' => $n === 1 ? 'Buổi tập đầu tiên' : "$n buổi tập",
            'desc' => $n === 1 ? 'Ghi nhật ký buổi tập đầu tiên.' : "Ghi nhật ký đủ $n ngày tập.",
            'achieved' => $sessions >= $n, 'date' => $sessionDates[$n - 1] ?? null, 'have' => min($sessions, $n), 'need' => $n];
    }
    foreach (ACH_STREAK_MILESTONES as $n) {
        $list[] = ['id' => "streak-$n", 'title' => "Chuỗi $n tuần",
            'desc' => "$n tuần liên tiếp tập đủ {$streak['target']} buổi mỗi tuần.",
            'achieved' => $streak['best'] >= $n, 'date' => $streak['firstReach'][$n] ?? null, 'have' => min($streak['best'], $n), 'need' => $n];
    }
    $metrics = count($metricDates);
    foreach (ACH_METRIC_MILESTONES as $n) {
        $list[] = ['id' => "metrics-$n", 'title' => $n === 1 ? 'Lần đo đầu tiên' : "$n lần ghi số đo",
            'desc' => $n === 1 ? 'Ghi cân nặng lần đầu ở trang Tiến trình.' : "Ghi cân nặng $n lần để thấy rõ xu hướng.",
            'achieved' => $metrics >= $n, 'date' => $metricDates[$n - 1] ?? null, 'have' => min($metrics, $n), 'need' => $n];
    }
    return $list;
}

/**
 * Tóm tắt một tháng.
 * @param array $logs    [['date','name','sets','reps','weight'], ...] nhật ký TRONG tháng
 * @param array $metrics [['date','weight'], ...] số đo TRONG tháng, cũ đến mới
 * @return array ['sessions','sets','volume' (kg = set × lặp × tạ),'top' => [['name','sets'], ...] tối đa 5,
 *                'weightStart','weightEnd','weightChange' (null nếu dưới 2 lần đo)]
 */
function achMonthSummary(array $logs, array $metrics): array
{
    $byName = [];
    $sets = 0;
    $volume = 0.0;
    foreach ($logs as $l) {
        $sets += (int)$l['sets'];
        $volume += (int)$l['sets'] * (int)$l['reps'] * (float)$l['weight'];
        $byName[$l['name']] = ($byName[$l['name']] ?? 0) + (int)$l['sets'];
    }
    arsort($byName);
    $top = [];
    foreach (array_slice($byName, 0, 5, true) as $name => $n) {
        $top[] = ['name' => (string)$name, 'sets' => $n];
    }
    $start = $metrics ? (float)$metrics[0]['weight'] : null;
    $end = $metrics ? (float)$metrics[count($metrics) - 1]['weight'] : null;
    return [
        'sessions' => count(array_unique(array_column($logs, 'date'))),
        'sets' => $sets,
        'volume' => round($volume),
        'top' => $top,
        'weightStart' => $start,
        'weightEnd' => $end,
        'weightChange' => count($metrics) >= 2 ? round($end - $start, 1) : null,
    ];
}
