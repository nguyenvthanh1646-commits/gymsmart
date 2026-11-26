<?php
/**
 * includes/expert-db.php — truy vấn DB của phần tư vấn sức khỏe từ chuyên gia (A-55). Bảng: sql/health_experts.sql.
 *
 * Quy ước giống coach-db.php:
 *   - $userId / $expertId / $adminId lấy từ $_SESSION ở nơi gọi; mọi câu SQL tự kiểm tra lại câu hỏi thuộc đúng người.
 *   - Lỗi nghiệp vụ ném ExpertException (câu tiếng Việt hiện cho người dùng).
 *   - Thời gian lấy từ PHP (coachNow()) để test chạy được trên SQLite.
 * Quyền xem một câu hỏi:
 *   - Người hỏi: luôn xem được câu của mình.
 *   - Chuyên gia (vai trò EXPERT trong DB): câu đang chờ (OPEN) đúng chủ đề mình nhận, hoặc câu mình đã nhận.
 *   - ADMIN và người khác: KHÔNG xem được (câu hỏi sức khỏe là dữ liệu riêng tư).
 */
require_once __DIR__ . '/coach-db.php';      // coachTx, coachNow, coachForUpdate, coachIsDuplicate, notify, file chứng chỉ
require_once __DIR__ . '/expert-rules.php';

/** Lỗi nghiệp vụ của phần chuyên gia. Kế thừa CoachException để các hàm dùng chung (lưu file chứng chỉ) bắt chung được. */
class ExpertException extends CoachException
{
}

/** DB đã chạy sql/health_experts.sql chưa. */
function expertTablesReady(PDO $pdo): bool
{
    static $ready = [];
    $key = spl_object_id($pdo);
    if (!isset($ready[$key])) {
        try {
            $pdo->query('SELECT 1 FROM expert_profiles WHERE 1 = 0');
            $pdo->query('SELECT 1 FROM health_questions WHERE 1 = 0');
            $ready[$key] = true;
        } catch (PDOException $e) {
            $ready[$key] = false;
        }
    }
    return $ready[$key];
}

// =====================================================================
// 1. ĐƠN ĐĂNG KÝ CHUYÊN GIA
// =====================================================================

function expertLatestApplication(PDO $pdo, int $userId): ?array
{
    $st = $pdo->prepare('SELECT * FROM expert_applications WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$userId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r === false ? null : $r;
}

/** Nộp đơn. Chỉ USER, không đang có / chờ HLV. $data đã qua validateExpertApplication, có thêm certificate_file. */
function expertSubmitApplication(PDO $pdo, int $userId, array $data): int
{
    $role = coachUserRole($pdo, $userId);
    if ($role !== 'USER') {
        throw new ExpertException($role === 'EXPERT' ? 'Bạn đã là chuyên gia.' : 'Tài khoản này không đăng ký làm chuyên gia được.');
    }
    if (coachTablesReady($pdo) && coachOpenRelationForClient($pdo, $userId) !== null) {
        throw new ExpertException('Bạn đang có (hoặc đang chờ) huấn luyện viên. Hãy kết thúc trước khi đăng ký làm chuyên gia.');
    }
    if (empty($data['certificate_file'])) {
        throw new ExpertException('Hãy tải lên ảnh hoặc file PDF chứng chỉ hành nghề.');
    }
    try {
        $pdo->prepare(
            "INSERT INTO expert_applications (user_id, profession, title, workplace, license_no, experience_years, topics, bio, certificate_file, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)"
        )->execute([$userId, $data['profession'], $data['title'], $data['workplace'], $data['license_no'], $data['experience_years'],
                    $data['topics'], $data['bio'], $data['certificate_file'], coachNow()]);
    } catch (PDOException $e) {
        if (coachIsDuplicate($e)) {
            throw new ExpertException('Bạn đã có một đơn đang chờ duyệt.');
        }
        throw $e;
    }
    $id = (int)$pdo->lastInsertId();
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'ADMIN'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        notify($pdo, (int)$adminId, 'admin.expert_application', 'Đơn đăng ký chuyên gia mới',
            notifName($pdo, $userId) . ': ' . (EXPERT_PROFESSIONS[$data['profession']] ?? '') . ', ' . notifExcerpt($data['title']), '/admin/expert-applications.php?id=' . $id);
    }
    return $id;
}

