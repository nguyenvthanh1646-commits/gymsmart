<?php
/**
 * includes/coach.php — phân quyền cho khu HLV (/coach/) và các API HLV.
 *
 * Dùng ở đầu trang /coach/*.php:
 *     require_once __DIR__ . '/../includes/auth.php';
 *     require_once __DIR__ . '/../includes/db.php';
 *     require_once __DIR__ . '/../includes/coach.php';
 *     requireCoach();                                         // phải là COACH (đọc lại từ DB)
 *     [$clientId, $relationId] = requireCoachClient($pdo, $_GET['id'] ?? null);   // trang về 1 học viên
 *
 * Vì sao không tin id trên URL? Ai cũng sửa được ?id=5 thành ?id=6. Id trên URL chỉ là "muốn xem ai";
 * quyền xem được quyết định bằng câu SQL kiểm tra quan hệ ACTIVE giữa HLV đang đăng nhập và học viên đó.
 * Không có quyền thì trả 404 (không phải 403) để không lộ id nào là học viên có thật.
 */

require_once __DIR__ . '/coach-db.php';
require_once __DIR__ . '/csrf.php';

/** Chặn trang nếu không phải HLV. Đọc lại vai trò từ DB nên bị thu hồi quyền là có hiệu lực ngay. */
function requireCoach(): void
{
    global $pdo;   // trang đã nạp includes/db.php trước khi gọi
    requireLogin();
    if ($pdo instanceof PDO) {
        refreshSessionRole($pdo);
    }
    if (($_SESSION['role'] ?? '') !== 'COACH') {
        showError(403, 'Bạn không có quyền truy cập', 'Khu này chỉ dành cho huấn luyện viên. Muốn trở thành HLV, hãy nộp đơn ở trang Huấn luyện viên.',
            [['/coaches.php', 'Trang Huấn luyện viên'], ['/index.php', 'Về trang chủ']]);
    }
}

/** Đọc id học viên từ URL/form: số nguyên ≥ 1, ngược lại 0. */
function coachParseId($raw): int
{
    $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : $id;
}

/**
 * Gọi sau requireCoach(): kiểm tra $rawClientId là học viên ĐANG ACTIVE của HLV đang đăng nhập.
 * Đúng thì trả về [clientId, relationId]; sai (id rác, học viên của HLV khác, quan hệ đã kết thúc) thì trang 404.
 */
function requireCoachClient(PDO $pdo, $rawClientId): array
{
    $clientId = coachParseId($rawClientId);
    $relationId = $clientId > 0 ? coachActiveRelationId($pdo, (int)$_SESSION['user_id'], $clientId) : null;
    if ($relationId === null) {
        showError(404, 'Không tìm thấy học viên', 'Học viên này không có trong danh sách của bạn, hoặc quan hệ huấn luyện đã kết thúc.',
            [['/coach/clients.php', 'Danh sách học viên'], ['/coach/dashboard.php', 'Bảng điều khiển']]);
    }
    return [$clientId, $relationId];
}

/**
 * Xử lý POST của HỌC VIÊN trên coaches.php và my-coach.php (dùng chung để khỏi viết 2 lần):
 *   action=request  coach_id, note   gửi yêu cầu tới HLV
 *   action=cancel   relation_id      hủy yêu cầu đang chờ
 *   action=end      relation_id      ngừng tập với HLV hiện tại
 *   action=change   area, reason     xin HLV cho tự sửa lịch tập/dinh dưỡng
 * client_id luôn là người đang đăng nhập; relation_id chỉ là "muốn tác động quan hệ nào",
 * hàm coachChangeRelation() tự kiểm tra quan hệ đó có đúng client_id này không.
 * Không phải POST thì không làm gì. Làm xong chuyển hướng về $redirect (Post/Redirect/Get).
 */
function coachHandleClientAction(PDO $pdo, string $redirect): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !coachTablesReady($pdo)) {
        return;
    }
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $me = (int)$_SESSION['user_id'];
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'request':
                coachRequest($pdo, $me, coachParseId($_POST['coach_id'] ?? null), coachCleanText($_POST['note'] ?? ''));
                coachFlash('ok', 'Đã gửi yêu cầu. HLV sẽ xem hồ sơ của bạn và trả lời sớm.');
                break;
            case 'cancel':
                coachChangeRelation($pdo, 'client', $me, coachParseId($_POST['relation_id'] ?? null), 'cancel');
                coachFlash('ok', 'Đã hủy yêu cầu. Bạn có thể chọn HLV khác.');
                break;
            case 'end':
                coachChangeRelation($pdo, 'client', $me, coachParseId($_POST['relation_id'] ?? null), 'end');
                coachFlash('ok', 'Đã ngừng tập với HLV. Lịch tập và mục tiêu hiện tại vẫn được giữ, bạn tự sửa được.');
                break;
            case 'change':
                $area = (string)($_POST['area'] ?? '');
                coachRequestChange($pdo, $me, $area, coachCleanText($_POST['reason'] ?? ''));
                coachFlash('ok', 'Đã gửi yêu cầu sửa ' . mb_strtolower(COACH_CHANGE_AREAS[$area] ?? '') . ' tới HLV.');
                break;
            default:
                throw new CoachException('Thao tác không hợp lệ.');
        }
    } catch (CoachException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: ' . $redirect);   // $redirect là đường dẫn viết sẵn ở trang gọi, không lấy từ request
    exit;
}

