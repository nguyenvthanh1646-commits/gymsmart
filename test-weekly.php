<?php
// test-weekly.php — kiểm tra số liệu và nội dung gửi AI cho bản tổng kết tuần (includes/weekly-summary.php).
// Chạy ở gốc repo: php test-weekly.php   (không cần DB, không gọi AI)

require __DIR__ . '/public/includes/weekly-summary.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}
$l = fn(string $d, string $n, int $s, int $r, float $w) => ['date' => $d, 'name' => $n, 'sets' => $s, 'reps' => $r, 'weight' => $w];
$week = '2026-11-09';   // thứ Hai, tuần 09/11 - 15/11

$logs = [$l('2026-11-09', 'Squat', 3, 8, 42.5), $l('2026-11-09', 'Hít đất', 2, 12, 0), $l('2026-11-12', 'Squat', 3, 7, 42.5), $l('2026-11-12', 'Kéo cáp', 2, 8, 30)];
$before = [$l('2026-10-20', 'Squat', 3, 8, 37.5), $l('2026-11-03', 'Squat', 3, 8, 40), $l('2026-11-03', 'Kéo cáp', 2, 8, 30), $l('2026-11-05', 'Hít đất', 2, 10, 0)];
$metrics = [['date' => '2026-11-06', 'weight' => 73.5], ['date' => '2026-11-14', 'weight' => 72.9]];
$s = weeklyStats($logs, $before, $metrics, 3, $week);

check('Tuần: 2 ngày tập trên 3 buổi theo lịch', $s['sessions'] === 2 && $s['target'] === 3);
check('Tuần: 10 set, khối lượng 3×8×42,5 + 3×7×42,5 + 2×8×30 = 2392,5 → 2393 kg', $s['sets'] === 10 && $s['volume'] == 2393);
check('Tuần trước (02-08/11): 2 ngày tập', $s['prevSessions'] === 2);
check('Bài tăng tạ: Squat 40 → 42,5 kg (so với mức cao nhất 4 tuần trước)', count($s['improved']) === 1 && $s['improved'][0]['name'] === 'Squat'
    && $s['improved'][0]['from'] == 40 && $s['improved'][0]['to'] == 42.5);
check('Bài giữ nguyên tạ và bài không tạ không tính là tăng', !in_array('Kéo cáp', array_column($s['improved'], 'name'), true) && !in_array('Hít đất', array_column($s['improved'], 'name'), true));
check('Cân nặng: 72,9 kg, giảm 0,6 kg so với lần đo trước tuần', $s['weightEnd'] === 72.9 && $s['weightChange'] === -0.6);

$empty = weeklyStats([], [], [], 0, $week);
check('Tuần trống: 0 ngày, chưa có cân nặng, không bài tăng tạ', $empty['sessions'] === 0 && $empty['weightEnd'] === null && $empty['improved'] === [] && $empty['weightChange'] === null);
$one = weeklyStats([], [], [['date' => '2026-11-10', 'weight' => 70]], 2, $week);
check('Chỉ 1 lần đo trong tuần, không có lần trước: không tính thay đổi', $one['weightEnd'] === 70.0 && $one['weightChange'] === null);

// ===== Nội dung gửi AI =====
$p = weeklyPrompt($s, 'giảm mỡ');
check('Prompt có khoảng ngày của tuần', str_contains($p['user'], 'Tuần 09/11 - 15/11/2026'));
check('Prompt có số ngày tập so với lịch', str_contains($p['user'], 'Số ngày tập: 2 / 3 buổi theo lịch'));
check('Prompt có bài tăng tạ dạng 40 -> 42,5 kg', str_contains($p['user'], 'Squat 40 -> 42,5 kg'));
check('Prompt có cân nặng và thay đổi', str_contains($p['user'], '72,9 kg (-0,6 kg so với lần đo trước)'));
check('Chỉ dẫn yêu cầu đúng 3 dòng và cấm bịa số liệu', str_contains($p['system'], 'Điểm tốt:') && str_contains($p['system'], 'không bịa số liệu'));
check('Chỉ dẫn chặn tư vấn y tế', str_contains($p['system'], 'Không chẩn đoán bệnh'));
$pe = weeklyPrompt($empty, 'duy trì');
check('Prompt tuần trống nói rõ chưa có lịch, chưa có cân nặng', str_contains($pe['user'], '(chưa có lịch tập)') && str_contains($pe['user'], 'chưa ghi cân nặng'));
check('Prompt không có tên hay email người dùng', !preg_match('/@|email/i', $p['user']));

// ===== Tạo lại và làm sạch =====
$now = strtotime('2026-11-18 12:00:00');
check('Chưa có bản nào: được tạo', weeklyCanGenerate(null, $now));
check('Bản tạo 3 giờ trước: chưa được tạo lại', !weeklyCanGenerate(['created_at' => '2026-11-18 09:00:00'], $now));
check('Bản tạo hơn 24 giờ trước: được tạo lại', weeklyCanGenerate(['created_at' => '2026-11-17 11:00:00'], $now));
check('Làm sạch Markdown và dòng trống', weeklyCleanReply("**Điểm tốt:** a\n\n\n# Cần chú ý: b") === "Điểm tốt: a\nCần chú ý: b");
check('Cắt câu trả lời quá dài', mb_strlen(weeklyCleanReply(str_repeat('x', 3000))) === 1200);

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
