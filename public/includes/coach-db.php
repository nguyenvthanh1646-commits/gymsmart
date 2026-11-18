<?php
/**
 * includes/coach-db.php — truy vấn DB của phân hệ HLV (PDO, prepared statement).
 *
 * Quy ước:
 *   - Mọi $coachId / $clientId / $actorId truyền vào PHẢI lấy từ $_SESSION hoặc đã qua canCoachAccessClient()
 *     ở nơi gọi. Các hàm dưới vẫn tự kiểm tra lại quan hệ trong câu WHERE (phòng thủ hai lớp).
 *   - Lỗi nghiệp vụ (người dùng làm sai) ném CoachException với câu tiếng Việt để hiện ra trang.
 *     Lỗi khác (mất kết nối DB...) để nguyên exception, trang sẽ báo lỗi chung và ghi log.
 *   - Thời gian lấy từ PHP (coachNow()) chứ không dùng NOW() của MySQL, để test chạy được trên SQLite.
 *     Hai bên cùng múi giờ máy chủ nên không lệch.
 */

require_once __DIR__ . '/coach-rules.php';
require_once __DIR__ . '/notifications.php';   // chuông thông báo (A-44): notify() gọi ngay trong transaction của từng thao tác

/** Lỗi nghiệp vụ, câu báo lỗi hiện thẳng cho người dùng. */
class CoachException extends RuntimeException
{
}

/** Gửi tin quá nhanh / quá nhiều (API trả mã 429). */
class CoachRateLimitException extends CoachException
{
}

