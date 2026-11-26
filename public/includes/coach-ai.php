<?php
/**
 * includes/coach-ai.php — dựng system prompt cho "Trợ lý HLV" (chatbot ở chế độ coach, dùng trong khu /coach/).
 *
 * Nguyên tắc dữ liệu (quan trọng vì dữ liệu này được gửi sang dịch vụ AI bên ngoài):
 *   - Chỉ đưa dữ liệu của học viên mà HLV ĐANG có quan hệ ACTIVE (api/chatbot.php đã kiểm tra bằng coachActiveRelationId
 *     trước khi gọi các hàm này; hàm vẫn tự lọc theo coach_id thêm một lần).
 *   - KHÔNG đưa email, mật khẩu, avatar; tên học viên chỉ lấy TÊN (từ cuối), không lấy họ đầy đủ.
 *   - Chỉ số liệu tập luyện cần cho việc điều chỉnh lịch: mục tiêu, trình độ, chỉ số cơ thể, lịch hiện tại, nhật ký 14 ngày,
 *     cân nặng gần đây, mục tiêu dinh dưỡng, tỉ lệ tập đúng lịch tuần này.
 */
require_once __DIR__ . '/coach-db.php';
require_once __DIR__ . '/coach.php';            // nhãn COACH_GOAL_LABELS, COACH_LEVEL_LABELS...: api/chatbot.php không nạp file này
require_once __DIR__ . '/workout-plan-db.php';

/** Chỉ lấy TÊN (từ cuối trong họ tên Việt Nam) để AI xưng hô, không gửi họ tên đầy đủ. */
function coachAiFirstName(string $fullName): string
{
    $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    return $parts ? end($parts) : 'học viên';
}

/** Khối dữ liệu về MỘT học viên (đã kiểm tra quyền ở nơi gọi). */
function coachAiClientBlock(PDO $pdo, int $coachId, int $clientId, array $labels): string
{
    if (coachActiveRelationId($pdo, $coachId, $clientId) === null) {
        return '';   // phòng thủ thêm một lớp: không phải học viên ACTIVE thì không đưa gì
    }
    $c = coachClientBasics($pdo, $clientId);
    $today = date('Y-m-d');
    $lines = [];
    $lines[] = '- Tên gọi: ' . coachAiFirstName((string)$c['full_name']);
    $lines[] = '- Hồ sơ: ' . ($c['age'] ? $c['age'] . ' tuổi' : 'chưa rõ tuổi') . ', ' . (COACH_GENDER_LABELS[$c['gender']] ?? 'chưa rõ giới tính')
        . ', ' . ($c['height_cm'] ? $c['height_cm'] . ' cm' : '?') . ', ' . ($c['weight_kg'] ? $c['weight_kg'] . ' kg' : '?')
        . ', vận động: ' . (COACH_ACTIVITY_LABELS[$c['activity_level']] ?? 'chưa rõ');
    $lines[] = '- Mục tiêu: ' . (COACH_GOAL_LABELS[$c['goal']] ?? 'chưa đặt') . '; trình độ: ' . (COACH_LEVEL_LABELS[$c['experience_level']] ?? 'chưa chọn');

    $plan = fetchActivePlan($pdo, $clientId);
    if ($plan === null) {
        $lines[] = '- Lịch tập: chưa có.';
    } else {
        $dayNames = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];
        $lines[] = '- Lịch tập hiện tại "' . $plan['name'] . '":';
        foreach ($plan['days'] as $dow => $rows) {
            $lines[] = '  ' . $dayNames[$dow] . ': ' . implode('; ', array_map(fn($r) => $r['name'] . ' ' . $r['sets'] . 'x' . $r['reps'], $rows));
        }
    }

    $cp = coachComplianceFor($pdo, [$clientId], $today)[$clientId] ?? null;
    if ($cp && $cp['planned_week'] > 0) {
        $lines[] = '- Tuần này: đã tập ' . $cp['done'] . '/' . $cp['planned_so_far'] . ' buổi tính tới hôm nay (lịch ' . $cp['planned_week'] . ' buổi/tuần).';
    }

    $logs = coachClientLogs($pdo, $clientId, $today, 14);
    if (!$logs) {
        $lines[] = '- Nhật ký 14 ngày: không ghi buổi nào.';
    } else {
        $lines[] = '- Nhật ký 14 ngày (mới nhất trước):';
        foreach (array_slice($logs, 0, 8, true) as $date => $rows) {
            $lines[] = '  ' . date('d/m', strtotime($date)) . ': ' . implode('; ', array_map(
                fn($r) => $r['name'] . ' ' . $r['sets_completed'] . 'x' . $r['reps_completed'] . ($r['weight_kg_used'] !== null ? ' @' . (float)$r['weight_kg_used'] . 'kg' : ''),
                $rows
            ));
        }
    }

    $st = $pdo->prepare('SELECT recorded_date, weight_kg FROM body_metrics WHERE user_id = ? ORDER BY recorded_date DESC, id DESC LIMIT 6');
    $st->execute([$clientId]);
    $metrics = array_reverse($st->fetchAll(PDO::FETCH_ASSOC));
    if ($metrics) {
        $lines[] = '- Cân nặng gần đây: ' . implode(' → ', array_map(fn($m) => date('d/m', strtotime($m['recorded_date'])) . ' ' . (float)$m['weight_kg'] . 'kg', $metrics));
    }
    $n = coachLatestNutrition($pdo, $clientId);
    if ($n) {
        $lines[] = '- Mục tiêu dinh dưỡng đang dùng: ' . round((float)$n['target_kcal']) . ' kcal, P ' . round((float)$n['protein_g']) . ' g, C '
            . round((float)$n['carbs_g']) . ' g, F ' . round((float)$n['fat_g']) . ' g (' . ((int)$n['assigned_by'] === $coachId ? 'do HLV đặt' : 'học viên tự tính') . ').';
    }
    $req = coachChangeRequests($pdo, (int)coachActiveRelationId($pdo, $coachId, $clientId), true);
    foreach ($req as $r) {
        $lines[] = '- Học viên đang xin sửa ' . mb_strtolower(COACH_CHANGE_AREAS[$r['area']] ?? '') . ': "' . mb_substr($r['reason'], 0, 200) . '"';
    }
    return implode("\n", $lines);
}

