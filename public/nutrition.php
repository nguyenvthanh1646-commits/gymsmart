<?php
/**
 * nutrition.php — trang Dinh dưỡng: BMR, TDEE, calo mục tiêu, macro và lịch ăn gợi ý.
 *
 * Luồng chạy:
 *   1. Đọc hồ sơ (tuổi, chiều cao, cân nặng, giới tính, mức vận động, mục tiêu) từ bảng users
 *   2. POST "đổi mục tiêu"  → cập nhật users.goal → chuyển lại trang (tính lại toàn bộ)
 *      POST kiểu ăn, số bữa, loại trừ thực phẩm, nhịp và ngày tập → lưu vào bảng nutrition_preferences và SESSION
 *      (chưa import bảng thì chỉ giữ trong SESSION, xem includes/nutrition-db.php)
 *   3. Tính BMR → TDEE → calo mục tiêu + macro (hàm trong includes/tdee.php, việc của C)
 *      rồi áp kiểu ăn (includes/meal-plan.php) và chia macro cho từng bữa
 *   4. Lưu kết quả của hôm nay vào nutrition_targets (mỗi ngày 1 dòng)
 *   HLV: đang có HLV thì dùng mục tiêu HLV đặt (nếu có), khóa 3 form làm đổi calo/macro (mục tiêu, kiểu ăn, nhịp)
 *   và không tự lưu bước 4; HLV duyệt yêu cầu sửa thì mở lại trong 24 giờ (includes/coach-db.php, coachSelfEditState).
 *   Số bữa và thực phẩm loại trừ vẫn tự đổi được vì không làm thay đổi mục tiêu.
 *   5. Vẽ trang: vòng tròn macro, tiến độ cân nặng, lịch ăn hôm nay hoặc cả tuần, danh sách đi chợ, bảng so sánh các mục tiêu
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/tdee.php';
require_once __DIR__ . '/includes/meal-plan.php';
require_once __DIR__ . '/includes/nutrition-db.php';
require_once __DIR__ . '/includes/coach.php';

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
// Nhãn cho khối "Thông số của bạn" ở cột trái
$genderLabels = ['MALE' => 'Nam', 'FEMALE' => 'Nữ', 'OTHER' => 'Khác'];
$levelLabels  = ['BEGINNER' => 'Mới tập', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Nâng cao'];
$paceLabels   = ['gentle' => 'Chậm', 'standard' => 'Vừa', 'fast' => 'Nhanh'];
$weekdayNames = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'];

// ===== 1. Đọc hồ sơ =====
$stmt = $pdo->prepare(
    'SELECT age, height_cm, weight_kg, gender, activity_level, experience_level, goal, CURDATE() AS today FROM users WHERE id = ?'
);
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Lựa chọn đang dùng: ưu tiên bảng nutrition_preferences, chưa có thì lấy trong session, cuối cùng là mặc định
$prefs = normalizeNutritionPrefs(loadNutritionPrefs($pdo, $userId) ?? ($_SESSION['nutri'] ?? []));

/** Đổi vài lựa chọn rồi lưu vào session và DB (DB lỗi hoặc chưa import bảng thì vẫn còn session). */
$savePrefs = function (array $changes) use (&$prefs, $pdo, $userId): void {
    $prefs = normalizeNutritionPrefs($changes + $prefs);
    $_SESSION['nutri'] = $prefs;
    saveNutritionPrefs($pdo, $userId, $prefs);
};

// Đang có HLV: khóa tự sửa mục tiêu dinh dưỡng (kiểm tra ở PHP, không chỉ ẩn form)
$nutLock = coachSelfEditState($pdo, (int)$userId, 'NUTRITION');
$coachTarget = $nutLock['coach_id'] !== null ? coachAssignedNutrition($pdo, (int)$userId, (int)$nutLock['coach_id']) : null;

$dietError = '';
$formPct = ['p' => (int)$prefs['p'], 'c' => (int)$prefs['c'], 'f' => (int)$prefs['f']]; // giá trị hiện trong ô tùy chỉnh
$formDiet = $prefs['diet'];