/**
 * Xử lý POST của HLV (dùng chung cho dashboard, clients, client): gọi SAU requireCoach().
 *   action=accept|reject|end  relation_id                 duyệt / từ chối yêu cầu, kết thúc quan hệ
 *   action=resolve_change     request_id, decision, reply  trả lời yêu cầu xin tự sửa của học viên
 * coach_id luôn là người đang đăng nhập; các hàm DB tự kiểm tra relation_id/request_id thuộc HLV này.
 * Thao tác thành công được ghi admin_audit_log với mã coach.*.
 */
function coachHandleCoachAction(PDO $pdo, string $redirect): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($_POST['action'] ?? '', ['accept', 'reject', 'end', 'resolve_change'], true)) {
        return;
    }
    if (!verifyCsrf()) {
        showCsrfError();
    }
    require_once __DIR__ . '/audit-log.php';
    $me = (int)$_SESSION['user_id'];
    $action = $_POST['action'];
    try {
        if ($action === 'resolve_change') {
            $approve = ($_POST['decision'] ?? '') === 'approve';
            $req = coachResolveChange($pdo, $me, coachParseId($_POST['request_id'] ?? null), $approve, coachCleanText($_POST['reply'] ?? ''));
            auditLog($pdo, 'coach.change_request', 'client', $req['client_id'],
                ($approve ? 'Cho phép' : 'Không cho phép') . ' học viên tự sửa ' . mb_strtolower(COACH_CHANGE_AREAS[$req['area']] ?? $req['area']));
            coachFlash('ok', $approve ? 'Đã cho phép học viên tự sửa trong ' . COACH_UNLOCK_HOURS . ' giờ.' : 'Đã trả lời: không cho phép sửa.');
        } else {
            $rel = coachChangeRelation($pdo, 'coach', $me, coachParseId($_POST['relation_id'] ?? null), $action);
            $codes = ['accept' => 'coach.accept', 'reject' => 'coach.decline', 'end' => 'coach.end'];
            $texts = ['accept' => 'Nhận học viên', 'reject' => 'Từ chối học viên', 'end' => 'Kết thúc với học viên'];
            auditLog($pdo, $codes[$action], 'client', $rel['client_id'], $texts[$action] . ' "' . $rel['client_name'] . '"');
            $msg = ['accept' => 'Đã nhận "%s". Hãy giao lịch tập và đặt mục tiêu dinh dưỡng.', 'reject' => 'Đã từ chối yêu cầu của "%s".',
                    'end' => 'Đã kết thúc quan hệ với "%s". Bạn không còn xem được dữ liệu của học viên này.'];
            coachFlash('ok', sprintf($msg[$action], $rel['client_name']));
        }
    } catch (CoachException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: ' . $redirect);   // đường dẫn viết sẵn ở trang gọi
    exit;
}

/** Trạng thái thông báo một lần (flash) dùng chung cho các trang HLV và học viên. */
function coachFlash(string $type, string $message): void
{
    $_SESSION['coach_flash'] = [$type === 'ok' ? 'ok' : 'error', $message];
}

function coachTakeFlash(): ?array
{
    $f = $_SESSION['coach_flash'] ?? null;
    unset($_SESSION['coach_flash']);
    return is_array($f) ? $f : null;
}

/**
 * HTML khung chat (dùng ở my-coach.php?tab=messages và coach/messages.php). Hành vi nằm ở js/coach-chat.js.
 * $withId = id người bên kia; máy chủ (api/coach-messages.php) vẫn tự kiểm tra quan hệ ACTIVE, đây chỉ là "nói chuyện với ai".
 * Tin nhắn ban đầu do JS tải về; trong lúc chờ hiện khung xương.
 */
