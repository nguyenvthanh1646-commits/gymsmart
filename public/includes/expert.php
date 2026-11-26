<?php
/**
 * includes/expert.php — phân quyền khu chuyên gia (/expert/) và phần hiển thị dùng chung của tư vấn sức khỏe (A-55).
 *
 * Dùng ở đầu trang /expert/*.php:
 *     require_once __DIR__ . '/../includes/auth.php';
 *     require_once __DIR__ . '/../includes/db.php';
 *     require_once __DIR__ . '/../includes/expert.php';
 *     requireExpert();   // phải là EXPERT (đọc lại vai trò trong DB)
 * Quyền xem từng câu hỏi kiểm tra trong healthQuestionForExpert() / healthQuestionForAsker(), không tin id trên URL.
 * Flash dùng chung coachFlash()/coachTakeFlash() của includes/coach.php.
 */
require_once __DIR__ . '/coach.php';        // coachFlash, coachAvatar, coachTimeAgo, coachParseId, csrf
require_once __DIR__ . '/expert-db.php';

/** Chặn trang nếu không phải chuyên gia. Bị thu hồi là có hiệu lực ngay vì đọc lại vai trò từ DB. */
function requireExpert(): void
{
    global $pdo;
    requireLogin();
    if ($pdo instanceof PDO) {
        refreshSessionRole($pdo);
    }
    if (($_SESSION['role'] ?? '') !== 'EXPERT') {
        showError(403, 'Bạn không có quyền truy cập', 'Khu này chỉ dành cho chuyên gia sức khỏe đã được duyệt.',
            [['/consult.php', 'Tư vấn sức khỏe'], ['/index.php', 'Về trang chủ']]);
    }
}

/** Khung nhắc an toàn cố định ở mọi trang tư vấn: gọi 115 khi khẩn cấp, tư vấn không thay thế khám trực tiếp. */
function healthSafetyBox(): string
{
    return '<div class="alert alert--error hq-sos" role="note"><div><b>Khẩn cấp (đau ngực, khó thở, ngất, yếu liệt nửa người, chảy máu nhiều…): gọi ngay 115</b> '
        . 'hoặc tới cơ sở y tế gần nhất, không chờ trả lời trên web.<br>'
        . 'Tư vấn trực tuyến chỉ để tham khảo, không thay thế khám trực tiếp; chuyên gia không chẩn đoán xác định và không kê đơn thuốc.</div></div>';
}

/** Nhãn trạng thái câu hỏi (class ch-badge của coach.css). */
function healthStatusBadge(string $status): string
{
    $tone = HEALTH_STATUS_TONES[$status] ?? '';
    return '<span class="ch-badge' . ($tone !== '' ? ' ch-badge--' . $tone : '') . '">' . htmlspecialchars(HEALTH_STATUS_LABELS[$status] ?? $status) . '</span>';
}

/**
 * Luồng tin của một câu hỏi: câu hỏi gốc (của người hỏi) rồi tới các tin trao đổi.
 * $askerId: id người hỏi; $me: người đang xem; $expertLabel: tên hiển thị của chuyên gia; $askerLabel: tên hiển thị của người hỏi.
 */
function healthThread(array $q, array $messages, int $me, string $askerLabel, string $expertLabel): string
{
    $askerId = (int)$q['user_id'];
    $html = '<ol class="hq-thread">';
    $html .= '<li class="hq-msg hq-msg--asker' . ($askerId === $me ? ' is-me' : '') . '"><div class="hq-msg__meta"><b>' . htmlspecialchars($askerLabel) . '</b> · '
        . htmlspecialchars(date('H:i d/m/Y', strtotime((string)$q['created_at']))) . '</div><p>' . nl2br(htmlspecialchars((string)$q['body'])) . '</p></li>';
    foreach ($messages as $m) {
        $isAsker = (int)$m['sender_id'] === $askerId;
        $html .= '<li class="hq-msg ' . ($isAsker ? 'hq-msg--asker' : 'hq-msg--expert') . ((int)$m['sender_id'] === $me ? ' is-me' : '') . '">'
            . '<div class="hq-msg__meta"><b>' . htmlspecialchars($isAsker ? $askerLabel : $expertLabel) . '</b> · '
            . htmlspecialchars(date('H:i d/m/Y', strtotime((string)$m['created_at']))) . '</div><p>' . nl2br(htmlspecialchars((string)$m['body'])) . '</p></li>';
    }
    return $html . '</ol>';
}
