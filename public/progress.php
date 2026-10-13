<?php
/**
 * progress.php — Tiến trình: nhập số đo cơ thể theo ngày và xem lịch sử (việc C-3).
 *
 * Luồng chạy:
 *   1. Chưa đăng nhập        → chuyển về /login.php (requireLogin)
 *   2. Mở trang (GET)         → đọc số đo của CHÍNH user này, tính thống kê, hiện form + lịch sử
 *                               (?edit=ID → điền sẵn form để sửa một bản ghi; ?all=1 → xem hết lịch sử)
 *   3. Bấm "Lưu" (POST)       → kiểm tra mã CSRF, kiểm tra từng ô, rồi INSERT (hoặc UPDATE nếu sửa)
 *   4. Bấm "Xóa" (POST)       → DELETE đúng bản ghi của user đang đăng nhập
 *   5. Lưu/xóa xong           → chuyển hướng về chính trang này (PRG) để F5 không gửi lại form
 *
 * Quy ước dữ liệu: mỗi user chỉ có MỘT bản ghi cho mỗi ngày. Nhập lại cùng ngày thì cập nhật bản ghi cũ
 * (không tạo trùng), vì biểu đồ ở bước sau cần đúng một điểm mỗi ngày.
 * Bảng: body_metrics (sql/body_metrics.sql). Biểu đồ (C-4) sẽ nằm ở bước sau, dữ liệu lấy qua api/body-metrics.php.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';

requireLogin();

// Lấy id từ SESSION, KHÔNG lấy từ form/URL (nếu không, người dùng sửa số id là xem/xóa được dữ liệu người khác).
$userId = (int)$_SESSION['user_id'];
$today  = date('Y-m-d');
$minDay = '2000-01-01';

// Các ô số đo: nhãn, đơn vị, khoảng hợp lệ, có bắt buộc không.
// Khoảng chọn rộng vừa đủ để chặn gõ nhầm (vd 700 kg), không chặn người thật.
$fieldDefs = [
    'weight_kg'        => ['label' => 'Cân nặng',     'unit' => 'kg', 'min' => 20, 'max' => 300, 'required' => true],
    'body_fat_percent' => ['label' => '% mỡ cơ thể',  'unit' => '%',  'min' => 2,  'max' => 60,  'required' => false],
    'chest_cm'         => ['label' => 'Vòng ngực',    'unit' => 'cm', 'min' => 50, 'max' => 200, 'required' => false],
    'waist_cm'         => ['label' => 'Vòng eo',      'unit' => 'cm', 'min' => 40, 'max' => 200, 'required' => false],
    'arm_cm'           => ['label' => 'Vòng tay',     'unit' => 'cm', 'min' => 15, 'max' => 80,  'required' => false],
];

// ---------- Hàm tiện ích ----------

/** Đọc một ô POST dạng chữ; nếu ai đó gửi mảng (weight_kg[]=...) thì coi như rỗng thay vì lỗi. */
function postText(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/**
 * Đọc số thập phân người dùng gõ (chấp nhận dấu phẩy kiểu Việt: 68,5).
 * Trả về: null nếu để trống, false nếu sai định dạng/ngoài khoảng, ngược lại là số đã làm tròn 1 chữ số.
 */
function parseDecimal(string $raw, float $min, float $max)
{
    $raw = trim(str_replace(',', '.', $raw));
    if ($raw === '') {
        return null;
    }
    // Chỉ cho chữ số, tối đa 3 chữ số phần nguyên và 2 chữ số thập phân (chặn "1e5", "-5", "abc", "0x1A")
    if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $raw)) {
        return false;
    }
    $v = (float)$raw;
    return ($v < $min || $v > $max) ? false : round($v, 1);
}

/** Kiểm tra chuỗi ngày đúng dạng Y-m-d và là ngày có thật (không nhận 2026-02-31). */
function validDate(string $s): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d !== false && $d->format('Y-m-d') === $s;
}

function dmy(string $ymd): string
{
    return date('d/m/Y', strtotime($ymd));
}

/** Số đẹp kiểu Việt: 68,5 */
function num($v, int $dec = 1): string
{
    return number_format((float)$v, $dec, ',', '.');
}

