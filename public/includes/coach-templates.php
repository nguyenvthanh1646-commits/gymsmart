<?php
/**
 * coach-templates.php — mẫu lịch tập của HLV (E1, A-48). Bảng coach_plan_templates + coach_plan_template_items.
 *
 * Luồng: HLV mở lịch (hoặc bản nháp) của một học viên → "Lưu làm mẫu". Ở trang Mẫu lịch, chọn một mẫu, tick nhiều học viên
 * đang tập → "Giao cho học viên đã chọn": mỗi học viên nhận một lịch ACTIVE mới (lịch cũ được lưu trữ), có thông báo.
 * Quyền: mọi hàm nhận $coachId từ session; mẫu chỉ đọc/sửa/xóa được bởi HLV tạo ra nó (WHERE coach_id = ?);
 * giao cho học viên nào thì học viên đó phải đang ACTIVE với HLV này (canCoachAccessClient), không tin danh sách id gửi lên.
 * Phần kiểm tra tên và dựng lịch từ các bài của mẫu là hàm thuần (test ở test-coach-templates.php).
 */
require_once __DIR__ . '/coach-db.php';
require_once __DIR__ . '/workout-plan-db.php';
require_once __DIR__ . '/coach-extras.php';   // coachDraftPlan()

const COACH_TEMPLATE_MAX = 20;           // mỗi HLV tối đa 20 mẫu
const COACH_TEMPLATE_MAX_CLIENTS = 30;   // giao một lần tối đa 30 học viên

/** DB đã có bảng mẫu lịch chưa. */
function coachTemplatesReady(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $pdo->query('SELECT 1 FROM coach_plan_templates LIMIT 1');
            $ready = true;
        } catch (PDOException $e) {
            $ready = false;
        }
    }
    return $ready;
}

/** Hàm thuần: kiểm tra tên mẫu, trả câu lỗi hoặc null. */
function coachTemplateNameError(string $name): ?string
{
    $len = mb_strlen(trim($name));
    if ($len < 3 || $len > 80) {
        return 'Tên mẫu cần từ 3 đến 80 ký tự.';
    }
    return null;
}

/**
 * Hàm thuần: dựng lịch (đúng định dạng saveWorkoutPlan cần) từ thông tin mẫu và các bài của mẫu.
 * @param array $tpl   ['split_type','days_per_week']
 * @param array $items [['exercise_id','day_of_week','sets','reps','order_index'], ...]
 */
function coachTemplateToPlan(array $tpl, array $items): array
{
    $days = [];
    foreach ($items as $it) {
        $d = (int)$it['day_of_week'];
        if ($d < 1 || $d > 7) {
            continue;
        }
        $days[$d]['day_of_week'] = $d;
        $days[$d]['exercises'][] = ['exercise_id' => (int)$it['exercise_id'], 'sets' => (int)$it['sets'], 'reps' => (int)$it['reps'],
            'order_index' => (int)$it['order_index']];
    }
    ksort($days);
    return ['split_type' => (string)$tpl['split_type'], 'days_per_week' => count($days) ?: (int)$tpl['days_per_week'], 'days' => array_values($days)];
}

