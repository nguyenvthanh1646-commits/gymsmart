<?php
/**
 * chatbot-widget.php — nút chat nổi + khung chat. Include MỘT lần trong footer.php, ngay trước </body>:
 *
 *     <?php include __DIR__ . '/chatbot-widget.php'; ?>
 *
 * - Chỉ hiện khi đã đăng nhập (api/chatbot.php cũng yêu cầu đăng nhập).
 * - Trong khu /admin/ → "Trợ lý quản trị" (số liệu hệ thống, nội dung còn thiếu, soạn nháp bài tập).
 *   Trong khu /coach/ (vai trò COACH) → "Trợ lý HLV"; ở trang về một học viên (?id=) thì gửi kèm client_id,
 *   máy chủ kiểm tra lại quyền trước khi đưa dữ liệu học viên vào prompt (includes/coach-ai.php).
 *   Ở các trang còn lại → "GymSmart AI" (huấn luyện viên ảo cho người tập).
 * - Phần chữ nằm trong mảng $cfg bên dưới, muốn đổi câu chào/gợi ý thì sửa ở đây.
 * - Giao diện: css/chatbot.css. Hành vi: js/chatbot.js. Logo vẽ SVG sẵn trong file này (không cần ảnh).
 */
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    return;
}
if (defined('GS_CHATBOT_WIDGET')) {
    return; // đã vẽ rồi (tránh hiện 2 nút chat nếu include nhầm hai lần)
}
define('GS_CHATBOT_WIDGET', true);
require_once __DIR__ . '/csrf.php';

$gcAdmin = (($_SESSION['role'] ?? '') === 'ADMIN') && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') === 0;
$gcCoach = (($_SESSION['role'] ?? '') === 'COACH') && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/coach/') === 0;
// Trang về một học viên (/coach/client*.php?id=, /coach/messages.php?id=): chỉ là gợi ý, máy chủ vẫn kiểm tra quan hệ ACTIVE
$gcClient = $gcCoach ? filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;