/** Chênh lệch có dấu: +1,2 / −0,8 / 0,0 */
function signed(float $d): string
{
    if (abs($d) < 0.05) {
        return '0,0';
    }
    return ($d > 0 ? '+' : '−') . number_format(abs($d), 1, ',', '.');
}

/**
 * Tô màu thay đổi cân nặng theo MỤC TIÊU của user: giảm mỡ thì giảm là tốt, tăng cơ thì tăng là tốt,
 * duy trì thì dao động nhỏ là tốt. "bad" chỉ là màu hổ phách nhắc nhẹ, không phải lỗi.
 */
function deltaTone(float $d, string $goal): string
{
    if (abs($d) < 0.05) {
        return 'flat';
    }
    if ($goal === 'LOSE_FAT') {
        return $d < 0 ? 'good' : 'bad';
    }
    if ($goal === 'GAIN_MUSCLE') {
        return $d > 0 ? 'good' : 'bad';
    }
    if ($goal === 'MAINTAIN') {
        return abs($d) <= 0.5 ? 'good' : 'bad';
    }
    return 'flat';
}

/** Chuỗi toạ độ cho đường biểu đồ nhỏ (sparkline) trong khung 100 x 30 */
function sparkPoints(array $values): string
{
    $n = count($values);
    if ($n < 2) {
        return '';
    }
    $min = min($values);
    $max = max($values);
    $span = ($max - $min) ?: 1;
    $pts = [];
    foreach (array_values($values) as $i => $v) {
        $x = round($i * 100 / ($n - 1), 1);
        $y = round(27 - (($v - $min) / $span) * 24, 1);
        $pts[] = $x . ',' . $y;
    }
    return implode(' ', $pts);
}

function redirectBack(): void
{
    header('Location: /progress.php');
    exit;
}

// ---------- Thông tin user (chiều cao để tính BMI, mục tiêu để tô màu) ----------
$stmt = $pdo->prepare('SELECT height_cm, weight_kg, goal FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    // Session còn nhưng user đã bị xóa khỏi DB → đăng xuất cho an toàn
    header('Location: /logout.php');
    exit;
}
$goal = (string)($user['goal'] ?? '');