// ===== 2. Xử lý form =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $action = $_POST['action'] ?? '';

    if ($nutLock['locked'] && in_array($action, ['goal', 'diet', 'rhythm'], true)) {
        coachFlash('error', 'Mục tiêu dinh dưỡng đang do HLV quản lý. Hãy gửi yêu cầu sửa tới HLV.');
        header('Location: /nutrition.php#macro');
        exit;
    }

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
            $savePrefs(['diet' => $formDiet] + $formPct);
            header('Location: /nutrition.php#macro');
            exit;
        }
        // Có lỗi: không chuyển trang, hiện lại form kèm thông báo (giữ nguyên số vừa nhập)
    }

    if ($action === 'rhythm') {
        $changes = ['cycle' => isset($_POST['cycle'])];
        // Mục tiêu duy trì không có ô chọn nhịp: giữ nguyên nhịp đã lưu
        if (isset($_POST['pace']) && in_array($_POST['pace'], array_keys($paceLabels), true)) {
            $changes['pace'] = $_POST['pace'];
        }
        $savePrefs($changes);
        header('Location: /nutrition.php#macro');
        exit;
    }

    // Hai form của khu lịch ăn giữ nguyên chế độ xem đang mở (hôm nay hay cả tuần)
    $backToMeals = '/nutrition.php' . (($_POST['view'] ?? '') === 'week' ? '?view=week' : '') . '#lich-an';

    if ($action === 'meals') {
        $newMeals = filter_var($_POST['meals'] ?? '', FILTER_VALIDATE_INT);
        if (in_array($newMeals, [3, 4, 5], true)) {
            $savePrefs(['meals' => $newMeals]);
        }
        header('Location: ' . $backToMeals);
        exit;
    }

    if ($action === 'foods') {
        $savePrefs(['exclusions' => normalizeExclusions($_POST['exclusions'] ?? [])]);
        header('Location: ' . $backToMeals);
        exit;
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
        $targets = calculateTargets($tdee, $weight, $user['goal'], $user['experience_level'], $prefs['pace']);
        $macros  = $applyDiet($targets);

        // Bảng so sánh 3 mục tiêu (cùng kiểu ăn đang chọn)
        foreach ($goalLabels as $g => $label) {
            $comparison[$g] = $applyDiet(calculateTargets($tdee, $weight, $g, $user['experience_level'], $prefs['pace']));
        }
    } catch (InvalidArgumentException $e) {
        // Dữ liệu hồ sơ sai (ví dụ tuổi = 0): báo lỗi thân thiện, không để trang sập
        $error = $e->getMessage();
    }
}

// ===== 3b. Mục tiêu do HLV đặt thay cho kết quả tự tính (chỉ khi đang khóa; HLV cho tự sửa thì dùng lại kết quả tự tính) =====
if ($macros !== null && $nutLock['locked'] && $coachTarget !== null) {
    $macros = array_merge($macros, [
        'target_kcal' => (float)$coachTarget['target_kcal'], 'protein_g' => (float)$coachTarget['protein_g'],
        'carbs_g' => (float)$coachTarget['carbs_g'], 'fat_g' => (float)$coachTarget['fat_g'],
    ]);
}

// ===== 4. Lưu kết quả hôm nay (mỗi ngày một dòng, vào lại thì cập nhật dòng đó) =====
// Đang có HLV quản lý thì không tự lưu. Chỉ cập nhật dòng tự tính (assigned_by IS NULL), không đè lên dòng HLV đặt.
if ($macros !== null && !$nutLock['locked']) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT id FROM nutrition_targets WHERE user_id = ? AND calculated_date = ?' . (coachTablesReady($pdo) ? ' AND assigned_by IS NULL' : ''));
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
$plan = ['meals' => [], 'total' => [], 'target' => []];
$meals = $prefs['meals'];
$variant = 0;
$picks = [];
$view = ($_GET['view'] ?? '') === 'week' ? 'week' : 'day';
$week = $shopping = $trainingDays = $paceRows = [];
$cycleOptions = $progressInfo = null;
$dayKind = [];                                   // [thứ => 'train' | 'rest'] khi bật ăn theo ngày tập
$todayN = 1;

