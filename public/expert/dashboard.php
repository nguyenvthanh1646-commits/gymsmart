<?php
/**
 * expert/dashboard.php — trang chính của chuyên gia (A-55): câu hỏi đang trao đổi, hàng chờ đúng chủ đề, câu đã đóng gần đây.
 * Nhận câu hỏi ngay tại hàng chờ (POST + CSRF, healthAction kiểm tra chủ đề, giới hạn, và chỉ 1 chuyên gia nhận được).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/expert.php';

requireExpert();
$expertId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'claim') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $qid = coachParseId($_POST['question_id'] ?? null);
    try {
        healthAction($pdo, 'expert', $expertId, $qid, 'claim');
        coachFlash('ok', 'Đã nhận câu hỏi. Người hỏi được báo là bạn sẽ trả lời.');
        header('Location: /expert/question.php?id=' . $qid);
        exit;
    } catch (ExpertException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: /expert/dashboard.php');
    exit;
}

$flash = coachTakeFlash();
$profile = expertProfile($pdo, $expertId);
$pool = healthPool($pdo, $expertId);
$mine = healthMine($pdo, $expertId);

$pageTitle = 'Khu chuyên gia';
$pageCss = ['/css/coach.css', '/css/consult.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$expertNavActive = 'dashboard';
include __DIR__ . '/../includes/expert-nav.php';
?>
<div class="adm-wrap">
    <section class="adm-hero">
        <div>
            <span class="adm-hero__chip"><?= htmlspecialchars(EXPERT_PROFESSIONS[$profile['profession'] ?? ''] ?? 'Chuyên gia') ?></span>
            <h1>Xin chào, <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></h1>
            <p><?= $profile && (int)$profile['accepting'] === 1 ? 'Bạn đang nhận câu hỏi về: ' . htmlspecialchars(implode(', ', healthTopicLabels((string)$profile['topics']))) . ' và Vấn đề khác.' : 'Bạn đang tạm ngừng nhận câu hỏi mới.' ?></p>
        </div>
        <div class="adm-hero__cta"><a class="adm-btn adm-btn-light" href="/expert/profile.php">Sửa hồ sơ</a></div>
    </section>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <div class="adm-hint-box">Nhắc lại quy tắc: chỉ tư vấn để tham khảo, không chẩn đoán xác định, không kê đơn thuốc. Có dấu hiệu nguy hiểm thì khuyên gọi 115 hoặc đi khám ngay. Người hỏi chỉ hiện tên, không có email hay số điện thoại.</div>

    <section class="adm-panel adm-card-pad" style="margin-bottom:16px" aria-labelledby="h-mine">
        <h2 id="h-mine">Đang trao đổi (<?= count($mine['claimed']) ?>/<?= EXPERT_CLAIM_MAX ?>)</h2>
        <?php if (!$mine['claimed']): ?>
            <p class="adm-sub">Bạn chưa nhận câu hỏi nào. Chọn ở hàng chờ bên dưới.</p>
        <?php else: ?>
            <ul class="hq-list">
                <?php foreach ($mine['claimed'] as $q): ?>
                    <li class="hq-item">
                        <div class="hq-item__main">
                            <a href="/expert/question.php?id=<?= (int)$q['id'] ?>"><?= htmlspecialchars($q['title']) ?></a>
                            <small><?= htmlspecialchars($q['asker_name']) ?> · <?= htmlspecialchars(HEALTH_TOPICS[$q['topic']] ?? '') ?> · nhận <?= htmlspecialchars(mb_strtolower(coachTimeAgo((string)$q['claimed_at']))) ?></small>
                        </div>
                        <div class="hq-item__side"><?php if ((int)$q['unread'] > 0): ?><span class="ch-badge ch-badge--accent"><?= (int)$q['unread'] ?> tin mới</span><?php endif; ?><?= healthStatusBadge($q['status']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="adm-panel adm-card-pad" style="margin-bottom:16px" aria-labelledby="h-pool">
        <h2 id="h-pool">Hàng chờ đúng chủ đề (<?= count($pool) ?>)</h2>
        <?php if (!$pool): ?>
            <p class="adm-sub">Không có câu hỏi nào đang chờ.</p>
        <?php else: ?>
            <ul class="hq-list">
                <?php foreach ($pool as $q): ?>
                    <li class="hq-item">
                        <div class="hq-item__main">
                            <a href="/expert/question.php?id=<?= (int)$q['id'] ?>"><?= htmlspecialchars($q['title']) ?></a>
                            <small><?= htmlspecialchars($q['asker_name']) ?> · <?= htmlspecialchars(HEALTH_TOPICS[$q['topic']] ?? '') ?> · gửi <?= htmlspecialchars(mb_strtolower(coachTimeAgo((string)$q['created_at']))) ?>
                                <?= $q['profile_snapshot'] !== null ? ' · có số liệu hồ sơ' : '' ?></small>
                            <?php if ($q['red_flags'] !== ''): ?><small><span class="ch-badge ch-badge--danger">Có dấu hiệu cần lưu ý: <?= htmlspecialchars($q['red_flags']) ?></span></small><?php endif; ?>
                        </div>
                        <div class="hq-item__side">
                            <a class="adm-btn adm-btn-ghost adm-btn-sm" href="/expert/question.php?id=<?= (int)$q['id'] ?>">Xem</a>
                            <form method="post" action="/expert/dashboard.php">
                                <?= csrfField() ?><input type="hidden" name="action" value="claim"><input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                                <button type="submit" class="adm-btn adm-btn-sm" data-busy-text="Đang nhận…"<?= ($profile && (int)$profile['accepting'] !== 1) ? ' disabled' : '' /* audit:ok chữ viết sẵn */ ?>>Nhận câu hỏi</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($mine['closed']): ?>
        <section class="adm-panel adm-card-pad" aria-labelledby="h-closed">
            <h2 id="h-closed">Đã đóng gần đây</h2>
            <ul class="hq-list">
                <?php foreach ($mine['closed'] as $q): ?>
                    <li class="hq-item">
                        <div class="hq-item__main">
                            <a href="/expert/question.php?id=<?= (int)$q['id'] ?>"><?= htmlspecialchars($q['title']) ?></a>
                            <small><?= htmlspecialchars($q['asker_name']) ?> · đóng <?= htmlspecialchars(mb_strtolower(coachTimeAgo((string)$q['closed_at']))) ?></small>
                        </div>
                        <div class="hq-item__side">
                            <?php if ($q['helpful'] !== null): ?><span class="ch-badge <?= (int)$q['helpful'] === 1 ? 'ch-badge--ok' : '' /* audit:ok tên class viết sẵn */ ?>"><?= (int)$q['helpful'] === 1 ? 'Hữu ích' : 'Chưa hữu ích' ?></span><?php endif; ?>
                            <?= healthStatusBadge($q['status']) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