// ---------- Xử lý POST ----------
$errors = [];
$form = ['id' => '', 'recorded_date' => $today];
foreach ($fieldDefs as $f => $def) {
    $form[$f] = '';
}
$editId = null;   // đang sửa bản ghi nào (null = thêm mới)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $action = postText('action');

    // ===== Xóa =====
    if ($action === 'delete') {
        $delId = filter_var(postText('id'), FILTER_VALIDATE_INT);
        if ($delId === false || $delId < 1) {
            $_SESSION['flash'] = ['error', 'Số đo không hợp lệ.'];
        } else {
            // "AND user_id = ?": chỉ xóa được bản ghi của chính mình, sửa id trên form cũng vô ích
            $stmt = $pdo->prepare('DELETE FROM body_metrics WHERE id = ? AND user_id = ?');
            $stmt->execute([$delId, $userId]);
            $_SESSION['flash'] = $stmt->rowCount() > 0
                ? ['ok', 'Đã xóa số đo.']
                : ['error', 'Không tìm thấy số đo cần xóa.'];
        }
        redirectBack();
    }

    // ===== Lưu (thêm mới hoặc sửa) =====
    if ($action === 'save') {
        $form['id'] = postText('id');
        $form['recorded_date'] = postText('recorded_date');
        foreach ($fieldDefs as $f => $def) {
            $form[$f] = postText($f);
        }

        // Đang sửa? Phải đúng là bản ghi của user này.
        if ($form['id'] !== '') {
            $editId = filter_var($form['id'], FILTER_VALIDATE_INT);
            $owned = false;
            if ($editId !== false && $editId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM body_metrics WHERE id = ? AND user_id = ?');
                $stmt->execute([$editId, $userId]);
                $owned = (bool)$stmt->fetch();
            }
            if (!$owned) {
                $_SESSION['flash'] = ['error', 'Không tìm thấy số đo cần sửa.'];
                redirectBack();
            }
        }

        // Ngày
        $date = $form['recorded_date'];
        if (!validDate($date)) {
            $errors[] = 'Vui lòng chọn ngày đo hợp lệ.';
        } elseif ($date > $today) {
            $errors[] = 'Ngày đo không được ở tương lai.';
        } elseif ($date < $minDay) {
            $errors[] = 'Ngày đo quá xa (từ năm 2000 trở đi).';
        }

        // Các số đo
        $vals = [];
        foreach ($fieldDefs as $f => $def) {
            $v = parseDecimal($form[$f], $def['min'], $def['max']);
            if ($v === false) {
                $errors[] = $def['label'] . ' phải là số từ ' . $def['min'] . ' đến ' . $def['max'] . ($def['unit'] === '%' ? '%' : ' ' . $def['unit']) . '.';
            } elseif ($v === null && $def['required']) {
                $errors[] = 'Vui lòng nhập ' . mb_strtolower($def['label']) . '.';
            }
            $vals[$f] = $v;   // null = để trống, sẽ lưu NULL
        }

        // Mỗi ngày một bản ghi
        $targetId = $editId;
        if (empty($errors)) {
            if ($editId !== null) {
                $stmt = $pdo->prepare('SELECT id FROM body_metrics WHERE user_id = ? AND recorded_date = ? AND id <> ?');
                $stmt->execute([$userId, $date, $editId]);
                if ($stmt->fetch()) {
                    $errors[] = 'Bạn đã có số đo ngày ' . dmy($date) . '. Hãy sửa bản ghi đó hoặc chọn ngày khác.';
                }
            } else {
                $stmt = $pdo->prepare('SELECT id FROM body_metrics WHERE user_id = ? AND recorded_date = ?');
                $stmt->execute([$userId, $date]);
                $same = $stmt->fetch();
                $targetId = $same ? (int)$same['id'] : null;   // nhập lại cùng ngày → cập nhật bản ghi cũ
            }
        }

        if (empty($errors)) {
            $params = [$date, $vals['weight_kg'], $vals['body_fat_percent'], $vals['chest_cm'], $vals['waist_cm'], $vals['arm_cm']];
            if ($targetId === null) {
                $stmt = $pdo->prepare(
                    'INSERT INTO body_metrics (user_id, recorded_date, weight_kg, body_fat_percent, chest_cm, waist_cm, arm_cm)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute(array_merge([$userId], $params));
                $_SESSION['flash'] = ['ok', 'Đã lưu số đo ngày ' . dmy($date) . '.'];
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE body_metrics
                     SET recorded_date = ?, weight_kg = ?, body_fat_percent = ?, chest_cm = ?, waist_cm = ?, arm_cm = ?
                     WHERE id = ? AND user_id = ?'
                );
                $stmt->execute(array_merge($params, [$targetId, $userId]));
                $_SESSION['flash'] = ['ok', 'Đã cập nhật số đo ngày ' . dmy($date) . '.'];
            }
            redirectBack();
        }
        // Có lỗi: không chuyển hướng, vẽ lại form với đúng những gì người dùng đã gõ (biến $form)
    }
}

// ---------- Đọc dữ liệu để hiển thị ----------
$stmt = $pdo->prepare(
    'SELECT id, recorded_date, weight_kg, body_fat_percent, chest_cm, waist_cm, arm_cm
     FROM body_metrics WHERE user_id = ? ORDER BY recorded_date ASC, id ASC'
);
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();
$count = count($rows);

// Chênh lệch cân nặng so với lần đo liền trước (cho cột "Thay đổi" trong bảng)
$prevWeight = null;
foreach ($rows as $i => $r) {
    $rows[$i]['dw'] = $prevWeight === null ? null : (float)$r['weight_kg'] - $prevWeight;
    $prevWeight = (float)$r['weight_kg'];
}