function coachChatBox(int $withId, string $name, ?string $avatar, string $subtitle): string
{
    ob_start(); ?>
    <section class="ch-chat" id="ch-chat" data-with="<?= $withId ?>" data-csrf="<?= htmlspecialchars(csrfToken()) ?>" aria-label="Nhắn tin với <?= htmlspecialchars($name) ?>">
        <header class="ch-chat__head">
            <?= coachAvatar($avatar, $name, 40) ?>
            <div><b><?= htmlspecialchars($name) ?></b><span><?= htmlspecialchars($subtitle) ?></span></div>
        </header>
        <div class="ch-chat__log" id="ch-log" role="log" aria-live="polite" aria-label="Tin nhắn" tabindex="0">
            <span class="ch-skel ch-skel--msg" aria-hidden="true"></span>
            <span class="ch-skel ch-skel--msg" aria-hidden="true"></span>
            <span class="ch-skel ch-skel--msg" aria-hidden="true"></span>
        </div>
        <form class="ch-chat__form" id="ch-form" autocomplete="off">
            <label for="ch-input" class="ch-sr">Nội dung tin nhắn</label>
            <textarea id="ch-input" rows="1" maxlength="<?= COACH_MESSAGE_MAX ?>" placeholder="Nhập tin nhắn…" aria-describedby="ch-status"></textarea>
            <button type="submit" class="btn">Gửi</button>
        </form>
        <div class="ch-chat__foot"><span id="ch-status" role="status"></span><span id="ch-counter" aria-hidden="true">0/<?= COACH_MESSAGE_MAX ?></span></div>
    </section>
    <?php
    return (string)ob_get_clean();
}

/** Số kiểu Việt Nam: 2.230 hoặc 72,5 (dùng khi in số ra HTML; kết quả chỉ có chữ số, dấu chấm, dấu phẩy nên an toàn). */
function coachNum($n, int $decimals = 0): string
{
    return number_format((float)$n, $decimals, ',', '.');
}

/** Chữ cái đầu của TÊN (từ cuối trong họ tên Việt Nam) để vẽ avatar tròn khi chưa có ảnh. */
function coachInitial(string $fullName): string
{
    $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    return $parts ? mb_strtoupper(mb_substr(end($parts), 0, 1)) : '?';
}

/** HTML avatar: ảnh nếu đường dẫn hợp lệ (cùng quy tắc với navbar.php), ngược lại chữ cái đầu. Đã escape. */
function coachAvatar(?string $avatar, string $fullName, int $size = 44): string
{
    $size = max(24, min(160, $size));
    if (is_string($avatar) && preg_match('#^uploads/avatars/[A-Za-z0-9._-]+\.jpg$#', $avatar)) {
        return '<img class="ch-av" src="/' . htmlspecialchars($avatar) . '" alt="" width="' . $size . '" height="' . $size . '" loading="lazy">';
    }
    return '<span class="ch-av ch-av--txt" style="width:' . $size . 'px;height:' . $size . 'px" aria-hidden="true">' . htmlspecialchars(coachInitial($fullName)) . '</span>';
}

/** Ngày dạng "Thứ Năm, 05/11/2026" (theo UI_UX_PROMPT.md mục 4). */
function coachDateLabel(string $date, bool $withWeekday = true): string
{
    $t = strtotime($date);
    if ($t === false) {
        return '';
    }
    $days = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
    return ($withWeekday ? $days[(int)date('w', $t)] . ', ' : '') . date('d/m/Y', $t);
}

/** "5 phút trước", "Hôm qua 21:10"... cho tin nhắn và nhận xét. */
function coachTimeAgo(string $datetime): string
{
    $t = strtotime($datetime);
    if ($t === false) {
        return '';
    }
    $diff = time() - $t;
    if ($diff < 60) return 'Vừa xong';
    if ($diff < 3600) return (int)floor($diff / 60) . ' phút trước';
    if (date('Y-m-d', $t) === date('Y-m-d')) return 'Hôm nay ' . date('H:i', $t);
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) return 'Hôm qua ' . date('H:i', $t);
    return date('H:i d/m/Y', $t);
}

/** Nhãn hồ sơ dùng chung cho các trang HLV. */
const COACH_GOAL_LABELS = ['LOSE_FAT' => 'Giảm mỡ', 'MAINTAIN' => 'Duy trì', 'GAIN_MUSCLE' => 'Tăng cơ'];
const COACH_LEVEL_LABELS = ['BEGINNER' => 'Người mới', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Nâng cao'];
const COACH_GENDER_LABELS = ['MALE' => 'Nam', 'FEMALE' => 'Nữ', 'OTHER' => 'Khác'];
const COACH_ACTIVITY_LABELS = [
    'SEDENTARY' => 'Ít vận động', 'LIGHT' => 'Nhẹ (1-3 buổi/tuần)', 'MODERATE' => 'Vừa (3-5 buổi/tuần)',
    'ACTIVE' => 'Nhiều (6-7 buổi/tuần)', 'EXTRA_ACTIVE' => 'Rất nhiều',
];
