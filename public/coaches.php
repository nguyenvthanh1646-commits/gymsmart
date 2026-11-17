<?php
/**
 * coaches.php — trang "Huấn luyện viên" của người tập: danh sách HLV, gửi yêu cầu kèm lời nhắn, hủy yêu cầu.
 *
 * Quy tắc (kiểm tra ở PHP trong coachRequest/coachChangeRelation, nút bị khóa chỉ để dễ hiểu):
 *   - Mỗi học viên chỉ đăng ký 1 HLV (đang chờ hoặc đang tập). DB còn chặn thêm bằng UNIQUE(open_client_id).
 *   - HLV tạm ngừng nhận hoặc đã đủ học viên thì không gửi được.
 *   - HLV và ADMIN xem được danh sách nhưng không gửi yêu cầu.
 * Danh sách HLV chỉ hiện thông tin công khai trong hồ sơ HLV, không có email.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/coach.php';

$userId = (int)$_SESSION['user_id'];
$ready  = coachTablesReady($pdo);
coachHandleClientAction($pdo, '/coaches.php');

$role    = coachUserRole($pdo, $userId) ?? 'USER';
$flash   = coachTakeFlash();
$coaches = $ready ? coachListPublic($pdo) : [];
$openRel = ($ready && $role === 'USER') ? coachOpenRelationForClient($pdo, $userId) : null;
$canAsk  = $ready && $role === 'USER' && $openRel === null;
$app     = ($ready && $role === 'USER') ? coachLatestApplication($pdo, $userId) : null;

$pageTitle = 'Huấn luyện viên';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/includes/header.php';
?>
<div class="ch">
    <header class="pg-head">
        <div>
            <h1>Huấn luyện viên</h1>
            <p class="pg-sub">Chọn một HLV để được giao lịch tập, đặt mục tiêu dinh dưỡng, nhận xét buổi tập và nhắn tin trao đổi. Bạn có thể ngừng bất cứ lúc nào.</p>
        </div>
        <?php if ($role === 'USER' && $openRel === null): ?>
            <a class="btn btn--ghost" href="/coach-apply.php"><?= ($app && $app['status'] === 'PENDING') ? 'Xem đơn HLV của bạn' : 'Đăng ký làm HLV' ?></a>
        <?php elseif ($role === 'COACH'): ?>
            <a class="btn" href="/coach/dashboard.php">Vào khu HLV</a>
        <?php endif; ?>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert--<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <?php if (!$ready): ?>
        <div class="ch-empty">
            <div class="ch-empty__ico" aria-hidden="true">!</div>
            <h2>Tính năng HLV chưa được bật</h2>
            <p>Cơ sở dữ liệu chưa có bảng của phân hệ HLV. Quản trị viên cần import <code>sql/coach_features.sql</code>.</p>
        </div>
    <?php else: ?>

        <?php if ($openRel !== null): ?>
            <section class="card ch-status" style="margin-bottom:var(--s-5)">
                <?= coachAvatar($openRel['coach_avatar'], $openRel['coach_name'], 56) ?>
                <div style="flex:1;min-width:0">
                    <?php if ($openRel['status'] === 'PENDING'): ?>
                        <span class="ch-badge ch-badge--warn">Chờ HLV duyệt</span>
                        <h2 style="margin-top:8px">Bạn đã gửi yêu cầu tới <?= htmlspecialchars($openRel['coach_name']) ?></h2>
                        <p>Gửi lúc <?= htmlspecialchars(coachTimeAgo($openRel['created_at'])) ?>. Trong lúc chờ, bạn không gửi yêu cầu tới HLV khác được.</p>
                        <form method="post" action="/coaches.php" data-confirm="Hủy yêu cầu gửi tới HLV này?">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="relation_id" value="<?= (int)$openRel['id'] ?>">
                            <button type="submit" class="btn btn--ghost" data-busy-text="Đang hủy…">Hủy yêu cầu</button>
                        </form>
                    <?php else: ?>
                        <span class="ch-badge ch-badge--ok">Đang tập</span>
                        <h2 style="margin-top:8px">Bạn đang tập với <?= htmlspecialchars($openRel['coach_name']) ?></h2>
                        <p>Xem nhận xét, nhắn tin và các yêu cầu sửa lịch ở trang HLV của tôi.</p>
                        <a class="btn" href="/my-coach.php">HLV của tôi</a>
                    <?php endif; ?>
                </div>
            </section>
        <?php elseif ($role === 'COACH'): ?>
            <div class="alert alert--note" role="note"><div>Bạn đang là huấn luyện viên nên không đăng ký tập với HLV khác. Đây là cách học viên nhìn thấy danh sách.</div></div>
        <?php elseif ($role === 'ADMIN'): ?>
            <div class="alert alert--note" role="note"><div>Tài khoản quản trị chỉ xem danh sách, không đăng ký HLV.</div></div>
        <?php endif; ?>

        <?php if (!$coaches): ?>
            <div class="ch-empty">
                <div class="ch-empty__ico" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.6 3.7-5.5 7-5.5s6.2 1.9 7 5.5"/></svg>
                </div>
                <h2>Chưa có huấn luyện viên nào</h2>
                <p>Khi quản trị viên duyệt đơn đăng ký HLV, danh sách sẽ hiện ở đây. Bạn có kinh nghiệm huấn luyện? Hãy nộp đơn.</p>
                <?php if ($role === 'USER'): ?><a class="btn" href="/coach-apply.php">Đăng ký làm HLV</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="ch-grid">
                <?php foreach ($coaches as $c):
                    $isMine = $openRel !== null && (int)$openRel['coach_id'] === (int)$c['id'];
                    $formId = 'req-' . (int)$c['id'];
                ?>
                    <article class="ch-coach<?= $c['can_request'] ? '' : ' is-full' ?>">
                        <div class="ch-coach__head">
                            <?= coachAvatar($c['avatar'], $c['full_name'], 56) ?>
                            <div>
                                <h2><?= htmlspecialchars($c['full_name']) ?></h2>
                                <p><?= htmlspecialchars($c['headline']) ?></p>
                            </div>
                        </div>
                        <div class="ch-tags">
                            <?php foreach (coachSpecialtyLabels($c['specialties']) as $label): ?>
                                <span class="ch-tag"><?= htmlspecialchars($label) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <dl class="ch-coach__facts">
                            <div><dt>năm kinh nghiệm</dt><dd><?= (int)$c['experience_years'] ?></dd></div>
                            <div><dt>học viên</dt><dd><?= (int)$c['active_clients'] ?></dd></div>
                            <div><dt>chỗ còn</dt><dd><?= (int)$c['seats_left'] ?></dd></div>
                        </dl>
                        <p class="ch-coach__bio"><?= htmlspecialchars($c['bio']) ?></p>
                        <details>
                            <summary>Phương pháp, chứng chỉ, thời gian</summary>
                            <p><b>Phương pháp:</b> <?= htmlspecialchars($c['approach'] !== '' ? $c['approach'] : 'Chưa ghi.') ?></p>
                            <p><b>Chứng chỉ:</b> <?= htmlspecialchars($c['certifications'] !== '' ? $c['certifications'] : 'Không ghi chứng chỉ.') ?></p>
                            <?php if ($c['availability'] !== ''): ?><p><b>Thời gian hỗ trợ:</b> <?= htmlspecialchars($c['availability']) ?></p><?php endif; ?>
                        </details>
                        <div class="ch-coach__act">
                            <?php if ($isMine): ?>
                                <span class="ch-badge ch-badge--accent" style="justify-self:start"><?= $openRel['status'] === 'ACTIVE' ? 'HLV của bạn' : 'Đã gửi yêu cầu' ?></span>
                            <?php elseif (!$c['can_request']): ?>
                                <button type="button" class="btn btn--ghost" disabled><?= (int)$c['accepting'] !== 1 ? 'Tạm ngừng nhận học viên' : 'Đã đủ học viên' ?></button>
                            <?php elseif ($canAsk): ?>
                                <button type="button" class="btn" data-toggle="<?= $formId ?>">Đăng ký tập với HLV này</button>
                                <form id="<?= $formId ?>" class="ch-req" method="post" action="/coaches.php" hidden>
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="request">
                                    <input type="hidden" name="coach_id" value="<?= (int)$c['id'] ?>">
                                    <label class="ch-label" for="note-<?= (int)$c['id'] ?>">Lời nhắn cho HLV (không bắt buộc)</label>
                                    <textarea id="note-<?= (int)$c['id'] ?>" name="note" maxlength="<?= COACH_NOTE_MAX ?>"
                                              placeholder="Mục tiêu, lịch rảnh, chấn thương nếu có…"></textarea>
                                    <span class="ch-count" data-count="note-<?= (int)$c['id'] ?>"></span>
                                    <p class="ch-muted ch-small" style="margin:6px 0 0">HLV sẽ xem được hồ sơ tập luyện, lịch tập, nhật ký và số đo của bạn (không xem email). Mỗi người chỉ đăng ký 1 HLV.</p>
                                    <div class="ch-req__row">
                                        <button type="submit" class="btn" data-busy-text="Đang gửi…">Gửi yêu cầu</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn btn--ghost" disabled><?= $role === 'USER' ? 'Bạn đã đăng ký một HLV' : 'Chỉ người tập gửi yêu cầu' ?></button>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