function coachNow(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * " FOR UPDATE" khi chạy MySQL/MariaDB: khóa dòng đang đọc tới hết transaction, để 2 yêu cầu cùng lúc
 * không cùng vượt qua một bước kiểm tra (ví dụ 2 người cùng lấy chỗ cuối cùng của HLV).
 * SQLite (dùng trong test) không có cú pháp này nhưng tự khóa cả database khi ghi, nên trả chuỗi rỗng.
 */
function coachForUpdate(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
}

/** Lỗi vi phạm UNIQUE (SQLSTATE 23000) — dùng để nhận ra "đã có yêu cầu đang mở" khi 2 lần bấm trùng nhau. */
function coachIsDuplicate(PDOException $e): bool
{
    return $e->getCode() === '23000' || strpos($e->getMessage(), 'UNIQUE') !== false;
}

/** Chạy $fn trong transaction; lỗi thì rollBack rồi ném tiếp. */
function coachTx(PDO $pdo, callable $fn)
{
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Database đã chạy sql/coach_features.sql chưa. Các trang dùng chung (lịch tập, dinh dưỡng, trang chủ)
 * gọi hàm này trước khi đọc bảng coach_*, để máy chưa import file đó vẫn chạy bình thường.
 */
function coachTablesReady(PDO $pdo): bool
{
    static $ready = [];
    $key = spl_object_id($pdo);
    if (!isset($ready[$key])) {
        try {
            $pdo->query('SELECT 1 FROM coach_clients WHERE 1 = 0');
            $ready[$key] = true;
        } catch (PDOException $e) {
            $ready[$key] = false;
        }
    }
    return $ready[$key];
}

function coachUserRole(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $role = $stmt->fetchColumn();
    return $role === false ? null : (string)$role;
}

// =====================================================================
// 1. QUYỀN TRUY CẬP: HLV chỉ thấy học viên đang ACTIVE với mình
// =====================================================================

/**
 * Trả về id quan hệ nếu $coachId đang là HLV (role COACH trong DB) và đang ACTIVE với $clientId; ngược lại null.
 * Kiểm tra role ngay trong câu SQL: ADMIN thu hồi quyền HLV là mất quyền xem ngay, kể cả quan hệ chưa kịp đóng.
 */
function coachActiveRelationId(PDO $pdo, int $coachId, int $clientId): ?int
{
    if ($coachId < 1 || $clientId < 1 || $coachId === $clientId) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT cc.id FROM coach_clients cc
         JOIN users u ON u.id = cc.coach_id AND u.role = 'COACH'
         WHERE cc.coach_id = ? AND cc.client_id = ? AND cc.status = 'ACTIVE'
         LIMIT 1"
    );
    $stmt->execute([$coachId, $clientId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

function canCoachAccessClient(PDO $pdo, int $coachId, int $clientId): bool
{
    return coachActiveRelationId($pdo, $coachId, $clientId) !== null;
}

// =====================================================================
// 2. ĐƠN ĐĂNG KÝ LÀM HLV
// =====================================================================

/** Đơn mới nhất của người dùng (để trang đăng ký hiện trạng thái), null nếu chưa nộp lần nào. */
function coachLatestApplication(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM coach_applications WHERE user_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/** Quan hệ đang mở (PENDING/ACTIVE) mà người này là HỌC VIÊN, kèm tên HLV. null nếu không có. */
function coachOpenRelationForClient(PDO $pdo, int $clientId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT cc.id, cc.coach_id, cc.status, cc.request_note, cc.created_at, cc.accepted_at, u.full_name AS coach_name, u.avatar AS coach_avatar
         FROM coach_clients cc JOIN users u ON u.id = cc.coach_id
         WHERE cc.client_id = ? AND cc.status IN ('PENDING', 'ACTIVE')
         ORDER BY cc.id DESC LIMIT 1"
    );
    $stmt->execute([$clientId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/**
 * Nộp đơn. Chỉ USER được nộp (ADMIN, COACH thì không); đang có HLV hoặc đang chờ HLV duyệt thì phải
 * kết thúc trước (một người không vừa là học viên vừa là HLV). Mỗi người chỉ 1 đơn đang chờ.
 */
function coachSubmitApplication(PDO $pdo, int $userId, array $data): int
{
    $role = coachUserRole($pdo, $userId);
    if ($role !== 'USER') {
        throw new CoachException($role === 'COACH' ? 'Bạn đã là huấn luyện viên.' : 'Tài khoản này không thể đăng ký làm huấn luyện viên.');
    }
    if (coachOpenRelationForClient($pdo, $userId) !== null) {
        throw new CoachException('Bạn đang có (hoặc đang chờ) huấn luyện viên. Hãy kết thúc trước khi đăng ký làm HLV.');
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO coach_applications
             (user_id, headline, specialties, experience_years, certifications, bio, approach, max_clients, availability, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)"
        );
        $stmt->execute([
            $userId, $data['headline'], $data['specialties'], $data['experience_years'], $data['certifications'],
            $data['bio'], $data['approach'], $data['max_clients'], $data['availability'], coachNow(),
        ]);
        $newId = (int)$pdo->lastInsertId();
        // File chứng chỉ (nếu có) ghi sau, vào cột thêm bởi sql/coach_extras.sql
        if (!empty($data['certificate_file'])) {
            $pdo->prepare('UPDATE coach_applications SET certificate_file = ? WHERE id = ?')->execute([$data['certificate_file'], $newId]);
        }
    } catch (PDOException $e) {
        if (coachIsDuplicate($e)) {
            throw new CoachException('Bạn đã có một đơn đang chờ duyệt.');
        }
        throw $e;
    }
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'ADMIN'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        notify($pdo, (int)$adminId, 'admin.coach_application', 'Đơn đăng ký HLV mới',
            notifName($pdo, $userId) . ': ' . notifExcerpt((string)$data['headline']), '/admin/coach-applications.php');   // mỗi đơn một thông báo
    }
    return $newId;
}

/** Người nộp tự rút đơn đang chờ (chuyển thành REJECTED kèm ghi chú). */
function coachWithdrawApplication(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        "UPDATE coach_applications SET status = 'REJECTED', admin_note = ?, reviewed_at = ?
         WHERE user_id = ? AND status = 'PENDING'"
    );
    $stmt->execute(['Người nộp tự rút đơn.', coachNow(), $userId]);
    if ($stmt->rowCount() === 0) {
        throw new CoachException('Không có đơn nào đang chờ duyệt.');
    }
}

/** Danh sách đơn cho admin. $status = '' lấy tất cả. Không lấy email (admin xem email ở trang người dùng). */
function coachListApplications(PDO $pdo, string $status = ''): array
{
    $sql = 'SELECT a.id, a.user_id, u.full_name, u.role, a.headline, a.specialties, a.experience_years, a.status, a.created_at, a.reviewed_at
            FROM coach_applications a JOIN users u ON u.id = a.user_id';
    $params = [];
    if (isset(COACH_APPLICATION_LABELS[$status])) {
        $sql .= ' WHERE a.status = ?';
        $params[] = $status;
    }
    $sql .= " ORDER BY CASE WHEN a.status = 'PENDING' THEN 0 ELSE 1 END, a.id DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function coachFindApplication(PDO $pdo, int $appId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT a.*, u.full_name, u.email, u.role, u.created_at AS user_created_at, r.full_name AS reviewer_name
         FROM coach_applications a
         JOIN users u ON u.id = a.user_id
         LEFT JOIN users r ON r.id = a.reviewed_by
         WHERE a.id = ?'
    );
    $stmt->execute([$appId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/**
 * ADMIN duyệt đơn: trong 1 transaction đổi vai trò USER → COACH, tạo/cập nhật hồ sơ HLV từ đơn, đánh dấu đơn APPROVED.
 * Trả về tên người được duyệt (để ghi nhật ký).
 */
function coachApproveApplication(PDO $pdo, int $appId, int $adminId, string $note): string
{
    return coachTx($pdo, function () use ($pdo, $appId, $adminId, $note) {
        $stmt = $pdo->prepare(
            'SELECT a.*, u.full_name, u.role FROM coach_applications a JOIN users u ON u.id = a.user_id WHERE a.id = ?' . coachForUpdate($pdo)
        );
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($app === false || $app['status'] !== 'PENDING') {
            throw new CoachException('Đơn không tồn tại hoặc đã được xử lý.');
        }
        if ($app['role'] !== 'USER') {
            throw new CoachException('Chỉ duyệt được cho tài khoản USER (tài khoản này đang là ' . $app['role'] . ').');
        }
        $userId = (int)$app['user_id'];
        if (coachOpenRelationForClient($pdo, $userId) !== null) {
            throw new CoachException('Người này đang là học viên của một HLV. Họ cần kết thúc trước khi được duyệt.');
        }

        $now = coachNow();
        $pdo->prepare("UPDATE coach_applications SET status = 'APPROVED', admin_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?")
            ->execute([$note, $adminId, $now, $appId]);
        $pdo->prepare("UPDATE users SET role = 'COACH' WHERE id = ?")->execute([$userId]);

        $fields = [$app['headline'], $app['specialties'], $app['experience_years'], $app['certifications'],
                   $app['bio'], $app['approach'], $app['max_clients'], $app['availability']];
        $exists = $pdo->prepare('SELECT COUNT(*) FROM coach_profiles WHERE user_id = ?');
        $exists->execute([$userId]);
        if ((int)$exists->fetchColumn() > 0) {
            // Từng là HLV, bị thu hồi rồi nộp lại: ghi đè hồ sơ cũ bằng thông tin trong đơn mới
            $pdo->prepare(
                'UPDATE coach_profiles SET headline = ?, specialties = ?, experience_years = ?, certifications = ?, bio = ?,
                        approach = ?, max_clients = ?, availability = ?, accepting = 1, updated_at = ? WHERE user_id = ?'
            )->execute(array_merge($fields, [$now, $userId]));
        } else {
            $pdo->prepare(
                'INSERT INTO coach_profiles (user_id, headline, specialties, experience_years, certifications, bio, approach,
                                             max_clients, availability, accepting, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
            )->execute(array_merge([$userId], $fields, [$now, $now]));
        }
        notify($pdo, $userId, 'coach.application_approved', 'Đơn đăng ký HLV đã được duyệt',
            'Bạn đã là huấn luyện viên. Vào khu HLV để kiểm tra hồ sơ và bắt đầu nhận học viên.', '/coach/dashboard.php');
        return (string)$app['full_name'];
    });
}

/** ADMIN từ chối đơn; $note (lý do) bắt buộc để người nộp biết cần bổ sung gì. Trả về tên người nộp. */
function coachRejectApplication(PDO $pdo, int $appId, int $adminId, string $note): string
{
    if ($note === '') {
        throw new CoachException('Hãy ghi lý do từ chối để người nộp biết cần bổ sung gì.');
    }
    $app = coachFindApplication($pdo, $appId);
    if ($app === null || $app['status'] !== 'PENDING') {
        throw new CoachException('Đơn không tồn tại hoặc đã được xử lý.');
    }
    $stmt = $pdo->prepare(
        "UPDATE coach_applications SET status = 'REJECTED', admin_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ? AND status = 'PENDING'"
    );
    $stmt->execute([$note, $adminId, coachNow(), $appId]);
    if ($stmt->rowCount() === 0) {
        throw new CoachException('Đơn đã được xử lý ở nơi khác.');
    }
    notify($pdo, (int)$app['user_id'], 'coach.application_rejected', 'Đơn đăng ký HLV chưa được duyệt',
        'Lý do: ' . notifExcerpt($note, 200) . ' Bạn có thể sửa và nộp lại.', '/coach-apply.php');
    return (string)$app['full_name'];
}

/**
 * ADMIN thu hồi vai trò HLV. Trong 1 transaction: quan hệ ACTIVE → ENDED, PENDING → REJECTED, yêu cầu sửa đang chờ → CANCELLED,
 * tắt nhận học viên, vai trò về USER. Lịch tập/mục tiêu HLV đã giao vẫn ở lại với học viên.
 * Trả về ['name' => tên HLV, 'ended' => số học viên bị kết thúc, 'rejected' => số yêu cầu bị từ chối].
 */
function coachRevoke(PDO $pdo, int $coachId, int $adminId): array
{
    return coachTx($pdo, function () use ($pdo, $coachId, $adminId) {
        $stmt = $pdo->prepare('SELECT full_name, role FROM users WHERE id = ?' . coachForUpdate($pdo));   // audit:ok coachForUpdate() chỉ trả " FOR UPDATE" hoặc rỗng
        $stmt->execute([$coachId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user === false || $user['role'] !== 'COACH') {
            throw new CoachException('Tài khoản này không phải huấn luyện viên.');
        }
        $now = coachNow();
        $affected = $pdo->prepare("SELECT client_id, status FROM coach_clients WHERE coach_id = ? AND status IN ('ACTIVE', 'PENDING')");
        $affected->execute([$coachId]);
        foreach ($affected->fetchAll(PDO::FETCH_ASSOC) as $a) {
            notify($pdo, (int)$a['client_id'], 'coach.relation', $a['status'] === 'ACTIVE' ? 'Quan hệ huấn luyện đã kết thúc' : 'Yêu cầu tới HLV không còn hiệu lực',
                'HLV ' . $user['full_name'] . ' không còn là huấn luyện viên trên GymSmart. Lịch và mục tiêu đã giao vẫn được giữ, bạn có thể chọn HLV khác.', '/coaches.php');
        }
        notify($pdo, $coachId, 'coach.revoked', 'Vai trò huấn luyện viên đã bị thu hồi',
            'Quản trị viên đã thu hồi vai trò HLV của bạn. Liên hệ quản trị viên nếu cần biết thêm.', '/index.php');
        $pdo->prepare(
            "UPDATE coach_change_requests SET status = 'CANCELLED', resolved_at = ?
             WHERE status = 'PENDING' AND coach_client_id IN (SELECT id FROM coach_clients WHERE coach_id = ? AND status = 'ACTIVE')"
        )->execute([$now, $coachId]);
        $end = $pdo->prepare("UPDATE coach_clients SET status = 'ENDED', ended_at = ?, ended_by = ? WHERE coach_id = ? AND status = 'ACTIVE'");
        $end->execute([$now, $adminId, $coachId]);
        $rej = $pdo->prepare("UPDATE coach_clients SET status = 'REJECTED', ended_at = ?, ended_by = ? WHERE coach_id = ? AND status = 'PENDING'");
        $rej->execute([$now, $adminId, $coachId]);
        $pdo->prepare('UPDATE coach_profiles SET accepting = 0 WHERE user_id = ?')->execute([$coachId]);
        $pdo->prepare("UPDATE users SET role = 'USER' WHERE id = ?")->execute([$coachId]);
        return ['name' => (string)$user['full_name'], 'ended' => $end->rowCount(), 'rejected' => $rej->rowCount()];
    });
}

/** Danh sách HLV cho trang admin: số học viên đang có, số yêu cầu chờ. */
function coachListForAdmin(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT u.id, u.full_name, u.email, p.headline, p.specialties, p.experience_years, p.max_clients, p.accepting,
                (SELECT COUNT(*) FROM coach_clients c WHERE c.coach_id = u.id AND c.status = 'ACTIVE') AS active_clients,
                (SELECT COUNT(*) FROM coach_clients c WHERE c.coach_id = u.id AND c.status = 'PENDING') AS pending_clients
         FROM users u LEFT JOIN coach_profiles p ON p.user_id = u.id
         WHERE u.role = ? ORDER BY u.full_name"
    );
    $stmt->execute(['COACH']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Số liệu cho trang tổng quan/thống kê admin. Chưa import bảng thì trả số 0. */
function coachAdminCounts(PDO $pdo): array
{
    $out = ['coaches' => 0, 'active_pairs' => 0, 'pending_apps' => 0];
    if (!coachTablesReady($pdo)) {
        return $out;
    }
    $stmt = $pdo->prepare(
        "SELECT (SELECT COUNT(*) FROM users WHERE role = 'COACH'),
                (SELECT COUNT(*) FROM coach_clients WHERE status = 'ACTIVE'),
                (SELECT COUNT(*) FROM coach_applications WHERE status = 'PENDING')"
    );
    $stmt->execute();
    [$out['coaches'], $out['active_pairs'], $out['pending_apps']] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));
    return $out;
}

// =====================================================================
// 3. HỒ SƠ HLV
// =====================================================================

function coachProfile(PDO $pdo, int $coachId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT p.*, u.full_name, u.avatar,
                (SELECT COUNT(*) FROM coach_clients c WHERE c.coach_id = p.user_id AND c.status = 'ACTIVE') AS active_clients
         FROM coach_profiles p JOIN users u ON u.id = p.user_id
         WHERE p.user_id = ?"
    );
    $stmt->execute([$coachId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/** HLV tự sửa hồ sơ ($data đã qua validateCoachApplication($in, false)). */
function coachSaveProfile(PDO $pdo, int $coachId, array $data, bool $accepting): void
{
    $stmt = $pdo->prepare(
        'UPDATE coach_profiles SET headline = ?, specialties = ?, experience_years = ?, certifications = ?, bio = ?,
                approach = ?, max_clients = ?, availability = ?, accepting = ?, updated_at = ?
         WHERE user_id = ?'
    );
    $stmt->execute([$data['headline'], $data['specialties'], $data['experience_years'], $data['certifications'], $data['bio'],
                    $data['approach'], $data['max_clients'], $data['availability'], $accepting ? 1 : 0, coachNow(), $coachId]);
}

/** HLV cho học viên chọn: mọi tài khoản COACH có hồ sơ, kèm số chỗ còn. HLV không nhận học viên vẫn hiện (nút bị khóa). */
function coachListPublic(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT u.id, u.full_name, u.avatar, p.headline, p.specialties, p.experience_years, p.certifications, p.bio,
                p.approach, p.availability, p.max_clients, p.accepting,
                (SELECT COUNT(*) FROM coach_clients c WHERE c.coach_id = u.id AND c.status = 'ACTIVE') AS active_clients
         FROM users u JOIN coach_profiles p ON p.user_id = u.id
         WHERE u.role = ?
         ORDER BY p.accepting DESC, u.full_name"
    );
    $stmt->execute(['COACH']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['seats_left'] = coachSeatsLeft((int)$r['max_clients'], (int)$r['active_clients']);
        $r['can_request'] = (int)$r['accepting'] === 1 && $r['seats_left'] > 0;
    }
    return $rows;
}

// =====================================================================
// 4. QUAN HỆ HLV - HỌC VIÊN
// =====================================================================

/** Học viên gửi yêu cầu tới HLV. Mỗi học viên chỉ có 1 HLV (đang chờ hoặc đang tập). */
function coachRequest(PDO $pdo, int $clientId, int $coachId, string $note): int
{
    if ($clientId === $coachId) {
        throw new CoachException('Bạn không thể chọn chính mình làm HLV.');
    }
    if (coachUserRole($pdo, $clientId) !== 'USER') {
        throw new CoachException('Chỉ tài khoản người tập mới gửi yêu cầu tới HLV được.');
    }
    if ($e = coachTextError($note, 'Lời nhắn', 0, COACH_NOTE_MAX)) {
        throw new CoachException($e);
    }
    $coach = coachProfile($pdo, $coachId);
    if ($coach === null || coachUserRole($pdo, $coachId) !== 'COACH') {
        throw new CoachException('Không tìm thấy huấn luyện viên này.');
    }
    if ((int)$coach['accepting'] !== 1) {
        throw new CoachException('HLV này đang tạm ngừng nhận học viên mới.');
    }
    if (coachSeatsLeft((int)$coach['max_clients'], (int)$coach['active_clients']) === 0) {
        throw new CoachException('HLV này đã đủ học viên.');
    }
    if (coachOpenRelationForClient($pdo, $clientId) !== null) {
        throw new CoachException('Bạn đã có HLV hoặc đang chờ một HLV duyệt. Mỗi học viên chỉ đăng ký 1 HLV.');
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO coach_clients (coach_id, client_id, status, request_note, created_at) VALUES (?, ?, 'PENDING', ?, ?)");
        $stmt->execute([$coachId, $clientId, $note, coachNow()]);
    } catch (PDOException $e) {
        if (coachIsDuplicate($e)) {   // 2 lần bấm gần như cùng lúc: UNIQUE(open_client_id) trong DB chặn lần thứ hai
            throw new CoachException('Bạn đã có HLV hoặc đang chờ một HLV duyệt. Mỗi học viên chỉ đăng ký 1 HLV.');
        }
        throw $e;
    }
    $newId = (int)$pdo->lastInsertId();
    notify($pdo, $coachId, 'coach.request', 'Yêu cầu học viên mới',
        notifName($pdo, $clientId) . ' muốn tập với bạn' . ($note !== '' ? ': ' . notifExcerpt($note) : '.'), '/coach/dashboard.php');
    return $newId;
}

/**
 * Đổi trạng thái quan hệ theo máy trạng thái coachNextStatus().
 *   $actor = 'client': $actorId là học viên, chỉ đụng tới quan hệ có client_id = $actorId
 *   $actor = 'coach' : $actorId là HLV,     chỉ đụng tới quan hệ có coach_id  = $actorId
 * Duyệt (accept) thì khóa hồ sơ HLV và đếm lại số chỗ trong transaction.
 * Trả về ['id','client_id','coach_id','client_name','status'] của quan hệ sau khi đổi.
 */
function coachChangeRelation(PDO $pdo, string $actor, int $actorId, int $relationId, string $action): array
{
    return coachTx($pdo, function () use ($pdo, $actor, $actorId, $relationId, $action) {
        $col = $actor === 'coach' ? 'coach_id' : 'client_id';   // audit:ok chỉ 1 trong 2 tên cột viết sẵn
        $stmt = $pdo->prepare(
            "SELECT cc.id, cc.coach_id, cc.client_id, cc.status, u.full_name AS client_name
             FROM coach_clients cc JOIN users u ON u.id = cc.client_id
             WHERE cc.id = ? AND cc.$col = ?" . coachForUpdate($pdo)   // audit:ok $col chỉ là coach_id hoặc client_id viết sẵn
        );
        $stmt->execute([$relationId, $actorId]);
        $rel = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rel === false) {
            throw new CoachException('Không tìm thấy yêu cầu này.');
        }
        $next = coachNextStatus($rel['status'], $actor, $action);
        if ($next === null) {
            throw new CoachException('Thao tác không còn phù hợp (trạng thái hiện tại: ' . (COACH_RELATION_LABELS[$rel['status']] ?? $rel['status']) . '). Hãy tải lại trang.');
        }
        $now = coachNow();
        if ($action === 'accept') {
            $p = $pdo->prepare('SELECT max_clients FROM coach_profiles WHERE user_id = ?' . coachForUpdate($pdo));   // audit:ok coachForUpdate() chỉ trả " FOR UPDATE" hoặc rỗng
            $p->execute([$rel['coach_id']]);
            $max = (int)$p->fetchColumn();
            $c = $pdo->prepare("SELECT COUNT(*) FROM coach_clients WHERE coach_id = ? AND status = 'ACTIVE'");
            $c->execute([$rel['coach_id']]);
            if (coachSeatsLeft($max, (int)$c->fetchColumn()) === 0) {
                throw new CoachException('Bạn đã đủ ' . $max . ' học viên. Tăng số học viên tối đa trong hồ sơ hoặc kết thúc bớt quan hệ.');
            }
            $pdo->prepare("UPDATE coach_clients SET status = 'ACTIVE', accepted_at = ? WHERE id = ?")->execute([$now, $relationId]);
        } else {
            $pdo->prepare('UPDATE coach_clients SET status = ?, ended_at = ?, ended_by = ? WHERE id = ?')
                ->execute([$next, $now, $actorId, $relationId]);
            // Kết thúc quan hệ thì các yêu cầu xin sửa còn chờ cũng không còn ý nghĩa
            $pdo->prepare("UPDATE coach_change_requests SET status = 'CANCELLED', resolved_at = ? WHERE coach_client_id = ? AND status = 'PENDING'")
                ->execute([$now, $relationId]);
        }
        $rel['status'] = $next;
        // Báo cho bên còn lại: HLV thao tác thì báo học viên, học viên thao tác thì báo HLV
        if ($actor === 'coach') {
            $coachName = notifName($pdo, (int)$rel['coach_id']);
            $texts = ['ACTIVE' => ['HLV đã nhận bạn làm học viên', "HLV $coachName đã duyệt yêu cầu. Từ giờ HLV sẽ giao lịch tập và mục tiêu dinh dưỡng cho bạn."],
                      'REJECTED' => ['Yêu cầu tới HLV chưa được nhận', "HLV $coachName chưa nhận thêm học viên lúc này. Bạn có thể chọn HLV khác."],
                      'ENDED' => ['Quan hệ huấn luyện đã kết thúc', "HLV $coachName đã kết thúc quan hệ huấn luyện. Lịch và mục tiêu đã giao vẫn được giữ."]];
            if (isset($texts[$next])) {
                notify($pdo, (int)$rel['client_id'], 'coach.relation', $texts[$next][0], $texts[$next][1], '/my-coach.php');
            }
        } else {
            $verb = ['ENDED' => 'đã kết thúc tập với bạn.', 'CANCELLED' => 'đã hủy yêu cầu tập với bạn.', 'REJECTED' => 'đã hủy yêu cầu tập với bạn.'];
            if (isset($verb[$next])) {
                notify($pdo, (int)$rel['coach_id'], 'coach.relation', 'Học viên thay đổi', $rel['client_name'] . ' ' . $verb[$next], '/coach/clients.php');
            }
        }
        return $rel;
    });
}

/** Thông tin cơ bản của học viên cho HLV xem. KHÔNG lấy email, mật khẩu. */
function coachClientBasics(PDO $pdo, int $clientId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, full_name, avatar, age, gender, height_cm, weight_kg, activity_level, experience_level, goal
         FROM users WHERE id = ?'
    );
    $stmt->execute([$clientId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/** Yêu cầu đang chờ HLV duyệt, kèm vài thông tin để HLV quyết định (không có email). */
function coachPendingRequests(PDO $pdo, int $coachId): array
{
    $stmt = $pdo->prepare(
        "SELECT cc.id, cc.request_note, cc.created_at, u.id AS client_id, u.full_name, u.avatar, u.age, u.gender,
                u.experience_level, u.goal
         FROM coach_clients cc JOIN users u ON u.id = cc.client_id
         WHERE cc.coach_id = ? AND cc.status = 'PENDING'
         ORDER BY cc.created_at ASC"
    );
    $stmt->execute([$coachId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Học viên đang ACTIVE của HLV, kèm ngày tập gần nhất và số tin chưa đọc. */
function coachActiveClients(PDO $pdo, int $coachId): array
{
    $stmt = $pdo->prepare(
        "SELECT cc.id AS relation_id, cc.accepted_at, u.id AS client_id, u.full_name, u.avatar, u.goal, u.experience_level,
                (SELECT MAX(l.log_date) FROM workout_logs l WHERE l.user_id = u.id) AS last_log,
                (SELECT COUNT(*) FROM coach_messages m WHERE m.coach_client_id = cc.id AND m.sender_id <> ? AND m.read_at IS NULL) AS unread,
                (SELECT COUNT(*) FROM coach_change_requests r WHERE r.coach_client_id = cc.id AND r.status = 'PENDING') AS change_requests
         FROM coach_clients cc JOIN users u ON u.id = cc.client_id
         WHERE cc.coach_id = ? AND cc.status = 'ACTIVE'
         ORDER BY u.full_name"
    );
    $stmt->execute([$coachId, $coachId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 4 con số trên bảng điều khiển HLV. $today dạng Y-m-d (truyền vào để test được).
 * "Chưa tập 7 ngày": học viên ACTIVE không có dòng workout_logs nào từ ($today - 6 ngày) tới nay.
 */
function coachDashboardCounts(PDO $pdo, int $coachId, string $today): array
{
    $since = date('Y-m-d', strtotime($today . ' -6 days'));
    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM coach_clients WHERE coach_id = ? AND status = 'ACTIVE'),
            (SELECT COUNT(*) FROM coach_clients WHERE coach_id = ? AND status = 'PENDING'),
            (SELECT COUNT(*) FROM coach_clients cc WHERE cc.coach_id = ? AND cc.status = 'ACTIVE'
                AND NOT EXISTS (SELECT 1 FROM workout_logs l WHERE l.user_id = cc.client_id AND l.log_date >= ?)),
            (SELECT COUNT(*) FROM coach_messages m JOIN coach_clients cc ON cc.id = m.coach_client_id
                WHERE cc.coach_id = ? AND cc.status = 'ACTIVE' AND m.sender_id <> ? AND m.read_at IS NULL),
            (SELECT COUNT(*) FROM coach_change_requests r JOIN coach_clients cc ON cc.id = r.coach_client_id
                WHERE cc.coach_id = ? AND cc.status = 'ACTIVE' AND r.status = 'PENDING')"
    );
    $stmt->execute([$coachId, $coachId, $coachId, $since, $coachId, $coachId, $coachId]);
    $n = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));
    return ['clients' => $n[0], 'pending' => $n[1], 'inactive7' => $n[2], 'unread' => $n[3], 'change_requests' => $n[4]];
}

/** Nhật ký tập của học viên trong $days ngày gần nhất, nhóm theo ngày: [log_date => [dòng...]]. */
function coachClientLogs(PDO $pdo, int $clientId, string $today, int $days = 30): array
{
    $since = date('Y-m-d', strtotime($today . ' -' . ($days - 1) . ' days'));
    $stmt = $pdo->prepare(
        'SELECT l.id, l.log_date, l.sets_completed, l.reps_completed, l.weight_kg_used, l.note, e.name
         FROM workout_logs l JOIN exercises e ON e.id = l.exercise_id
         WHERE l.user_id = ? AND l.log_date >= ?
         ORDER BY l.log_date DESC, l.id ASC'
    );
    $stmt->execute([$clientId, $since]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['log_date']][] = $r;
    }
    return $out;
}

/** Mục tiêu dinh dưỡng mới nhất của học viên (do ai đặt cũng được), kèm tên người giao nếu có. */
function coachLatestNutrition(PDO $pdo, int $clientId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT n.tdee_kcal, n.target_kcal, n.protein_g, n.carbs_g, n.fat_g, n.calculated_date, n.assigned_by, u.full_name AS assigned_name
         FROM nutrition_targets n LEFT JOIN users u ON u.id = n.assigned_by
         WHERE n.user_id = ? ORDER BY n.calculated_date DESC, n.id DESC LIMIT 1'
    );
    $stmt->execute([$clientId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

// =====================================================================
// 5. GIAO LỊCH TẬP, ĐẶT MỤC TIÊU DINH DƯỠNG
// =====================================================================

/** Ghi tên HLV vào lịch ACTIVE của học viên (sau khi HLV tạo mới, tiếp quản hoặc sửa lịch). */
function coachMarkPlanAssigned(PDO $pdo, int $coachId, int $clientId): int
{
    $stmt = $pdo->prepare("UPDATE workout_plans SET assigned_by = ? WHERE user_id = ? AND status = 'ACTIVE'");
    $stmt->execute([$coachId, $clientId]);
    if ($stmt->rowCount() > 0) {
        notify($pdo, $clientId, 'coach.plan', 'HLV đã giao lịch tập mới', 'HLV ' . notifName($pdo, $coachId) . ' vừa giao lịch tập cho bạn.', '/workout-plan.php', true);
    }
    return $stmt->rowCount();
}

/** Ai giao lịch ACTIVE của người dùng: ['id','full_name'] của HLV, hoặc null (tự tạo / chưa có lịch / chưa import bảng HLV). */
function coachPlanAssigner(PDO $pdo, int $userId): ?array
{
    if (!coachTablesReady($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT u.id, u.full_name FROM workout_plans wp JOIN users u ON u.id = wp.assigned_by
         WHERE wp.user_id = ? AND wp.status = 'ACTIVE' ORDER BY wp.id DESC LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/**
 * Chặn API tự sửa lịch của người dùng khi đang có HLV quản lý. Trả về câu báo lỗi, hoặc null nếu được sửa.
 * Dùng ở api/workout-plan-generate.php và api/workout-plan-edit.php (kiểm tra ở máy chủ, không chỉ ẩn nút).
 */
function coachPlanLockError(PDO $pdo, int $userId): ?string
{
    $state = coachSelfEditState($pdo, $userId, 'PLAN');
    return $state['locked']
        ? 'Lịch tập đang do HLV ' . $state['coach_name'] . ' quản lý. Hãy gửi yêu cầu sửa tới HLV ở trang HLV của tôi.'
        : null;
}

/**
 * HLV đặt mục tiêu dinh dưỡng: thêm dòng nutrition_targets mới (assigned_by = HLV), giữ lịch sử các lần trước.
 * $tdee là TDEE tính từ hồ sơ học viên (tham khảo), $macros đã qua validateCoachMacros().
 */
function coachSaveNutrition(PDO $pdo, int $coachId, int $clientId, float $tdee, int $kcal, int $protein, int $carbs, int $fat, string $today): int
{
    // Nếu hôm nay HLV đã đặt rồi thì cập nhật dòng đó (giống trang Dinh dưỡng: mỗi ngày 1 dòng mỗi nguồn)
    $find = $pdo->prepare('SELECT id FROM nutrition_targets WHERE user_id = ? AND calculated_date = ? AND assigned_by IS NOT NULL');
    $find->execute([$clientId, $today]);
    $id = $find->fetchColumn();
    if ($id !== false) {
        $pdo->prepare('UPDATE nutrition_targets SET tdee_kcal = ?, target_kcal = ?, protein_g = ?, carbs_g = ?, fat_g = ?, assigned_by = ? WHERE id = ?')
            ->execute([$tdee, $kcal, $protein, $carbs, $fat, $coachId, $id]);
        notify($pdo, $clientId, 'coach.nutrition', 'HLV đã đặt mục tiêu dinh dưỡng',
            "Mục tiêu mới: $kcal kcal, đạm $protein g, tinh bột $carbs g, béo $fat g mỗi ngày.", '/nutrition.php', true);
        return (int)$id;
    }
    $pdo->prepare(
        'INSERT INTO nutrition_targets (user_id, tdee_kcal, target_kcal, protein_g, carbs_g, fat_g, calculated_date, assigned_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$clientId, $tdee, $kcal, $protein, $carbs, $fat, $today, $coachId]);
    $newId = (int)$pdo->lastInsertId();
    notify($pdo, $clientId, 'coach.nutrition', 'HLV đã đặt mục tiêu dinh dưỡng',
        "Mục tiêu mới: $kcal kcal, đạm $protein g, tinh bột $carbs g, béo $fat g mỗi ngày.", '/nutrition.php', true);
    return $newId;
}

/** Mục tiêu HLV đang giao cho học viên: dòng mới nhất có assigned_by = HLV hiện tại. null nếu chưa đặt. */
function coachAssignedNutrition(PDO $pdo, int $clientId, int $coachId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT tdee_kcal, target_kcal, protein_g, carbs_g, fat_g, calculated_date FROM nutrition_targets
         WHERE user_id = ? AND assigned_by = ? ORDER BY calculated_date DESC, id DESC LIMIT 1'
    );
    $stmt->execute([$clientId, $coachId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

// =====================================================================
// 6. KHÓA TỰ SỬA KHI ĐANG CÓ HLV + YÊU CẦU XIN SỬA
// =====================================================================

/**
 * Học viên có được tự sửa phần $area ('PLAN' | 'NUTRITION') không.
 * Đang có HLV ACTIVE thì bị khóa, trừ khi HLV đã duyệt một yêu cầu sửa còn hạn (unlock_until > bây giờ).
 * Trả về ['locked' => bool, 'relation_id', 'coach_id', 'coach_name', 'unlock_until', 'pending' => có yêu cầu đang chờ không].
 * Chưa import bảng HLV hoặc không có HLV → không khóa.
 */
function coachSelfEditState(PDO $pdo, int $clientId, string $area): array
{
    $state = ['locked' => false, 'relation_id' => null, 'coach_id' => null, 'coach_name' => null, 'unlock_until' => null, 'pending' => false];
    if (!isset(COACH_CHANGE_AREAS[$area]) || !coachTablesReady($pdo)) {
        return $state;
    }
    $rel = coachOpenRelationForClient($pdo, $clientId);
    if ($rel === null || $rel['status'] !== 'ACTIVE') {
        return $state;
    }
    $state['relation_id'] = (int)$rel['id'];
    $state['coach_id'] = (int)$rel['coach_id'];
    $state['coach_name'] = $rel['coach_name'];

    $stmt = $pdo->prepare(
        "SELECT MAX(CASE WHEN status = 'APPROVED' AND unlock_until > ? THEN unlock_until END) AS unlock_until,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending
         FROM coach_change_requests WHERE coach_client_id = ? AND area = ?"
    );
    $stmt->execute([coachNow(), $rel['id'], $area]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $state['unlock_until'] = $r['unlock_until'] ?? null;
    $state['pending'] = (int)($r['pending'] ?? 0) > 0;
    $state['locked'] = $state['unlock_until'] === null;
    return $state;
}

/** Học viên gửi yêu cầu xin tự sửa lịch tập/dinh dưỡng tới HLV hiện tại. */
function coachRequestChange(PDO $pdo, int $clientId, string $area, string $reason): int
{
    if (!isset(COACH_CHANGE_AREAS[$area])) {
        throw new CoachException('Phần cần sửa không hợp lệ.');
    }
    if ($e = coachTextError($reason, 'Lý do', 5, COACH_NOTE_MAX)) {
        throw new CoachException($e);
    }
    $state = coachSelfEditState($pdo, $clientId, $area);
    if ($state['relation_id'] === null) {
        throw new CoachException('Bạn chưa có HLV nên được tự sửa, không cần xin phép.');
    }
    if (!$state['locked']) {
        throw new CoachException('HLV đã cho phép bạn tự sửa phần này tới ' . date('H:i d/m', strtotime($state['unlock_until'])) . '.');
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO coach_change_requests (coach_client_id, area, reason, status, created_at) VALUES (?, ?, ?, 'PENDING', ?)");
        $stmt->execute([$state['relation_id'], $area, $reason, coachNow()]);
    } catch (PDOException $e) {
        if (coachIsDuplicate($e)) {
            throw new CoachException('Bạn đã gửi một yêu cầu cho phần này, đang chờ HLV trả lời.');
        }
        throw $e;
    }
    $newId = (int)$pdo->lastInsertId();
    $coach = $pdo->prepare('SELECT coach_id FROM coach_clients WHERE id = ?');
    $coach->execute([$state['relation_id']]);
    notify($pdo, (int)$coach->fetchColumn(), 'coach.change_request', 'Học viên xin tự sửa ' . mb_strtolower(COACH_CHANGE_AREAS[$area]),
        notifName($pdo, $clientId) . ': ' . notifExcerpt($reason), '/coach/client.php?id=' . $clientId);
    return $newId;
}

/** Yêu cầu xin sửa đang chờ của một học viên (cho trang hồ sơ học viên phía HLV). */
function coachChangeRequests(PDO $pdo, int $relationId, bool $pendingOnly = true): array
{
    $sql = 'SELECT id, area, reason, status, coach_reply, unlock_until, created_at, resolved_at FROM coach_change_requests WHERE coach_client_id = ?';
    if ($pendingOnly) {
        $sql .= " AND status = 'PENDING'";
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY id DESC LIMIT 20');
    $stmt->execute([$relationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** HLV duyệt / từ chối yêu cầu xin sửa. Duyệt thì mở khóa COACH_UNLOCK_HOURS giờ. Trả về dòng yêu cầu. */
function coachResolveChange(PDO $pdo, int $coachId, int $requestId, bool $approve, string $reply): array
{
    if ($e = coachTextError($reply, 'Câu trả lời', 0, COACH_NOTE_MAX)) {
        throw new CoachException($e);
    }
    $stmt = $pdo->prepare(
        "SELECT r.id, r.area, r.coach_client_id, cc.client_id FROM coach_change_requests r
         JOIN coach_clients cc ON cc.id = r.coach_client_id
         WHERE r.id = ? AND cc.coach_id = ? AND cc.status = 'ACTIVE' AND r.status = 'PENDING'"
    );
    $stmt->execute([$requestId, $coachId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($req === false) {
        throw new CoachException('Yêu cầu không tồn tại hoặc đã được xử lý.');
    }
    $now = coachNow();
    $until = $approve ? date('Y-m-d H:i:s', time() + COACH_UNLOCK_HOURS * 3600) : null;
    $pdo->prepare('UPDATE coach_change_requests SET status = ?, coach_reply = ?, unlock_until = ?, resolved_at = ? WHERE id = ?')
        ->execute([$approve ? 'APPROVED' : 'REJECTED', $reply, $until, $now, $requestId]);
    $req['unlock_until'] = $until;
    $area = mb_strtolower(COACH_CHANGE_AREAS[$req['area']] ?? $req['area']);
    notify($pdo, (int)$req['client_id'], 'coach.change_reply',
        $approve ? "HLV cho phép bạn tự sửa $area" : "HLV chưa đồng ý cho tự sửa $area",
        ($approve ? 'Mở khóa tới ' . date('H:i d/m', strtotime($until)) . '.' : 'Bạn xem lời nhắn của HLV.') . ($reply !== '' ? ' HLV: ' . notifExcerpt($reply) : ''),
        $req['area'] === 'PLAN' ? '/workout-plan.php' : '/nutrition.php');
    return $req;
}

// =====================================================================
// 7. NHẬN XÉT VÀ TIN NHẮN
// =====================================================================

/**
 * HLV thêm nhận xét. $logDate (Y-m-d) = nhận xét buổi tập ngày đó, phải là ngày học viên có tập; null = nhận xét chung.
 */
function coachAddFeedback(PDO $pdo, int $relationId, int $clientId, ?string $logDate, string $content): int
{
    if ($e = coachTextError($content, 'Nhận xét', 2, COACH_FEEDBACK_MAX)) {
        throw new CoachException($e);
    }
    if ($logDate !== null) {
        $d = DateTime::createFromFormat('Y-m-d', $logDate);
        if ($d === false || $d->format('Y-m-d') !== $logDate) {
            throw new CoachException('Ngày tập không hợp lệ.');
        }
        $chk = $pdo->prepare('SELECT COUNT(*) FROM workout_logs WHERE user_id = ? AND log_date = ?');
        $chk->execute([$clientId, $logDate]);
        if ((int)$chk->fetchColumn() === 0) {
            throw new CoachException('Học viên không có buổi tập nào vào ngày này.');
        }
    }
    $stmt = $pdo->prepare('INSERT INTO coach_feedback (coach_client_id, log_date, content, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$relationId, $logDate, $content, coachNow()]);
    $newId = (int)$pdo->lastInsertId();
    $coach = $pdo->prepare('SELECT coach_id FROM coach_clients WHERE id = ?');
    $coach->execute([$relationId]);
    notify($pdo, $clientId, 'coach.feedback', 'HLV ' . notifName($pdo, (int)$coach->fetchColumn()) . ' có nhận xét mới',
        ($logDate !== null ? 'Buổi tập ' . date('d/m', strtotime($logDate)) . ': ' : '') . notifExcerpt($content), '/my-coach.php', true);
    return $newId;
}

/** Nhận xét trong một quan hệ, mới nhất trước. */
function coachFeedbackList(PDO $pdo, int $relationId, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM coach_feedback WHERE coach_client_id = ? ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
    );
    $stmt->execute([$relationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function coachMarkFeedbackRead(PDO $pdo, int $relationId): void
{
    $pdo->prepare('UPDATE coach_feedback SET read_at = ? WHERE coach_client_id = ? AND read_at IS NULL')->execute([coachNow(), $relationId]);
    $client = $pdo->prepare('SELECT client_id FROM coach_clients WHERE id = ?');
    $client->execute([$relationId]);
    notifMarkReadWhere($pdo, (int)$client->fetchColumn(), 'coach.feedback', '/my-coach.php');
}

/** Tin nhắn có id > $afterId (để hỏi tin mới định kỳ), cũ → mới, tối đa 100. */
function coachMessages(PDO $pdo, int $relationId, int $afterId = 0, int $limit = 100): array
{
    $stmt = $pdo->prepare(
        'SELECT id, sender_id, content, read_at, created_at FROM coach_messages
         WHERE coach_client_id = ? AND id > ? ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
    );
    $stmt->execute([$relationId, $afterId]);
    return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/** Gửi tin (đã kiểm tra quyền ở nơi gọi). Kiểm tra độ dài và nhịp gửi ở máy chủ. */
function coachSendMessage(PDO $pdo, int $relationId, int $senderId, string $content): array
{
    if ($e = coachTextError($content, 'Tin nhắn', 1, COACH_MESSAGE_MAX)) {
        throw new CoachException($e);
    }
    $now = time();
    $stmt = $pdo->prepare(
        'SELECT MAX(created_at) AS last_at, SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS last_hour
         FROM coach_messages WHERE sender_id = ?'
    );
    $stmt->execute([date('Y-m-d H:i:s', $now - 3600), $senderId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $since = $r['last_at'] ? $now - strtotime($r['last_at']) : null;
    if ($e = coachMessageRateError($since, (int)$r['last_hour'])) {
        throw new CoachRateLimitException($e);
    }
    $at = date('Y-m-d H:i:s', $now);
    $pdo->prepare('INSERT INTO coach_messages (coach_client_id, sender_id, content, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$relationId, $senderId, $content, $at]);
    $msgId = (int)$pdo->lastInsertId();
    [$receiver, $link] = coachMessageNotifTarget($pdo, $relationId, $senderId);
    if ($receiver > 0) {
        notify($pdo, $receiver, 'coach.message', 'Tin nhắn mới từ ' . notifName($pdo, $senderId), notifExcerpt($content), $link, true);
    }
    return ['id' => $msgId, 'sender_id' => $senderId, 'content' => $content, 'read_at' => null, 'created_at' => $at];
}

/** Người đọc ($readerId) mở khung chat: đánh dấu đã đọc mọi tin của bên kia. */
function coachMarkMessagesRead(PDO $pdo, int $relationId, int $readerId): void
{
    $pdo->prepare('UPDATE coach_messages SET read_at = ? WHERE coach_client_id = ? AND sender_id <> ? AND read_at IS NULL')
        ->execute([coachNow(), $relationId, $readerId]);
    // Người đọc chính là "người nhận" của tin bên kia gửi: tìm link khung chat theo góc nhìn của họ
    $rel = $pdo->prepare('SELECT coach_id, client_id FROM coach_clients WHERE id = ?');
    $rel->execute([$relationId]);
    $r = $rel->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        $other = (int)$r['coach_id'] === $readerId ? (int)$r['client_id'] : (int)$r['coach_id'];
        notifMarkReadWhere($pdo, $readerId, 'coach.message', coachMessageNotifTarget($pdo, $relationId, $other)[1]);
    }
}

/**
 * Người nhận và đường dẫn khung chat cho thông báo tin nhắn: HLV gửi thì học viên nhận (trang HLV của tôi, tab Tin nhắn);
 * học viên gửi thì HLV nhận (khu HLV, khung chat với học viên đó). Trả [id người nhận, link] hoặc [0, ''].
 */
function coachMessageNotifTarget(PDO $pdo, int $relationId, int $senderId): array
{
    $st = $pdo->prepare('SELECT coach_id, client_id FROM coach_clients WHERE id = ?');
    $st->execute([$relationId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return [0, ''];
    }
    return (int)$r['coach_id'] === $senderId
        ? [(int)$r['client_id'], '/my-coach.php?tab=messages']
        : [(int)$r['coach_id'], '/coach/messages.php?id=' . (int)$r['client_id']];
}

/**
 * Thông báo nhỏ cho học viên (trang chủ, trang HLV của tôi): tin chưa đọc, nhận xét chưa xem,
 * yêu cầu xin sửa HLV vừa trả lời trong 3 ngày. null nếu không có HLV hoặc chưa import bảng.
 */
function coachClientNotice(PDO $pdo, int $clientId): ?array
{
    if (!coachTablesReady($pdo)) {
        return null;
    }
    $rel = coachOpenRelationForClient($pdo, $clientId);
    if ($rel === null) {
        return null;
    }
    $out = ['relation' => $rel, 'messages' => 0, 'feedback' => 0, 'replies' => 0];
    if ($rel['status'] === 'ACTIVE') {
        $stmt = $pdo->prepare(
            "SELECT (SELECT COUNT(*) FROM coach_messages WHERE coach_client_id = ? AND sender_id <> ? AND read_at IS NULL),
                    (SELECT COUNT(*) FROM coach_feedback WHERE coach_client_id = ? AND read_at IS NULL),
                    (SELECT COUNT(*) FROM coach_change_requests WHERE coach_client_id = ? AND status IN ('APPROVED', 'REJECTED') AND resolved_at >= ?)"
        );
        $stmt->execute([$rel['id'], $clientId, $rel['id'], $rel['id'], date('Y-m-d H:i:s', time() - 3 * 86400)]);
        [$out['messages'], $out['feedback'], $out['replies']] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));
    }
    return $out;
}


// Phần mở rộng (bản nháp lịch, sửa nhận xét, lịch sử, chứng chỉ...) nằm ở file riêng cho dễ đọc
require_once __DIR__ . '/coach-extras.php';