/** Tóm tắt danh sách học viên (khi HLV hỏi ở trang không gắn với học viên nào). */
function coachAiRosterBlock(PDO $pdo, int $coachId): string
{
    $clients = coachActiveClients($pdo, $coachId);
    if (!$clients) {
        return '- HLV chưa có học viên nào.';
    }
    $cp = coachComplianceFor($pdo, array_column($clients, 'client_id'), date('Y-m-d'));
    $lines = [];
    foreach ($clients as $c) {
        $x = $cp[(int)$c['client_id']] ?? null;
        $lines[] = '- ' . coachAiFirstName($c['full_name']) . ': ' . (COACH_GOAL_LABELS[$c['goal']] ?? 'chưa đặt mục tiêu') . ', '
            . (COACH_LEVEL_LABELS[$c['experience_level']] ?? 'chưa rõ trình độ') . ', tập gần nhất ' . ($c['last_log'] ? date('d/m', strtotime($c['last_log'])) : 'chưa có')
            . ($x && $x['planned_week'] > 0 ? ', tuần này ' . $x['done'] . '/' . $x['planned_so_far'] . ' buổi' : '')
            . ((int)$c['change_requests'] > 0 ? ', đang xin sửa lịch/ăn' : '');
    }
    return implode("\n", $lines);
}

/** System prompt cho Trợ lý HLV. $clientId = null: hỏi chung về các học viên. */
function coachSystemPrompt(PDO $pdo, int $coachId, ?int $clientId, array $labels): string
{
    $data = $clientId !== null
        ? "DỮ LIỆU HỌC VIÊN ĐANG XEM (chỉ dùng để tư vấn cho HLV, không bịa thêm số liệu):\n" . coachAiClientBlock($pdo, $coachId, $clientId, $labels)
        : "TÓM TẮT CÁC HỌC VIÊN ĐANG TẬP VỚI HLV:\n" . coachAiRosterBlock($pdo, $coachId);
    return "Bạn là Trợ lý HLV của website GymSmart. Người đang trò chuyện là một HUẤN LUYỆN VIÊN (không phải học viên).\n"
        . "Việc của bạn: giúp HLV đọc nhật ký và số đo của học viên, phát hiện vấn đề (tập thiếu buổi, không tăng tạ, cân nặng đi ngược mục tiêu), "
        . "gợi ý cách điều chỉnh lịch (đổi bài, set × rep, số buổi, tăng tải từ từ) và mục tiêu dinh dưỡng trong giới hạn an toàn "
        . "(1200-5000 kcal; protein 0,8-3,3 g/kg; chất béo tối thiểu 0,5 g/kg), và soạn lời nhận xét ngắn, cụ thể, động viên để HLV gửi cho học viên.\n"
        . "Quy tắc:\n"
        . "1. Trả lời bằng tiếng Việt có dấu, ngắn gọn, gạch đầu dòng khi liệt kê.\n"
        . "2. Chỉ dựa vào dữ liệu bên dưới; thiếu dữ liệu thì nói rõ là thiếu và gợi ý HLV hỏi học viên. Không bịa con số.\n"
        . "3. Bạn không tự sửa lịch hay dữ liệu; HLV tự thao tác ở trang Giao lịch tập / Đặt dinh dưỡng / Nhận xét.\n"
        . "4. Dấu hiệu chấn thương, đau kéo dài, bệnh lý: khuyên HLV đề nghị học viên gặp bác sĩ, không chẩn đoán.\n"
        . "5. Không hỏi hay nhắc tới email, số điện thoại hay thông tin cá nhân khác của học viên.\n\n"
        . $data;
}
