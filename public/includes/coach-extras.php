<?php
/**
 * includes/coach-extras.php — phần mở rộng của phân hệ HLV (nhánh a-36), nạp tự động từ coach-db.php.
 *   - tỉ lệ tập đúng lịch tuần này (coachComplianceFor)
 *   - thông tin HLV ở trang chi tiết người dùng của admin (coachAdminUserInfo)
 *   - HLV sửa / xóa nhận xét, "Đã xem" trong khung chat
 *   - lịch sử với HLV cũ của học viên
 *   - bản nháp lịch tập (status = 'DRAFT'): HLV soạn xong mới giao
 *   - file chứng chỉ trong mẫu đơn HLV (lưu ngoài public/)
 * Cần sql/coach_extras.sql cho: bản nháp, sửa nhận xét, file chứng chỉ (coachExtrasReady() kiểm tra).
 */

/** Đã chạy sql/coach_extras.sql chưa (cột coach_feedback.updated_at là dấu hiệu). Chưa thì các trang ẩn tính năng cần cột mới. */
function coachExtrasReady(PDO $pdo): bool
{
    static $ready = [];
    $key = spl_object_id($pdo);
    if (!isset($ready[$key])) {
        try {
            $pdo->query('SELECT updated_at FROM coach_feedback WHERE 1 = 0');
            $ready[$key] = true;
        } catch (PDOException $e) {
            $ready[$key] = false;
        }
    }
    return $ready[$key];
}

/** n dấu ? cho câu IN (...). Chỉ ghép DẤU HỎI vào SQL, giá trị vẫn truyền qua execute(). */
function coachPlaceholders(array $values): string
{
    return implode(',', array_fill(0, max(1, count($values)), '?'));
}

// =====================================================================
// Tỉ lệ tập đúng lịch
// =====================================================================

/** Kết quả coachWeekCompliance() cho nhiều học viên một lúc: [client_id => [...]]. */
function coachComplianceFor(PDO $pdo, array $clientIds, string $today): array
{
    $ids = array_values(array_unique(array_map('intval', $clientIds)));
    if (!$ids) {
        return [];
    }
    $in = coachPlaceholders($ids);
    $plans = array_fill_keys($ids, []);
    $stmt = $pdo->prepare(
        "SELECT DISTINCT wp.user_id, wpe.day_of_week FROM workout_plans wp
         JOIN workout_plan_exercises wpe ON wpe.workout_plan_id = wp.id
         WHERE wp.status = 'ACTIVE' AND wp.user_id IN ($in)"   // audit:ok $in chỉ gồm dấu ?
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $plans[(int)$r['user_id']][] = (int)$r['day_of_week'];
    }
    $monday = date('Y-m-d', strtotime($today . ' -' . ((int)date('N', strtotime($today)) - 1) . ' days'));
    $logs = array_fill_keys($ids, []);
    $stmt = $pdo->prepare("SELECT DISTINCT user_id, log_date FROM workout_logs WHERE log_date >= ? AND user_id IN ($in)");   // audit:ok $in chỉ gồm dấu ?
    $stmt->execute(array_merge([$monday], $ids));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $logs[(int)$r['user_id']][] = $r['log_date'];
    }
    $out = [];
    foreach ($ids as $id) {
        $out[$id] = coachWeekCompliance($plans[$id], $logs[$id], $today);
    }
    return $out;
}

// =====================================================================
// Trang chi tiết người dùng (admin)
// =====================================================================

