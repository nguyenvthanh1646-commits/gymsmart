<?php
/**
 * admin/coach-reviews.php — ADMIN xem mọi đánh giá HLV và xóa đánh giá vi phạm (A-49).
 * Xóa là form POST có CSRF, có hộp xác nhận; ghi nhật ký coach.review_delete. Không sửa nội dung đánh giá của người dùng.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit-log.php';
require_once __DIR__ . '/../includes/coach.php';
require_once __DIR__ . '/../includes/coach-reviews.php';

requireAdmin();
$ready = coachTablesReady($pdo) && coachReviewsReady($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    try {
        $desc = coachReviewAdminDelete($pdo, coachParseId($_POST['review_id'] ?? null));
        auditLog($pdo, 'coach.review_delete', 'coach', null, 'Xóa đánh giá ' . $desc);
        $_SESSION['flash'] = ['ok', 'Đã xóa đánh giá.'];
    } catch (CoachException $e) {
        $_SESSION['flash'] = ['error', $e->getMessage()];
    }
    header('Location: /admin/coach-reviews.php');
    exit;
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$reviews = $ready ? coachReviewsAdmin($pdo) : [];

$pageTitle = 'Đánh giá HLV';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$admNavActive = 'coaches';
include __DIR__ . '/../includes/admin-nav.php';
?>
<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Đánh giá huấn luyện viên</h1>
            <p class="adm-sub">Chỉ học viên đã tập đủ 14 ngày và có ghi buổi tập mới đánh giá được. Xóa đánh giá vi phạm (xúc phạm, quảng cáo, thông tin cá nhân).</p>
        </div>
        <a class="adm-btn adm-btn-ghost" href="/admin/coaches.php">Danh sách HLV</a>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <div class="adm-panel">
        <?php if (!$ready): ?>
            <div class="adm-empty">Chưa có bảng đánh giá. Chạy <code>sql/coach_reviews.sql</code> (hoặc <code>php tools/install.php --upgrade</code>).</div>
        <?php elseif (!$reviews): ?>
            <div class="adm-empty">Chưa có đánh giá nào.</div>
        <?php else: ?>
            <table class="adm-table">
                <thead><tr><th>HLV</th><th>Học viên</th><th>Sao</th><th>Nhận xét</th><th>Ngày</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td data-label="HLV"><?= htmlspecialchars($r['coach_name']) ?></td>
                            <td data-label="Học viên"><?= htmlspecialchars($r['client_name']) ?></td>
                            <td data-label="Sao"><span class="ch-stars" aria-label="<?= (int)$r['rating'] ?> trên 5 sao"><?= str_repeat('★', (int)$r['rating']) ?></span></td>
                            <td data-label="Nhận xét"><?= $r['comment'] !== '' ? htmlspecialchars($r['comment']) : '<span class="adm-sub">(không có)</span>' ?></td>
                            <td data-label="Ngày"><?= htmlspecialchars(date('d/m/Y', strtotime($r['updated_at']))) ?></td>
                            <td class="adm-cell-actions">
                                <form method="post" action="/admin/coach-reviews.php" data-confirm="Xóa đánh giá này? Không hoàn tác được.">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<script src="/js/coach.js" defer></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
