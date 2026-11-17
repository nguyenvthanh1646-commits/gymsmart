<?php
/**
 * admin/user-detail.php?id=... — xem chi tiết một tài khoản (chỉ đọc) + đổi vai trò / xóa.
 * Hiển thị hồ sơ tập luyện và chỉ số BMI. Không hiển thị mật khẩu/hash.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin-users.php';

requireAdmin();

// Id trên URL do người dùng gõ được, nên phải kiểm tra: số nguyên >= 1
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$selfId = (int)$_SESSION['user_id'];

// Hành động POST gửi về chính trang này, kèm ?id=... nên đọc id từ $_POST bên trong hàm.
// Sau khi xóa thì quay về danh sách (trang chi tiết của user đó không còn).
handleUserAction($pdo, '/admin/user-detail.php?id=' . (int)($id ?: 0), '/admin/users.php');
$flash = takeFlash();

$user = false;
if ($id !== false && $id >= 1) {
    $stmt = $pdo->prepare(
        "SELECT id, full_name, email, role, age, height_cm, weight_kg, gender,
                activity_level, experience_level, goal, created_at
         FROM users WHERE id = ?"
    );
    $stmt->execute([$id]);
    $user = $stmt->fetch();
}

$genderLabels     = ['MALE' => 'Nam', 'FEMALE' => 'Nữ', 'OTHER' => 'Khác'];
$activityLabels   = ['SEDENTARY' => 'Ít vận động', 'LIGHT' => 'Vận động nhẹ', 'MODERATE' => 'Vận động vừa',
                     'ACTIVE' => 'Vận động nhiều', 'EXTRA_ACTIVE' => 'Rất nhiều'];
$experienceLabels = ['BEGINNER' => 'Mới tập', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Nâng cao'];
$goalLabels       = ['LOSE_FAT' => 'Giảm mỡ', 'MAINTAIN' => 'Duy trì', 'GAIN_MUSCLE' => 'Tăng cơ'];

/** Chữ hiển thị: giá trị rỗng/NULL → "Chưa nhập" */
function show($value, array $labels = []): string
{
    if ($value === null || $value === '') {
        return '<span class="adm-video-no">Chưa nhập</span>';
    }
    return htmlspecialchars((string)($labels[$value] ?? $value));
}

$bmi = null;
if ($user && $user['height_cm'] > 0 && $user['weight_kg'] > 0) {
    $m = $user['height_cm'] / 100;
    $bmi = round($user['weight_kg'] / ($m * $m), 1);
}
// Phân loại BMI theo mốc WHO (chỉ để tham khảo, không phải chẩn đoán)
$bmiText = '';
if ($bmi !== null) {
    $bmiText = $bmi < 18.5 ? 'Thiếu cân' : ($bmi < 25 ? 'Bình thường' : ($bmi < 30 ? 'Thừa cân' : 'Béo phì'));
}

$pageTitle = $user ? 'Chi tiết người dùng' : 'Không tìm thấy người dùng';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$admNavActive = 'users';
include __DIR__ . '/../includes/admin-nav.php';
?>

<div class="adm-wrap">
<?php if (!$user): ?>
    <div class="adm-panel"><div class="adm-empty">
        Không tìm thấy người dùng. <a href="/admin/users.php">Về danh sách</a>
    </div></div>
