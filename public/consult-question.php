<?php
/**
 * consult-question.php?id=... — một câu hỏi tư vấn phía người hỏi (A-55): xem trao đổi, gửi thêm, hủy (khi chưa ai nhận),
 * đóng câu hỏi kèm đánh giá hữu ích. healthQuestionForAsker() chỉ trả câu của chính người đang đăng nhập; sai id thì 404.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/expert.php';

$userId = (int)$_SESSION['user_id'];
$qid = coachParseId($_GET['id'] ?? null);
$q = expertTablesReady($pdo) ? healthQuestionForAsker($pdo, $userId, $qid) : null;
if ($q === null) {
    showError(404, 'Không tìm thấy câu hỏi', 'Câu hỏi này không có trong tài khoản của bạn.', [['/consult.php', 'Tư vấn sức khỏe']]);
}
$self = '/consult-question.php?id=' . $qid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    try {
        switch ($_POST['action'] ?? '') {
            case 'message':
                healthSendMessage($pdo, 'asker', $userId, $qid, coachCleanText($_POST['body'] ?? ''));
                coachFlash('ok', 'Đã gửi.');
                break;
            case 'cancel':
                healthAction($pdo, 'asker', $userId, $qid, 'cancel');
                coachFlash('ok', 'Đã hủy câu hỏi.');
                break;
            case 'close':
                $h = $_POST['helpful'] ?? '';
                healthAction($pdo, 'asker', $userId, $qid, 'close', $h === '1' ? 1 : ($h === '0' ? 0 : null));
                coachFlash('ok', 'Đã đóng câu hỏi. Cảm ơn bạn!');
                break;
            default:
                throw new ExpertException('Thao tác không hợp lệ.');
        }
    } catch (ExpertException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: ' . $self . '#cuoi');
    exit;
}

$flash = coachTakeFlash();
$messages = healthMessages($pdo, $qid);
healthMarkRead($pdo, $qid, $userId);
$expertLabel = $q['expert_name'] ? (string)$q['expert_name'] : 'Chuyên gia';

$pageTitle = 'Câu hỏi tư vấn';
$pageCss = ['/css/coach.css', '/css/consult.css'];
include __DIR__ . '/includes/header.php';
?>
<div class="ch">
    <a class="btn btn--ghost" href="/consult.php" style="margin-bottom:var(--s-4)">&larr; Tư vấn sức khỏe</a>
    <header class="pg-head">
        <div>
            <h1><?= htmlspecialchars($q['title']) ?></h1>
            <p class="pg-sub"><?= htmlspecialchars(HEALTH_TOPICS[$q['topic']] ?? $q['topic']) ?> · gửi <?= htmlspecialchars(date('H:i d/m/Y', strtotime($q['created_at']))) ?> · <?= healthStatusBadge($q['status']) ?></p>
        </div>
    </header>
    <?php if ($flash): ?>
        <div class="alert alert--<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <?= healthSafetyBox() ?>

    <div class="ch-layout">
        <div class="ch-stack">
            <section class="card" aria-label="Trao đổi">
                <?= healthThread($q, $messages, $userId, 'Bạn', $expertLabel) ?>
                <span id="cuoi"></span>
                <?php if ($q['status'] === 'OPEN' || $q['status'] === 'CLAIMED'): ?>
                    <form class="hq-reply" method="post" action="<?= htmlspecialchars($self) ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="message">
                        <label for="msg"><?= $q['status'] === 'OPEN' ? 'Bổ sung thông tin (chuyên gia nhận câu hỏi sẽ đọc được)' : 'Trả lời chuyên gia' ?></label>
                        <textarea id="msg" name="body" maxlength="<?= HEALTH_MSG_MAX ?>" required></textarea>
                        <span class="ch-count" data-count="msg"></span>
                        <div><button type="submit" class="btn" data-busy-text="Đang gửi…">Gửi</button></div>
                    </form>
                <?php else: ?>
                    <p class="ch-muted" style="margin:var(--s-4) 0 0">Câu hỏi đã <?= $q['status'] === 'CANCELLED' ? 'hủy' : 'đóng' ?>. Cần hỏi thêm, hãy <a href="/consult-ask.php?topic=<?= htmlspecialchars($q['topic']) ?>">gửi câu hỏi mới</a>.</p>
                <?php endif; ?>
            </section>
        </div>
        <aside class="ch-side">
            <section class="card">
                <h2 class="card__head">Chuyên gia</h2>
                <?php if ($q['expert_name']): ?>
                    <div class="ch-coach__head">
                        <?= coachAvatar($q['expert_avatar'], $q['expert_name'], 48) ?>
                        <div><b><?= htmlspecialchars($q['expert_name']) ?></b><br><span class="ch-muted ch-small"><?= htmlspecialchars((string)$q['expert_title']) ?></span></div>
                    </div>
                <?php else: ?>
                    <p class="ch-muted" style="margin:0">Đang chờ chuyên gia phù hợp nhận câu hỏi.</p>
                <?php endif; ?>
            </section>
            <?php if ($q['profile_snapshot'] !== null): ?>
                <section class="card">
                    <h2 class="card__head">Số liệu bạn đã chia sẻ</h2>
                    <p class="ch-small" style="margin:0;white-space:pre-line"><?= htmlspecialchars((string)$q['profile_snapshot']) ?></p>
                </section>
            <?php endif; ?>
            <?php if ($q['status'] === 'CLAIMED'): ?>
                <section class="card">
                    <h2 class="card__head">Đóng câu hỏi</h2>
                    <form class="hq-close" method="post" action="<?= htmlspecialchars($self) ?>" data-confirm="Đóng câu hỏi? Sau khi đóng không gửi thêm được.">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="close">
                        <fieldset>
                            <legend class="ch-small ch-muted">Câu trả lời có hữu ích không? (không bắt buộc)</legend>
                            <label><input type="radio" name="helpful" value="1"> Hữu ích</label>
                            <label><input type="radio" name="helpful" value="0"> Chưa hữu ích</label>
                        </fieldset>
                        <button type="submit" class="btn btn--ghost" data-busy-text="Đang đóng…">Đóng câu hỏi</button>
                    </form>
                </section>
            <?php elseif ($q['status'] === 'OPEN'): ?>
                <section class="card">
                    <h2 class="card__head">Hủy câu hỏi</h2>
                    <p class="card__sub">Chưa chuyên gia nào nhận nên bạn hủy được.</p>
                    <form method="post" action="<?= htmlspecialchars($self) ?>" data-confirm="Hủy câu hỏi này?">
                        <?= csrfField() ?><input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn--ghost" data-busy-text="Đang hủy…">Hủy câu hỏi</button>
                    </form>
                </section>
            <?php elseif ($q['status'] === 'CLOSED' && $q['helpful'] !== null): ?>
                <section class="card"><p class="ch-small" style="margin:0">Bạn đánh giá: <b><?= (int)$q['helpful'] === 1 ? 'Hữu ích' : 'Chưa hữu ích' ?></b></p></section>
            <?php endif; ?>
        </aside>
    </div>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
