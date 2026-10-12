<?php
/**
 * admin/dashboard.php — trang tổng quan của quản trị viên.
 * Cho admin thấy nhanh: số liệu hệ thống, bài tập phân bố ra sao,
 * việc còn thiếu (bài chưa có video/ảnh), người dùng mới đăng ký.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireAdmin();

/** Chạy câu SQL không có tham số và trả về một con số (COUNT...) */
function scalar(PDO $pdo, string $sql): int
{
    return (int)$pdo->query($sql)->fetchColumn();
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
    // $column là chuỗi do CHÍNH code này truyền vào (không phải dữ liệu người dùng),
    // nên đưa thẳng vào SQL là an toàn.
    $rows = $pdo->query("SELECT $column AS k, COUNT(*) AS c FROM exercises GROUP BY $column")->fetchAll();
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
    <div class="adm-head">
        <div>
            <h1>Tổng quan</h1>
            <p class="adm-sub">Xin chào, <?= htmlspecialchars($_SESSION['full_name'] ?? 'quản trị viên') ?>.</p>
        </div>
        <a class="adm-btn" href="/admin/exercise-form.php">+ Thêm bài tập</a>
    </div>

    <div class="adm-stats">
        <div class="adm-stat"><div class="adm-stat-num"><?= $totalUsers ?></div><div class="adm-stat-label">Người dùng</div></div>
        <div class="adm-stat"><div class="adm-stat-num"><?= $totalEx ?></div><div class="adm-stat-label">Bài tập</div></div>
        <div class="adm-stat <?= $noVideo > 0 ? 'adm-stat-warn' : '' ?>"><div class="adm-stat-num"><?= $noVideo ?></div><div class="adm-stat-label">Bài chưa có video</div></div>
        <div class="adm-stat <?= $noImage > 0 ? 'adm-stat-warn' : '' ?>"><div class="adm-stat-num"><?= $noImage ?></div><div class="adm-stat-label">Bài chưa có ảnh</div></div>
    </div>

    <?php if ($noVideo > 0 || $noImage > 0 || $incomplete > 0): ?>
        <div class="adm-todo">
            <?php if ($noVideo > 0): ?><a href="/admin/exercises.php"><?= $noVideo ?> bài cần thêm video</a><?php endif; ?>
            <?php if ($noImage > 0): ?><a href="/admin/exercises.php"><?= $noImage ?> bài cần thêm ảnh</a><?php endif; ?>
            <?php if ($incomplete > 0): ?><a href="/admin/users.php"><?= $incomplete ?> người dùng chưa đủ hồ sơ</a><?php endif; ?>
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