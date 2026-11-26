<?php
/**
 * consult.php — "Tư vấn sức khỏe" phía người dùng (A-55): câu hỏi của tôi, đội ngũ chuyên gia, nút đặt câu hỏi.
 * Câu hỏi là riêng tư: chỉ người hỏi và chuyên gia nhận câu hỏi xem được (ADMIN cũng không đọc).
 * Danh sách chuyên gia không hiện số chứng chỉ hành nghề hay email, chỉ nhãn "Đã xác minh chứng chỉ" (ADMIN đã duyệt đơn).
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/expert.php';

$userId = (int)$_SESSION['user_id'];
refreshSessionRole($pdo);
$role = $_SESSION['role'] ?? 'USER';
$ready = expertTablesReady($pdo);
$canAsk = $ready && ($role === 'USER' || $role === 'COACH');
$flash = coachTakeFlash();
$questions = $ready ? healthQuestionsForAsker($pdo, $userId) : [];   // cả câu hỏi cũ gửi trước khi thành chuyên gia
$experts = $ready ? expertListPublic($pdo) : [];
$openCount = count(array_filter($questions, fn($q) => in_array($q['status'], ['OPEN', 'CLAIMED'], true)));
$app = ($ready && $role === 'USER') ? expertLatestApplication($pdo, $userId) : null;

$pageTitle = 'Tư vấn sức khỏe';
$pageCss = ['/css/coach.css', '/css/consult.css'];
include __DIR__ . '/includes/header.php';
?>
<div class="ch">
    <header class="pg-head">
        <div>
            <h1>Tư vấn sức khỏe</h1>
            <p class="pg-sub">Hỏi riêng chuyên gia sức khỏe (bác sĩ, chuyên gia dinh dưỡng, vật lý trị liệu) về chấn thương, bệnh nền khi tập, dinh dưỡng, phục hồi. Miễn phí, chỉ bạn và chuyên gia nhận câu hỏi xem được.</p>
        </div>
        <?php if ($canAsk): ?>
            <a class="btn" href="/consult-ask.php">Đặt câu hỏi</a>
        <?php elseif ($role === 'EXPERT'): ?>
            <a class="btn" href="/expert/dashboard.php">Vào khu chuyên gia</a>
        <?php endif; ?>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert--<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <?= healthSafetyBox() ?>

    <?php if (!$ready): ?>
        <div class="ch-empty">
            <div class="ch-empty__ico" aria-hidden="true">!</div>
            <h2>Tính năng tư vấn chưa được bật</h2>
            <p>Quản trị viên cần chạy <code>sql/health_experts.sql</code> (hoặc <code>php tools/install.php --upgrade</code>).</p>
        </div>
    <?php else: ?>

        <?php if ($canAsk || $questions): ?>
        <section class="card" style="margin-bottom:var(--s-5)" aria-labelledby="h-mine">
            <h2 class="card__head" id="h-mine">Câu hỏi của tôi</h2>
            <p class="card__sub">Tối đa <?= HEALTH_Q_OPEN_MAX ?> câu chưa đóng cùng lúc (đang có <?= (int)$openCount ?>). Trao đổi xong, hãy đóng câu hỏi để hỏi tiếp.</p>
            <?php if (!$questions): ?>
                <p class="ch-muted" style="margin:0">Bạn chưa gửi câu hỏi nào.</p>
            <?php else: ?>
                <ul class="hq-list">
                    <?php foreach ($questions as $q): ?>
                        <li class="hq-item">
                            <div class="hq-item__main">
                                <a href="/consult-question.php?id=<?= (int)$q['id'] ?>"><?= htmlspecialchars($q['title']) ?></a>
                                <small><?= htmlspecialchars(HEALTH_TOPICS[$q['topic']] ?? $q['topic']) ?> · gửi <?= htmlspecialchars(mb_strtolower(coachTimeAgo($q['created_at']))) ?>
                                    <?= $q['expert_name'] ? ' · ' . htmlspecialchars($q['expert_name']) : '' ?></small>
                            </div>
                            <div class="hq-item__side">
                                <?php if ((int)$q['unread'] > 0): ?><span class="ch-badge ch-badge--accent"><?= (int)$q['unread'] ?> trả lời mới</span><?php endif; ?>
                                <?= healthStatusBadge($q['status']) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php elseif ($role === 'ADMIN'): ?>
            <div class="alert alert--note" role="note"><div>Tài khoản quản trị không gửi và không đọc câu hỏi tư vấn (dữ liệu sức khỏe riêng tư). Quản lý chuyên gia ở khu quản trị.</div></div>
        <?php endif; ?>

        <section aria-labelledby="h-experts">
            <h2 id="h-experts" style="margin:0 0 var(--s-3)">Đội ngũ chuyên gia</h2>
            <?php if (!$experts): ?>
                <p class="ch-muted">Chưa có chuyên gia nào. Câu hỏi của bạn vẫn được lưu và chuyên gia sẽ nhận khi có.</p>
            <?php else: ?>
                <div class="hq-experts">
                    <?php foreach ($experts as $e): ?>
                        <article class="hq-expert">
                            <div class="ch-coach__head">
                                <?= coachAvatar($e['avatar'], $e['full_name'], 48) ?>
                                <div>
                                    <h3><?= htmlspecialchars($e['full_name']) ?></h3>
                                    <p class="hq-expert__meta"><?= htmlspecialchars(EXPERT_PROFESSIONS[$e['profession']] ?? '') ?> · <?= (int)$e['experience_years'] ?> năm</p>
                                </div>
                            </div>
                            <p><b><?= htmlspecialchars($e['title']) ?></b><br><span class="ch-muted"><?= htmlspecialchars($e['workplace']) ?></span></p>
                            <?php if ((int)$e['verified'] > 0): ?>
                                <span class="hq-verified">✓ Quản trị viên đã kiểm tra chứng chỉ hành nghề</span>
                            <?php else: ?>
                                <span class="ch-badge ch-badge--warn" style="justify-self:start">Hồ sơ mẫu, chưa kiểm tra chứng chỉ</span>
                            <?php endif; ?>
                            <div class="ch-tags">
                                <?php foreach (healthTopicLabels($e['topics']) as $label): ?><span class="ch-tag"><?= htmlspecialchars($label) ?></span><?php endforeach; ?>
                            </div>
                            <p><?= htmlspecialchars($e['bio']) ?></p>
                            <p class="hq-expert__meta">
                                <?= (int)$e['answered'] ?> câu hỏi đã trả lời<?php if ((int)$e['helpful_rated'] > 0): ?> · <?= (int)round($e['helpful_yes'] / $e['helpful_rated'] * 100) ?>% thấy hữu ích<?php endif; ?>
                                <?= (int)$e['accepting'] !== 1 ? ' · <b>Đang tạm nghỉ</b>' : '' /* audit:ok chữ viết sẵn */ ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($role === 'USER'): ?>
            <p class="ch-muted ch-small" style="margin-top:var(--s-5)">Bạn là bác sĩ, chuyên gia dinh dưỡng hoặc kỹ thuật viên vật lý trị liệu có chứng chỉ hành nghề?
                <a href="/expert-apply.php"><?= ($app && $app['status'] === 'PENDING') ? 'Xem đơn của bạn' : 'Đăng ký làm chuyên gia' ?></a></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
