<?php
/**
 * nutrition.php — trang Dinh dưỡng: BMR, TDEE, calo mục tiêu, macro và lịch ăn gợi ý.
 *
 * Luồng chạy:
 *   1. Đọc hồ sơ (tuổi, chiều cao, cân nặng, giới tính, mức vận động, mục tiêu) từ bảng users
 *   2. POST "đổi mục tiêu"  → cập nhật users.goal → chuyển lại trang (tính lại toàn bộ)
 *      POST "kiểu ăn"       → lưu lựa chọn (cân bằng, giàu protein, ít tinh bột, tùy chỉnh %) vào SESSION
 *   3. Tính BMR → TDEE → calo mục tiêu + macro (hàm trong includes/tdee.php, việc của C)
 *      rồi áp kiểu ăn (includes/meal-plan.php) và chia macro cho từng bữa
 *   4. Lưu kết quả của hôm nay vào nutrition_targets (mỗi ngày 1 dòng)
 *   5. Vẽ trang: vòng tròn macro, lịch ăn, bảng so sánh các mục tiêu
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/tdee.php';
require_once __DIR__ . '/includes/meal-plan.php';

requireLogin();

$userId = $_SESSION['user_id'];

// Nhãn hiển thị. Khóa trùng ENUM trong bảng users.
$goalLabels = [
    'LOSE_FAT'    => 'Giảm mỡ',
    'MAINTAIN'    => 'Duy trì',
    'GAIN_MUSCLE' => 'Tăng cơ',
];
$activityLabels = [
    'SEDENTARY' => 'Ít vận động', 'LIGHT' => 'Vận động nhẹ', 'MODERATE' => 'Vận động vừa',
    'ACTIVE' => 'Vận động nhiều', 'EXTRA_ACTIVE' => 'Rất nhiều',
];
$fieldLabels = [
    'age' => 'tuổi', 'height_cm' => 'chiều cao', 'weight_kg' => 'cân nặng',
    'gender' => 'giới tính', 'activity_level' => 'mức độ vận động', 'goal' => 'mục tiêu',
];
$styles = dietStyles();
// Protein theo trình độ: người mới 1,6 g/kg, có kinh nghiệm 1,8 g/kg (xem calculateTargets trong tdee.php)
$experienceLabels = [
    'BEGINNER' => 'người mới tập', 'INTERMEDIATE' => 'đã có kinh nghiệm', 'ADVANCED' => 'đã có kinh nghiệm',
];

// ===== 1. Đọc hồ sơ =====
$stmt = $pdo->prepare(
    'SELECT age, height_cm, weight_kg, gender, activity_level, experience_level, goal FROM users WHERE id = ?'
);
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Lựa chọn kiểu ăn đang dùng (lưu trong session, mặc định: theo mục tiêu)
$prefs = $_SESSION['nutri'] ?? ['diet' => 'goal', 'p' => 25, 'c' => 45, 'f' => 30];
if (!isset($styles[$prefs['diet'] ?? ''])) {
    $prefs = ['diet' => 'goal', 'p' => 25, 'c' => 45, 'f' => 30];
}

$dietError = '';
$formPct = ['p' => (int)$prefs['p'], 'c' => (int)$prefs['c'], 'f' => (int)$prefs['f']]; // giá trị hiện trong ô tùy chỉnh
$formDiet = $prefs['diet'];

// ===== 2. Xử lý form =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'goal') {
        // Đổi mục tiêu: whitelist rồi ghi vào hồ sơ (cùng cột với profile.php nên hai trang luôn khớp nhau)
        $newGoal = $_POST['goal'] ?? '';
        if (array_key_exists($newGoal, $goalLabels)) {
            $stmt = $pdo->prepare('UPDATE users SET goal = ? WHERE id = ?');
            $stmt->execute([$newGoal, $userId]);
        }
        header('Location: /nutrition.php#macro');
        exit;
    }

    if ($action === 'diet') {
        $newDiet = $_POST['diet'] ?? '';
        $formDiet = array_key_exists($newDiet, $styles) ? $newDiet : 'goal';

        if ($formDiet === 'custom') {
            // filter_var trả về false nếu không phải số nguyên
            $p = filter_var($_POST['p'] ?? '', FILTER_VALIDATE_INT);
            $c = filter_var($_POST['c'] ?? '', FILTER_VALIDATE_INT);
            $f = filter_var($_POST['f'] ?? '', FILTER_VALIDATE_INT);
            $formPct = ['p' => (int)$p, 'c' => (int)$c, 'f' => (int)$f];
            if ($p === false || $c === false || $f === false) {
                $dietError = 'Hãy nhập tỉ lệ là số nguyên.';
            } else {
                $dietError = (string)validateCustomPercent($p, $c, $f);
            }
        }

        if ($dietError === '') {
            $_SESSION['nutri'] = ['diet' => $formDiet] + $formPct;
            header('Location: /nutrition.php#macro');
            exit;
        }
        // Có lỗi: không chuyển trang, hiện lại form kèm thông báo (giữ nguyên số vừa nhập)
    }
}

// ===== 3. Tính toán =====
$missing = [];
if (!$user) {
    $missing = array_values($fieldLabels);
} else {
    foreach ($fieldLabels as $field => $label) {
        if ($user[$field] === null || $user[$field] === '') {
            $missing[] = $label;
        }
    }
}

$error = '';
$bmr = $tdee = $targets = $macros = null;
$weight = 0.0;
$comparison = [];

/** Áp kiểu ăn đang chọn lên kết quả mặc định của tdee.php. */
$applyDiet = function (array $base) use ($prefs, $styles) {
    $diet = $prefs['diet'];
    if ($diet === 'custom') {
        return macrosFromPercent($base['target_kcal'], (int)$prefs['p'], (int)$prefs['c'], (int)$prefs['f']);
    }
    if ($styles[$diet]['pct'] !== null) {
        [$p, $c, $f] = $styles[$diet]['pct'];
        return macrosFromPercent($base['target_kcal'], $p, $c, $f);
    }
    return $base; // 'goal': giữ nguyên cách tính theo cân nặng của tdee.php
};

