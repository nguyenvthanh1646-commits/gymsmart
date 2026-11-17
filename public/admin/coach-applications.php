<?php
/**
 * admin/coach-applications.php — duyệt đơn đăng ký làm huấn luyện viên.
 *   ?status=PENDING|APPROVED|REJECTED|all  danh sách (mặc định: đơn đang chờ)
 *   ?id=...                               xem đầy đủ một đơn, duyệt hoặc từ chối (từ chối phải ghi lý do)
 *
 * Duyệt = đổi users.role thành COACH + tạo hồ sơ HLV, làm trong 1 transaction (coachApproveApplication).
 * Mỗi lần duyệt/từ chối ghi admin_audit_log. Chỉ ADMIN (requireAdmin đọc lại vai trò trong DB).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit-log.php';
require_once __DIR__ . '/../includes/coach.php';

requireAdmin();
$ready = coachTablesReady($pdo);
$adminId = (int)$_SESSION['user_id'];

// ---------- Xử lý duyệt / từ chối (POST → chuyển hướng lại, mẫu Post/Redirect/Get) ----------
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $appId  = coachParseId($_POST['id'] ?? null);
    $action = $_POST['action'] ?? '';
    $note   = coachCleanText($_POST['note'] ?? '');
    try {
        if ($e = coachTextError($note, 'Ghi chú', 0, COACH_NOTE_MAX)) {
            throw new CoachException($e);
        }
        if ($action === 'approve') {
            $name = coachApproveApplication($pdo, $appId, $adminId, $note);
            auditLog($pdo, 'coach.approve', 'coach_application', $appId, 'Duyệt đơn HLV của "' . $name . '"');
            coachFlash('ok', 'Đã duyệt. "' . $name . '" giờ là huấn luyện viên.');
        } elseif ($action === 'reject') {
            $name = coachRejectApplication($pdo, $appId, $adminId, $note);
            auditLog($pdo, 'coach.reject', 'coach_application', $appId, 'Từ chối đơn HLV của "' . $name . '"');
            coachFlash('ok', 'Đã từ chối đơn của "' . $name . '". Người nộp sẽ thấy lý do.');
        } else {
            throw new CoachException('Thao tác không hợp lệ.');
        }
    } catch (CoachException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: /admin/coach-applications.php?id=' . $appId);
    exit;
}

$flash = coachTakeFlash();
$viewId = coachParseId($_GET['id'] ?? null);
$app = ($ready && $viewId > 0) ? coachFindApplication($pdo, $viewId) : null;
$status = strtoupper((string)($_GET['status'] ?? 'PENDING'));
if (!isset(COACH_APPLICATION_LABELS[$status])) {
    $status = '';   // "all" hoặc giá trị lạ → tất cả
}
$list = ($ready && $viewId === 0) ? coachListApplications($pdo, $status) : [];
$conflict = ($app !== null && $app['status'] === 'PENDING') ? coachOpenRelationForClient($pdo, (int)$app['user_id']) : null;
$badgeClass = ['PENDING' => 'adm-b-PENDING', 'APPROVED' => 'adm-b-APPROVED', 'REJECTED' => 'adm-b-REJECTED'];

$pageTitle = $app ? 'Đơn đăng ký HLV' : 'Đơn đăng ký HLV';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
include __DIR__ . '/../includes/admin-style-extra.php';
$admNavActive = 'coaches';
include __DIR__ . '/../includes/admin-nav.php';
?>
<div class="adm-wrap">
<?php if (!$ready): ?>
    <div class="adm-hint-box">Chưa có bảng của phân hệ HLV. Hãy import <b>sql/coach_features.sql</b> trong phpMyAdmin.</div>

<?php elseif ($viewId > 0): ?>
    <a class="adm-back" href="/admin/coach-applications.php">&larr; Danh sách đơn</a>
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
                <h2>Nội dung đơn</h2>
                <dl class="adm-kv">
                    <div><dt>Chức danh</dt><dd><?= htmlspecialchars($app['headline']) ?></dd></div>
                    <div><dt>Chuyên môn</dt><dd><?= htmlspecialchars(implode(', ', coachSpecialtyLabels($app['specialties']))) ?></dd></div>
                    <div><dt>Kinh nghiệm</dt><dd><?= (int)$app['experience_years'] ?> năm</dd></div>
                    <div><dt>Nhận tối đa</dt><dd><?= (int)$app['max_clients'] ?> học viên</dd></div>
                    <div><dt>Thời gian hỗ trợ</dt><dd><?= $app['availability'] !== '' ? htmlspecialchars($app['availability']) : '<span class="adm-video-no">Không ghi</span>' ?></dd></div>
                </dl>
                <h2 style="margin-top:20px">Chứng chỉ</h2>
                <p class="adm-sub" style="white-space:pre-line"><?= $app['certifications'] !== '' ? htmlspecialchars($app['certifications']) : 'Không ghi chứng chỉ.' ?></p>
                <?php if (coachCertNameOk($app['certificate_file'] ?? null)): ?>
                    <p><a class="adm-btn adm-btn-ghost adm-btn-sm" href="/admin/certificate.php?id=<?= (int)$app['id'] ?>" target="_blank" rel="noopener">Xem file chứng chỉ đính kèm (<?= htmlspecialchars(strtoupper(pathinfo($app['certificate_file'], PATHINFO_EXTENSION))) ?>)</a></p>
                <?php endif; ?>
                <h2 style="margin-top:20px">Giới thiệu</h2>
                <p style="white-space:pre-line;margin:0"><?= htmlspecialchars($app['bio']) ?></p>
                <h2 style="margin-top:20px">Phương pháp huấn luyện</h2>
                <p style="white-space:pre-line;margin:0"><?= htmlspecialchars($app['approach']) ?></p>
            </div>

            <div class="adm-side">
                <?php if ($app['status'] === 'PENDING'): ?>
                    <div class="adm-panel adm-card-pad">
                        <h2>Quyết định</h2>
                        <?php if ($conflict !== null): ?>
                            <div class="adm-hint-box">Người này đang <?= $conflict['status'] === 'ACTIVE' ? 'tập với' : 'chờ' ?> HLV "<?= htmlspecialchars($conflict['coach_name']) ?>". Không duyệt được cho tới khi họ kết thúc.</div>
                        <?php endif; ?>
                        <p class="adm-hint">Trước khi duyệt: đọc kỹ chứng chỉ và giới thiệu; nếu cần, liên hệ người nộp qua email để xác minh.</p>
                        <form method="post" action="/admin/coach-applications.php" id="adm-decide">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
                            <div class="adm-field">
                                <label for="note">Ghi chú gửi người nộp</label>
                                <textarea class="adm-textarea" id="note" name="note" maxlength="<?= COACH_NOTE_MAX ?>" placeholder="Bắt buộc khi từ chối: nêu rõ cần bổ sung gì."></textarea>
                            </div>
                            <div class="adm-actions" style="justify-content:stretch">
                                <button type="submit" name="action" value="reject" class="adm-btn adm-btn-danger" data-need-note>Từ chối</button>
                                <button type="submit" name="action" value="approve" class="adm-btn"<?= $conflict !== null ? ' disabled' : '' ?>>Duyệt làm HLV</button>
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
                        <?php if ($app['admin_note'] !== ''): ?>
                            <p class="adm-sub" style="white-space:pre-line;margin-top:12px"><?= htmlspecialchars($app['admin_note']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="adm-head">
        <div>
            <h1>Đơn đăng ký HLV</h1>
            <p class="adm-sub">Duyệt người dùng muốn trở thành huấn luyện viên.</p>
        </div>
        <a class="adm-btn adm-btn-ghost" href="/admin/coaches.php">Danh sách HLV</a>
    </div>
    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <div class="adm-todo" role="group" aria-label="Lọc theo trạng thái">
        <?php foreach (['PENDING' => 'Đang chờ', 'APPROVED' => 'Đã duyệt', 'REJECTED' => 'Từ chối', 'all' => 'Tất cả'] as $key => $label): ?>
            <a href="/admin/coach-applications.php?status=<?= htmlspecialchars($key) ?>"<?= ($status === $key || ($key === 'all' && $status === '')) ? ' aria-current="page" class="is-on"' : '' ?>><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="adm-panel">
        <?php if (!$list): ?>
            <div class="adm-empty">Không có đơn nào<?= $status === 'PENDING' ? ' đang chờ duyệt' : '' ?>.</div>
        <?php else: ?>
        <table class="adm-table">
            <thead><tr><th>Người nộp</th><th>Chức danh</th><th>Kinh nghiệm</th><th>Trạng thái</th><th>Ngày nộp</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($list as $a): ?>
                <tr>
                    <td class="adm-cell-name adm-name"><?= htmlspecialchars($a['full_name']) ?></td>
                    <td data-label="Chức danh"><?= htmlspecialchars($a['headline']) ?></td>
                    <td data-label="Kinh nghiệm"><?= (int)$a['experience_years'] ?> năm</td>
                    <td data-label="Trạng thái"><span class="adm-badge <?= $badgeClass[$a['status']] ?? '' ?>"><?= htmlspecialchars(COACH_APPLICATION_LABELS[$a['status']] ?? $a['status']) ?></span></td>
                    <td data-label="Ngày nộp"><?= htmlspecialchars(date('d/m/Y', strtotime($a['created_at']))) ?></td>
                    <td class="adm-cell-actions"><div class="adm-actions"><a class="adm-btn adm-btn-ghost adm-btn-sm" href="/admin/coach-applications.php?id=<?= (int)$a['id'] ?>"><?= $a['status'] === 'PENDING' ? 'Xem và duyệt' : 'Xem' ?></a></div></td>
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
        } else if (btn && btn.value === 'approve' && !confirm('Duyệt người này làm huấn luyện viên?')) {
            e.preventDefault();
        }
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
