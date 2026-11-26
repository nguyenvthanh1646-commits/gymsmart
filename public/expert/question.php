<?php
/**
 * expert/question.php?id=... — chuyên gia xem một câu hỏi (A-55).
 *   - Câu đang chờ đúng chủ đề: đọc rồi bấm "Nhận câu hỏi".
 *   - Câu mình đã nhận: trả lời, trả lại hàng chờ (khi chưa trả lời câu nào), đóng câu hỏi.
 * Quyền xem kiểm tra trong healthQuestionForExpert(): câu của chuyên gia khác, câu sai chủ đề, id rác → 404.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/expert.php';

requireExpert();
$expertId = (int)$_SESSION['user_id'];
$qid = coachParseId($_GET['id'] ?? null);
$q = healthQuestionForExpert($pdo, $expertId, $qid);
if ($q === null) {
    showError(404, 'Không tìm thấy câu hỏi', 'Câu hỏi không tồn tại, đã có chuyên gia khác nhận, hoặc không thuộc chủ đề bạn nhận.', [['/expert/dashboard.php', 'Danh sách câu hỏi']]);
}
$self = '/expert/question.php?id=' . $qid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'message') {
            healthSendMessage($pdo, 'expert', $expertId, $qid, coachCleanText($_POST['body'] ?? ''));
            coachFlash('ok', 'Đã gửi câu trả lời. Người hỏi được báo qua chuông thông báo.');
        } elseif (in_array($action, ['claim', 'release', 'close'], true)) {
            healthAction($pdo, 'expert', $expertId, $qid, $action);
            $texts = ['claim' => 'Đã nhận câu hỏi.', 'release' => 'Đã trả câu hỏi về hàng chờ.', 'close' => 'Đã đóng câu hỏi.'];
            coachFlash('ok', $texts[$action]);
            if ($action === 'release') {
                header('Location: /expert/dashboard.php');
                exit;
            }
        } else {
            throw new ExpertException('Thao tác không hợp lệ.');
        }
    } catch (ExpertException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: ' . $self . '#cuoi');
    exit;
}

$flash = coachTakeFlash();
$messages = $q['is_mine'] ? healthMessages($pdo, $qid) : [];
if ($q['is_mine']) {
    healthMarkRead($pdo, $qid, $expertId);
}
$replied = (bool)array_filter($messages, fn($m) => (int)$m['sender_id'] === $expertId);
$askerLabel = 'Người hỏi: ' . $q['asker_name'];

$pageTitle = 'Câu hỏi tư vấn';
$pageCss = ['/css/coach.css', '/css/consult.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$expertNavActive = 'dashboard';
include __DIR__ . '/../includes/expert-nav.php';
?>
<div class="adm-wrap">
    <a class="adm-back" href="/expert/dashboard.php">&larr; Danh sách câu hỏi</a>
    <div class="adm-head">
        <div>
            <h1><?= htmlspecialchars($q['title']) ?></h1>
            <p class="adm-sub"><?= htmlspecialchars($q['asker_name']) ?> · <?= htmlspecialchars(HEALTH_TOPICS[$q['topic']] ?? '') ?> · gửi <?= htmlspecialchars(date('H:i d/m/Y', strtotime($q['created_at']))) ?> · <?= healthStatusBadge($q['status']) ?></p>
        </div>
    </div>
    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <?php if ($q['red_flags'] !== ''): ?>
        <div class="adm-errors" role="alert"><b>Câu hỏi có dấu hiệu cần lưu ý: <?= htmlspecialchars($q['red_flags']) ?>.</b> Người hỏi đã xác nhận không trong tình huống khẩn cấp; nếu triệu chứng có thể nguy hiểm, hãy khuyên đi khám hoặc gọi 115.</div>
    <?php endif; ?>

    <div class="adm-split">
        <section class="adm-panel adm-card-pad" aria-label="Trao đổi">
            <?= healthThread($q, $messages, $expertId, $askerLabel, 'Bạn') ?>
            <span id="cuoi"></span>
            <?php if ($q['is_mine'] && $q['status'] === 'CLAIMED'): ?>
                <form class="hq-reply" method="post" action="<?= htmlspecialchars($self) ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="message">
                    <label for="msg">Trả lời</label>
                    <textarea class="adm-textarea" id="msg" name="body" maxlength="<?= HEALTH_MSG_MAX ?>" required placeholder="Giải thích nguyên nhân có thể gặp, việc nên làm và nên tránh, khi nào cần đi khám…"></textarea>
                    <span class="ch-count" data-count="msg"></span>
                    <div><button type="submit" class="adm-btn" data-busy-text="Đang gửi…">Gửi câu trả lời</button></div>
                </form>
            <?php elseif (!$q['is_mine']): ?>
                <form method="post" action="<?= htmlspecialchars($self) ?>" style="margin-top:16px">
                    <?= csrfField() ?><input type="hidden" name="action" value="claim">
                    <button type="submit" class="adm-btn" data-busy-text="Đang nhận…">Nhận câu hỏi</button>
                    <span class="adm-sub">Nhận rồi thì chỉ bạn trả lời được câu này.</span>
                </form>
            <?php endif; ?>
        </section>

        <div class="adm-side" style="display:grid;gap:16px">
            <section class="adm-panel adm-card-pad">
                <h2>Số liệu người hỏi chia sẻ</h2>
                <?php if ($q['profile_snapshot'] !== null): ?>
                    <p style="margin:0;white-space:pre-line"><?= htmlspecialchars((string)$q['profile_snapshot']) ?></p>
                <?php else: ?>
                    <p class="adm-sub" style="margin:0">Người hỏi không chia sẻ số liệu hồ sơ. Hỏi thêm nếu cần.</p>
                <?php endif; ?>
            </section>
            <?php if ($q['is_mine'] && $q['status'] === 'CLAIMED'): ?>
                <section class="adm-panel adm-card-pad">
                    <h2>Kết thúc</h2>
                    <form method="post" action="<?= htmlspecialchars($self) ?>" data-confirm="Đóng câu hỏi? Hai bên không gửi thêm được.">
                        <?= csrfField() ?><input type="hidden" name="action" value="close">
                        <button type="submit" class="adm-btn adm-btn-ghost adm-btn-sm" data-busy-text="Đang đóng…">Đóng câu hỏi</button>
                    </form>
                    <?php if (!$replied): ?>
                        <form method="post" action="<?= htmlspecialchars($self) ?>" style="margin-top:8px" data-confirm="Trả câu hỏi về hàng chờ cho chuyên gia khác?">
                            <?= csrfField() ?><input type="hidden" name="action" value="release">
                            <button type="submit" class="adm-btn adm-btn-ghost adm-btn-sm" data-busy-text="Đang xử lý…">Trả lại hàng chờ</button>
                        </form>
                        <p class="adm-hint">Chỉ trả lại được khi bạn chưa trả lời, ví dụ câu hỏi ngoài chuyên môn.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