// Mở trang sửa: ?edit=ID (chỉ nhận bản ghi của chính mình; id lạ thì bỏ qua, mở form thêm mới)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $reqId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    foreach ($rows as $r) {
        if ($reqId !== false && (int)$r['id'] === $reqId) {
            $editId = (int)$r['id'];
            $form['id'] = (string)$editId;
            $form['recorded_date'] = $r['recorded_date'];
            foreach ($fieldDefs as $f => $def) {
                $form[$f] = $r[$f] === null ? '' : rtrim(rtrim(number_format((float)$r[$f], 2, '.', ''), '0'), '.');
            }
            break;
        }
    }
}
// Thêm mới: gợi ý sẵn cân nặng lần trước (hoặc cân nặng trong hồ sơ) để chỉ cần chỉnh vài trăm gram
$weightHint = '';
if ($editId === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $base = $count > 0 ? (float)$rows[$count - 1]['weight_kg'] : (float)($user['weight_kg'] ?? 0);
    $weightHint = $base > 0 ? num($base) : '';
}
$hasExtra = false;   // mở sẵn mục "số đo thêm" nếu đã có giá trị
foreach (['body_fat_percent', 'chest_cm', 'waist_cm', 'arm_cm'] as $f) {
    if ($form[$f] !== '') {
        $hasExtra = true;
    }
}

// ----- Thống kê đầu trang -----
$latest = $count > 0 ? $rows[$count - 1] : null;
$first  = $count > 0 ? $rows[0] : null;
$stat = ['total' => null, 'last' => null, 'days' => 0, 'bmi' => null, 'bmiLabel' => '', 'fat' => null, 'waist' => null, 'spark' => ''];
if ($latest) {
    $stat['total'] = (float)$latest['weight_kg'] - (float)$first['weight_kg'];
    $stat['last']  = $latest['dw'];
    $stat['days']  = (int)((strtotime($latest['recorded_date']) - strtotime($first['recorded_date'])) / 86400);
    $stat['spark'] = sparkPoints(array_map(fn($r) => (float)$r['weight_kg'], array_slice($rows, -12)));
    // Số đo mới nhất có giá trị (không phải lần nào cũng nhập % mỡ/eo)
    foreach (array_reverse($rows) as $r) {
        if ($stat['fat'] === null && $r['body_fat_percent'] !== null) {
            $stat['fat'] = (float)$r['body_fat_percent'];
        }
        if ($stat['waist'] === null && $r['waist_cm'] !== null) {
            $stat['waist'] = (float)$r['waist_cm'];
        }
    }
    $h = (float)($user['height_cm'] ?? 0);
    if ($h >= 100 && $h <= 250) {
        $bmi = (float)$latest['weight_kg'] / (($h / 100) ** 2);
        $stat['bmi'] = $bmi;
        // Mốc phân loại của WHO (người trưởng thành); chỉ mang tính tham khảo
        $stat['bmiLabel'] = $bmi < 18.5 ? 'Thiếu cân' : ($bmi < 25 ? 'Bình thường' : ($bmi < 30 ? 'Thừa cân' : 'Béo phì'));
    }
}