if (empty($missing)) {
    try {
        $weight  = (float)$user['weight_kg'];
        $bmr     = calculateBMR($weight, (float)$user['height_cm'], (int)$user['age'], $user['gender']);
        $tdee    = calculateTDEE($bmr, $user['activity_level']);
        $targets = calculateTargets($tdee, $weight, $user['goal'], $user['experience_level']);
        $macros  = $applyDiet($targets);

        // Bảng so sánh 3 mục tiêu (cùng kiểu ăn đang chọn)
        foreach ($goalLabels as $g => $label) {
            $comparison[$g] = $applyDiet(calculateTargets($tdee, $weight, $g, $user['experience_level']));
        }
    } catch (InvalidArgumentException $e) {
        // Dữ liệu hồ sơ sai (ví dụ tuổi = 0): báo lỗi thân thiện, không để trang sập
        $error = $e->getMessage();
    }
}

// ===== 4. Lưu kết quả hôm nay (mỗi ngày một dòng, vào lại thì cập nhật dòng đó) =====
if ($macros !== null) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT id FROM nutrition_targets WHERE user_id = ? AND calculated_date = ?');
    $stmt->execute([$userId, $today]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare(
            'UPDATE nutrition_targets
             SET tdee_kcal = ?, target_kcal = ?, protein_g = ?, carbs_g = ?, fat_g = ?
             WHERE id = ?'
        );
        $stmt->execute([$tdee, $macros['target_kcal'], $macros['protein_g'], $macros['carbs_g'], $macros['fat_g'], $existing['id']]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO nutrition_targets
             (user_id, tdee_kcal, target_kcal, protein_g, carbs_g, fat_g, calculated_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $tdee, $macros['target_kcal'], $macros['protein_g'], $macros['carbs_g'], $macros['fat_g'], $today]);
    }
}

