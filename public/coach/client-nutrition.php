<?php
/**
 * coach/client-nutrition.php?id=... — HLV đặt mục tiêu calo/macro thủ công cho học viên.
 *
 * HLV nhập gam protein, tinh bột, chất béo; MÁY CHỦ tự tính kcal = 4P + 4C + 9F và kiểm tra giới hạn an toàn
 * (validateCoachMacros trong includes/coach-rules.php: 1200-5000 kcal, protein 0,8-3,3 g/kg, chất béo ≥ 0,5 g/kg).
 * Lưu vào nutrition_targets với assigned_by = HLV; học viên thấy nhãn "Do HLV giao" ở trang Dinh dưỡng và không tự đổi được.
 * Gợi ý ban đầu lấy từ chính công thức của trang Dinh dưỡng (includes/tdee.php) để HLV chỉnh từ đó.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';
require_once __DIR__ . '/../includes/tdee.php';
require_once __DIR__ . '/../includes/audit-log.php';

requireCoach();
[$clientId, $relationId] = requireCoachClient($pdo, $_GET['id'] ?? null);
$coachId = (int)$_SESSION['user_id'];
$self = '/coach/client-nutrition.php?id=' . $clientId;
$client = coachClientBasics($pdo, $clientId);
$weight = $client['weight_kg'] ? (float)$client['weight_kg'] : null;

// TDEE và gợi ý tự tính (cùng công thức với trang Dinh dưỡng). Hồ sơ thiếu thì $tdee = null.
$bmr = $tdee = $auto = null;
try {
    if ($weight && $client['height_cm'] && $client['age'] && $client['gender'] && $client['activity_level']) {
        $bmr  = calculateBMR($weight, $client['height_cm'], $client['age'], $client['gender']);
        $tdee = calculateTDEE($bmr, $client['activity_level']);
        if ($client['goal']) {
            $auto = calculateTargets($tdee, $weight, $client['goal'], $client['experience_level']);
        }
    }
} catch (InvalidArgumentException $e) {
    $bmr = $tdee = $auto = null;
}

$errors = [];
$input = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $input = ['protein_g' => $_POST['protein_g'] ?? '', 'carbs_g' => $_POST['carbs_g'] ?? '', 'fat_g' => $_POST['fat_g'] ?? ''];
    $check = validateCoachMacros($input['protein_g'], $input['carbs_g'], $input['fat_g'], $weight);
    $errors = $check['errors'];
    if ($tdee === null) {
        $errors['kcal'] = 'Hồ sơ học viên chưa đủ thông tin (tuổi, chiều cao, cân nặng, giới tính, vận động) để tính TDEE tham chiếu.';
    }
    if (!$errors) {
        try {
            coachSaveNutrition($pdo, $coachId, $clientId, round($tdee), $check['kcal'], (int)$input['protein_g'], (int)$input['carbs_g'], (int)$input['fat_g'], date('Y-m-d'));
        } catch (CoachException $e) {   // A-54: học viên hết buổi
            coachFlash('error', $e->getMessage());
            header('Location: ' . $self);
            exit;
        }
        auditLog($pdo, 'coach.set_macros', 'client', $clientId, 'Đặt ' . $check['kcal'] . ' kcal (P' . (int)$input['protein_g'] . ' C' . (int)$input['carbs_g'] . ' F' . (int)$input['fat_g'] . ') cho "' . $client['full_name'] . '"');
        coachFlash('ok', 'Đã đặt mục tiêu ' . number_format($check['kcal'], 0, ',', '.') . ' kcal/ngày cho học viên.');
        header('Location: ' . $self);
        exit;
    }
}

$flash   = coachTakeFlash();
$current = coachAssignedNutrition($pdo, $clientId, $coachId);
$st = $pdo->prepare(
    'SELECT n.target_kcal, n.protein_g, n.carbs_g, n.fat_g, n.calculated_date, n.assigned_by, u.full_name AS by_name
     FROM nutrition_targets n LEFT JOIN users u ON u.id = n.assigned_by
     WHERE n.user_id = ? ORDER BY n.calculated_date DESC, n.id DESC LIMIT 8'
);
$st->execute([$clientId]);
$history = $st->fetchAll();

// Giá trị điền sẵn: vừa nhập lỗi > mục tiêu HLV đang đặt > gợi ý tự tính
$val = $input ?? ($current
    ? ['protein_g' => (int)$current['protein_g'], 'carbs_g' => (int)$current['carbs_g'], 'fat_g' => (int)$current['fat_g']]
    : ($auto ? ['protein_g' => (int)round($auto['protein_g']), 'carbs_g' => (int)round($auto['carbs_g']), 'fat_g' => (int)round($auto['fat_g'])]
             : ['protein_g' => '', 'carbs_g' => '', 'fat_g' => '']));

$pageTitle = 'Đặt mục tiêu dinh dưỡng';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$coachNavActive = 'clients';
include __DIR__ . '/../includes/coach-nav.php';
?>
<div class="adm-wrap">
    <a class="adm-back" href="/coach/client.php?id=<?= (int)$clientId ?>">&larr; Hồ sơ <?= htmlspecialchars($client['full_name']) ?></a>
    <div class="adm-head">
        <div>
            <h1>Dinh dưỡng của <?= htmlspecialchars($client['full_name']) ?></h1>
            <p class="adm-sub">Nhập gam mỗi nhóm chất, tổng calo được tính tự động. Học viên thấy mục tiêu này ở trang Dinh dưỡng và không tự đổi được.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>
    <?= coachBillingBanner($pdo, $relationId) ?>

    <div class="adm-split">
        <form class="adm-panel" method="post" action="<?= htmlspecialchars($self) ?>" id="cn-form" novalidate>
            <?= csrfField() ?>
            <div class="adm-form-section">
                <h2>Mục tiêu mỗi ngày</h2>
                <?php if ($errors): ?>
                    <ul class="adm-errors" role="alert"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <div class="adm-grid">
                    <div class="adm-field">
                        <label for="protein_g">Protein (g)</label>
                        <input class="adm-input" id="protein_g" name="protein_g" type="number" inputmode="numeric" min="40" max="400" required value="<?= htmlspecialchars((string)$val['protein_g']) ?>"<?= isset($errors['protein_g']) ? ' aria-invalid="true"' : '' ?>>
                        <p class="adm-hint" data-perkg="protein_g"></p>
                    </div>
                    <div class="adm-field">
                        <label for="carbs_g">Tinh bột (g)</label>
                        <input class="adm-input" id="carbs_g" name="carbs_g" type="number" inputmode="numeric" min="0" max="800" required value="<?= htmlspecialchars((string)$val['carbs_g']) ?>"<?= isset($errors['carbs_g']) ? ' aria-invalid="true"' : '' ?>>
                    </div>
                    <div class="adm-field">
                        <label for="fat_g">Chất béo (g)</label>
                        <input class="adm-input" id="fat_g" name="fat_g" type="number" inputmode="numeric" min="20" max="250" required value="<?= htmlspecialchars((string)$val['fat_g']) ?>"<?= isset($errors['fat_g']) ? ' aria-invalid="true"' : '' ?>>
                        <p class="adm-hint" data-perkg="fat_g"></p>
                    </div>
                </div>
                <div class="cn-total" aria-live="polite">
                    <span class="ch-label">Tổng năng lượng</span>
                    <b id="cn-kcal">—</b>
                    <span id="cn-diff" class="adm-sub"></span>
                </div>
                <p class="adm-hint">Giới hạn máy chủ kiểm tra: 1.200–5.000 kcal; protein 0,8–3,3 g/kg; chất béo tối thiểu 0,5 g/kg cân nặng. Không thay thế tư vấn y tế, học viên có bệnh lý cần hỏi bác sĩ.</p>
            </div>
            <div class="adm-bar">
                <?php if ($auto): ?>
                    <button type="button" class="adm-btn adm-btn-ghost" id="cn-auto"
                            data-p="<?= (int)round($auto['protein_g']) ?>" data-c="<?= (int)round($auto['carbs_g']) ?>" data-f="<?= (int)round($auto['fat_g']) ?>">Dùng gợi ý tự tính</button>
                <?php endif; ?>
                <button type="submit" class="adm-btn" data-busy-text="Đang lưu…">Giao mục tiêu</button>
            </div>
        </form>

        <div class="adm-side" style="display:grid;gap:16px">
            <section class="adm-panel adm-card-pad">
                <h2>Tham chiếu từ hồ sơ</h2>
                <?php if ($tdee === null): ?>
                    <p class="adm-sub">Hồ sơ học viên chưa đủ thông tin để tính TDEE. Nhắn tin nhờ học viên cập nhật hồ sơ.</p>
                <?php else: ?>
                    <dl class="adm-kv">
                        <div><dt>Cân nặng</dt><dd><?= coachNum($weight, 1) ?> kg</dd></div>
                        <div><dt>BMR</dt><dd><?= coachNum($bmr) ?> kcal</dd></div>
                        <div><dt>TDEE</dt><dd id="cn-tdee" data-tdee="<?= (int)round($tdee) ?>"><?= coachNum($tdee) ?> kcal</dd></div>
                        <?php if ($auto): ?><div><dt>Tự tính (<?= htmlspecialchars(mb_strtolower(COACH_GOAL_LABELS[$client['goal']] ?? '')) ?>)</dt><dd><?= coachNum($auto['target_kcal']) ?> kcal</dd></div><?php endif; ?>
                    </dl>
                <?php endif; ?>
            </section>
            <section class="adm-panel adm-card-pad">
                <h2>Lịch sử mục tiêu</h2>
                <?php if (!$history): ?>
                    <p class="adm-sub">Chưa có mục tiêu nào được lưu.</p>
                <?php else: ?>
                    <ul class="adm-list">
                        <?php foreach ($history as $h): ?>
                            <li><span><b><?= coachNum($h['target_kcal']) ?> kcal</b><br><small>P <?= coachNum($h['protein_g']) ?> · C <?= coachNum($h['carbs_g']) ?> · F <?= coachNum($h['fat_g']) ?></small></span>
                                <small style="text-align:right"><?= htmlspecialchars(date('d/m/Y', strtotime($h['calculated_date']))) ?><br><?= (int)$h['assigned_by'] === $coachId ? 'Bạn đặt' : ($h['assigned_by'] ? 'HLV khác' : 'Tự tính') ?></small></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
<script>
// Tính kcal ngay khi gõ (chỉ để xem trước; máy chủ tính lại và kiểm tra giới hạn khi lưu)
(function () {
    var form = document.getElementById('cn-form');
    if (!form) return;
    var weight = <?= $weight ? json_encode($weight) : 'null' ?>;
    var tdeeEl = document.getElementById('cn-tdee');
    var tdee = tdeeEl ? parseInt(tdeeEl.dataset.tdee, 10) : null;
    var fmt = function (n) { return Math.round(n).toLocaleString('vi-VN'); };
    function num(id) { var v = parseInt(document.getElementById(id).value, 10); return isNaN(v) ? 0 : v; }
    function update() {
        var kcal = 4 * num('protein_g') + 4 * num('carbs_g') + 9 * num('fat_g');
        document.getElementById('cn-kcal').textContent = fmt(kcal) + ' kcal';
        document.getElementById('cn-diff').textContent = tdee ? (kcal >= tdee ? '+' : '−') + fmt(Math.abs(kcal - tdee)) + ' kcal so với TDEE' : '';
        if (weight) {
            form.querySelectorAll('[data-perkg]').forEach(function (p) {
                p.textContent = (num(p.dataset.perkg) / weight).toFixed(1).replace('.', ',') + ' g/kg cân nặng';
            });
        }
    }
    form.addEventListener('input', update);
    var auto = document.getElementById('cn-auto');
    if (auto) auto.addEventListener('click', function () {
        document.getElementById('protein_g').value = auto.dataset.p;
        document.getElementById('carbs_g').value = auto.dataset.c;
        document.getElementById('fat_g').value = auto.dataset.f;
        update();
    });
    update();
})();
</script>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
