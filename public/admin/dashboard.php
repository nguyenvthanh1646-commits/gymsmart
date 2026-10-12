<?php
/**
 * admin/dashboard.php — trang tổng quan của quản trị viên.
 * Cho admin thấy nhanh: số liệu hệ thống, bài tập phân bố ra sao,
 * việc còn thiếu (bài chưa có video/ảnh), người dùng mới đăng ký.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireAdmin();

/** Chạy câu SQL KHÔNG có dữ liệu người dùng (chỉ truyền hằng số viết sẵn trong file này) và trả về một con số */
function scalar(PDO $pdo, string $sql): int
{
    return (int)$pdo->query($sql)->fetchColumn(); // audit:ok $sql là hằng số viết sẵn trong file này
}

$totalUsers = scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'USER'");
$totalEx    = scalar($pdo, "SELECT COUNT(*) FROM exercises");
$noVideo    = scalar($pdo, "SELECT COUNT(*) FROM exercises WHERE video_url IS NULL OR video_url = ''");
$noImage    = scalar($pdo, "SELECT COUNT(*) FROM exercises WHERE image_url IS NULL OR image_url = ''");

// Người dùng chưa nhập đủ 7 mục hồ sơ (cùng tiêu chí với trang chủ người dùng)
$incomplete = scalar($pdo,
    "SELECT COUNT(*) FROM users WHERE role = 'USER' AND (
        age IS NULL OR height_cm IS NULL OR weight_kg IS NULL OR gender IS NULL
        OR activity_level IS NULL OR experience_level IS NULL OR goal IS NULL)"
);

