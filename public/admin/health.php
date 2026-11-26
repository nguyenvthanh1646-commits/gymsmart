<?php
/**
 * admin/health.php — Sức khỏe hệ thống (G2, A-51): kiểm tra PHP, database, múi giờ, cấu hình Gemini, thư mục ghi file.
 * Chỉ ADMIN, chỉ ĐỌC. Không hiển thị giá trị bí mật (khóa Gemini, mật khẩu DB), chỉ báo đã/chưa cấu hình.
 * Nút "Tải bản sao lưu" gửi POST + CSRF sang admin/backup.php.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/health.php';

requireAdmin();

$checks = healthChecks($pdo, dirname(__DIR__, 2));
$overall = healthOverall($checks);
$groups = [];
foreach ($checks as $c) {
    $groups[$c['group']][] = $c;
}
$statusText = ['ok' => 'Ổn', 'warn' => 'Cần xem', 'fail' => 'Lỗi'];
$flash = $_SESSION['adm_health_flash'] ?? null;
unset($_SESSION['adm_health_flash']);

$pageTitle = 'Sức khỏe hệ thống';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
include __DIR__ . '/../includes/admin-style-extra.php';
$admNavActive = 'health';
include __DIR__ . '/../includes/admin-nav.php';
?>
<style>
.hl-sum{display:flex;align-items:center;gap:14px;padding:18px 20px;border-radius:14px;margin-bottom:18px;font-weight:600}
.hl-sum b{font-size:18px}
.hl-sum--ok{background:#dcfce7;color:#166534}.hl-sum--warn{background:#fef3c7;color:#92400e}.hl-sum--fail{background:#fee2e2;color:#991b1b}
.hl-st{display:inline-block;min-width:68px;padding:3px 10px;border-radius:999px;font-size:13px;font-weight:700;text-align:center}
.hl-st--ok{background:#dcfce7;color:#166534}.hl-st--warn{background:#fef3c7;color:#92400e}.hl-st--fail{background:#fee2e2;color:#991b1b}
.hl-group{margin:0 0 6px;font-size:16px}
.hl-table td:first-child{width:110px;white-space:nowrap}
.adm-wrap > .adm-panel + .adm-panel{margin-top:14px}
.hl-backup p{margin:0 0 10px;color:#475569}
.hl-backup label{display:flex;gap:8px;align-items:flex-start;margin:0 0 12px;font-size:14px}
.hl-backup input[type=checkbox]{width:18px;height:18px;margin-top:2px;flex:none}
</style>
<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Sức khỏe hệ thống</h1>
            <p class="adm-sub">Kiểm tra nhanh máy chủ, database và cấu hình. Tải lại trang để kiểm tra lại.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <div class="hl-sum hl-sum--<?= htmlspecialchars($overall) ?>" role="status">
        <b><?= htmlspecialchars(['ok' => 'Mọi thứ ổn', 'warn' => 'Có mục cần xem', 'fail' => 'Có lỗi cần sửa'][$overall]) ?></b>
        <span><?= count(array_filter($checks, fn($c) => $c['status'] === 'ok')) ?>/<?= count($checks) ?> mục ổn</span>
    </div>

    <?php foreach ($groups as $g => $items): ?>
        <div class="adm-panel adm-card-pad">
            <h2 class="hl-group"><?= htmlspecialchars($g) ?></h2>
            <table class="adm-table hl-table">
                <thead><tr><th>Trạng thái</th><th>Mục</th><th>Chi tiết</th></tr></thead>
                <tbody>
                <?php foreach ($items as $c): ?>
                    <tr>
                        <td data-label="Trạng thái"><span class="hl-st hl-st--<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars($statusText[$c['status']]) ?></span></td>
                        <td data-label="Mục"><?= htmlspecialchars($c['label']) ?></td>
                        <td data-label="Chi tiết"><?= htmlspecialchars($c['detail']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <div class="adm-panel adm-card-pad hl-backup">
        <h2 class="hl-group">Sao lưu database</h2>
        <p>Tải về một file <code>.sql</code> chứa cấu trúc và toàn bộ dữ liệu. Nhập lại bằng tab <b>Import</b> của phpMyAdmin vào một database trống.</p>
        <p>File có <b>mã băm mật khẩu, email và số đo cơ thể</b> của mọi người dùng. Không gửi file qua mạng xã hội, không đưa lên GitHub. Mỗi lần tải được ghi vào Nhật ký hoạt động.</p>
        <form method="post" action="/admin/backup.php">
            <?= csrfField() ?>
            <label><input type="checkbox" name="confirm" value="1" required> Tôi hiểu file này chứa dữ liệu cá nhân và sẽ cất giữ kín.</label>
            <button type="submit" class="adm-btn">Tải bản sao lưu</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
