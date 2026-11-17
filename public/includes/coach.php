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