$muscleLabels = [
    'CHEST' => 'Ngực', 'BACK' => 'Lưng', 'SHOULDERS' => 'Vai', 'LEGS' => 'Chân',
    'ARMS' => 'Tay', 'ABS' => 'Bụng', 'FULL_BODY' => 'Toàn thân',
];
$difficultyLabels = ['BEGINNER' => 'Dễ', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Khó'];

/** Đếm số bài theo một cột, trả về mảng [giá trị => số bài] đủ mọi nhãn (nhãn không có bài thì 0) */
function countBy(PDO $pdo, string $column, array $labels): array
{
    // Tên cột không thể đưa vào dấu ? của prepare(), nên phải ghép vào câu SQL.
    // Để an toàn, chỉ cho phép đúng các cột nằm trong danh sách này (whitelist).
    if (!in_array($column, ['muscle_group', 'difficulty', 'type'], true)) {
        throw new InvalidArgumentException('Cột không được phép: ' . $column);
    }
    $rows = $pdo->query("SELECT $column AS k, COUNT(*) AS c FROM exercises GROUP BY $column")->fetchAll(); // audit:ok $column đã qua whitelist ở trên
    $found = [];
    foreach ($rows as $r) {
        $found[$r['k']] = (int)$r['c'];
    }
    $result = [];
    foreach ($labels as $key => $label) {
        $result[$key] = $found[$key] ?? 0;
    }
    return $result;
}

$byMuscle = countBy($pdo, 'muscle_group', $muscleLabels);
$byDiff   = countBy($pdo, 'difficulty', $difficultyLabels);

$recentUsers = $pdo->query(
    "SELECT full_name, email, created_at FROM users ORDER BY id DESC LIMIT 5"
)->fetchAll();

/** In một nhóm thanh ngang; độ dài thanh tính theo giá trị lớn nhất của nhóm */
function renderMeters(array $counts, array $labels): void
{
    $max = max(1, max($counts ?: [0]));
    foreach ($counts as $key => $n) {
        $pct = (int)round($n / $max * 100);
        echo '<div class="adm-meter-row"><span>' . htmlspecialchars($labels[$key]) . '</span>'
           . '<div class="adm-meter-track"><div class="adm-meter-fill" style="width:' . $pct . '%"></div></div>'
           . '<span class="adm-meter-num">' . (int)$n . '</span></div>';
    }
}

$pageTitle = 'Tổng quan quản trị';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$admNavActive = 'dashboard';
include __DIR__ . '/../includes/admin-nav.php';
?>

<div class="adm-wrap">
    <section class="adm-hero">
        <div>
            <span class="adm-hero__chip">Bảng điều khiển</span>
            <h1>Xin chào, <?= htmlspecialchars($_SESSION['full_name'] ?? 'quản trị viên') ?></h1>
            <p>Hệ thống đang có <?= (int)$totalUsers ?> người dùng và <?= (int)$totalEx ?> bài tập.</p>
        </div>
        <div class="adm-hero__cta">
            <a class="adm-btn" href="/admin/exercise-form.php">+ Thêm bài tập</a>
            <a class="adm-btn adm-btn-light" href="/admin/users.php">Xem người dùng</a>
        </div>
    </section>

    <?php
    // Biểu tượng nhỏ cho thẻ số liệu (nét đơn giản, vẽ bằng SVG)
    $ico = function (string $inner): string {
        return '<span class="adm-stat-ico"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg></span>';
    };
    $icoUsers = '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.7-3.4 3.3-5.2 6.5-5.2s5.8 1.8 6.5 5.2M16 4.8a3.5 3.5 0 010 6.4M18 14.8c2 .6 3.1 2.3 3.5 5.2"/>';
    $icoDumb  = '<path d="M6.5 6.5v11M17.5 6.5v11M3.5 9v6M20.5 9v6M6.5 12h11"/>';
    $icoVideo = '<circle cx="12" cy="12" r="9"/><path d="M10 8.5l5.5 3.5-5.5 3.5z"/>';
    $icoImage = '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.6"/><path d="M4 17l5-4.5 4 3.5 3-2.5 4 3.5"/>';
    ?>
    <div class="adm-stats">
        <div class="adm-stat"><?= $ico($icoUsers) ?><div><div class="adm-stat-num"><?= (int)$totalUsers ?></div><div class="adm-stat-label">Người dùng</div></div></div>
        <div class="adm-stat"><?= $ico($icoDumb) ?><div><div class="adm-stat-num"><?= (int)$totalEx ?></div><div class="adm-stat-label">Bài tập</div></div></div>
        <div class="adm-stat <?= $noVideo > 0 ? 'adm-stat-warn' : '' ?>"><?= $ico($icoVideo) ?><div><div class="adm-stat-num"><?= (int)$noVideo ?></div><div class="adm-stat-label">Bài chưa có video</div></div></div>
        <div class="adm-stat <?= $noImage > 0 ? 'adm-stat-warn' : '' ?>"><?= $ico($icoImage) ?><div><div class="adm-stat-num"><?= (int)$noImage ?></div><div class="adm-stat-label">Bài chưa có ảnh</div></div></div>
    </div>

    <?php if ($noVideo > 0 || $noImage > 0 || $incomplete > 0): ?>
        <div class="adm-todo">
            <?php if ($noVideo > 0): ?><a href="/admin/exercises.php"><?= (int)$noVideo ?> bài cần thêm video</a><?php endif; ?>
            <?php if ($noImage > 0): ?><a href="/admin/exercises.php"><?= (int)$noImage ?> bài cần thêm ảnh</a><?php endif; ?>
            <?php if ($incomplete > 0): ?><a href="/admin/users.php"><?= (int)$incomplete ?> người dùng chưa đủ hồ sơ</a><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="adm-two">
        <div class="adm-panel adm-card-pad">
            <h2>Bài tập theo nhóm cơ</h2>
            <?php renderMeters($byMuscle, $muscleLabels); ?>
        </div>
        <div class="adm-panel adm-card-pad">
            <h2>Bài tập theo độ khó</h2>
            <?php renderMeters($byDiff, $difficultyLabels); ?>
            <h2 style="margin-top:22px;">Người dùng mới</h2>
            <?php if (empty($recentUsers)): ?>
                <p class="adm-sub">Chưa có người dùng nào.</p>
            <?php else: ?>
                <ul class="adm-list">
                    <?php foreach ($recentUsers as $u): ?>
                        <li>
                            <span><?= htmlspecialchars($u['full_name']) ?><br><small><?= htmlspecialchars($u['email']) ?></small></span>
                            <small><?= htmlspecialchars(substr((string)($u['created_at'] ?? ''), 0, 10)) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
