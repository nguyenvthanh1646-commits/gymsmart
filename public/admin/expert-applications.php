<?php
/**
 * admin/expert-applications.php — duyệt đơn đăng ký chuyên gia sức khỏe (A-55).
 *   ?status=PENDING|APPROVED|REJECTED|all  danh sách (mặc định: đơn đang chờ)
 *   ?id=...                               xem đầy đủ một đơn (có số chứng chỉ và file chứng chỉ), duyệt hoặc từ chối
 * Duyệt = đổi users.role thành EXPERT + tạo hồ sơ chuyên gia, trong 1 transaction (expertApproveApplication). Ghi admin_audit_log.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit-log.php';
require_once __DIR__ . '/../includes/expert.php';

requireAdmin();
$ready = expertTablesReady($pdo);
$adminId = (int)$_SESSION['user_id'];

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $appId = coachParseId($_POST['id'] ?? null);
    $action = $_POST['action'] ?? '';
    $note = coachCleanText($_POST['note'] ?? '');
    try {
        if ($e = coachTextError($note, 'Ghi chú', 0, COACH_NOTE_MAX)) {
            throw new ExpertException($e);
        }
        if ($action === 'approve') {
            $name = expertApproveApplication($pdo, $appId, $adminId, $note);
            auditLog($pdo, 'expert.approve', 'expert_application', $appId, 'Duyệt đơn chuyên gia của "' . $name . '"');
            coachFlash('ok', 'Đã duyệt. "' . $name . '" giờ là chuyên gia sức khỏe.');
        } elseif ($action === 'reject') {
            $name = expertRejectApplication($pdo, $appId, $adminId, $note);
            auditLog($pdo, 'expert.reject', 'expert_application', $appId, 'Từ chối đơn chuyên gia của "' . $name . '"');
            coachFlash('ok', 'Đã từ chối đơn của "' . $name . '". Người nộp sẽ thấy lý do.');
        } else {
            throw new ExpertException('Thao tác không hợp lệ.');
        }
    } catch (ExpertException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: /admin/expert-applications.php?id=' . $appId);
    exit;
}

$flash = coachTakeFlash();
$viewId = coachParseId($_GET['id'] ?? null);
$app = ($ready && $viewId > 0) ? expertFindApplication($pdo, $viewId) : null;
$status = strtoupper((string)($_GET['status'] ?? 'PENDING'));
if (!isset(COACH_APPLICATION_LABELS[$status])) {
    $status = '';
}
$list = ($ready && $viewId === 0) ? expertListApplications($pdo, $status) : [];
$badgeClass = ['PENDING' => 'adm-b-PENDING', 'APPROVED' => 'adm-b-APPROVED', 'REJECTED' => 'adm-b-REJECTED'];

$pageTitle = 'Đơn đăng ký chuyên gia';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
include __DIR__ . '/../includes/admin-style-extra.php';
$admNavActive = 'experts';
include __DIR__ . '/../includes/admin-nav.php';
?>
<div class="adm-wrap">
<?php if (!$ready): ?>
    <div class="adm-hint-box">Chưa có bảng của phần tư vấn sức khỏe. Hãy chạy <b>sql/health_experts.sql</b> (hoặc <code>php tools/install.php --upgrade</code>).</div>

<?php elseif ($viewId > 0): ?>
    <a class="adm-back" href="/admin/expert-applications.php">&larr; Danh sách đơn</a>
    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <?php if ($app === null): ?>
        <div class="adm-panel"><div class="adm-empty">Không tìm thấy đơn này.</div></div>
    <?php else: ?>
        <div class="adm-head">
            <div>
                <h1>Đơn của <?= htmlspecialchars($app['full_name']) ?>
                    <span class="adm-badge <?= $badgeClass[$app['status']] ?? '' ?>"><?= htmlspecialchars(COACH_APPLICATION_LABELS[$app['status']] ?? $app['status']) ?></span></h1>
                <p class="adm-sub"><?= htmlspecialchars($app['email']) ?> · vai trò hiện tại <?= htmlspecialchars($app['role']) ?> · nộp lúc <?= htmlspecialchars(date('H:i d/m/Y', strtotime($app['created_at']))) ?></p>
            </div>
            <a class="adm-btn adm-btn-ghost" href="/admin/user-detail.php?id=<?= (int)$app['user_id'] ?>">Xem tài khoản</a>
        </div>
        <div class="adm-split">
            <div class="adm-panel adm-card-pad">
                <h2>Hành nghề</h2>
                <dl class="adm-kv">
                    <div><dt>Ngành</dt><dd><?= htmlspecialchars(EXPERT_PROFESSIONS[$app['profession']] ?? $app['profession']) ?></dd></div>
                    <div><dt>Chức danh</dt><dd><?= htmlspecialchars($app['title']) ?></dd></div>
                    <div><dt>Nơi công tác</dt><dd><?= htmlspecialchars($app['workplace']) ?></dd></div>
                    <div><dt>Số chứng chỉ hành nghề</dt><dd class="adm-mono"><?= htmlspecialchars($app['license_no']) ?></dd></div>
                    <div><dt>Số năm hành nghề</dt><dd><?= (int)$app['experience_years'] ?></dd></div>
                    <div><dt>Chủ đề</dt><dd><?= htmlspecialchars(implode(', ', healthTopicLabels($app['topics']))) ?></dd></div>
                </dl>
                <?php if (coachCertNameOk($app['certificate_file'] ?? null)): ?>
                    <p style="margin-top:16px"><a class="adm-btn adm-btn-ghost adm-btn-sm" href="/admin/expert-certificate.php?id=<?= (int)$app['id'] ?>" target="_blank" rel="noopener">Xem file chứng chỉ hành nghề (<?= htmlspecialchars(strtoupper(pathinfo($app['certificate_file'], PATHINFO_EXTENSION))) ?>)</a></p>
                <?php else: ?>
                    <p class="adm-sub" style="margin-top:16px">Đơn không có file chứng chỉ.</p>
                <?php endif; ?>
                <h2 style="margin-top:20px">Giới thiệu</h2>
                <p style="white-space:pre-line;margin:0"><?= htmlspecialchars($app['bio']) ?></p>
            </div>
            <div class="adm-side">
                <?php if ($app['status'] === 'PENDING'): ?>
                    <div class="adm-panel adm-card-pad">
                        <h2>Quyết định</h2>
                        <p class="adm-hint">Trước khi duyệt: mở file chứng chỉ, đối chiếu họ tên, số chứng chỉ và phạm vi hành nghề với cơ quan cấp (ví dụ tra cứu của Sở Y tế). Chưa xác minh được thì từ chối và ghi rõ cần bổ sung gì.</p>
                        <form method="post" action="/admin/expert-applications.php" id="adm-decide">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
                            <div class="adm-field">
                                <label for="note">Ghi chú gửi người nộp</label>
                                <textarea class="adm-textarea" id="note" name="note" maxlength="<?= COACH_NOTE_MAX ?>" placeholder="Bắt buộc khi từ chối."></textarea>
                            </div>
                            <div class="adm-actions" style="justify-content:stretch">
                                <button type="submit" name="action" value="reject" class="adm-btn adm-btn-danger" data-need-note>Từ chối</button>
                                <button type="submit" name="action" value="approve" class="adm-btn">Duyệt làm chuyên gia</button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="adm-panel adm-card-pad">
                        <h2>Đã xử lý</h2>
                        <dl class="adm-kv">
                            <div><dt>Kết quả</dt><dd><?= htmlspecialchars(COACH_APPLICATION_LABELS[$app['status']] ?? $app['status']) ?></dd></div>
                            <div><dt>Người xử lý</dt><dd><?= htmlspecialchars($app['reviewer_name'] ?? '—') ?></dd></div>
                            <div><dt>Lúc</dt><dd><?= $app['reviewed_at'] ? htmlspecialchars(date('H:i d/m/Y', strtotime($app['reviewed_at']))) : '—' ?></dd></div>
                        </dl>
                        <?php if ($app['admin_note'] !== ''): ?><p class="adm-sub" style="white-space:pre-line;margin-top:12px"><?= htmlspecialchars($app['admin_note']) ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="adm-head">
        <div>
            <h1>Đơn đăng ký chuyên gia</h1>
            <p class="adm-sub">Duyệt bác sĩ, chuyên gia dinh dưỡng, vật lý trị liệu muốn tư vấn trên GymSmart.</p>
        </div>
        <a class="adm-btn adm-btn-ghost" href="/admin/experts.php">Danh sách chuyên gia</a>
    </div>
    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <div class="adm-todo" role="group" aria-label="Lọc theo trạng thái">
        <?php foreach (['PENDING' => 'Đang chờ', 'APPROVED' => 'Đã duyệt', 'REJECTED' => 'Từ chối', 'all' => 'Tất cả'] as $key => $label): ?>
            <a href="/admin/expert-applications.php?status=<?= htmlspecialchars($key) ?>"<?= ($status === $key || ($key === 'all' && $status === '')) ? ' aria-current="page" class="is-on"' : '' ?>><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="adm-panel">
        <?php if (!$list): ?>
            <div class="adm-empty">Không có đơn nào<?= $status === 'PENDING' ? ' đang chờ duyệt' : '' ?>.</div>
        <?php else: ?>
        <table class="adm-table">
            <thead><tr><th>Người nộp</th><th>Ngành</th><th>Chức danh</th><th>Trạng thái</th><th>Ngày nộp</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($list as $a): ?>
                <tr>
                    <td class="adm-cell-name adm-name"><?= htmlspecialchars($a['full_name']) ?></td>
                    <td data-label="Ngành"><?= htmlspecialchars(EXPERT_PROFESSIONS[$a['profession']] ?? '') ?></td>
                    <td data-label="Chức danh"><?= htmlspecialchars($a['title']) ?></td>
                    <td data-label="Trạng thái"><span class="adm-badge <?= $badgeClass[$a['status']] ?? '' ?>"><?= htmlspecialchars(COACH_APPLICATION_LABELS[$a['status']] ?? $a['status']) ?></span></td>
                    <td data-label="Ngày nộp"><?= htmlspecialchars(date('d/m/Y', strtotime($a['created_at']))) ?></td>
                    <td class="adm-cell-actions"><div class="adm-actions"><a class="adm-btn adm-btn-ghost adm-btn-sm" href="/admin/expert-applications.php?id=<?= (int)$a['id'] ?>"><?= $a['status'] === 'PENDING' ? 'Xem và duyệt' : 'Xem' ?></a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
<script>
// Từ chối mà chưa ghi lý do: nhắc ngay (máy chủ vẫn kiểm tra lại)
(function () {
    var form = document.getElementById('adm-decide');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var btn = e.submitter;
        var note = document.getElementById('note');
        if (btn && btn.hasAttribute('data-need-note') && note.value.trim() === '') {
            e.preventDefault();
            note.focus();
            note.setAttribute('aria-invalid', 'true');
            alert('Hãy ghi lý do từ chối để người nộp biết cần bổ sung gì.');
        } else if (btn && btn.value === 'approve' && !confirm('Đã kiểm tra chứng chỉ hành nghề và duyệt người này làm chuyên gia?')) {
            e.preventDefault();
        }
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