/** Danh sách mẫu của HLV, kèm số buổi và số bài. */
function coachTemplates(PDO $pdo, int $coachId): array
{
    $st = $pdo->prepare(
        'SELECT t.id, t.name, t.split_type, t.days_per_week, t.updated_at,
                COUNT(i.id) AS exercises, COUNT(DISTINCT i.day_of_week) AS days
         FROM coach_plan_templates t LEFT JOIN coach_plan_template_items i ON i.template_id = t.id
         WHERE t.coach_id = ? GROUP BY t.id, t.name, t.split_type, t.days_per_week, t.updated_at ORDER BY t.updated_at DESC, t.id DESC'
    );
    $st->execute([$coachId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Một mẫu của HLV này kèm các bài (để xem trước và giao). Không phải của HLV này thì ném lỗi. */
function coachTemplate(PDO $pdo, int $coachId, int $templateId): array
{
    $st = $pdo->prepare('SELECT id, name, split_type, days_per_week FROM coach_plan_templates WHERE id = ? AND coach_id = ?');
    $st->execute([$templateId, $coachId]);
    $tpl = $st->fetch(PDO::FETCH_ASSOC);
    if ($tpl === false) {
        throw new CoachException('Không tìm thấy mẫu lịch này.');
    }
    $st = $pdo->prepare(
        'SELECT i.exercise_id, i.day_of_week, i.sets, i.reps, i.order_index, e.name
         FROM coach_plan_template_items i JOIN exercises e ON e.id = i.exercise_id
         WHERE i.template_id = ? ORDER BY i.day_of_week, i.order_index, i.id'
    );
    $st->execute([$templateId]);
    $tpl['items'] = $st->fetchAll(PDO::FETCH_ASSOC);
    return $tpl;
}

/**
 * Lưu lịch của học viên thành mẫu: lấy bản nháp HLV đang soạn cho học viên này nếu có, không thì lịch đang dùng.
 * $clientId phải đã qua requireCoachClient() ở trang gọi. Trả id mẫu mới.
 */
function coachTemplateSaveFromClient(PDO $pdo, int $coachId, int $clientId, string $name, bool $fromDraft): int
{
    if ($e = coachTemplateNameError($name)) {
        throw new CoachException($e);
    }
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM coach_plan_templates WHERE coach_id = ?');
    $cnt->execute([$coachId]);
    if ((int)$cnt->fetchColumn() >= COACH_TEMPLATE_MAX) {
        throw new CoachException('Mỗi HLV lưu tối đa ' . COACH_TEMPLATE_MAX . ' mẫu. Xóa bớt mẫu cũ trước.');
    }
    if ($fromDraft) {
        $src = coachDraftPlan($pdo, $coachId, $clientId);
        if ($src === null) {
            throw new CoachException('Chưa có bản nháp để lưu làm mẫu.');
        }
    } else {
        $src = fetchActivePlan($pdo, $clientId);
        if ($src === null) {
            throw new CoachException('Học viên chưa có lịch đang dùng để lưu làm mẫu.');
        }
    }
    if (!array_filter($src['days'])) {
        throw new CoachException('Lịch này chưa có bài nào.');
    }
    return coachTx($pdo, function () use ($pdo, $coachId, $name, $src) {
        $now = coachNow();
        $pdo->prepare('INSERT INTO coach_plan_templates (coach_id, name, split_type, days_per_week, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$coachId, trim($name), $src['split_type'], count(array_filter($src['days'])), $now, $now]);
        $tplId = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO coach_plan_template_items (template_id, exercise_id, day_of_week, sets, reps, order_index, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($src['days'] as $day => $rows) {
            foreach (array_values($rows) as $i => $r) {
                $ins->execute([$tplId, (int)$r['exercise_id'], (int)$day, (int)$r['sets'], (int)$r['reps'], $i, $now]);
            }
        }
        return $tplId;
    });
}

/** Xóa mẫu của chính HLV này. */
function coachTemplateDelete(PDO $pdo, int $coachId, int $templateId): void
{
    $pdo->prepare('DELETE FROM coach_plan_template_items WHERE template_id IN (SELECT id FROM coach_plan_templates WHERE id = ? AND coach_id = ?)')
        ->execute([$templateId, $coachId]);
    $st = $pdo->prepare('DELETE FROM coach_plan_templates WHERE id = ? AND coach_id = ?');
    $st->execute([$templateId, $coachId]);
    if ($st->rowCount() === 0) {
        throw new CoachException('Không tìm thấy mẫu lịch này.');
    }
}

/**
 * Giao mẫu cho nhiều học viên. Học viên nào không còn ACTIVE với HLV này thì bỏ qua (ghi vào 'skipped'), không dừng cả lượt.
 * Mỗi học viên được lưu trong transaction riêng của saveWorkoutPlan (lịch cũ → ARCHIVED, lịch mẫu → ACTIVE, assigned_by = HLV).
 * Học viên đã hết buổi tập (A-54) cũng bỏ qua, ghi vào 'locked'.
 * @return array ['applied' => [clientId => tên], 'skipped' => [clientId, ...], 'locked' => [clientId, ...]]
 */
function coachTemplateApply(PDO $pdo, int $coachId, int $templateId, array $clientIds): array
{
    $tpl = coachTemplate($pdo, $coachId, $templateId);
    if (!$tpl['items']) {
        throw new CoachException('Mẫu này không còn bài nào (bài tập có thể đã bị xóa khỏi thư viện).');
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $clientIds), fn($id) => $id > 0)));
    if (!$ids) {
        throw new CoachException('Hãy chọn ít nhất 1 học viên.');
    }
    if (count($ids) > COACH_TEMPLATE_MAX_CLIENTS) {
        throw new CoachException('Mỗi lần giao tối đa ' . COACH_TEMPLATE_MAX_CLIENTS . ' học viên.');
    }
    $plan = coachTemplateToPlan($tpl, $tpl['items']);
    $coachName = notifName($pdo, $coachId);
    $out = ['applied' => [], 'skipped' => [], 'locked' => []];
    foreach ($ids as $clientId) {
        if (!canCoachAccessClient($pdo, $coachId, $clientId)) {   // quyền kiểm tra lại ở máy chủ cho TỪNG học viên
            $out['skipped'][] = $clientId;
            continue;
        }
        $relId = coachActiveRelationId($pdo, $coachId, $clientId);
        if ($relId !== null && coachBillingLocked($pdo, $relId)) {   // A-54: học viên hết buổi thì bỏ qua
            $out['locked'][] = $clientId;
            continue;
        }
        $planId = saveWorkoutPlan($pdo, $clientId, $plan, $coachId, 'ACTIVE');
        $pdo->prepare('UPDATE workout_plans SET name = ? WHERE id = ?')->execute([$tpl['name'], $planId]);
        notify($pdo, $clientId, 'coach.plan', 'HLV đã giao lịch tập mới',
            "HLV $coachName vừa giao lịch \"" . notifExcerpt($tpl['name'], 60) . '" (' . $plan['days_per_week'] . ' buổi/tuần).', '/workout-plan.php', true);
        $out['applied'][$clientId] = notifName($pdo, $clientId);
    }
    return $out;
}
