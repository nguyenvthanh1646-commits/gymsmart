<?php
/**
 * admin/audit-log.php — nhật ký hoạt động của admin: ai làm gì, lúc nào, từ IP nào.
 * Chỉ ADMIN, chỉ ĐỌC (không có nút sửa/xóa log: nhật ký mà sửa được thì không còn đáng tin).
 * Lọc bằng GET: ?action=...&q=...&from=YYYY-MM-DD&to=YYYY-MM-DD&page=N
 * Mọi giá trị lọc đi qua dấu ? của prepare(); phần WHERE chỉ ghép từ các đoạn viết sẵn trong file này.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit-log.php';

requireAdmin();

const AUDIT_PER_PAGE = 25;
$actions = auditActions();

/** Ngày hợp lệ dạng YYYY-MM-DD thì trả về đúng chuỗi đó, ngược lại trả về '' */
function validDate($value): string
{
    if (!is_string($value)) {
        return '';
    }
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : '';
}

$action = $_GET['action'] ?? '';
$action = (is_string($action) && isset($actions[$action])) ? $action : '';   // whitelist
$q      = $_GET['q'] ?? '';
$q      = is_string($q) ? mb_substr(trim($q), 0, 100) : '';
$from   = validDate($_GET['from'] ?? '');
$to     = validDate($_GET['to'] ?? '');
$page   = max(1, (int)filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));

$where = [];
$params = [];
if ($action !== '') {
    $where[] = 'action = ?';
    $params[] = $action;
}
if ($q !== '') {
    // Escape % _ \ để người dùng gõ ký tự đó thì tìm đúng ký tự, không thành ký tự đại diện
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where[] = '(admin_name LIKE ? OR detail LIKE ?)';
    $params[] = $like;
    $params[] = $like;
}
if ($from !== '') {
    $where[] = 'created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'created_at <= ?';
    $params[] = $to . ' 23:59:59';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$missing = false;
$rows = [];
$total = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_log $whereSql"); // audit:ok $whereSql ghép từ đoạn viết sẵn
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $pages = max(1, (int)ceil($total / AUDIT_PER_PAGE));
    $page = min($page, $pages);
    $offset = ($page - 1) * AUDIT_PER_PAGE;

    // LIMIT/OFFSET là số nguyên do ta tự tính (đã ép kiểu int), không đến từ chuỗi người dùng
    $stmt = $pdo->prepare(
        "SELECT created_at, admin_name, action, target_type, target_id, detail, ip
         FROM admin_audit_log $whereSql ORDER BY id DESC LIMIT " . (int)AUDIT_PER_PAGE . ' OFFSET ' . (int)$offset // audit:ok
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    $missing = true; // chưa import sql/admin_features.sql
    $pages = 1;
}

/** Link sang trang $n nhưng giữ nguyên các bộ lọc */
function pageLink(int $n, array $keep): string
{
    return '/admin/audit-log.php?' . http_build_query(array_filter($keep + ['page' => $n], fn($v) => $v !== '' && $v !== null));
}
$keep = ['action' => $action, 'q' => $q, 'from' => $from, 'to' => $to];

$pageTitle = 'Nhật ký hoạt động';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
include __DIR__ . '/../includes/admin-style-extra.php';
$admNavActive = 'audit';
include __DIR__ . '/../includes/admin-nav.php';
?>

<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Nhật ký hoạt động</h1>
            <p class="adm-sub">Mọi thao tác thay đổi dữ liệu của quản trị viên. Chỉ xem, không sửa được.</p>
        </div>
    </div>

    <?php if ($missing): ?>
        <div class="adm-hint-box">Chưa có bảng <b>admin_audit_log</b>. Hãy import file <b>sql/admin_features.sql</b> trong phpMyAdmin.</div>
    <?php endif; ?>

    <div class="adm-panel">
        <form class="adm-filters" method="get" action="/admin/audit-log.php">
            <div class="adm-ff">
                <label class="adm-flabel" for="f-q">Tìm theo tên admin hoặc nội dung</label>
                <input class="adm-input" type="text" id="f-q" name="q" value="<?= htmlspecialchars($q) ?>" maxlength="100" autocomplete="off">
            </div>
            <div class="adm-ff">
                <label class="adm-flabel" for="f-action">Hành động</label>
                <select class="adm-select" id="f-action" name="action">
                    <option value="">Tất cả</option>
                    <?php foreach ($actions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>"<?= $action === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="adm-ff">
                <label class="adm-flabel" for="f-from">Từ ngày</label>
                <input class="adm-input" type="date" id="f-from" name="from" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div class="adm-ff">
                <label class="adm-flabel" for="f-to">Đến ngày</label>
                <input class="adm-input" type="date" id="f-to" name="to" value="<?= htmlspecialchars($to) ?>">
            </div>
            <button class="adm-btn" type="submit">Lọc</button>
        </form>
        <div class="adm-count">Tìm thấy <?= (int)$total ?> bản ghi</div>

        <?php if (empty($rows)): ?>
            <div class="adm-empty"><?= $missing ? 'Chưa có dữ liệu.' : 'Không có bản ghi nào khớp.' ?></div>
        <?php else: ?>
        <table class="adm-table">
            <thead><tr><th>Thời gian</th><th>Admin</th><th>Hành động</th><th>Đối tượng</th><th>Chi tiết</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $cls = str_ends_with($r['action'], '.delete') ? ' is-del' : (str_ends_with($r['action'], '.create') ? ' is-new' : '');
            ?>
                <tr>
                    <td class="adm-mono" data-label="Thời gian"><?= htmlspecialchars((string)$r['created_at']) ?></td>
                    <td data-label="Admin"><?= htmlspecialchars($r['admin_name']) ?></td>
                    <td data-label="Hành động"><span class="adm-badge adm-act-badge<?= $cls ?>"><?= htmlspecialchars($actions[$r['action']] ?? $r['action']) ?></span></td>
                    <td data-label="Đối tượng"><?= htmlspecialchars($r['target_type'] . ($r['target_id'] !== null ? ' #' . $r['target_id'] : '')) ?></td>
                    <td data-label="Chi tiết"><?= htmlspecialchars($r['detail']) ?></td>
                    <td class="adm-mono" data-label="IP"><?= htmlspecialchars($r['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="adm-pager">
            <span>Trang <?= (int)$page ?> / <?= (int)$pages ?></span>
            <span class="adm-actions">
                <?php if ($page > 1): ?><a class="adm-btn adm-btn-ghost adm-btn-sm" href="<?= htmlspecialchars(pageLink($page - 1, $keep)) ?>">&larr; Trước</a><?php endif; ?>
                <?php if ($page < $pages): ?><a class="adm-btn adm-btn-ghost adm-btn-sm" href="<?= htmlspecialchars(pageLink($page + 1, $keep)) ?>">Sau &rarr;</a><?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