if ($macros !== null) {
    $shares = macroShares($macros['protein_g'], $macros['carbs_g'], $macros['fat_g']);

    // Phương án món và các lần đổi món lấy từ URL (chỉ để xem, không đổi dữ liệu nên không cần CSRF)
    $variantIn = filter_var($_GET['v'] ?? 0, FILTER_VALIDATE_INT);
    $variant = ($variantIn !== false && $variantIn >= 0 && $variantIn < 100) ? $variantIn : 0;
    $picks = parseMealPicks($_GET['p'] ?? '', $meals);

    // "Hôm nay" lấy từ MySQL để khớp với lịch tập và nhật ký trên trang chủ
    $todayN = (int)(new DateTimeImmutable($user['today']))->format('N');

    // Mỗi nhịp mục tiêu tương ứng bao nhiêu calo và dự kiến thay đổi bao nhiêu kg/tuần
    foreach ($paceLabels as $key => $label) {
        $kcal = calculateTargets($tdee, $weight, $user['goal'], $user['experience_level'], $key)['target_kcal'];
        $paceRows[$key] = ['label' => $label, 'kcal' => $kcal, 'weekly' => expectedWeeklyChange($kcal, $tdee)];
    }

    // Ngày tập và ngày nghỉ: số ngày tập lấy từ lịch tập đang dùng, tổng calo cả tuần không đổi
    $trainingDays = loadTrainingWeekdays($pdo, $userId);
    $cycleOptions = splitTrainingRestDays($macros, count($trainingDays));
    $dayTargets = array_fill(1, 7, $macros);
    if ($prefs['cycle'] && $cycleOptions !== null) {
        for ($d = 1; $d <= 7; $d++) {
            $dayKind[$d] = in_array($d, $trainingDays, true) ? 'train' : 'rest';
            $dayTargets[$d] = $cycleOptions[$dayKind[$d]];
        }
    }

    // Lịch hôm nay chính là ngày hôm nay trong lịch tuần, nên hai chế độ xem luôn cùng món
    $week = buildWeekPlan($dayTargets, $meals, $variant, $prefs['exclusions'], [$todayN => $picks]);
    $plan = $week[$todayN];
    $shopping = buildShoppingList($week);

    $minKcal = $user['gender'] === 'MALE' ? 1500 : 1200;
    if ($dayKind && $cycleOptions['rest']['target_kcal'] < $minKcal) {
        $notes[] = 'Ngày nghỉ đang dưới ' . $minKcal . ' kcal. Bạn nên tắt ăn theo ngày tập hoặc chọn nhịp chậm hơn.';
    }

    // So cân nặng thực tế với kế hoạch; không gợi ý ăn thấp hơn BMR hay mức tối thiểu
    $progressInfo = analyzeWeightProgress(
        loadRecentWeights($pdo, $userId), $macros['target_kcal'], $tdee, max($bmr, $minKcal), $user['today']
    );
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

/** Số có dấu kiểu Việt Nam: +0,25 hoặc −0,5 */
function signedNf(float $n, int $d = 0): string
{
    if (round($n, $d) == 0) {
        return nf(0, $d);
    }
    return ($n > 0 ? '+' : '−') . nf(abs($n), $d);
}

/** Khối lượng cho danh sách đi chợ: từ 1000 g đổi sang kg. */
function shoppingAmount(int $grams): string
{
    return $grams >= 1000 ? nf($grams / 1000, 1) . ' kg' : nf($grams) . ' g';
}

/** Tổng cả ngày có khớp mục tiêu không: mỗi macro lệch tối đa 1 g, calo lệch tối đa 1% (phần còn lại chỉ do làm tròn). */
function dayMatchesTarget(array $plan): bool
{
    $ok = abs($plan['total']['kcal'] - $plan['target']['kcal']) <= $plan['target']['kcal'] * 0.01;
    foreach (['p', 'c', 'f'] as $k) {
        $ok = $ok && abs($plan['total'][$k] - $plan['target'][$k]) <= 1;
    }
    return $ok;
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
<link rel="stylesheet" href="/css/coach.css">

<div class="nt">
    <div class="pg-head">
        <div>
            <h1>Dinh dưỡng</h1>
            <p class="pg-sub">Calo và macro mỗi ngày tính từ hồ sơ của bạn. Đổi mục tiêu hoặc kiểu ăn, kết quả và lịch ăn cập nhật theo.</p>
        </div>
        <span class="pg-count"><?= date('d/m/Y') ?></span>
    </div>

    <?php $ntFlash = coachTakeFlash(); if ($ntFlash): ?>
        <div class="alert alert--<?= $ntFlash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($ntFlash[1]) ?></div>
    <?php endif; ?>
    <?php if ($nutLock['coach_id'] !== null): ?>
        <div class="ch-lock<?= $nutLock['locked'] ? '' : ' is-open' ?>" role="note">
            <div class="ch-lock__txt">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0<?= $nutLock['locked'] ? 'v3' : '' ?>"/></svg>
                <div>
                    <?php if ($nutLock['locked']): ?>
                        <b><?= $coachTarget !== null ? 'Mục tiêu do HLV ' . htmlspecialchars((string)$nutLock['coach_name']) . ' giao' : 'HLV ' . htmlspecialchars((string)$nutLock['coach_name']) . ' đang quản lý dinh dưỡng của bạn' ?></b>
                        <span>Bạn vẫn đổi được số bữa và thực phẩm loại trừ. Đổi calo/macro cần HLV đồng ý.</span>
                    <?php else: ?>
                        <b>HLV cho phép bạn tự sửa tới <?= htmlspecialchars(date('H:i d/m', strtotime((string)$nutLock['unlock_until']))) ?></b>
                        <span>Trong thời gian này trang dùng kết quả tự tính theo lựa chọn của bạn.</span>
                    <?php endif; ?>
                </div>
            </div>
            <a class="btn btn--ghost" href="/my-coach.php">HLV của tôi</a>
        </div>
    <?php endif; ?>

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

    <?php
    // ===== Số liệu cho cột kết quả (chỉ định dạng để hiển thị, không tính lại công thức) =====
    $factorText = rtrim(rtrim(number_format($tdee / $bmr, 3, ',', '.'), '0'), ',');  // hệ số vận động, ví dụ 1,55
    $stepDelta  = round($macros['target_kcal'] - $tdee);                            // làm tròn để không hiện "−0"
    $off  = 25.0; // vòng tròn bắt đầu từ đỉnh
    $arcP = donutArc($shares['protein'], $off);
    $arcC = donutArc($shares['carbs'], $off);
    $arcF = donutArc($shares['fat'], $off);
    // 3 thẻ macro: [class màu, tên, gram, % calo, kcal mỗi gram]
    $macroCards = [
        ['nt-p', 'Protein',  $macros['protein_g'], $shares['protein'], 4],
        ['nt-c', 'Tinh bột', $macros['carbs_g'],   $shares['carbs'],   4],
        ['nt-f', 'Chất béo', $macros['fat_g'],     $shares['fat'],     9],
    ];
    // Chiều cao, cân nặng: số nguyên thì không hiện ",0"
    $nfBody = fn($v) => nf($v, floor((float)$v) == (float)$v ? 0 : 1);
    ?>

    <!-- ===== Bố cục 2 cột: trái là form (thông số, mục tiêu, kiểu ăn), phải là kết quả ===== -->
    <section class="nt-calc" id="macro">
        <div class="nt-side">
            <section class="card nt-profile" aria-labelledby="nt-profile-title">
                <div class="nt-card-top">
                    <h2 class="card__head" id="nt-profile-title">Thông số của bạn</h2>
                    <a class="nt-link" href="/profile.php">Sửa hồ sơ</a>
                </div>
                <dl class="nt-facts">
                    <div><dt>Giới tính</dt><dd><?= htmlspecialchars($genderLabels[$user['gender']] ?? '') ?></dd></div>
                    <div><dt>Tuổi</dt><dd><?= (int)$user['age'] ?></dd></div>
                    <div><dt>Chiều cao</dt><dd><?= htmlspecialchars($nfBody($user['height_cm'])) ?> cm</dd></div>
                    <div><dt>Cân nặng</dt><dd><?= htmlspecialchars($nfBody($user['weight_kg'])) ?> kg</dd></div>
                    <div class="nt-facts__wide"><dt>Vận động</dt><dd><?= htmlspecialchars($activityLabels[$user['activity_level']] ?? '') ?></dd></div>
                    <div class="nt-facts__wide"><dt>Trình độ</dt><dd><?= htmlspecialchars($levelLabels[$user['experience_level']] ?? 'Chưa chọn') ?></dd></div>
                </dl>
            </section>

            <?php if ($nutLock['locked']): ?>
            <!-- Đang có HLV: thay 3 form đổi mục tiêu bằng thẻ mục tiêu HLV + nút xin sửa (form gửi về my-coach.php) -->
            <section class="card" aria-labelledby="nt-coach-title">
                <h2 class="card__head" id="nt-coach-title">Mục tiêu do HLV quản lý</h2>
                <?php if ($coachTarget !== null): ?>
                    <p class="card__sub">HLV <?= htmlspecialchars((string)$nutLock['coach_name']) ?> đặt ngày <?= htmlspecialchars(date('d/m/Y', strtotime($coachTarget['calculated_date']))) ?>: <b><?= nf($coachTarget['target_kcal']) ?> kcal</b> · P <?= nf($coachTarget['protein_g']) ?> g · C <?= nf($coachTarget['carbs_g']) ?> g · F <?= nf($coachTarget['fat_g']) ?> g.</p>
                <?php else: ?>
                    <p class="card__sub">HLV <?= htmlspecialchars((string)$nutLock['coach_name']) ?> chưa đặt mục tiêu riêng, trang đang dùng mức tính từ hồ sơ của bạn.</p>
                <?php endif; ?>
                <?php if ($nutLock['pending']): ?>
                    <span class="ch-badge ch-badge--warn">Đã xin sửa, chờ HLV trả lời</span>
                <?php else: ?>
                    <button type="button" class="btn btn--ghost btn--block" data-toggle="nt-change">Xin HLV cho tự sửa</button>
                    <form id="nt-change" class="ch-req" method="post" action="/my-coach.php" hidden>
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change">
                        <input type="hidden" name="area" value="NUTRITION">
                        <label class="ch-label" for="nt-reason">Lý do</label>
                        <textarea id="nt-reason" name="reason" maxlength="<?= COACH_NOTE_MAX ?>" required placeholder="Ví dụ: muốn đổi sang ăn ít tinh bột"></textarea>
                        <div class="ch-req__row"><button type="submit" class="btn" data-busy-text="Đang gửi…">Gửi yêu cầu</button></div>
                    </form>
                <?php endif; ?>
            </section>
            <?php else: ?>
            <!-- Chọn mục tiêu: bấm là tính lại -->
            <form class="card nt-goal-form" method="post" action="/nutrition.php" aria-labelledby="nt-goal-title">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="goal">
                <h2 class="card__head" id="nt-goal-title">Mục tiêu</h2>
                <p class="card__sub">Bấm để đổi, calo và macro tính lại ngay.</p>
                <div class="nt-goals">
                    <?php foreach ($goalLabels as $g => $label):
                        $delta = round($comparison[$g]['target_kcal'] - $tdee);
                        $active = $user['goal'] === $g;
                    ?>
                        <button type="submit" name="goal" value="<?= htmlspecialchars($g) ?>"
                                class="nt-goal<?= $active ? ' is-active' : '' ?>" <?= $active ? 'aria-pressed="true"' : 'aria-pressed="false"' ?>>
                            <span class="nt-goal__name"><?= htmlspecialchars($label) ?><?= $active ? ' <i>Đang chọn</i>' : '' ?></span>
                            <span class="nt-goal__kcal"><?= nf($comparison[$g]['target_kcal']) ?> <small>kcal</small></span>
                            <span class="nt-goal__delta"><?= $delta == 0 ? 'Bằng TDEE' : (($delta > 0 ? '+' : '−') . nf(abs($delta)) . ' kcal so với TDEE') /* audit:ok chỉ là số do nf() định dạng */ ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </form>

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

            <form class="card nt-rhythm" method="post" action="/nutrition.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="rhythm">
                <h2 class="card__head">Nhịp và ngày tập</h2>
                <?php if ($user['goal'] === 'MAINTAIN'): ?>
                    <p class="card__sub">Mục tiêu duy trì luôn ăn bằng TDEE nên không cần chọn nhịp.</p>
                <?php else: ?>
                    <p class="card__sub">Nhịp càng nhanh thì càng khó theo lâu dài. Con số kg/tuần chỉ là ước tính.</p>
                    <div class="nt-styles">
                        <?php foreach ($paceRows as $key => $row): ?>
                            <label class="nt-style">
                                <input type="radio" name="pace" value="<?= htmlspecialchars($key) ?>" <?= $prefs['pace'] === $key ? 'checked' : '' ?>>
                                <span class="nt-style__box">
                                    <b><?= htmlspecialchars($row['label']) ?></b>
                                    <small><?= nf($row['kcal']) ?> kcal/ngày, dự kiến <?= htmlspecialchars(signedNf($row['weekly'], 2)) ?> kg/tuần</small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($cycleOptions !== null): ?>
                    <label class="nt-toggle">
                        <input type="checkbox" name="cycle" value="1" <?= $prefs['cycle'] ? 'checked' : '' ?>>
                        <span><b>Ăn nhiều hơn vào ngày tập</b>
                            <small>Lịch tập của bạn có <?= count($trainingDays) ?> ngày/tuần. Ngày tập <?= nf($cycleOptions['train']['target_kcal']) ?> kcal, ngày nghỉ <?= nf($cycleOptions['rest']['target_kcal']) ?> kcal. Tổng calo cả tuần không đổi, phần chênh nằm ở tinh bột.</small>
                        </span>
                    </label>
                <?php elseif (!$trainingDays): ?>
                    <p class="nt-hint nt-hint--flush">Muốn ăn khác nhau giữa ngày tập và ngày nghỉ, bạn cần có lịch tập trước. <a href="/workout-plan.php">Tạo lịch tập</a></p>
                <?php else: ?>
                    <p class="nt-hint nt-hint--flush">Chưa chia được calo theo ngày tập, vì lịch của bạn tập cả 7 ngày hoặc kiểu ăn hiện tại không có tinh bột để chia.</p>
                <?php endif; ?>

                <button type="submit" class="btn btn--block">Áp dụng</button>
            </form>
            <?php endif; /* hết phần khóa khi có HLV */ ?>
        </div>

        <!-- ===== Cột kết quả ===== -->
        <div class="nt-result">
            <?php foreach ($notes as $note): ?>
                <div class="alert alert--note" role="note"><div><?= htmlspecialchars($note) ?></div></div>
            <?php endforeach; ?>

            <div class="nt-kpis">
                <article class="nt-kpi">
                    <span class="nt-kpi__tag">TDEE</span>
                    <p class="nt-kpi__label">Tổng năng lượng tiêu hao</p>
                    <p class="nt-kpi__num"><?= nf($tdee) ?> <small>kcal/ngày</small></p>
                    <p class="nt-kpi__sub">BMR <b><?= nf($bmr) ?></b> × <b><?= htmlspecialchars($factorText) ?></b>
                        (<?= htmlspecialchars($activityLabels[$user['activity_level']] ?? '') ?>). Ăn đúng mức này thì cân nặng giữ nguyên.</p>
                </article>
                <article class="nt-kpi nt-kpi--main">
                    <span class="nt-kpi__tag">Mục tiêu calo</span>
                    <p class="nt-kpi__label">Calo nên ăn mỗi ngày</p>
                    <p class="nt-kpi__num"><?= nf($macros['target_kcal']) ?> <small>kcal/ngày</small></p>
                    <p class="nt-kpi__sub"><b><?= $stepDelta == 0 ? '± 0' : (($stepDelta > 0 ? '+' : '−') . nf(abs($stepDelta))) /* audit:ok số do nf() định dạng */ ?> kcal</b>
                        so với TDEE (<?= htmlspecialchars($goalLabels[$user['goal']] ?? '') ?><?= $user['goal'] !== 'MAINTAIN' ? ', nhịp ' . htmlspecialchars(mb_strtolower($paceLabels[$prefs['pace']])) : '' ?>). Macro và lịch ăn được chia từ con số này.</p>
                </article>
            </div>

            <section class="card nt-macro-card" aria-labelledby="nt-macro-title">
                <div class="nt-card-top">
                    <h2 class="card__head" id="nt-macro-title">Macro mỗi ngày</h2>
                    <span class="tag"><?= htmlspecialchars($styles[$prefs['diet']]['label']) ?></span>
                </div>
                <div class="nt-macro-body">
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

                    <div class="nt-mstats">
                        <?php foreach ($macroCards as [$cls, $name, $grams, $pct, $kcalPerGram]): ?>
                            <article class="nt-mstat <?= htmlspecialchars($cls) ?>">
                                <span class="nt-mstat__name"><span class="nt-dot"></span><?= htmlspecialchars($name) ?></span>
                                <p class="nt-mstat__num"><?= nf($grams) ?> <small>g</small></p>
                                <p class="nt-mstat__meta"><?= nf($pct) ?>% · <?= nf($grams * $kcalPerGram) ?> kcal</p>
                                <span class="nt-bar" aria-hidden="true"><span style="width: <?= round($pct, 1) ?>%"></span></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p class="nt-hint">Protein <?= nf($macros['protein_g'] / $weight, 1) ?> g/kg cân nặng
                    (khuyến nghị 1,6 g/kg cho người mới tập, 1,8 g/kg cho người đã có kinh nghiệm; hồ sơ của bạn: <?= htmlspecialchars($experienceLabels[$user['experience_level']] ?? 'người mới tập') ?>).
                    Quy đổi: protein và tinh bột 4 kcal/g, chất béo 9 kcal/g.
                    <a href="/articles.php">Tìm hiểu vai trò của protein, carb, fat</a></p>
            </section>
        </div>
    </section>

    <!-- ===== Tiến độ cân nặng thực tế so với kế hoạch ===== -->
    <section class="card nt-track" aria-labelledby="nt-track-title">
        <div class="nt-card-top">
            <h2 class="card__head" id="nt-track-title">Tiến độ thực tế</h2>
            <a class="nt-link" href="/progress.php">Ghi cân nặng</a>
        </div>
        <?php if ($progressInfo['status'] === 'need_more'): ?>
            <p class="nt-track__msg">Bạn đã có <b><?= (int)$progressInfo['count'] ?></b> lần cân trong <b><?= (int)$progressInfo['span_days'] ?></b> ngày gần nhất.
                Cần ít nhất <?= (int)$progressInfo['need_count'] ?> lần cân, trải trên <?= (int)$progressInfo['need_days'] ?> ngày, để so với kế hoạch cho đáng tin.</p>
        <?php elseif ($progressInfo['status'] === 'stale'): ?>
            <p class="nt-track__msg">Lần cân gần nhất đã quá 14 ngày. Hãy cân lại để xem tiến độ hiện tại.</p>
        <?php else:
            $trackMsg = [
                'on_track'   => 'Tiến độ đang đúng kế hoạch, bạn chưa cần đổi lượng calo.',
                'above_plan' => 'Cân nặng đang cao hơn kế hoạch.',
                'below_plan' => 'Cân nặng đang thấp hơn kế hoạch.',
            ][$progressInfo['status']];
            $adjust = $progressInfo['adjust_kcal'];
        ?>
            <div class="nt-track__grid">
                <div><span>Thực tế</span><b><?= htmlspecialchars(signedNf($progressInfo['actual_per_week'], 2)) ?></b><small>kg/tuần</small></div>
                <div><span>Kế hoạch</span><b><?= htmlspecialchars(signedNf($progressInfo['planned_per_week'], 2)) ?></b><small>kg/tuần</small></div>
                <div><span>TDEE ước tính</span><b><?= nf($progressInfo['estimated_tdee']) ?></b><small>kcal/ngày</small></div>
                <div class="<?= $adjust !== 0 ? 'is-action' : '' ?>"><span>Gợi ý calo</span><b><?= nf($progressInfo['suggested_kcal']) ?></b><small><?= $adjust === 0 ? 'giữ nguyên' : htmlspecialchars(signedNf($adjust)) . ' kcal/ngày' ?></small></div>
            </div>
            <p class="nt-track__msg"><?= htmlspecialchars($trackMsg) ?>
                <?php if ($adjust !== 0): ?>Thử ăn theo mức gợi ý trong 2 tuần rồi xem lại.<?php endif; ?>
                <?php if ($progressInfo['limited']): ?>Mức gợi ý đã dừng ở ngưỡng thấp nhất nên ăn, không giảm thêm.<?php endif; ?></p>
        <?php endif; ?>
        <p class="nt-hint">So sánh các lần cân trong 28 ngày gần nhất. Kết quả chỉ đúng khi bạn ăn gần với calo mục tiêu, và trang không tự đổi calo của bạn.</p>
    </section>

    <!-- ===== Lịch ăn gợi ý ===== -->
    <?php
    // Đường dẫn của khu lịch ăn, giữ lại những gì đang xem
    $mealUrl = function (array $query) use ($variant, $picks, $view): string {
        $query += ['v' => $variant, 'p' => implode(',', $picks), 'view' => $view];
        $query = array_filter($query, fn($value) => !in_array($value, [0, 'day', ''], true) && !preg_match('/^0(,0)*$/', (string)$value));
        return '/nutrition.php' . ($query ? '?' . http_build_query($query) : '') . '#lich-an';
    };
    $dayKindLabels = ['train' => 'Ngày tập', 'rest' => 'Ngày nghỉ'];
    ?>
    <section class="nt-meals" id="lich-an">
        <div class="nt-meals__head">
            <div>
                <h2>Gợi ý lịch ăn</h2>
                <p class="pg-sub">Macro được chia theo bữa, kèm món và khối lượng (cân sau khi nấu chín) để bạn dễ làm theo.</p>
            </div>
            <div class="nt-meals__ctrl">
                <div class="chips" role="group" aria-label="Chế độ xem">
                    <a class="chip<?= $view === 'day' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($mealUrl(['view' => 'day'])) ?>" <?= $view === 'day' ? 'aria-current="true"' : '' ?>>Hôm nay</a>
                    <a class="chip<?= $view === 'week' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($mealUrl(['view' => 'week'])) ?>" <?= $view === 'week' ? 'aria-current="true"' : '' ?>>Cả tuần</a>
                </div>
                <form class="chips" method="post" action="/nutrition.php" aria-label="Số bữa mỗi ngày">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="meals">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                    <?php foreach ([3, 4, 5] as $m): ?>
                        <button type="submit" name="meals" value="<?= $m ?>" class="chip<?= $meals === $m ? ' is-active' : '' ?>" aria-pressed="<?= $meals === $m ? 'true' : 'false' ?>"><?= $m ?> bữa</button>
                    <?php endforeach; ?>
                </form>
                <a class="btn btn--ghost" href="<?= htmlspecialchars($mealUrl(['v' => $variant + 1 < 100 ? $variant + 1 : 0, 'p' => ''])) ?>">Gợi ý món khác</a>
            </div>
        </div>

        <!-- Loại trừ thực phẩm -->
        <form class="nt-excl" method="post" action="/nutrition.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="foods">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <fieldset>
                <legend>Thực phẩm không ăn</legend>
                <div class="nt-excl__list">
                    <?php foreach (foodExclusions() as $code => $exclusion): ?>
                        <label class="nt-excl__item" <?= $exclusion['hint'] !== '' ? 'title="' . htmlspecialchars($exclusion['hint']) . '"' : '' ?>>
                            <input type="checkbox" name="exclusions[]" value="<?= htmlspecialchars($code) ?>" <?= in_array($code, $prefs['exclusions'], true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($exclusion['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <button type="submit" class="btn btn--ghost">Lưu lựa chọn</button>
        </form>

        <?php if ($view === 'day'): ?>
        <?php if ($dayKind): ?>
            <p class="nt-daytype">Hôm nay là <b><?= htmlspecialchars(mb_strtolower($dayKindLabels[$dayKind[$todayN]])) ?></b>: <?= nf($dayTargets[$todayN]['target_kcal']) ?> kcal.</p>
        <?php endif; ?>
        <div class="nt-meal-grid" data-n="<?= (int)$meals ?>">
            <?php foreach ($plan['meals'] as $slotIndex => $meal):
                $s = $meal['suggestion'];
                $nextPicks = $picks;
                $nextPicks[$slotIndex] = ($picks[$slotIndex] + 1) % 100;
            ?>
                <article class="card nt-meal">
                    <header>
                        <span class="nt-meal__time"><?= htmlspecialchars($meal['slot']['time']) ?></span>
                        <h3><?= htmlspecialchars($meal['slot']['name']) ?></h3>
                    </header>
                    <div class="nt-meal__dish">
                        <p class="nt-meal__name"><?= htmlspecialchars($s['dish']) ?></p>
                        <p class="nt-meal__kcal"><?= nf($s['kcal']) ?> <small>kcal</small></p>
                    </div>
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
                    <?php if ($s['can_swap']): ?>
                        <a class="btn btn--ghost nt-swap" data-slot="<?= (int)$slotIndex ?>" href="<?= htmlspecialchars($mealUrl(['p' => implode(',', $nextPicks)])) ?>" aria-label="Đổi món <?= htmlspecialchars(mb_strtolower($meal['slot']['name'])) ?>">Đổi món</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php
        // Tổng cả ngày (cộng từ các bữa ở trên) so với mục tiêu. Coi là khớp khi mỗi macro lệch tối đa 1 g
        // và calo lệch tối đa 1% (phần lệch còn lại chỉ do làm tròn khối lượng thực phẩm).
        $total = $plan['total'];
        $goalRow = $plan['target'];
        $dayRows = [
            'kcal' => ['Calo', 'kcal'], 'p' => ['Protein', 'g'], 'c' => ['Tinh bột', 'g'], 'f' => ['Chất béo', 'g'],
        ];
        $dayOk = dayMatchesTarget($plan);
        ?>
        <section class="card nt-day" aria-labelledby="nt-day-title">
            <div class="nt-card-top">
                <h3 class="card__head" id="nt-day-title">Tổng cả ngày</h3>
                <span class="nt-day__status<?= $dayOk ? ' is-ok' : '' ?>"><?= $dayOk ? 'Khớp mục tiêu' : 'Chưa khớp mục tiêu' ?></span>
            </div>
            <div class="nt-table-wrap">
                <table class="nt-table nt-day__table">
                    <thead>
                        <tr><th></th><?php foreach ($dayRows as [$label, $unit]): ?><th><?= htmlspecialchars($label) ?></th><?php endforeach; ?></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Tổng các bữa</td>
                            <?php foreach ($dayRows as $k => [$label, $unit]): ?><td><b><?= nf($total[$k]) ?></b> <?= htmlspecialchars($unit) ?></td><?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Mục tiêu</td>
                            <?php foreach ($dayRows as $k => [$label, $unit]): ?><td><?= nf($goalRow[$k]) ?> <?= htmlspecialchars($unit) ?></td><?php endforeach; ?>
                        </tr>
                        <tr class="nt-day__diff">
                            <td>Chênh lệch</td>
                            <?php foreach ($dayRows as $k => [$label, $unit]): $d = $total[$k] - $goalRow[$k]; ?>
                                <td><?= $d == 0 ? '0' : (($d > 0 ? '+' : '−') . nf(abs($d))) /* audit:ok số do nf() định dạng */ ?> <?= htmlspecialchars($unit) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="nt-sum">Đổi món hoặc làm tròn khối lượng ở bữa trước thì bữa sau tự bù; bữa cuối được tính chính xác tới 1 g để tổng ngày khớp mục tiêu.
                <?php if (!$dayOk && $prefs['exclusions']): ?>Ngày này chưa khớp vì lựa chọn thực phẩm đang hạn chế nguồn đạm ít béo.<?php endif; ?></p>
        </section>

        <?php else: ?>
        <!-- Lịch cả tuần -->
        <div class="nt-week">
            <?php foreach ($week as $weekday => $dayPlan): ?>
                <article class="card nt-wday<?= $weekday === $todayN ? ' is-today' : '' ?>">
                    <header class="nt-wday__head">
                        <h3><?= htmlspecialchars($weekdayNames[$weekday]) ?></h3>
                        <?php if ($weekday === $todayN): ?><span class="tag">Hôm nay</span><?php endif; ?>
                        <?php if ($dayKind): ?><span class="tag"><?= htmlspecialchars($dayKindLabels[$dayKind[$weekday]]) ?></span><?php endif; ?>
                        <p class="nt-wday__kcal"><?= nf($dayPlan['total']['kcal']) ?> <small>kcal</small></p>
                    </header>
                    <?php foreach ($dayPlan['meals'] as $meal): ?>
                        <div class="nt-wmeal">
                            <p class="nt-wmeal__slot"><?= htmlspecialchars($meal['slot']['name']) ?></p>
                            <p class="nt-wmeal__dish"><?= htmlspecialchars($meal['suggestion']['dish']) ?></p>
                            <p class="nt-wmeal__foods"><?= htmlspecialchars(implode(', ', array_map(fn($item) => $item['name'] . ' ' . nf($item['grams']) . ' g', $meal['suggestion']['items']))) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <p class="nt-wday__macro">P <?= nf($dayPlan['total']['p']) ?> g · C <?= nf($dayPlan['total']['c']) ?> g · F <?= nf($dayPlan['total']['f']) ?> g</p>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Danh sách đi chợ -->
        <section class="card nt-shop" aria-labelledby="nt-shop-title">
            <h3 class="card__head" id="nt-shop-title">Danh sách đi chợ cho 7 ngày</h3>
            <p class="card__sub">Cộng dồn từ lịch ăn ở trên. Khối lượng là sau khi nấu chín (yến mạch tính khô), nên khi mua đồ sống số gram sẽ khác.</p>
            <div class="nt-shop__groups">
                <?php foreach (array_unique(array_column($shopping, 'group')) as $group): ?>
                    <div>
                        <h4><?= htmlspecialchars($group) ?></h4>
                        <ul class="nt-foods">
                            <?php foreach ($shopping as $row): if ($row['group'] !== $group) { continue; } ?>
                                <li>
                                    <span><?= htmlspecialchars($row['name']) ?></span>
                                    <b><?= htmlspecialchars(shoppingAmount($row['grams'])) ?></b>
                                    <?php if ($row['hint'] !== ''): ?><small><?= htmlspecialchars($row['hint']) ?></small><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

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
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