// Lấy TÊN (từ cuối trong họ tên Việt Nam) để chào
$gcParts = preg_split('/\s+/u', trim((string)($_SESSION['full_name'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
$gcName  = $gcParts ? end($gcParts) : '';

if ($gcAdmin) {
    $cfg = [
        'mode'     => 'admin',
        'title'    => 'Trợ lý quản trị',
        'subtitle' => 'Vận hành & nội dung',
        'nudge'    => 'Cần số liệu hệ thống? Hỏi mình nhé',
        'welcome'  => 'Chào ' . ($gcName ?: 'bạn') . '! Mình là trợ lý quản trị của GymSmart. Mình xem được số liệu tổng hợp của hệ thống, chỉ ra nội dung còn thiếu và soạn nháp bài tập giúp bạn. Mình không xem được email hay mật khẩu của ai và không tự sửa dữ liệu.',
        'chips'    => [
            ['icon' => '📊', 'text' => 'Tóm tắt tình hình hệ thống'],
            ['icon' => '🎬', 'text' => 'Bài tập nào còn thiếu video hoặc ảnh?'],
            ['icon' => '🧭', 'text' => 'Nhóm cơ nào đang có ít bài tập nhất?'],
            ['icon' => '🛠️', 'text' => 'Hướng dẫn thêm một bài tập mới'],
        ],
        'tabs'     => [
            ['id' => 'chat',  'label' => 'Hỏi đáp',        'placeholder' => 'Hỏi về số liệu, nội dung...'],
            ['id' => 'draft', 'label' => 'Soạn bài tập',   'placeholder' => 'Tên bài, ví dụ: Hít đất'],
        ],
        'draftHint'  => 'Nhập tên bài tập, mình soạn nháp mô tả, các bước và lỗi thường gặp. Bạn đọc lại rồi mới điền vào form.',
        'draftChips' => ['Hít đất kim cương', 'Romanian deadlift', 'Chạy interval 30 giây'],
        'footer'   => 'Chỉ thấy số liệu tổng hợp, không tự sửa dữ liệu.',
        'personalize' => false,
    ];
} elseif ($gcCoach) {
    $cfg = [
        'mode'     => 'coach',
        'title'    => 'Trợ lý HLV',
        'subtitle' => $gcClient ? 'Đang xem 1 học viên' : 'Tổng quan học viên',
        'nudge'    => $gcClient ? 'Cần gợi ý chỉnh lịch cho học viên này?' : 'Hỏi về các học viên của bạn',
        'welcome'  => 'Chào ' . ($gcName ?: 'bạn') . '! Mình là trợ lý cho HLV. ' . ($gcClient
            ? 'Mình đã đọc hồ sơ, lịch, nhật ký 14 ngày và số đo của học viên đang mở để gợi ý cách điều chỉnh. Mình không thấy email và không tự sửa dữ liệu.'
            : 'Mình xem được tóm tắt các học viên đang tập với bạn. Mở hồ sơ một học viên để mình gợi ý chi tiết hơn.'),
        'chips'    => $gcClient ? [
            ['icon' => '📈', 'text' => 'Học viên này tiến bộ thế nào 2 tuần qua?'],
            ['icon' => '🛠️', 'text' => 'Nên chỉnh lịch tập ra sao?'],
            ['icon' => '🍽️', 'text' => 'Mục tiêu calo và macro có hợp lý không?'],
            ['icon' => '✍️', 'text' => 'Soạn giúp lời nhận xét cho buổi gần nhất'],
        ] : [
            ['icon' => '🚩', 'text' => 'Học viên nào cần chú ý tuần này?'],
            ['icon' => '📋', 'text' => 'Gợi ý cách nhắc học viên tập đều hơn'],
        ],
        'tabs'     => [['id' => 'chat', 'label' => 'Hỏi đáp', 'placeholder' => 'Hỏi về học viên, lịch tập...']],
        'footer'   => 'Chỉ gợi ý, HLV tự quyết định. Không thay thế tư vấn y tế.',
        'personalize' => false,
        'clientId' => $gcClient ?: null,
    ];
} else {
    $cfg = [
        'mode'     => 'user',
        'title'    => 'GymSmart AI',
        'subtitle' => 'Huấn luyện viên ảo',
        'nudge'    => 'Thắc mắc về tập luyện? Hỏi mình nhé',
        'welcome'  => 'Chào ' . ($gcName ?: 'bạn') . '! Mình là GymSmart AI. Mình giải đáp về tập luyện, kỹ thuật bài tập và dinh dưỡng bằng tiếng Việt. Bạn muốn bắt đầu từ đâu?',
        'chips'    => [
            ['icon' => '🥩', 'text' => 'Mỗi ngày tôi nên ăn bao nhiêu protein?'],
            ['icon' => '💪', 'text' => 'Gợi ý bài tập cho ngực'],
            ['icon' => '🔥', 'text' => 'TDEE là gì và tính thế nào?'],
            ['icon' => '📅', 'text' => 'Người mới nên tập mấy buổi mỗi tuần?'],
        ],
        'tabs'     => [['id' => 'chat', 'label' => 'Hỏi đáp', 'placeholder' => 'Hỏi về tập luyện, dinh dưỡng...']],
        'footer'   => 'Chỉ để tham khảo, không thay thế tư vấn y tế.',
        'personalize' => true,
    ];
}
$cfg['endpoint'] = '/api/chatbot.php';
$cfg['maxLen']   = 500;
$cfg['uid']      = (int)($_SESSION['user_id'] ?? 0);   // để mỗi tài khoản có lịch sử chat riêng trong trình duyệt
?>
<link rel="stylesheet" href="/css/chatbot.css">
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="gc-g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff8a3d"/><stop offset="1" stop-color="#ff4b0f"/></linearGradient>
    </defs>
    <!-- Logo GymSmart đầy đủ (nền cam) -->
    <symbol id="gc-logo" viewBox="0 0 64 64">
        <rect width="64" height="64" rx="16" fill="url(#gc-g)"/>
        <path d="M42.5 20.5 A15.5 15.5 0 1 0 46 32" fill="none" stroke="#fff" stroke-width="5.5" stroke-linecap="round"/>
        <rect x="30" y="30" width="18" height="4" rx="2" fill="#fff"/>
        <rect x="44" y="22" width="5" height="20" rx="2.5" fill="#fff"/>
        <rect x="50.5" y="26" width="4" height="12" rx="2" fill="#fff"/>
    </symbol>
    <!-- Chỉ phần chữ G + tạ (không nền) để đặt lên nền màu khác -->
    <symbol id="gc-glyph" viewBox="0 0 64 64">
        <path d="M42.5 20.5 A15.5 15.5 0 1 0 46 32" fill="none" stroke="currentColor" stroke-width="5.5" stroke-linecap="round"/>
        <rect x="30" y="30" width="18" height="4" rx="2" fill="currentColor"/>
        <rect x="44" y="22" width="5" height="20" rx="2.5" fill="currentColor"/>
        <rect x="50.5" y="26" width="4" height="12" rx="2" fill="currentColor"/>
    </symbol>
</svg>

<div class="gc gc--<?= $gcAdmin ? 'admin' : 'user' ?>" id="gschat" data-mode="<?= $gcAdmin ? 'admin' : 'user' ?>" data-csrf="<?= htmlspecialchars(csrfToken()) ?>">
    <div class="gc-nudge" id="gc-nudge" hidden role="status"></div>

    <button class="gc-fab" id="gc-fab" type="button" aria-expanded="false" aria-controls="gc-panel"
            aria-label="<?= $gcAdmin ? 'Mở trợ lý quản trị' : 'Mở chatbot GymSmart AI' ?>">
        <span class="gc-fab__face" aria-hidden="true">
            <?php if ($gcAdmin): ?>
                <svg class="gc-fab__glyph" viewBox="0 0 64 64"><use href="#gc-glyph"/></svg>
                <span class="gc-fab__badge gc-fab__badge--admin">AD</span>
            <?php else: ?>
                <svg class="gc-fab__logo" viewBox="0 0 64 64"><use href="#gc-logo"/></svg>
            <?php endif; ?>
        </span>
        <span class="gc-fab__dot" id="gc-dot" hidden aria-hidden="true"></span>
    </button>

    <section class="gc-panel" id="gc-panel" role="dialog" aria-label="<?= htmlspecialchars($cfg['title']) ?>" hidden>
        <span class="gc-edge gc-edge--l" data-edge="l" aria-hidden="true"></span>
        <span class="gc-edge gc-edge--r" data-edge="r" aria-hidden="true"></span>
        <span class="gc-edge gc-edge--t" data-edge="t" aria-hidden="true"></span>
        <span class="gc-edge gc-edge--b" data-edge="b" aria-hidden="true"></span>
        <span class="gc-grip" id="gc-grip" data-edge="lt" title="Kéo để đổi cỡ (bấm đúp để về mặc định)" aria-hidden="true"></span>
        <header class="gc-head">
            <div class="gc-av gc-av--lg" aria-hidden="true">
                <?php if ($gcAdmin): ?>
                    <svg viewBox="0 0 64 64"><use href="#gc-glyph"/></svg>
                <?php else: ?>
                    <svg viewBox="0 0 64 64"><use href="#gc-logo"/></svg>
                <?php endif; ?>
            </div>
            <div class="gc-head__txt">
                <strong><?= htmlspecialchars($cfg['title']) ?><?php if ($gcAdmin): ?> <em class="gc-tag">ADMIN</em><?php elseif ($gcCoach): ?> <em class="gc-tag">HLV</em><?php endif; ?></strong>
                <span class="gc-status" id="gc-status"><i class="gc-status__dot"></i><span id="gc-status-text"><?= htmlspecialchars($cfg['subtitle']) ?></span></span>
            </div>
            <button class="gc-ibtn" id="gc-reset" type="button" title="Cuộc trò chuyện mới" aria-label="Bắt đầu cuộc trò chuyện mới">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.6-5.9M20 4v5h-5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button class="gc-ibtn gc-ibtn--max" id="gc-max" type="button" title="Phóng to / thu nhỏ khung chat" aria-label="Phóng to khung chat" aria-pressed="false">
                <svg class="gc-ic-max" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M10 20H4v-6M20 4l-7 7M4 20l7-7" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <svg class="gc-ic-min" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10h-6V4M4 14h6v6M14 10l7-7M10 14l-7 7" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button class="gc-ibtn" id="gc-close" type="button" title="Đóng (Esc)" aria-label="Đóng khung chat">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" fill="none"/></svg>
            </button>
        </header>

        <?php if (count($cfg['tabs']) > 1): ?>
            <div class="gc-tabs" role="tablist" id="gc-tabs">
                <?php foreach ($cfg['tabs'] as $i => $tab): ?>
                    <button type="button" role="tab" class="gc-tab<?= $i === 0 ? ' is-on' : '' ?>" data-tab="<?= htmlspecialchars($tab['id']) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= htmlspecialchars($tab['label']) ?></button>
                <?php endforeach; ?>
                <span class="gc-tabs__bar" aria-hidden="true"></span>
            </div>
        <?php endif; ?>

        <div class="gc-body" id="gc-body" role="log" aria-live="polite" aria-relevant="additions"></div>

        <form class="gc-form" id="gc-form" autocomplete="off">
            <textarea id="gc-input" rows="1" maxlength="<?= (int)$cfg['maxLen'] ?>" placeholder="<?= htmlspecialchars($cfg['tabs'][0]['placeholder']) ?>" aria-label="Nhập tin nhắn"></textarea>
            <button class="gc-send" id="gc-send" type="submit" aria-label="Gửi" disabled>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12L20 4l-5 16-3.2-6.8L4 12z" fill="currentColor"/></svg>
            </button>
            <button class="gc-send gc-stop" id="gc-stop" type="button" aria-label="Dừng chờ câu trả lời" title="Dừng" hidden>
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="2" fill="currentColor"/></svg>
            </button>
        </form>
        <div class="gc-foot">
            <?php if ($cfg['personalize']): ?>
                <label class="gc-switch" title="Gửi mục tiêu, trình độ, chiều cao, cân nặng tới Google Gemini để trả lời sát hơn. Tên và email không được gửi.">
                    <input type="checkbox" id="gc-pers">
                    <span class="gc-switch__track" aria-hidden="true"></span>
                    <span>Dùng hồ sơ của tôi</span>
                </label>
            <?php endif; ?>
            <span class="gc-count" id="gc-count" aria-hidden="true">0/<?= (int)$cfg['maxLen'] ?></span>
            <span class="gc-foot__note"><?= htmlspecialchars($cfg['footer']) ?></span>
        </div>
    </section>

    <div class="gc-toast" id="gc-toast" hidden role="status"></div>
</div>
<script type="application/json" id="gc-cfg"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/js/chatbot.js" defer></script>