function expertWithdrawApplication(PDO $pdo, int $userId): void
{
    $st = $pdo->prepare("UPDATE expert_applications SET status = 'REJECTED', admin_note = 'Người nộp tự rút đơn.', reviewed_at = ? WHERE user_id = ? AND status = 'PENDING'");
    $st->execute([coachNow(), $userId]);
    if ($st->rowCount() === 0) {
        throw new ExpertException('Không có đơn nào đang chờ để rút.');
    }
}

function expertListApplications(PDO $pdo, string $status = ''): array
{
    $sql = 'SELECT a.id, a.profession, a.title, a.experience_years, a.status, a.created_at, u.full_name FROM expert_applications a JOIN users u ON u.id = a.user_id';
    $args = [];
    if ($status !== '') {
        $sql .= ' WHERE a.status = ?';
        $args[] = $status;
    }
    $st = $pdo->prepare($sql . ' ORDER BY a.id DESC LIMIT 200');
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function expertFindApplication(PDO $pdo, int $appId): ?array
{
    $st = $pdo->prepare(
        'SELECT a.*, u.full_name, u.email, u.role, r.full_name AS reviewer_name FROM expert_applications a
         JOIN users u ON u.id = a.user_id LEFT JOIN users r ON r.id = a.reviewed_by WHERE a.id = ?'
    );
    $st->execute([$appId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r === false ? null : $r;
}

/** ADMIN duyệt: trong 1 transaction đổi vai trò thành EXPERT và tạo / ghi đè hồ sơ chuyên gia từ đơn. Trả tên người được duyệt. */
function expertApproveApplication(PDO $pdo, int $appId, int $adminId, string $note): string
{
    return coachTx($pdo, function () use ($pdo, $appId, $adminId, $note) {
        $st = $pdo->prepare('SELECT a.*, u.full_name, u.role FROM expert_applications a JOIN users u ON u.id = a.user_id WHERE a.id = ?' . coachForUpdate($pdo));   // audit:ok coachForUpdate() chỉ trả " FOR UPDATE" hoặc rỗng
        $st->execute([$appId]);
        $app = $st->fetch(PDO::FETCH_ASSOC);
        if ($app === false || $app['status'] !== 'PENDING') {
            throw new ExpertException('Đơn không tồn tại hoặc đã được xử lý.');
        }
        if ($app['role'] !== 'USER') {
            throw new ExpertException('Chỉ duyệt được cho tài khoản USER (tài khoản này đang là ' . $app['role'] . ').');
        }
        $userId = (int)$app['user_id'];
        if (coachTablesReady($pdo) && coachOpenRelationForClient($pdo, $userId) !== null) {
            throw new ExpertException('Người này đang là học viên của một HLV. Họ cần kết thúc trước khi được duyệt.');
        }
        $now = coachNow();
        $pdo->prepare("UPDATE expert_applications SET status = 'APPROVED', admin_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?")
            ->execute([$note, $adminId, $now, $appId]);
        $pdo->prepare("UPDATE users SET role = 'EXPERT' WHERE id = ?")->execute([$userId]);
        $fields = [$app['profession'], $app['title'], $app['workplace'], $app['license_no'], $app['experience_years'], $app['topics'], $app['bio']];
        $ex = $pdo->prepare('SELECT COUNT(*) FROM expert_profiles WHERE user_id = ?');
        $ex->execute([$userId]);
        if ((int)$ex->fetchColumn() > 0) {
            $pdo->prepare('UPDATE expert_profiles SET profession = ?, title = ?, workplace = ?, license_no = ?, experience_years = ?, topics = ?, bio = ?, accepting = 1, updated_at = ? WHERE user_id = ?')
                ->execute(array_merge($fields, [$now, $userId]));
        } else {
            $pdo->prepare('INSERT INTO expert_profiles (user_id, profession, title, workplace, license_no, experience_years, topics, bio, accepting, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)')
                ->execute(array_merge([$userId], $fields, [$now, $now]));
        }
        notify($pdo, $userId, 'expert.application_approved', 'Đơn đăng ký chuyên gia đã được duyệt',
            'Bạn đã là chuyên gia sức khỏe trên GymSmart. Vào khu chuyên gia để nhận câu hỏi.', '/expert/dashboard.php');
        return (string)$app['full_name'];
    });
}

/** ADMIN từ chối, bắt buộc ghi lý do. Trả tên người nộp. */
function expertRejectApplication(PDO $pdo, int $appId, int $adminId, string $note): string
{
    if ($note === '') {
        throw new ExpertException('Hãy ghi lý do từ chối để người nộp biết cần bổ sung gì.');
    }
    $app = expertFindApplication($pdo, $appId);
    if ($app === null || $app['status'] !== 'PENDING') {
        throw new ExpertException('Đơn không tồn tại hoặc đã được xử lý.');
    }
    $st = $pdo->prepare("UPDATE expert_applications SET status = 'REJECTED', admin_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ? AND status = 'PENDING'");
    $st->execute([$note, $adminId, coachNow(), $appId]);
    if ($st->rowCount() === 0) {
        throw new ExpertException('Đơn đã được xử lý ở nơi khác.');
    }
    notify($pdo, (int)$app['user_id'], 'expert.application_rejected', 'Đơn đăng ký chuyên gia chưa được duyệt',
        'Lý do: ' . notifExcerpt($note, 200) . ' Bạn có thể sửa và nộp lại.', '/expert-apply.php');
    return (string)$app['full_name'];
}

/**
 * ADMIN thu hồi vai trò chuyên gia: câu hỏi chuyên gia đang trao đổi trả về hàng chờ (OPEN) để chuyên gia khác nhận,
 * tắt nhận câu hỏi, vai trò về USER. Trả ['name', 'released' => số câu trả về].
 */
function expertRevoke(PDO $pdo, int $expertId, int $adminId): array
{
    return coachTx($pdo, function () use ($pdo, $expertId) {
        $st = $pdo->prepare('SELECT full_name, role FROM users WHERE id = ?' . coachForUpdate($pdo));   // audit:ok coachForUpdate() chỉ trả " FOR UPDATE" hoặc rỗng
        $st->execute([$expertId]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u === false || $u['role'] !== 'EXPERT') {
            throw new ExpertException('Tài khoản này không phải chuyên gia.');
        }
        $qs = $pdo->prepare("SELECT id, user_id, title FROM health_questions WHERE expert_id = ? AND status = 'CLAIMED'");
        $qs->execute([$expertId]);
        $released = $qs->fetchAll(PDO::FETCH_ASSOC);
        foreach ($released as $q) {
            notify($pdo, (int)$q['user_id'], 'expert.question', 'Câu hỏi được chuyển lại hàng chờ',
                'Chuyên gia đang trả lời câu "' . notifExcerpt($q['title'], 60) . '" không còn hoạt động trên GymSmart. Câu hỏi đã được chuyển lại để chuyên gia khác nhận.',
                '/consult-question.php?id=' . (int)$q['id']);
        }
        $pdo->prepare("UPDATE health_questions SET status = 'OPEN', expert_id = NULL, claimed_at = NULL WHERE expert_id = ? AND status = 'CLAIMED'")->execute([$expertId]);
        $pdo->prepare('UPDATE expert_profiles SET accepting = 0, updated_at = ? WHERE user_id = ?')->execute([coachNow(), $expertId]);
        $pdo->prepare("UPDATE users SET role = 'USER' WHERE id = ?")->execute([$expertId]);
        notify($pdo, $expertId, 'expert.revoked', 'Vai trò chuyên gia đã bị thu hồi', 'Quản trị viên đã thu hồi vai trò chuyên gia của bạn.', '/index.php');
        return ['name' => (string)$u['full_name'], 'released' => count($released)];
    });
}

// =====================================================================
// 2. HỒ SƠ CHUYÊN GIA
// =====================================================================

function expertProfile(PDO $pdo, int $expertId): ?array
{
    $st = $pdo->prepare('SELECT p.*, u.full_name, u.avatar FROM expert_profiles p JOIN users u ON u.id = p.user_id WHERE p.user_id = ?');
    $st->execute([$expertId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r === false ? null : $r;
}

/** Chuyên gia tự sửa: giới thiệu, chủ đề, bật/tắt nhận câu hỏi. Ngành, chức danh, nơi công tác, số chứng chỉ không sửa ở đây. */
function expertSaveProfile(PDO $pdo, int $expertId, string $bio, string $topicsCsv, bool $accepting): void
{
    $st = $pdo->prepare("UPDATE expert_profiles SET bio = ?, topics = ?, accepting = ?, updated_at = ? WHERE user_id = ? AND user_id IN (SELECT id FROM users WHERE role = 'EXPERT')");
    $st->execute([$bio, $topicsCsv, $accepting ? 1 : 0, coachNow(), $expertId]);
    if ($st->rowCount() === 0 && coachUserRole($pdo, $expertId) !== 'EXPERT') {
        throw new ExpertException('Tài khoản này không còn là chuyên gia.');
    }
}

/**
 * Danh sách chuyên gia cho người dùng xem, kèm số câu đã trả lời và tỉ lệ hữu ích. KHÔNG có số chứng chỉ, email.
 * verified > 0 khi có đơn ĐÃ DUYỆT kèm file chứng chỉ; hồ sơ mẫu (tools/seed-experts.php) không có file nên không được gắn nhãn đã kiểm tra.
 */
function expertListPublic(PDO $pdo): array
{
    $st = $pdo->prepare(
        "SELECT u.id, u.full_name, u.avatar, p.profession, p.title, p.workplace, p.experience_years, p.topics, p.bio, p.accepting,
                (SELECT COUNT(*) FROM health_questions q WHERE q.expert_id = u.id
                    AND EXISTS (SELECT 1 FROM health_messages m WHERE m.question_id = q.id AND m.sender_id = u.id)) AS answered,
                (SELECT COUNT(*) FROM health_questions q WHERE q.expert_id = u.id AND q.helpful = 1) AS helpful_yes,
                (SELECT COUNT(*) FROM health_questions q WHERE q.expert_id = u.id AND q.helpful IS NOT NULL) AS helpful_rated,
                (SELECT COUNT(*) FROM expert_applications a WHERE a.user_id = u.id AND a.status = 'APPROVED' AND a.certificate_file IS NOT NULL) AS verified
         FROM users u JOIN expert_profiles p ON p.user_id = u.id
         WHERE u.role = 'EXPERT' ORDER BY p.accepting DESC, u.full_name"
    );
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Cho trang admin: chuyên gia kèm số câu đang trao đổi. */
function expertListForAdmin(PDO $pdo): array
{
    $st = $pdo->prepare(
        "SELECT u.id, u.full_name, u.email, p.profession, p.title, p.workplace, p.license_no, p.accepting,
                (SELECT COUNT(*) FROM health_questions q WHERE q.expert_id = u.id AND q.status = 'CLAIMED') AS claimed,
                (SELECT COUNT(*) FROM health_questions q WHERE q.expert_id = u.id AND q.status = 'CLOSED') AS closed
         FROM users u JOIN expert_profiles p ON p.user_id = u.id WHERE u.role = 'EXPERT' ORDER BY u.full_name"
    );
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================================
// 3. CÂU HỎI
// =====================================================================

/**
 * Người dùng gửi câu hỏi ($data đã qua validateHealthQuestion, không có lỗi). Chỉ USER và COACH hỏi được.
 * share_profile = true thì chụp lại số liệu hồ sơ + số đo gần nhất vào câu hỏi. Báo cho các chuyên gia có chủ đề phù hợp.
 */
function healthAsk(PDO $pdo, int $userId, array $data): int
{
    $role = coachUserRole($pdo, $userId);
    if ($role !== 'USER' && $role !== 'COACH') {
        throw new ExpertException('Tài khoản này không gửi câu hỏi tư vấn được.');
    }
    return coachTx($pdo, function () use ($pdo, $userId, $data) {
        $pdo->prepare('SELECT id FROM users WHERE id = ?' . coachForUpdate($pdo))->execute([$userId]);   // audit:ok khóa dòng: bấm gửi 2 lần không vượt giới hạn
        $st = $pdo->prepare("SELECT (SELECT COUNT(*) FROM health_questions WHERE user_id = ? AND status IN ('OPEN', 'CLAIMED')),
                                    (SELECT COUNT(*) FROM health_questions WHERE user_id = ? AND created_at >= ?)");
        $st->execute([$userId, $userId, date('Y-m-d H:i:s', time() - 86400)]);
        [$open, $day] = array_map('intval', $st->fetch(PDO::FETCH_NUM));
        if ($e = healthAskLimitError($open, $day)) {
            throw new ExpertException($e);
        }
        $snapshot = null;
        if ($data['share_profile']) {
            $u = $pdo->prepare('SELECT age, gender, height_cm, weight_kg, activity_level, goal FROM users WHERE id = ?');
            $u->execute([$userId]);
            $metric = null;
            try {
                $m = $pdo->prepare('SELECT recorded_date, weight_kg, waist_cm FROM body_metrics WHERE user_id = ? ORDER BY recorded_date DESC, id DESC LIMIT 1');
                $m->execute([$userId]);
                $metric = $m->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (PDOException $e) {
                $metric = null;   // DB cũ chưa có bảng số đo
            }
            $snapshot = healthProfileSnapshot($u->fetch(PDO::FETCH_ASSOC) ?: [], $metric);
            $snapshot = $snapshot === '' ? null : mb_substr($snapshot, 0, 600);
        }
        $pdo->prepare("INSERT INTO health_questions (user_id, topic, title, body, profile_snapshot, red_flags, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'OPEN', ?)")
            ->execute([$userId, $data['topic'], $data['title'], $data['body'], $snapshot, mb_substr(implode(', ', $data['red_flags']), 0, 255), coachNow()]);
        $id = (int)$pdo->lastInsertId();
        // Báo các chuyên gia đang nhận câu hỏi, đúng chủ đề (chủ đề "Vấn đề khác": báo mọi chuyên gia)
        $ex = $pdo->prepare("SELECT p.user_id, p.topics FROM expert_profiles p JOIN users u ON u.id = p.user_id WHERE u.role = 'EXPERT' AND p.accepting = 1 AND p.user_id <> ?");
        $ex->execute([$userId]);
        foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $e) {
            if (expertTopicMatches(explode(',', (string)$e['topics']), $data['topic'])) {
                notify($pdo, (int)$e['user_id'], 'expert.question_new', 'Câu hỏi mới: ' . HEALTH_TOPICS[$data['topic']],
                    notifExcerpt($data['title'], 100), '/expert/dashboard.php', true);
            }
        }
        return $id;
    });
}

/** Câu hỏi của chính người hỏi (null nếu không phải của họ). */
function healthQuestionForAsker(PDO $pdo, int $userId, int $qid): ?array
{
    $st = $pdo->prepare(
        'SELECT q.*, e.full_name AS expert_name, e.avatar AS expert_avatar, p.title AS expert_title, p.profession AS expert_profession
         FROM health_questions q LEFT JOIN users e ON e.id = q.expert_id LEFT JOIN expert_profiles p ON p.user_id = q.expert_id
         WHERE q.id = ? AND q.user_id = ?'
    );
    $st->execute([$qid, $userId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r === false ? null : $r;
}

/** Mọi câu hỏi của người hỏi, kèm số tin chưa đọc từ chuyên gia. */
function healthQuestionsForAsker(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare(
        "SELECT q.id, q.topic, q.title, q.status, q.created_at, q.closed_at, e.full_name AS expert_name,
                (SELECT COUNT(*) FROM health_messages m WHERE m.question_id = q.id AND m.sender_id <> q.user_id AND m.read_at IS NULL) AS unread
         FROM health_questions q LEFT JOIN users e ON e.id = q.expert_id
         WHERE q.user_id = ? ORDER BY CASE q.status WHEN 'CLAIMED' THEN 1 WHEN 'OPEN' THEN 2 WHEN 'CLOSED' THEN 3 ELSE 4 END, q.id DESC LIMIT 100"
    );   // CASE thay cho FIELD() để chạy được cả trên SQLite (test)
    $st->execute([$userId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Chủ đề chuyên gia đang nhận (mã), null nếu không còn là chuyên gia. */
function expertTopicsOf(PDO $pdo, int $expertId): ?array
{
    $st = $pdo->prepare("SELECT p.topics FROM expert_profiles p JOIN users u ON u.id = p.user_id WHERE p.user_id = ? AND u.role = 'EXPERT'");
    $st->execute([$expertId]);
    $t = $st->fetchColumn();
    return $t === false ? null : array_values(array_filter(explode(',', (string)$t)));
}

/**
 * Câu hỏi chuyên gia được xem: câu mình đã nhận (mọi trạng thái), hoặc câu đang chờ đúng chủ đề và không phải câu của chính mình.
 * null nếu không được xem. Kèm tên người hỏi đã rút gọn (asker_name), không có email.
 */
function healthQuestionForExpert(PDO $pdo, int $expertId, int $qid): ?array
{
    $topics = expertTopicsOf($pdo, $expertId);
    if ($topics === null) {
        return null;
    }
    $st = $pdo->prepare('SELECT q.*, u.full_name AS asker_full FROM health_questions q JOIN users u ON u.id = q.user_id WHERE q.id = ?');
    $st->execute([$qid]);
    $q = $st->fetch(PDO::FETCH_ASSOC);
    if ($q === false) {
        return null;
    }
    $mine = (int)($q['expert_id'] ?? 0) === $expertId;
    $poolOk = $q['status'] === 'OPEN' && (int)$q['user_id'] !== $expertId && expertTopicMatches($topics, (string)$q['topic']);
    if (!$mine && !$poolOk) {
        return null;
    }
    $q['asker_name'] = healthAskerName((string)$q['asker_full']);
    unset($q['asker_full']);
    $q['is_mine'] = $mine;
    return $q;
}

/** Hàng chờ của chuyên gia: câu OPEN đúng chủ đề, cũ nhất trước. */
function healthPool(PDO $pdo, int $expertId): array
{
    $topics = expertTopicsOf($pdo, $expertId);
    if ($topics === null) {
        return [];
    }
    $st = $pdo->prepare("SELECT q.id, q.topic, q.title, q.body, q.red_flags, q.profile_snapshot, q.created_at, u.full_name AS asker_full
                         FROM health_questions q JOIN users u ON u.id = q.user_id WHERE q.status = 'OPEN' AND q.user_id <> ? ORDER BY q.id");
    $st->execute([$expertId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $q) {
        if (expertTopicMatches($topics, (string)$q['topic'])) {
            $q['asker_name'] = healthAskerName((string)$q['asker_full']);
            unset($q['asker_full']);
            $out[] = $q;
        }
    }
    return $out;
}

/** Câu chuyên gia đã nhận: đang trao đổi trước, rồi 20 câu đã đóng gần nhất. Kèm số tin chưa đọc từ người hỏi. */
function healthMine(PDO $pdo, int $expertId): array
{
    $st = $pdo->prepare(
        "SELECT q.id, q.topic, q.title, q.status, q.claimed_at, q.closed_at, q.helpful, u.full_name AS asker_full,
                (SELECT COUNT(*) FROM health_messages m WHERE m.question_id = q.id AND m.sender_id = q.user_id AND m.read_at IS NULL) AS unread,
                (SELECT MAX(m.created_at) FROM health_messages m WHERE m.question_id = q.id) AS last_msg
         FROM health_questions q JOIN users u ON u.id = q.user_id WHERE q.expert_id = ? AND q.status IN ('CLAIMED', 'CLOSED') ORDER BY q.id DESC LIMIT 100"
    );
    $st->execute([$expertId]);
    $out = ['claimed' => [], 'closed' => []];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $q) {
        $q['asker_name'] = healthAskerName((string)$q['asker_full']);
        unset($q['asker_full']);
        if ($q['status'] === 'CLAIMED') {
            $out['claimed'][] = $q;
        } elseif (count($out['closed']) < 20) {
            $out['closed'][] = $q;
        }
    }
    return $out;
}

function healthMessages(PDO $pdo, int $qid): array
{
    $st = $pdo->prepare('SELECT id, sender_id, body, read_at, created_at FROM health_messages WHERE question_id = ? ORDER BY id');
    $st->execute([$qid]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Đánh dấu đã đọc các tin người khác gửi trong câu hỏi (gọi sau khi đã kiểm tra quyền xem). */
function healthMarkRead(PDO $pdo, int $qid, int $readerId): void
{
    $pdo->prepare('UPDATE health_messages SET read_at = ? WHERE question_id = ? AND sender_id <> ? AND read_at IS NULL')->execute([coachNow(), $qid, $readerId]);
}

/**
 * Đổi trạng thái câu hỏi theo healthNextStatus().
 *   $actor = 'asker' : $actorId là người hỏi, chỉ đụng câu có user_id = $actorId. $helpful (1/0/null) khi đóng.
 *   $actor = 'expert': $actorId là chuyên gia (vai trò EXPERT trong DB). Nhận: đúng chủ đề, không phải câu của mình,
 *                      đang bật nhận câu hỏi, chưa quá EXPERT_CLAIM_MAX câu. Trả lại / đóng: chỉ câu mình đã nhận.
 * Nhận câu hỏi dùng UPDATE ... WHERE status = 'OPEN' nên 2 chuyên gia bấm cùng lúc thì chỉ 1 người nhận được.
 * Trả về dòng câu hỏi sau khi đổi.
 */
function healthAction(PDO $pdo, string $actor, int $actorId, int $qid, string $action, ?int $helpful = null): array
{
    return coachTx($pdo, function () use ($pdo, $actor, $actorId, $qid, $action, $helpful) {
        $st = $pdo->prepare('SELECT * FROM health_questions WHERE id = ?' . coachForUpdate($pdo));   // audit:ok coachForUpdate() chỉ trả " FOR UPDATE" hoặc rỗng
        $st->execute([$qid]);
        $q = $st->fetch(PDO::FETCH_ASSOC);
        $notFound = new ExpertException('Không tìm thấy câu hỏi này.');
        if ($q === false) {
            throw $notFound;
        }
        $now = coachNow();
        if ($actor === 'asker') {
            if ((int)$q['user_id'] !== $actorId) {
                throw $notFound;
            }
        } else {
            $topics = expertTopicsOf($pdo, $actorId);
            if ($topics === null) {
                throw new ExpertException('Tài khoản này không còn là chuyên gia.');
            }
            if ($action === 'claim') {
                if ((int)$q['user_id'] === $actorId || !expertTopicMatches($topics, (string)$q['topic'])) {
                    throw $notFound;
                }
                $p = $pdo->prepare('SELECT accepting FROM expert_profiles WHERE user_id = ?');
                $p->execute([$actorId]);
                if ((int)$p->fetchColumn() !== 1) {
                    throw new ExpertException('Bạn đang tạm ngừng nhận câu hỏi. Bật lại trong hồ sơ chuyên gia.');
                }
                $c = $pdo->prepare("SELECT COUNT(*) FROM health_questions WHERE expert_id = ? AND status = 'CLAIMED'");
                $c->execute([$actorId]);
                if ((int)$c->fetchColumn() >= EXPERT_CLAIM_MAX) {
                    throw new ExpertException('Bạn đang trao đổi ' . EXPERT_CLAIM_MAX . ' câu hỏi. Đóng bớt rồi nhận thêm.');
                }
            } elseif ((int)($q['expert_id'] ?? 0) !== $actorId) {
                throw $notFound;
            }
        }
        $replied = false;
        if ($action === 'release') {
            $r = $pdo->prepare('SELECT COUNT(*) FROM health_messages WHERE question_id = ? AND sender_id = ?');
            $r->execute([$qid, $actorId]);
            $replied = (int)$r->fetchColumn() > 0;
        }
        $next = healthNextStatus((string)$q['status'], $actor, $action, $replied);
        if ($next === null) {
            throw new ExpertException($action === 'release' && $replied
                ? 'Bạn đã trả lời câu hỏi này nên không trả lại được. Hãy đóng câu hỏi khi trao đổi xong.'
                : 'Thao tác không còn phù hợp (câu hỏi đang ở trạng thái: ' . (HEALTH_STATUS_LABELS[$q['status']] ?? $q['status']) . '). Hãy tải lại trang.');
        }
        $title = notifExcerpt((string)$q['title'], 60);
        if ($action === 'claim') {
            $u = $pdo->prepare("UPDATE health_questions SET status = 'CLAIMED', expert_id = ?, claimed_at = ? WHERE id = ? AND status = 'OPEN'");
            $u->execute([$actorId, $now, $qid]);
            if ($u->rowCount() === 0) {
                throw new ExpertException('Chuyên gia khác vừa nhận câu hỏi này.');
            }
            notify($pdo, (int)$q['user_id'], 'expert.question', 'Chuyên gia đã nhận câu hỏi của bạn',
                notifName($pdo, $actorId) . ' đã nhận câu "' . $title . '" và sẽ trả lời sớm.', '/consult-question.php?id=' . $qid);
            $q['expert_id'] = $actorId;
        } elseif ($action === 'release') {
            $pdo->prepare("UPDATE health_questions SET status = 'OPEN', expert_id = NULL, claimed_at = NULL WHERE id = ?")->execute([$qid]);
            notify($pdo, (int)$q['user_id'], 'expert.question', 'Câu hỏi được chuyển lại hàng chờ',
                'Chuyên gia đã trả lại câu "' . $title . '" để chuyên gia phù hợp hơn nhận.', '/consult-question.php?id=' . $qid);
        } elseif ($action === 'cancel') {
            $pdo->prepare("UPDATE health_questions SET status = 'CANCELLED', closed_at = ?, closed_by = ? WHERE id = ?")->execute([$now, $actorId, $qid]);
        } else {   // close
            $h = ($actor === 'asker' && ($helpful === 0 || $helpful === 1)) ? $helpful : null;
            $pdo->prepare("UPDATE health_questions SET status = 'CLOSED', closed_at = ?, closed_by = ?, helpful = ? WHERE id = ?")->execute([$now, $actorId, $h, $qid]);
            if ($actor === 'asker') {
                notify($pdo, (int)$q['expert_id'], 'expert.question', 'Người hỏi đã đóng câu hỏi',
                    'Câu "' . $title . '"' . ($h === 1 ? ' được đánh giá hữu ích.' : '.'), '/expert/question.php?id=' . $qid);
            } else {
                notify($pdo, (int)$q['user_id'], 'expert.question', 'Chuyên gia đã đóng câu hỏi',
                    'Câu "' . $title . '" đã đóng. Bạn vẫn xem lại được câu trả lời; cần hỏi thêm thì gửi câu hỏi mới.', '/consult-question.php?id=' . $qid);
            }
        }
        $q['status'] = $next;
        return $q;
    });
}

/**
 * Gửi tin trong câu hỏi. Người hỏi: khi câu đang chờ (bổ sung thông tin) hoặc đang trao đổi.
 * Chuyên gia: chỉ câu mình đã nhận và đang trao đổi. Giới hạn nhịp gửi giống tin nhắn HLV (2 giây, 60 tin/giờ).
 */
function healthSendMessage(PDO $pdo, string $actor, int $senderId, int $qid, string $body): int
{
    if ($e = coachTextError($body, 'Tin nhắn', 2, HEALTH_MSG_MAX)) {
        throw new ExpertException($e);
    }
    $st = $pdo->prepare('SELECT id, user_id, expert_id, status, title FROM health_questions WHERE id = ?');
    $st->execute([$qid]);
    $q = $st->fetch(PDO::FETCH_ASSOC);
    if ($actor === 'asker') {
        if ($q === false || (int)$q['user_id'] !== $senderId) {
            throw new ExpertException('Không tìm thấy câu hỏi này.');
        }
        if (!in_array($q['status'], ['OPEN', 'CLAIMED'], true)) {
            throw new ExpertException('Câu hỏi đã đóng. Cần hỏi thêm, hãy gửi câu hỏi mới.');
        }
    } else {
        if ($q === false || (int)($q['expert_id'] ?? 0) !== $senderId || expertTopicsOf($pdo, $senderId) === null) {
            throw new ExpertException('Không tìm thấy câu hỏi này.');
        }
        if ($q['status'] !== 'CLAIMED') {
            throw new ExpertException('Câu hỏi đã đóng nên không gửi thêm được.');
        }
    }
    $r = $pdo->prepare('SELECT MAX(created_at), SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) FROM health_messages WHERE sender_id = ?');
    $r->execute([date('Y-m-d H:i:s', time() - 3600), $senderId]);
    [$last, $hour] = $r->fetch(PDO::FETCH_NUM);
    if ($e = coachMessageRateError($last ? time() - strtotime((string)$last) : null, (int)$hour)) {
        throw new ExpertException($e);
    }
    $pdo->prepare('INSERT INTO health_messages (question_id, sender_id, body, created_at) VALUES (?, ?, ?, ?)')->execute([$qid, $senderId, $body, coachNow()]);
    $id = (int)$pdo->lastInsertId();
    if ($actor === 'expert') {
        notify($pdo, (int)$q['user_id'], 'expert.reply', 'Chuyên gia đã trả lời', '"' . notifExcerpt((string)$q['title'], 60) . '": ' . notifExcerpt($body),
            '/consult-question.php?id=' . $qid, true);
    } elseif ($q['status'] === 'CLAIMED') {
        notify($pdo, (int)$q['expert_id'], 'expert.reply', 'Người hỏi gửi thêm', '"' . notifExcerpt((string)$q['title'], 60) . '": ' . notifExcerpt($body),
            '/expert/question.php?id=' . $qid, true);
    }
    return $id;
}

/** Số liệu cho bảng điều khiển chuyên gia: hàng chờ đúng chủ đề, đang trao đổi, tin chưa đọc. */
function expertCounts(PDO $pdo, int $expertId): array
{
    $mine = healthMine($pdo, $expertId);
    return [
        'pool'    => count(healthPool($pdo, $expertId)),
        'claimed' => count($mine['claimed']),
        'unread'  => array_sum(array_map(fn($q) => (int)$q['unread'], $mine['claimed'])),
    ];
}
