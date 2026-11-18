<?php
// test-achievements.php — kiểm tra chuỗi tuần, huy hiệu và báo cáo tháng (includes/achievements.php).
// Chạy ở gốc repo: php test-achievements.php   (không cần DB)

require __DIR__ . '/public/includes/achievements.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}
$today = '2026-11-18';   // thứ Tư, tuần bắt đầu thứ Hai 16/11

check('Thứ Hai của tuần', achWeekStart('2026-11-18') === '2026-11-16' && achWeekStart('2026-11-22') === '2026-11-16' && achWeekStart('2026-11-16') === '2026-11-16');

// 3 tuần trước đều tập 2 ngày, tuần này mới 1 ngày
$dates = ['2026-10-27', '2026-10-29', '2026-11-03', '2026-11-05', '2026-11-10', '2026-11-12', '2026-11-17'];
$s = achWeeklyStreak($dates, $today, 2);
check('Tuần này chưa đủ thì chưa đứt chuỗi: hiện tại 3 tuần', $s['current'] === 3);
check('Tuần này đã tập 1/2 ngày', $s['thisWeek'] === 1 && $s['target'] === 2);
check('Chuỗi dài nhất 3 tuần', $s['best'] === 3);
check('Ngày đạt chuỗi 2 tuần là Chủ nhật tuần thứ hai (08/11)', $s['firstReach'][2] === '2026-11-08');

$s = achWeeklyStreak(array_merge($dates, ['2026-11-18']), $today, 2);
check('Tuần này đủ 2 ngày: chuỗi tăng lên 4', $s['current'] === 4 && $s['best'] === 4);

// Có một tuần trống ở giữa
$gap = ['2026-10-20', '2026-10-21', '2026-11-03', '2026-11-04', '2026-11-10', '2026-11-11'];
$s = achWeeklyStreak($gap, $today, 2);
check('Tuần trống làm đứt chuỗi: hiện tại 2, dài nhất 2', $s['current'] === 2 && $s['best'] === 2);

$s = achWeeklyStreak(['2026-11-02', '2026-11-03'], $today, 2);
check('Tuần trước không tập (đã hết tuần): chuỗi hiện tại 0, dài nhất vẫn 1', $s['current'] === 0 && $s['best'] === 1);

$s = achWeeklyStreak(['2026-11-10', '2026-11-10', '2026-11-10'], $today, 2);
check('Ghi nhiều lần cùng một ngày chỉ tính 1 ngày tập', $s['best'] === 0);

$s = achWeeklyStreak(['2026-11-30', '2026-12-01'], $today, 2);
check('Bỏ qua ngày trong tương lai', $s['best'] === 0 && $s['thisWeek'] === 0);

check('Chưa tập gì: mọi số bằng 0', achWeeklyStreak([], $today, 3) === ['current' => 0, 'best' => 0, 'thisWeek' => 0, 'target' => 3, 'firstReach' => []]);
check('Mục tiêu 0 được nâng thành 1', achWeeklyStreak([], $today, 0)['target'] === 1);

// ===== Huy hiệu =====
$sessionDates = [];
for ($i = 0; $i < 12; $i++) {
    $sessionDates[] = date('Y-m-d', strtotime("2026-09-01 +$i days"));
}
$streak = achWeeklyStreak($dates, $today, 2);
$m = array_column(achMilestones($sessionDates, ['2026-10-01'], $streak), null, 'id');
check('Đủ 12 buổi: đạt huy hiệu 1 và 10 buổi', $m['sessions-1']['achieved'] && $m['sessions-10']['achieved'] && !$m['sessions-25']['achieved']);
check('Ngày đạt 10 buổi là ngày tập thứ 10 (10/09)', $m['sessions-10']['date'] === '2026-09-10');
check('Huy hiệu chưa đạt có tiến độ 12/25', $m['sessions-25']['have'] === 12 && $m['sessions-25']['need'] === 25 && $m['sessions-25']['date'] === null);
check('Chuỗi 2 tuần đạt, 4 tuần chưa', $m['streak-2']['achieved'] && !$m['streak-4']['achieved'] && $m['streak-4']['have'] === 3);
check('Mô tả chuỗi nêu đúng số buổi mỗi tuần', str_contains($m['streak-2']['desc'], 'đủ 2 buổi'));
check('Lần đo đầu tiên đạt, 10 lần chưa', $m['metrics-1']['achieved'] && $m['metrics-1']['date'] === '2026-10-01' && !$m['metrics-10']['achieved']);
check('Người mới: không có huy hiệu nào đạt', !in_array(true, array_column(achMilestones([], [], achWeeklyStreak([], $today, 2)), 'achieved'), true));

// ===== Báo cáo tháng =====
$logs = [
    ['date' => '2026-11-02', 'name' => 'Squat', 'sets' => 3, 'reps' => 8, 'weight' => 40],
    ['date' => '2026-11-02', 'name' => 'Hít đất', 'sets' => 2, 'reps' => 12, 'weight' => 0],
    ['date' => '2026-11-05', 'name' => 'Squat', 'sets' => 3, 'reps' => 8, 'weight' => 42.5],
];
$sum = achMonthSummary($logs, [['date' => '2026-11-01', 'weight' => 74.5], ['date' => '2026-11-15', 'weight' => 73.1]]);
check('Tháng: 2 ngày tập, 8 set', $sum['sessions'] === 2 && $sum['sets'] === 8);
check('Tháng: khối lượng = 3×8×40 + 3×8×42,5 = 1980 kg (bài không tạ tính 0)', $sum['volume'] == 1980);
check('Tháng: bài nhiều set nhất đứng đầu', $sum['top'][0] === ['name' => 'Squat', 'sets' => 6]);
check('Tháng: cân nặng giảm 1,4 kg', $sum['weightChange'] === -1.4 && $sum['weightStart'] === 74.5);
$sum = achMonthSummary([], [['date' => '2026-11-01', 'weight' => 74.5]]);
check('Tháng chỉ 1 lần đo: không tính thay đổi', $sum['weightChange'] === null && $sum['sessions'] === 0 && $sum['top'] === []);

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