// ===== 5. Chuẩn bị dữ liệu hiển thị =====
$notes = []; // lưu ý sức khỏe (không chặn, chỉ nhắc)
$shares = ['protein' => 0, 'carbs' => 0, 'fat' => 0];
$plan = [];
$meals = 3;
$variant = 0;

if ($macros !== null) {
    $shares = macroShares($macros['protein_g'], $macros['carbs_g'], $macros['fat_g']);

    // Số bữa và phương án món lấy từ URL (chỉ để xem, không đổi dữ liệu nên không cần CSRF)
    $mealsIn = filter_var($_GET['meals'] ?? 3, FILTER_VALIDATE_INT);
    $meals = in_array($mealsIn, [3, 4, 5], true) ? $mealsIn : 3;
    $variantIn = filter_var($_GET['v'] ?? 0, FILTER_VALIDATE_INT);
    $variant = ($variantIn !== false && $variantIn >= 0 && $variantIn < 100) ? $variantIn : 0;
    $plan = buildMealPlan($macros, $meals, $variant);

    $minKcal = $user['gender'] === 'MALE' ? 1500 : 1200;
    if ($macros['target_kcal'] < $bmr) {
        $notes[] = 'Calo mục tiêu đang thấp hơn BMR của bạn. Ăn thấp hơn mức cơ thể cần khi nghỉ khó duy trì lâu và dễ mất cơ. Hãy cân nhắc mục tiêu nhẹ hơn hoặc tăng mức vận động.';
    } elseif ($macros['target_kcal'] < $minKcal) {
        $notes[] = 'Calo mục tiêu dưới ' . $minKcal . ' kcal, thấp hơn mức tối thiểu thường được khuyến nghị. Nên hỏi ý kiến chuyên gia dinh dưỡng trước khi theo.';
    }
    $proteinPerKg = $macros['protein_g'] / $weight;
    if ($proteinPerKg < 1.2) {
        $notes[] = 'Protein đang ở ' . number_format($proteinPerKg, 1, ',', '.') . ' g/kg, hơi thấp nếu bạn tập kháng lực (thường khuyến nghị khoảng 1,4 đến 2,0 g/kg).';
    } elseif ($proteinPerKg > 2.2) {
        $notes[] = 'Protein đang ở ' . number_format($proteinPerKg, 1, ',', '.') . ' g/kg, cao hơn mức cần thiết với phần lớn người tập (khoảng 1,6 đến 1,8 g/kg). Bạn có thể giảm tỉ lệ protein.';
    }
    $macroKcal = $macros['protein_g'] * 4 + $macros['carbs_g'] * 4 + $macros['fat_g'] * 9;
    if ($macroKcal > $macros['target_kcal'] * 1.05) {
        $notes[] = 'Protein và chất béo tính theo cân nặng đã vượt calo mục tiêu nên tinh bột bị đặt về 0. Hãy thử kiểu ăn theo tỉ lệ % bên dưới.';
    }
}

/** Định dạng số kiểu Việt Nam: 2.224 hoặc 2,5 */
function nf($n, int $d = 0): string
{
    return number_format((float)$n, $d, ',', '.');
}

/** Một đoạn của vòng tròn (SVG): độ dài và vị trí bắt đầu, đơn vị là % chu vi */
function donutArc(float $pct, float &$offset): array
{
    $gap = $pct > 3 ? 1.0 : 0.0;                 // khe hở nhỏ giữa các đoạn
    $arc = ['len' => max(0, $pct - $gap), 'start' => $offset];
    $offset -= $pct;
    return $arc;
}

$pageTitle = 'Dinh dưỡng';
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/nutrition.css">

<div class="nt">
    <div class="pg-head">
        <div>
            <h1>Dinh dưỡng</h1>
            <p class="pg-sub">Calo và macro mỗi ngày tính từ hồ sơ của bạn. Đổi mục tiêu hoặc kiểu ăn, kết quả và lịch ăn cập nhật theo.</p>
        </div>
        <span class="pg-count"><?= date('d/m/Y') ?></span>
    </div>