// Mục tiêu dinh dưỡng gần nhất (từ trang Dinh dưỡng); bảng chưa có hoặc lỗi thì bỏ qua, không làm sập trang
$target = null;
try {
    $stmt = $pdo->prepare('SELECT target_kcal, protein_g FROM nutrition_targets WHERE user_id = ? ORDER BY calculated_date DESC, id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $target = $stmt->fetch() ?: null;
} catch (PDOException $e) {
    error_log('progress.php: không đọc được nutrition_targets: ' . $e->getMessage());
}

// Lịch sử hiển thị mới nhất trước; mặc định 30 dòng
$showAll = isset($_GET['all']);
$history = array_reverse($rows);
$historyShown = $showAll ? $history : array_slice($history, 0, 30);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Tiến trình';
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/progress.css">

<div class="pr">
    <div class="pg-head">
        <div>
            <h1>Tiến trình</h1>
            <p class="pg-sub">Ghi lại cân nặng và số đo theo ngày để thấy cơ thể thay đổi thế nào so với mục tiêu của bạn.</p>
        </div>
        <?php if ($count > 0): ?>
            <p class="pg-count"><b><?= (int)$count ?></b> lần đo<?= $stat['days'] > 0 ? ' trong ' . (int)$stat['days'] . ' ngày' : '' ?></p>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert--<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <?php if ($count > 0): ?>
        <!-- ===== Thống kê ===== -->
        <section class="pr-stats" aria-label="Tóm tắt">
            <article class="pr-stat pr-stat--main">
                <span class="pr-stat__label">Cân nặng hiện tại</span>
                <strong class="pr-stat__value"><?= htmlspecialchars(num($latest['weight_kg'])) ?><small> kg</small></strong>
                <span class="pr-stat__sub">Ngày <?= htmlspecialchars(dmy($latest['recorded_date'])) ?></span>
                <?php if ($stat['spark'] !== ''): ?>
                    <svg class="pr-spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">
                        <polyline points="<?= htmlspecialchars($stat['spark']) ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                    </svg>
                <?php endif; ?>
            </article>

            <article class="pr-stat">
                <span class="pr-stat__label">Thay đổi tổng</span>
                <?php if ($count > 1): ?>
                    <strong class="pr-stat__value pr-tone--<?= htmlspecialchars(deltaTone($stat['total'], $goal)) ?>"><?= htmlspecialchars(signed($stat['total'])) ?><small> kg</small></strong>
                    <span class="pr-stat__sub">so với lần đầu (<?= htmlspecialchars(dmy($first['recorded_date'])) ?>)</span>
                <?php else: ?>
                    <strong class="pr-stat__value pr-tone--flat">—</strong>
                    <span class="pr-stat__sub">Cần ít nhất 2 lần đo</span>
                <?php endif; ?>
            </article>

            <article class="pr-stat">
                <span class="pr-stat__label">So với lần đo trước</span>
                <?php if ($stat['last'] !== null): ?>
                    <strong class="pr-stat__value pr-tone--<?= htmlspecialchars(deltaTone($stat['last'], $goal)) ?>"><?= htmlspecialchars(signed($stat['last'])) ?><small> kg</small></strong>
                    <span class="pr-stat__sub">
                        <?php if ($stat['waist'] !== null): ?>Vòng eo gần nhất <?= htmlspecialchars(num($stat['waist'])) ?> cm<?php else: ?>Chưa có số đo vòng eo<?php endif; ?>
                    </span>
                <?php else: ?>
                    <strong class="pr-stat__value pr-tone--flat">—</strong>
                    <span class="pr-stat__sub">Cần ít nhất 2 lần đo</span>
                <?php endif; ?>
            </article>

            <article class="pr-stat">
                <span class="pr-stat__label">BMI ước tính</span>
                <?php if ($stat['bmi'] !== null): ?>
                    <strong class="pr-stat__value"><?= htmlspecialchars(num($stat['bmi'])) ?></strong>
                    <span class="pr-stat__sub"><?= htmlspecialchars($stat['bmiLabel']) ?><?= $stat['fat'] !== null ? ' · mỡ ' . htmlspecialchars(num($stat['fat'])) . '%' : '' ?></span>
                <?php else: ?>
                    <strong class="pr-stat__value pr-tone--flat">—</strong>
                    <span class="pr-stat__sub"><a href="/profile.php">Nhập chiều cao</a> trong hồ sơ</span>
                <?php endif; ?>
            </article>
        </section>

        <?php if ($target): ?>
            <p class="pr-target">Mục tiêu dinh dưỡng gần nhất của bạn: <b><?= htmlspecialchars(num($target['target_kcal'], 0)) ?> kcal</b>, protein <b><?= htmlspecialchars(num($target['protein_g'], 0)) ?> g</b> mỗi ngày. <a href="/nutrition.php">Xem chi tiết</a></p>
        <?php else: ?>
            <p class="pr-target">Bạn chưa tính mục tiêu dinh dưỡng. <a href="/nutrition.php">Tính TDEE/Macro</a> để có calo và protein mỗi ngày.</p>
        <?php endif; ?>

        <!-- ===== Biểu đồ (js/progress.js vẽ bằng Chart.js, dữ liệu từ /api/body-metrics.php) ===== -->
        <section class="pr-charts" id="pr-charts" aria-labelledby="pr-charts-title">
            <div class="pr-charts__head">
                <h2 id="pr-charts-title">Biểu đồ tiến trình</h2>
                <div class="pr-range" role="group" aria-label="Khoảng thời gian">
                    <button type="button" class="pr-range__btn" data-days="30" aria-pressed="false">30 ngày</button>
                    <button type="button" class="pr-range__btn" data-days="90" aria-pressed="true">90 ngày</button>
                    <button type="button" class="pr-range__btn" data-days="0" aria-pressed="false">Tất cả</button>
                </div>
            </div>
            <p class="pr-charts__status" id="pr-status" role="status" hidden></p>
            <div class="pr-charts__grid">
                <article class="pr-chart pr-chart--wide">
                    <h3>Cân nặng <small>kg</small></h3>
                    <div class="pr-chart__box"><canvas id="pr-c-weight" role="img" aria-label="Biểu đồ đường cân nặng theo ngày"></canvas></div>
                    <p class="pr-chart__empty" hidden>Chưa có dữ liệu trong khoảng này.</p>
                </article>
                <article class="pr-chart">
                    <h3>Mỡ cơ thể <small>%</small> và vòng eo <small>cm</small></h3>
                    <div class="pr-chart__box"><canvas id="pr-c-body" role="img" aria-label="Biểu đồ đường tỉ lệ mỡ và vòng eo theo ngày"></canvas></div>
                    <p class="pr-chart__empty" hidden>Chưa có số đo mỡ hoặc vòng eo trong khoảng này. Nhập thêm ở form bên dưới.</p>
                </article>
                <article class="pr-chart">
                    <h3>Khối lượng cơ bắp <small>kg, ước tính</small></h3>
                    <div class="pr-chart__box"><canvas id="pr-c-muscle" role="img" aria-label="Biểu đồ đường khối lượng nạc (cơ bắp ước tính) và khối lượng mỡ theo ngày"></canvas></div>
                    <p class="pr-chart__empty" hidden>Cần số đo có cả cân nặng và % mỡ cơ thể trong khoảng này. Nhập thêm ở form bên dưới.</p>
                    <p class="pr-chart__note">Ước tính: khối lượng nạc = cân nặng × (1 − % mỡ). Gồm cơ, xương và nước nên chỉ dùng để theo dõi xu hướng.</p>
                </article>
            </div>
        </section>
    <?php endif; ?>

    <div class="pr-layout">
        <!-- ===== Form nhập số đo ===== -->
        <section class="card pr-form" id="form" aria-labelledby="pr-form-title">
            <h2 class="card__head" id="pr-form-title"><?= $editId !== null ? 'Sửa số đo' : 'Thêm số đo' ?></h2>
            <p class="card__sub"><?= $editId !== null ? 'Chỉnh các ô cần đổi rồi bấm lưu.' : 'Chỉ cân nặng là bắt buộc. Nhập lại cùng một ngày sẽ cập nhật số đo ngày đó.' ?></p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert--error" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="/progress.php#form" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= htmlspecialchars($form['id']) ?>">

                <div class="field">
                    <label for="recorded_date">Ngày đo</label>
                    <input type="date" id="recorded_date" name="recorded_date" required
                           min="<?= htmlspecialchars($minDay) ?>" max="<?= htmlspecialchars($today) ?>"
                           value="<?= htmlspecialchars($form['recorded_date']) ?>">
                </div>

                <div class="field">
                    <label for="weight_kg">Cân nặng (kg)</label>
                    <input type="text" id="weight_kg" name="weight_kg" inputmode="decimal" autocomplete="off" required
                           placeholder="<?= $weightHint !== '' ? 'Lần trước: ' . htmlspecialchars($weightHint) : 'Ví dụ 68,5' ?>"
                           value="<?= htmlspecialchars($form['weight_kg']) ?>">
                    <small>Từ 20 đến 300 kg. Có thể gõ dấu phẩy hoặc dấu chấm.</small>
                </div>

                <details class="pr-more"<?= $hasExtra ? ' open' : '' ?>>
                    <summary>Số đo thêm <span>(không bắt buộc)</span></summary>
                    <div class="pr-more__grid">
                        <?php foreach (['body_fat_percent', 'chest_cm', 'waist_cm', 'arm_cm'] as $f): ?>
                            <div class="field">
                                <label for="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($fieldDefs[$f]['label']) ?> (<?= htmlspecialchars($fieldDefs[$f]['unit']) ?>)</label>
                                <input type="text" id="<?= htmlspecialchars($f) ?>" name="<?= htmlspecialchars($f) ?>" inputmode="decimal" autocomplete="off"
                                       placeholder="<?= (int)$fieldDefs[$f]['min'] ?>–<?= (int)$fieldDefs[$f]['max'] ?>"
                                       value="<?= htmlspecialchars($form[$f]) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>

                <div class="form-actions">
                    <button type="submit" class="btn"><?= $editId !== null ? 'Lưu thay đổi' : 'Lưu số đo' ?></button>
                    <?php if ($editId !== null): ?>
                        <a class="btn btn--ghost" href="/progress.php">Hủy</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- ===== Lịch sử ===== -->
        <section class="card pr-history" aria-labelledby="pr-hist-title">
            <h2 class="card__head" id="pr-hist-title">Lịch sử số đo</h2>

            <?php if ($count === 0): ?>
                <div class="pr-empty">
                    <div class="pr-empty__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 19V5M4 19h16M8 15l3-4 3 2 5-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <h3>Chưa có số đo nào</h3>
                    <p>Nhập cân nặng đầu tiên ở khung bên cạnh. Từ lần đo thứ hai, bạn sẽ thấy cân nặng đang thay đổi bao nhiêu.</p>
                </div>
            <?php else: ?>
                <p class="card__sub">Mới nhất ở trên cùng. Màu xanh là thay đổi hợp với mục tiêu của bạn<?= $goal === '' ? ' (chọn mục tiêu trong hồ sơ để bật màu)' : '' ?>.</p>
                <div class="pr-table-wrap">
                    <table class="pr-table">
                        <thead>
                            <tr>
                                <th scope="col">Ngày</th>
                                <th scope="col" class="num">Cân nặng</th>
                                <th scope="col" class="num">Thay đổi</th>
                                <th scope="col" class="num">% mỡ</th>
                                <th scope="col" class="num">Ngực</th>
                                <th scope="col" class="num">Eo</th>
                                <th scope="col" class="num">Tay</th>
                                <th scope="col"><span class="sr-only">Thao tác</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyShown as $r): ?>
                                <tr<?= $editId !== null && (int)$r['id'] === $editId ? ' class="is-editing"' : '' ?>>
                                    <td data-label="Ngày"><?= htmlspecialchars(dmy($r['recorded_date'])) ?></td>
                                    <td class="num" data-label="Cân nặng"><span><b><?= htmlspecialchars(num($r['weight_kg'])) ?></b> kg</span></td>
                                    <td class="num" data-label="Thay đổi">
                                        <?php if ($r['dw'] === null): ?>
                                            <span class="pr-chip pr-chip--flat">Lần đầu</span>
                                        <?php else: ?>
                                            <span class="pr-chip pr-chip--<?= htmlspecialchars(deltaTone($r['dw'], $goal)) ?>"><?= htmlspecialchars(signed($r['dw'])) ?> kg</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num" data-label="% mỡ"><?= $r['body_fat_percent'] === null ? '<span class="pr-none">—</span>' : htmlspecialchars(num($r['body_fat_percent'])) . '%' ?></td>
                                    <td class="num" data-label="Ngực"><?= $r['chest_cm'] === null ? '<span class="pr-none">—</span>' : htmlspecialchars(num($r['chest_cm'])) ?></td>
                                    <td class="num" data-label="Eo"><?= $r['waist_cm'] === null ? '<span class="pr-none">—</span>' : htmlspecialchars(num($r['waist_cm'])) ?></td>
                                    <td class="num" data-label="Tay"><?= $r['arm_cm'] === null ? '<span class="pr-none">—</span>' : htmlspecialchars(num($r['arm_cm'])) ?></td>
                                    <td class="pr-actions">
                                        <a class="pr-link" href="/progress.php?edit=<?= (int)$r['id'] ?>#form" aria-label="Sửa số đo ngày <?= htmlspecialchars(dmy($r['recorded_date'])) ?>">Sửa</a>
                                        <form method="post" action="/progress.php" data-confirm="Xóa số đo ngày <?= htmlspecialchars(dmy($r['recorded_date'])) ?>?">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="pr-link pr-link--danger" aria-label="Xóa số đo ngày <?= htmlspecialchars(dmy($r['recorded_date'])) ?>">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!$showAll && $count > 30): ?>
                    <p class="pr-more-link"><a href="/progress.php?all=1">Xem tất cả <?= (int)$count ?> lần đo</a></p>
                <?php elseif ($showAll && $count > 30): ?>
                    <p class="pr-more-link"><a href="/progress.php">Chỉ xem 30 lần gần nhất</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<script src="/js/vendor/chart.umd.min.js" defer></script>
<script src="/js/progress.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