<?php else: ?>
    <?php $isSelf = (int)$user['id'] === $selfId; ?>
    <div class="adm-head">
        <div>
            <a class="adm-back" href="/admin/users.php">&larr; Danh sách người dùng</a>
            <h1><?= htmlspecialchars($user['full_name']) ?>
                <span class="adm-badge adm-b-<?= htmlspecialchars($user['role']) ?>"><?= htmlspecialchars($user['role']) ?></span>
                <?php if ($isSelf): ?><span class="adm-badge adm-self">Bạn</span><?php endif; ?>
            </h1>
            <p class="adm-sub"><?= htmlspecialchars($user['email']) ?> &middot; đăng ký <?= htmlspecialchars(substr((string)($user['created_at'] ?? ''), 0, 10)) ?></p>
        </div>
        <?php if (!$isSelf): ?>
        <div class="adm-actions">
            <?php if ($user['role'] === 'COACH'): ?>
            <!-- COACH: không đổi thẳng sang ADMIN; thu hồi HLV trước (kết thúc quan hệ với học viên trong cùng 1 transaction) -->
            <form method="post" action="/admin/user-detail.php?id=<?= (int)$user['id'] ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="revoke_coach">
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                <button type="submit" class="adm-btn adm-btn-danger"
                        onclick="return confirm('Thu hồi vai trò HLV? Mọi học viên đang tập sẽ bị kết thúc, yêu cầu đang chờ bị từ chối. Lịch tập đã giao vẫn ở lại với học viên.');">Thu hồi HLV</button>
            </form>
            <?php else: ?>
            <form method="post" action="/admin/user-detail.php?id=<?= (int)$user['id'] ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="set_role">
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                <input type="hidden" name="role" value="<?= $user['role'] === 'ADMIN' ? 'USER' : 'ADMIN' ?>">
                <button type="submit" class="adm-btn adm-btn-ghost"
                        onclick="return confirm('Đổi vai trò của tài khoản này?');">
                    <?= $user['role'] === 'ADMIN' ? 'Hạ xuống USER' : 'Lên ADMIN' ?>
                </button>
            </form>
            <?php endif; ?>
            <?php if ($user['role'] !== 'ADMIN'): ?>
            <form method="post" action="/admin/user-detail.php?id=<?= (int)$user['id'] ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                <button type="submit" class="adm-btn adm-btn-danger"
                        onclick="return confirm('Xóa tài khoản này cùng toàn bộ dữ liệu? Không hoàn tác được.');">Xóa tài khoản</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status">
            <?= htmlspecialchars($flash[1]) ?>
        </div>
    <?php endif; ?>

    <div class="adm-two">
        <div class="adm-panel adm-card-pad">
            <h2>Chỉ số cơ thể</h2>
            <dl class="adm-kv">
                <div><dt>Tuổi</dt><dd><?= show($user['age']) ?></dd></div>
                <div><dt>Giới tính</dt><dd><?= show($user['gender'], $genderLabels) ?></dd></div>
                <div><dt>Chiều cao</dt><dd><?= $user['height_cm'] !== null ? htmlspecialchars((string)$user['height_cm']) . ' cm' : show(null) ?></dd></div>
                <div><dt>Cân nặng</dt><dd><?= $user['weight_kg'] !== null ? htmlspecialchars((string)$user['weight_kg']) . ' kg' : show(null) ?></dd></div>
                <div><dt>BMI</dt><dd><?= $bmi !== null ? htmlspecialchars((string)$bmi) . ' <small>(' . htmlspecialchars($bmiText) . ')</small>' : show(null) ?></dd></div>
            </dl>
        </div>
        <div class="adm-panel adm-card-pad">
            <h2>Tập luyện</h2>
            <dl class="adm-kv">
                <div><dt>Mức vận động</dt><dd><?= show($user['activity_level'], $activityLabels) ?></dd></div>
                <div><dt>Trình độ</dt><dd><?= show($user['experience_level'], $experienceLabels) ?></dd></div>
                <div><dt>Mục tiêu</dt><dd><?= show($user['goal'], $goalLabels) ?></dd></div>
            </dl>
        </div>
    </div>

    <?php if (coachTablesReady($pdo)): $ci = coachAdminUserInfo($pdo, (int)$user['id']); ?>
    <!-- Phân hệ HLV: chỉ đọc. Thu hồi vai trò HLV dùng nút ở đầu trang. -->
    <div class="adm-panel adm-card-pad" style="margin-top:16px">
        <h2>Huấn luyện viên</h2>
        <?php if ($ci['as_client']): $r = $ci['as_client']; ?>
            <p style="margin:0 0 8px">Đang <?= $r['status'] === 'ACTIVE' ? 'tập với' : 'chờ duyệt từ' ?> HLV <a class="adm-link" href="/admin/user-detail.php?id=<?= (int)$r['coach_id'] ?>"><b><?= htmlspecialchars($r['coach_name']) ?></b></a>
                <small class="adm-sub">(<?= $r['status'] === 'ACTIVE' ? 'từ ' . htmlspecialchars(date('d/m/Y', strtotime((string)$r['accepted_at']))) : 'gửi ' . htmlspecialchars(date('d/m/Y', strtotime($r['created_at']))) ?>)</small></p>
        <?php endif; ?>
        <?php if ($ci['clients']): ?>
            <p style="margin:0 0 6px">Là HLV của <?= count(array_filter($ci['clients'], fn($c) => $c['status'] === 'ACTIVE')) ?> học viên, <?= count(array_filter($ci['clients'], fn($c) => $c['status'] === 'PENDING')) ?> yêu cầu chờ:</p>
            <ul class="adm-list">
                <?php foreach ($ci['clients'] as $c): ?>
                    <li><a class="adm-link" href="/admin/user-detail.php?id=<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></a>
                        <span class="adm-badge <?= $c['status'] === 'ACTIVE' ? 'adm-ok' : 'adm-warn' ?>"><?= $c['status'] === 'ACTIVE' ? 'Đang tập' : 'Chờ duyệt' ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!$ci['as_client'] && !$ci['clients']): ?><p class="adm-sub" style="margin:0">Hiện không có quan hệ HLV nào.</p><?php endif; ?>
        <?php if ($ci['history']): ?>
            <h3 style="margin:16px 0 6px;font-size:1rem">Đã kết thúc</h3>
            <ul class="adm-list">
                <?php foreach ($ci['history'] as $h): $isCoach = (int)$h['coach_id'] === (int)$user['id']; ?>
                    <li><span><?= $isCoach ? 'Học viên ' . htmlspecialchars($h['client_name']) : 'HLV ' . htmlspecialchars($h['coach_name']) ?></span>
                        <small><?= $h['status'] === 'REJECTED' ? 'Bị từ chối' : ($h['accepted_at'] ? 'Đã kết thúc' : 'Đã hủy') ?><?= $h['ended_at'] ? ' · ' . htmlspecialchars(date('d/m/Y', strtotime($h['ended_at']))) : '' ?></small></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