<?php if ($user && !empty($missing)): ?>
    <div class="card nt-empty">
        <h2>Hồ sơ chưa đủ thông tin</h2>
        <p>Cần bổ sung: <b><?= htmlspecialchars(implode(', ', $missing)) ?></b>.</p>
        <a class="btn" href="/profile.php">Cập nhật hồ sơ</a>
    </div>
<?php elseif ($error !== ''): ?>
    <div class="card nt-empty">
        <h2>Không tính được</h2>
        <p><?= htmlspecialchars($error) ?> Hãy kiểm tra lại hồ sơ.</p>
        <a class="btn" href="/profile.php">Mở hồ sơ</a>
    </div>
<?php elseif (!$user): ?>
    <div class="card nt-empty"><p>Không tìm thấy tài khoản.</p></div>
<?php else: ?>

    <!-- ===== Chọn mục tiêu: bấm là tính lại ===== -->
    <h2 class="nt-label">Mục tiêu của bạn <small>Bấm để đổi, calo và macro tính lại ngay</small></h2>
    <form method="post" action="/nutrition.php" class="nt-goals" aria-label="Mục tiêu tập luyện">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="goal">
        <?php foreach ($goalLabels as $g => $label):
            $delta = round($comparison[$g]['target_kcal'] - $tdee);   // làm tròn để không hiện "−0"
            $active = $user['goal'] === $g;
        ?>
            <button type="submit" name="goal" value="<?= htmlspecialchars($g) ?>"
                    class="nt-goal<?= $active ? ' is-active' : '' ?>" <?= $active ? 'aria-pressed="true"' : 'aria-pressed="false"' ?>>
                <span class="nt-goal__name"><?= htmlspecialchars($label) ?><?= $active ? ' <i>Đang chọn</i>' : '' ?></span>
                <span class="nt-goal__kcal"><?= nf($comparison[$g]['target_kcal']) ?> <small>kcal/ngày</small></span>
                <span class="nt-goal__delta"><?= $delta == 0 ? 'Bằng TDEE (không đổi)' : (($delta > 0 ? '+' : '−') . nf(abs($delta)) . ' kcal so với TDEE') /* audit:ok chỉ là số do nf() định dạng */ ?></span>
            </button>
        <?php endforeach; ?>
    </form>

    <?php foreach ($notes as $note): ?>
        <div class="alert alert--note" role="note"><div><?= htmlspecialchars($note) ?></div></div>
    <?php endforeach; ?>

    <!-- ===== Calo mục tiêu được tính như thế nào: BMR → TDEE → mục tiêu ===== -->
    <h2 class="nt-label">Calo của bạn được tính như thế nào <small>Từ trái sang phải</small></h2>
    <section class="nt-flow">
        <article class="nt-step">
            <span class="nt-step__tag">BMR</span>
            <h3>Năng lượng cơ bản</h3>
            <p class="nt-step__num"><?= nf($bmr) ?> <small>kcal/ngày</small></p>
            <p class="nt-step__desc">Số calo cơ thể đốt khi nằm nghỉ cả ngày, chỉ để duy trì sự sống: thở, tim đập, giữ thân nhiệt.</p>
            <p class="nt-step__en">Basal Metabolic Rate</p>
        </article>

        <div class="nt-op">
            <span class="nt-op__val">× <?= htmlspecialchars(rtrim(rtrim(number_format($tdee / $bmr, 3, ',', '.'), '0'), ',')) ?></span>
            <small>Hệ số vận động<br>(<?= htmlspecialchars($activityLabels[$user['activity_level']] ?? '') ?>)</small>
        </div>

        <article class="nt-step">
            <span class="nt-step__tag">TDEE</span>
            <h3>Tổng năng lượng tiêu hao</h3>
            <p class="nt-step__num"><?= nf($tdee) ?> <small>kcal/ngày</small></p>
            <p class="nt-step__desc">Tổng calo bạn đốt trong một ngày, gồm BMR cộng calo từ sinh hoạt và tập luyện. Ăn đúng mức này thì cân nặng giữ nguyên.</p>
            <p class="nt-step__en">Total Daily Energy Expenditure</p>
        </article>

        <?php $stepDelta = round($macros['target_kcal'] - $tdee); ?>
        <div class="nt-op">
            <span class="nt-op__val"><?= $stepDelta == 0 ? '± 0' : (($stepDelta > 0 ? '+ ' : '− ') . nf(abs($stepDelta))) /* audit:ok số do nf() định dạng */ ?></span>
            <small>kcal theo mục tiêu<br>(<?= htmlspecialchars($goalLabels[$user['goal']] ?? '') ?>)</small>
        </div>

        <article class="nt-step nt-step--main">
            <span class="nt-step__tag">Mục tiêu</span>
            <h3>Calo nên ăn mỗi ngày</h3>
            <p class="nt-step__num"><?= nf($macros['target_kcal']) ?> <small>kcal/ngày</small></p>
            <p class="nt-step__desc">Lượng calo phù hợp với mục tiêu của bạn. Macro và lịch ăn bên dưới được chia từ con số này.</p>
        </article>
    </section>

    <!-- ===== Macro: vòng tròn + kiểu ăn ===== -->
    <section class="nt-macro" id="macro">
        <div class="card nt-donut-card">
            <h2 class="card__head">Macro mỗi ngày</h2>
            <div class="nt-donut-wrap">
                <?php $off = 25.0; // bắt đầu từ đỉnh vòng tròn
                    $arcP = donutArc($shares['protein'], $off);
                    $arcC = donutArc($shares['carbs'], $off);
                    $arcF = donutArc($shares['fat'], $off);
                ?>
                <div class="nt-donut" role="img"
                     aria-label="Protein <?= nf($shares['protein']) ?>%, tinh bột <?= nf($shares['carbs']) ?>%, chất béo <?= nf($shares['fat']) ?>%">
                    <svg viewBox="0 0 42 42" aria-hidden="true">
                        <circle class="nt-donut__bg" cx="21" cy="21" r="15.9155"/>
                        <circle class="nt-donut__seg nt-p" cx="21" cy="21" r="15.9155"
                                stroke-dasharray="<?= round($arcP['len'], 2) ?> <?= round(100 - $arcP['len'], 2) ?>" stroke-dashoffset="<?= round($arcP['start'], 2) ?>"/>
                        <circle class="nt-donut__seg nt-c" cx="21" cy="21" r="15.9155"
                                stroke-dasharray="<?= round($arcC['len'], 2) ?> <?= round(100 - $arcC['len'], 2) ?>" stroke-dashoffset="<?= round($arcC['start'], 2) ?>"/>
                        <circle class="nt-donut__seg nt-f" cx="21" cy="21" r="15.9155"
                                stroke-dasharray="<?= round($arcF['len'], 2) ?> <?= round(100 - $arcF['len'], 2) ?>" stroke-dashoffset="<?= round($arcF['start'], 2) ?>"/>
                    </svg>
                    <div class="nt-donut__mid">
                        <b><?= nf($macros['target_kcal']) ?></b>
                        <span>kcal / ngày</span>
                    </div>
                </div>

                <ul class="nt-legend">
                    <li class="nt-p">
                        <span class="nt-dot"></span>
                        <span class="nt-legend__name">Protein</span>
                        <b><?= nf($macros['protein_g']) ?> g</b>
                        <em><?= nf($shares['protein']) ?>%</em>
                    </li>
                    <li class="nt-c">
                        <span class="nt-dot"></span>
                        <span class="nt-legend__name">Tinh bột</span>
                        <b><?= nf($macros['carbs_g']) ?> g</b>
                        <em><?= nf($shares['carbs']) ?>%</em>
                    </li>
                    <li class="nt-f">
                        <span class="nt-dot"></span>
                        <span class="nt-legend__name">Chất béo</span>
                        <b><?= nf($macros['fat_g']) ?> g</b>
                        <em><?= nf($shares['fat']) ?>%</em>
                    </li>
                </ul>
            </div>
            <p class="nt-hint">Protein <?= nf($macros['protein_g'] / $weight, 1) ?> g/kg cân nặng
                (khuyến nghị 1,6 g/kg cho người mới tập, 1,8 g/kg cho người đã có kinh nghiệm; hồ sơ của bạn: <?= htmlspecialchars($experienceLabels[$user['experience_level']] ?? 'người mới tập') ?>).
                Quy đổi: protein và tinh bột 4 kcal/g, chất béo 9 kcal/g.
                <a href="/articles.php">Tìm hiểu vai trò của protein, carb, fat</a></p>
        </div>

        <form class="card nt-diet" method="post" action="/nutrition.php#macro">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="diet">
            <h2 class="card__head">Kiểu ăn</h2>
            <p class="card__sub">Chọn cách chia macro bạn muốn. Tổng calo mỗi ngày không đổi.</p>

            <?php if ($dietError !== ''): ?>
                <div class="alert alert--error" role="alert"><?= htmlspecialchars($dietError) ?></div>
            <?php endif; ?>

            <div class="nt-styles">
                <?php foreach ($styles as $key => $style): ?>
                    <label class="nt-style">
                        <input type="radio" name="diet" value="<?= htmlspecialchars($key) ?>" <?= $formDiet === $key ? 'checked' : '' ?>>
                        <span class="nt-style__box">
                            <b><?= htmlspecialchars($style['label']) ?></b>
                            <small><?= htmlspecialchars($style['desc']) ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="nt-custom<?= $formDiet === 'custom' ? ' is-open' : '' ?>" id="nt-custom" data-kcal="<?= htmlspecialchars((string)$macros['target_kcal']) ?>">
                <div class="nt-custom__grid">
                    <div class="field">
                        <label for="nt-p">Protein (%)</label>
                        <input type="number" id="nt-p" name="p" min="10" max="40" step="1" value="<?= (int)$formPct['p'] ?>" inputmode="numeric">
                        <span class="nt-g" data-for="p"></span>
                    </div>
                    <div class="field">
                        <label for="nt-c">Tinh bột (%)</label>
                        <input type="number" id="nt-c" name="c" min="5" max="70" step="1" value="<?= (int)$formPct['c'] ?>" inputmode="numeric">
                        <span class="nt-g" data-for="c"></span>
                    </div>
                    <div class="field">
                        <label for="nt-f">Chất béo (%)</label>
                        <input type="number" id="nt-f" name="f" min="15" max="60" step="1" value="<?= (int)$formPct['f'] ?>" inputmode="numeric">
                        <span class="nt-g" data-for="f"></span>
                    </div>
                </div>
                <p class="nt-total" id="nt-total">Tổng: <b>100%</b></p>
            </div>

            <button type="submit" class="btn btn--block">Áp dụng kiểu ăn</button>
        </form>
    </section>

    <!-- ===== Lịch ăn gợi ý ===== -->
    <section class="nt-meals" id="lich-an">
        <div class="nt-meals__head">
            <div>
                <h2>Gợi ý lịch ăn trong ngày</h2>
                <p class="pg-sub">Macro được chia theo bữa, kèm món và khối lượng (cân sau khi nấu chín) để bạn dễ làm theo.</p>
            </div>
            <div class="nt-meals__ctrl">
                <div class="chips" role="group" aria-label="Số bữa mỗi ngày">
                    <?php foreach ([3, 4, 5] as $m): ?>
                        <a class="chip<?= $meals === $m ? ' is-active' : '' ?>" href="/nutrition.php?meals=<?= $m ?>&amp;v=<?= (int)$variant ?>#lich-an"><?= $m ?> bữa</a>
                    <?php endforeach; ?>
                </div>
                <a class="btn btn--ghost" href="/nutrition.php?meals=<?= (int)$meals ?>&amp;v=<?= (int)$variant + 1 ?>#lich-an">Gợi ý món khác</a>
            </div>
        </div>

        <div class="nt-meal-grid" data-n="<?= (int)$meals ?>">
            <?php
            $sum = ['p' => 0, 'c' => 0, 'f' => 0, 'kcal' => 0];
            foreach ($plan as $meal):
                $s = $meal['suggestion'];
                $sum['p'] += $s['p']; $sum['c'] += $s['c']; $sum['f'] += $s['f']; $sum['kcal'] += $s['kcal'];
            ?>
                <article class="card nt-meal">
                    <header>
                        <span class="nt-meal__time"><?= htmlspecialchars($meal['slot']['time']) ?></span>
                        <h3><?= htmlspecialchars($meal['slot']['name']) ?></h3>
                        <span class="nt-meal__kcal"><?= nf($s['kcal']) ?> kcal</span>
                    </header>
                    <ul class="nt-foods">
                        <?php foreach ($s['items'] as $item): ?>
                            <li>
                                <span><?= htmlspecialchars($item['name']) ?></span>
                                <b><?= nf($item['grams']) ?> g</b>
                                <?php if ($item['hint'] !== ''): ?><small><?= htmlspecialchars($item['hint']) ?></small><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <li class="nt-foods__free"><span>Rau xanh các loại</span><b>ăn tự do</b></li>
                    </ul>
                    <footer class="nt-pills">
                        <span class="nt-pill nt-p">P <?= nf($s['p']) ?> g</span>
                        <span class="nt-pill nt-c">C <?= nf($s['c']) ?> g</span>
                        <span class="nt-pill nt-f">F <?= nf($s['f']) ?> g</span>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="nt-sum">Tổng lịch ăn khoảng <b><?= nf($sum['kcal']) ?> kcal</b>
            (protein <?= nf($sum['p']) ?> g, tinh bột <?= nf($sum['c']) ?> g, chất béo <?= nf($sum['f']) ?> g),
            mục tiêu <?= nf($macros['target_kcal']) ?> kcal. Chênh lệch nhỏ do làm tròn khối lượng.</p>

        <?php $tips = goalTips($user['goal']); if ($tips): ?>
            <ul class="nt-tips">
                <?php foreach ($tips as $tip): ?><li><?= htmlspecialchars($tip) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- ===== So sánh các mục tiêu ===== -->
    <section class="card nt-compare">
        <h2 class="card__head">So sánh các mục tiêu</h2>
        <p class="card__sub">Cùng hồ sơ và kiểu ăn "<?= htmlspecialchars($styles[$prefs['diet']]['label']) ?>".</p>
        <div class="nt-table-wrap">
            <table class="nt-table">
                <thead>
                    <tr><th>Mục tiêu</th><th>Calo</th><th>Protein</th><th>Tinh bột</th><th>Chất béo</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($goalLabels as $g => $label): $m = $comparison[$g]; ?>
                        <tr class="<?= $user['goal'] === $g ? 'is-current' : '' ?>">
                            <td><?= htmlspecialchars($label) ?><?= $user['goal'] === $g ? ' <span class="tag">Đang chọn</span>' : '' ?></td>
                            <td><?= nf($m['target_kcal']) ?> kcal</td>
                            <td><?= nf($m['protein_g']) ?> g</td>
                            <td><?= nf($m['carbs_g']) ?> g</td>
                            <td><?= nf($m['fat_g']) ?> g</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <p class="nt-foot">
        BMR tính theo công thức Mifflin-St Jeor, TDEE = BMR × hệ số vận động. Các con số chỉ mang tính tham khảo,
        không thay thế tư vấn của bác sĩ hoặc chuyên gia dinh dưỡng, đặc biệt khi bạn có bệnh nền.
    </p>

<?php endif; ?>
</div>

<script src="/js/nutrition.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
