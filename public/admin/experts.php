<?php
/**
 * admin/experts.php — danh sách chuyên gia sức khỏe và thu hồi vai trò (A-55). CHỈ ADMIN.
 * Thu hồi (expertRevoke, 1 transaction): câu hỏi chuyên gia đang trao đổi trả về hàng chờ, tắt nhận câu hỏi, vai trò về USER.
 * ADMIN không đọc nội dung câu hỏi (dữ liệu sức khỏe riêng tư), chỉ thấy số lượng.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit-log.php';
require_once __DIR__ . '/../includes/expert.php';

requireAdmin();
$ready = expertTablesReady($pdo);

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revoke') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $target = coachParseId($_POST['expert_id'] ?? null);   // tài khoản chuyên gia cần thu hồi (người thao tác lấy từ session)
    try {
        $res = expertRevoke($pdo, $target, (int)$_SESSION['user_id']);
        auditLog($pdo, 'expert.revoke', 'user', $target, 'Thu hồi chuyên gia "' . $res['name'] . '": trả ' . $res['released'] . ' câu hỏi về hàng chờ');
        coachFlash('ok', 'Đã thu hồi vai trò chuyên gia của "' . $res['name'] . '". ' . $res['released'] . ' câu hỏi đang trao đổi được trả về hàng chờ.');
    } catch (ExpertException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: /admin/experts.php');
    exit;
}

$flash = coachTakeFlash();
$experts = $ready ? expertListForAdmin($pdo) : [];
$pendingApps = $ready ? count(expertListApplications($pdo, 'PENDING')) : 0;

$pageTitle = 'Chuyên gia sức khỏe';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
include __DIR__ . '/../includes/admin-style-extra.php';
$admNavActive = 'experts';
include __DIR__ . '/../includes/admin-nav.php';
?>
<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Chuyên gia sức khỏe</h1>
            <p class="adm-sub">Bác sĩ, chuyên gia dinh dưỡng, vật lý trị liệu đã được duyệt. Quản trị viên không đọc nội dung câu hỏi tư vấn.</p>
        </div>
        <a class="adm-btn" href="/admin/expert-applications.php">Đơn đăng ký<?= $pendingApps > 0 ? ' (' . (int)$pendingApps . ' chờ duyệt)' : '' ?></a>
    </div>
    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <?php if (!$ready): ?>
        <div class="adm-hint-box">Chưa có bảng của phần tư vấn sức khỏe. Hãy chạy <b>sql/health_experts.sql</b>.</div>
    <?php else: ?>
    <div class="adm-panel">
        <?php if (!$experts): ?>
            <div class="adm-empty">Chưa có chuyên gia nào.</div>
        <?php else: ?>
        <table class="adm-table">
            <thead><tr><th>Chuyên gia</th><th>Ngành · chức danh</th><th>Số chứng chỉ</th><th>Đang trao đổi</th><th>Đã đóng</th><th>Nhận câu hỏi</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($experts as $e): ?>
                <tr>
                    <td class="adm-cell-name"><a class="adm-link adm-name" href="/admin/user-detail.php?id=<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?></a><br><small class="adm-sub"><?= htmlspecialchars($e['email']) ?></small></td>
                    <td data-label="Ngành"><?= htmlspecialchars(EXPERT_PROFESSIONS[$e['profession']] ?? '') ?><br><small class="adm-sub"><?= htmlspecialchars($e['title']) ?> · <?= htmlspecialchars($e['workplace']) ?></small></td>
                    <td data-label="Số chứng chỉ" class="adm-mono"><?= htmlspecialchars($e['license_no']) ?></td>
                    <td data-label="Đang trao đổi"><?= (int)$e['claimed'] ?></td>
                    <td data-label="Đã đóng"><?= (int)$e['closed'] ?></td>
                    <td data-label="Nhận câu hỏi"><?= (int)$e['accepting'] === 1 ? 'Có' : 'Tạm nghỉ' ?></td>
                    <td class="adm-cell-actions"><div class="adm-actions">
                        <form method="post" action="/admin/experts.php" data-confirm="Thu hồi vai trò chuyên gia của <?= htmlspecialchars($e['full_name']) ?>? Câu hỏi đang trao đổi sẽ trả về hàng chờ.">
                            <?= csrfField() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="expert_id" value="<?= (int)$e['id'] ?>">
                            <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm" data-busy-text="Đang xử lý…">Thu hồi</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