/** HLV hiện tại (nếu là học viên), học viên đang có (nếu là HLV), và 20 quan hệ đã kết thúc gần nhất. */
function coachAdminUserInfo(PDO $pdo, int $userId): array
{
    $info = ['as_client' => coachOpenRelationForClient($pdo, $userId), 'clients' => [], 'history' => []];
    $stmt = $pdo->prepare(
        "SELECT cc.status, cc.accepted_at, cc.created_at, u.id, u.full_name FROM coach_clients cc JOIN users u ON u.id = cc.client_id
         WHERE cc.coach_id = ? AND cc.status IN ('ACTIVE', 'PENDING') ORDER BY cc.status, u.full_name"
    );
    $stmt->execute([$userId]);
    $info['clients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare(
        "SELECT cc.status, cc.accepted_at, cc.ended_at, cc.coach_id, cc.client_id, c.full_name AS coach_name, s.full_name AS client_name
         FROM coach_clients cc JOIN users c ON c.id = cc.coach_id JOIN users s ON s.id = cc.client_id
         WHERE (cc.coach_id = ? OR cc.client_id = ?) AND cc.status IN ('ENDED', 'REJECTED')
         ORDER BY cc.id DESC LIMIT 20"
    );
    $stmt->execute([$userId, $userId]);
    $info['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $info;
}

// =====================================================================
// Sửa / xóa nhận xét: chỉ HLV đã viết, khi quan hệ còn ACTIVE
// =====================================================================

function coachOwnFeedback(PDO $pdo, int $coachId, int $feedbackId): array
{
    $stmt = $pdo->prepare(
        "SELECT f.id, f.log_date, cc.client_id FROM coach_feedback f JOIN coach_clients cc ON cc.id = f.coach_client_id
         WHERE f.id = ? AND cc.coach_id = ? AND cc.status = 'ACTIVE'"
    );
    $stmt->execute([$feedbackId, $coachId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        throw new CoachException('Không tìm thấy nhận xét này.');
    }
    return $row;
}

/** Sửa nhận xét: ghi updated_at, đặt lại read_at = NULL để học viên được báo lần nữa. */
function coachUpdateFeedback(PDO $pdo, int $coachId, int $feedbackId, string $content): array
{
    if ($e = coachTextError($content, 'Nhận xét', 2, COACH_FEEDBACK_MAX)) {
        throw new CoachException($e);
    }
    $row = coachOwnFeedback($pdo, $coachId, $feedbackId);
    $pdo->prepare('UPDATE coach_feedback SET content = ?, updated_at = ?, read_at = NULL WHERE id = ?')->execute([$content, coachNow(), $feedbackId]);
    return $row;
}

function coachDeleteFeedback(PDO $pdo, int $coachId, int $feedbackId): array
{
    $row = coachOwnFeedback($pdo, $coachId, $feedbackId);
    $pdo->prepare('DELETE FROM coach_feedback WHERE id = ?')->execute([$feedbackId]);
    return $row;
}

// =====================================================================
// "Đã xem" trong khung chat
// =====================================================================

/** Id lớn nhất trong các tin CỦA $me mà người kia đã đọc (0 nếu chưa có). JS dùng để hiện "Đã xem". */
function coachSeenUpTo(PDO $pdo, int $relationId, int $me): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM coach_messages WHERE coach_client_id = ? AND sender_id = ? AND read_at IS NOT NULL');
    $stmt->execute([$relationId, $me]);
    return (int)$stmt->fetchColumn();
}

// =====================================================================
// Lịch sử của học viên sau khi ngừng tập (HLV thì vẫn mất quyền như thiết kế)
// =====================================================================

/** Các lần học viên đã tập với HLV và đã kết thúc (chỉ quan hệ từng ACTIVE, có accepted_at). */
function coachPastRelations(PDO $pdo, int $clientId): array
{
    $stmt = $pdo->prepare(
        "SELECT cc.id, cc.coach_id, cc.accepted_at, cc.ended_at, u.full_name AS coach_name, u.avatar AS coach_avatar,
                (SELECT COUNT(*) FROM coach_feedback f WHERE f.coach_client_id = cc.id) AS feedback_count,
                (SELECT COUNT(*) FROM coach_messages m WHERE m.coach_client_id = cc.id) AS message_count
         FROM coach_clients cc JOIN users u ON u.id = cc.coach_id
         WHERE cc.client_id = ? AND cc.status = 'ENDED' AND cc.accepted_at IS NOT NULL
         ORDER BY cc.ended_at DESC"
    );
    $stmt->execute([$clientId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Một quan hệ cũ của CHÍNH học viên này (id trên URL phải thuộc học viên đang đăng nhập), null nếu không. */
function coachPastRelationForClient(PDO $pdo, int $clientId, int $relationId): ?array
{
    foreach (coachPastRelations($pdo, $clientId) as $r) {
        if ((int)$r['id'] === $relationId) {
            return $r;
        }
    }
    return null;
}

// =====================================================================
// Bản nháp lịch tập (status = 'DRAFT')
// =====================================================================

/** Bài của một lịch, nhóm theo thứ (cùng dạng với fetchActivePlan). */
function coachPlanDays(PDO $pdo, int $planId): array
{
    $stmt = $pdo->prepare(
        'SELECT wpe.id AS row_id, wpe.day_of_week, wpe.sets, wpe.reps, e.id AS exercise_id, e.name, e.muscle_group
         FROM workout_plan_exercises wpe JOIN exercises e ON e.id = wpe.exercise_id
         WHERE wpe.workout_plan_id = ? ORDER BY wpe.day_of_week, wpe.order_index'
    );
    $stmt->execute([$planId]);
    $days = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $days[(int)$row['day_of_week']][] = [
            'row_id' => (int)$row['row_id'], 'exercise_id' => (int)$row['exercise_id'], 'name' => $row['name'],
            'muscle_group' => $row['muscle_group'], 'sets' => (int)$row['sets'], 'reps' => (int)$row['reps'],
        ];
    }
    return $days;
}

/** Bản nháp HLV này đang soạn cho học viên (mỗi HLV tối đa 1 bản nháp cho mỗi học viên), null nếu chưa có. */
function coachDraftPlan(PDO $pdo, int $coachId, int $clientId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, name, split_type, days_per_week, updated_at FROM workout_plans
         WHERE user_id = ? AND assigned_by = ? AND status = 'DRAFT' ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$clientId, $coachId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($plan === false) {
        return null;
    }
    $plan['days'] = coachPlanDays($pdo, (int)$plan['id']);
    return $plan;
}

function coachDeleteDrafts(PDO $pdo, int $coachId, int $clientId): void
{
    // workout_plan_exercises có ON DELETE CASCADE nên bài của bản nháp tự xóa theo
    $pdo->prepare("DELETE FROM workout_plans WHERE user_id = ? AND assigned_by = ? AND status = 'DRAFT'")->execute([$clientId, $coachId]);
}

/** Tạo bản nháp bằng thuật toán gợi ý (thay bản nháp cũ). Lịch học viên đang tập KHÔNG bị đụng tới. */
function coachDraftFromGenerator(PDO $pdo, int $coachId, int $clientId, int $daysPerWeek, string $level, ?string $splitType): int
{
    $goal = fetchUserGoal($pdo, $clientId);
    if ($goal === null) {
        throw new CoachException('Học viên chưa chọn mục tiêu trong hồ sơ.');
    }
    $plan = generateWorkoutPlan($daysPerWeek, $goal, $level, fetchExercisesGroupedByMuscle($pdo), $splitType);
    coachDeleteDrafts($pdo, $coachId, $clientId);
    return saveWorkoutPlan($pdo, $clientId, $plan, $coachId, 'DRAFT');
}

/** Tạo bản nháp bằng cách chép lịch đang dùng (để sửa nhiều chỗ rồi giao một lần). */
function coachDraftFromActive(PDO $pdo, int $coachId, int $clientId): int
{
    $active = fetchActivePlan($pdo, $clientId);
    if ($active === null) {
        throw new CoachException('Học viên chưa có lịch đang dùng để chép.');
    }
    return coachTx($pdo, function () use ($pdo, $coachId, $clientId, $active) {
        coachDeleteDrafts($pdo, $coachId, $clientId);
        $pdo->prepare("INSERT INTO workout_plans (user_id, name, split_type, days_per_week, status, assigned_by) VALUES (?, ?, ?, ?, 'DRAFT', ?)")
            ->execute([$clientId, 'Bản nháp: ' . $active['name'], $active['split_type'], $active['days_per_week'], $coachId]);
        $draftId = (int)$pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO workout_plan_exercises (workout_plan_id, exercise_id, day_of_week, sets, reps, weight_kg_suggested, order_index)
             SELECT ?, exercise_id, day_of_week, sets, reps, weight_kg_suggested, order_index FROM workout_plan_exercises WHERE workout_plan_id = ?'
        )->execute([$draftId, $active['id']]);
        return $draftId;
    });
}

/** Dòng bài thuộc bản nháp của HLV này cho học viên này; ngược lại ném lỗi (chống sửa row_id của lịch khác). */
function coachDraftRow(PDO $pdo, int $coachId, int $clientId, int $rowId): array
{
    $stmt = $pdo->prepare(
        "SELECT wpe.id, wpe.workout_plan_id, wpe.day_of_week, wpe.exercise_id FROM workout_plan_exercises wpe
         JOIN workout_plans wp ON wp.id = wpe.workout_plan_id
         WHERE wpe.id = ? AND wp.user_id = ? AND wp.assigned_by = ? AND wp.status = 'DRAFT'"
    );
    $stmt->execute([$rowId, $clientId, $coachId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        throw new CoachException('Không tìm thấy bài này trong bản nháp.');
    }
    return $row;
}

/**
 * Sửa bản nháp. $action = update | replace | remove | add; $p gồm row_id, sets, reps, new_exercise_id, day_of_week, exercise_id (số nguyên).
 * Cùng quy tắc kiểm tra với sửa lịch thường (assertValidSetsReps, assertResistanceExerciseExists, không trùng bài trong ngày).
 */
function coachDraftEdit(PDO $pdo, int $coachId, int $clientId, string $action, array $p): void
{
    $draft = coachDraftPlan($pdo, $coachId, $clientId);
    if ($draft === null) {
        throw new CoachException('Chưa có bản nháp.');
    }
    switch ($action) {
        case 'update':
            assertValidSetsReps($p['sets'], $p['reps']);
            coachDraftRow($pdo, $coachId, $clientId, $p['row_id']);
            $pdo->prepare('UPDATE workout_plan_exercises SET sets = ?, reps = ? WHERE id = ?')->execute([$p['sets'], $p['reps'], $p['row_id']]);
            break;
        case 'replace':
            assertResistanceExerciseExists($pdo, $p['new_exercise_id']);
            $row = coachDraftRow($pdo, $coachId, $clientId, $p['row_id']);
            if (planDayHasExercise($pdo, (int)$row['workout_plan_id'], (int)$row['day_of_week'], $p['new_exercise_id'], $p['row_id'])) {
                throw new CoachException('Bài này đã có trong ngày đó.');
            }
            $pdo->prepare('UPDATE workout_plan_exercises SET exercise_id = ? WHERE id = ?')->execute([$p['new_exercise_id'], $p['row_id']]);
            break;
        case 'remove':
            coachDraftRow($pdo, $coachId, $clientId, $p['row_id']);
            $pdo->prepare('DELETE FROM workout_plan_exercises WHERE id = ?')->execute([$p['row_id']]);
            break;
        case 'add':
            if ($p['day_of_week'] < 1 || $p['day_of_week'] > 7) {
                throw new CoachException('Ngày trong tuần không hợp lệ.');
            }
            assertValidSetsReps($p['sets'], $p['reps']);
            assertResistanceExerciseExists($pdo, $p['exercise_id']);
            if (planDayHasExercise($pdo, (int)$draft['id'], $p['day_of_week'], $p['exercise_id'])) {
                throw new CoachException('Bài này đã có trong ngày đó.');
            }
            $max = $pdo->prepare('SELECT COALESCE(MAX(order_index), 0) FROM workout_plan_exercises WHERE workout_plan_id = ? AND day_of_week = ?');
            $max->execute([$draft['id'], $p['day_of_week']]);
            $pdo->prepare('INSERT INTO workout_plan_exercises (workout_plan_id, exercise_id, day_of_week, sets, reps, order_index) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$draft['id'], $p['exercise_id'], $p['day_of_week'], $p['sets'], $p['reps'], (int)$max->fetchColumn() + 1]);
            break;
        default:
            throw new CoachException('Thao tác không hợp lệ.');
    }
    $pdo->prepare('UPDATE workout_plans SET updated_at = ? WHERE id = ?')->execute([coachNow(), $draft['id']]);
}

/**
 * Giao bản nháp: trong 1 transaction, lịch đang dùng → ARCHIVED, bản nháp → ACTIVE (bỏ chữ "Bản nháp: " ở tên,
 * số buổi/tuần tính lại theo số ngày có bài). Bản nháp rỗng thì không cho giao. Trả về id lịch vừa giao.
 */
function coachPublishDraft(PDO $pdo, int $coachId, int $clientId): int
{
    return coachTx($pdo, function () use ($pdo, $coachId, $clientId) {
        $draft = coachDraftPlan($pdo, $coachId, $clientId);
        if ($draft === null) {
            throw new CoachException('Chưa có bản nháp để giao.');
        }
        if (!$draft['days']) {
            throw new CoachException('Bản nháp chưa có bài nào.');
        }
        $pdo->prepare("UPDATE workout_plans SET status = 'ARCHIVED' WHERE user_id = ? AND status = 'ACTIVE'")->execute([$clientId]);
        $name = preg_replace('/^Bản nháp: /u', '', $draft['name']);
        $pdo->prepare("UPDATE workout_plans SET status = 'ACTIVE', name = ?, days_per_week = ? WHERE id = ?")
            ->execute([$name, count($draft['days']), $draft['id']]);
        notify($pdo, $clientId, 'coach.plan', 'HLV đã giao lịch tập mới',
            'HLV ' . notifName($pdo, $coachId) . ' vừa giao lịch "' . notifExcerpt((string)$name, 60) . '" (' . count($draft['days']) . ' buổi/tuần).', '/workout-plan.php', true);
        return (int)$draft['id'];
    });
}

// =====================================================================
// File chứng chỉ trong mẫu đơn HLV
// =====================================================================

const COACH_CERT_MAX_BYTES = 3 * 1024 * 1024;   // 3 MB
const COACH_CERT_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

/** Thư mục lưu chứng chỉ: NGOÀI public/ nên không mở được bằng URL; chỉ admin/certificate.php đọc ra. */
function coachCertDir(): string
{
    return dirname(__DIR__, 2) . '/storage/certificates';
}

/**
 * Nhận file từ $_FILES['certificate_file']. Không chọn file → null. Lỗi → CoachException.
 * Kiểm tra: tải lên thành công, tối đa 3 MB, loại file đọc từ NỘI DUNG (finfo), không tin đuôi file hay loại trình duyệt khai.
 * Tên lưu do máy chủ đặt ngẫu nhiên: c<user_id>-<16 ký tự hex>.<đuôi>.
 */
function coachStoreCertificate(?array $file, int $userId): ?string
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($file === null || $err === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || ($err === UPLOAD_ERR_OK && $file['size'] > COACH_CERT_MAX_BYTES)) {
        throw new CoachException('File chứng chỉ tối đa 3 MB.');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new CoachException('Tải file chứng chỉ lên không thành công, thử lại.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(COACH_CERT_TYPES[$mime])) {
        throw new CoachException('Chứng chỉ chỉ nhận ảnh JPG, PNG, WebP hoặc file PDF.');
    }
    $dir = coachCertDir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        throw new RuntimeException('Không tạo được thư mục lưu chứng chỉ.');
    }
    $name = 'c' . $userId . '-' . bin2hex(random_bytes(8)) . '.' . COACH_CERT_TYPES[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Không lưu được file chứng chỉ.');
    }
    return $name;
}

/** Tên file chứng chỉ chỉ hợp lệ khi đúng dạng coachStoreCertificate đặt (chống đường dẫn lạ trong DB như ../../config.php). */
function coachCertNameOk(?string $name): bool
{
    return is_string($name) && preg_match('/^c\d+-[a-f0-9]{16}\.(jpg|png|webp|pdf)$/', $name) === 1;
}
