<?php
/**
 * food-log.php — Nhật ký ăn (C1, A-50): ghi món đã ăn theo bữa, so tổng trong ngày với mục tiêu dinh dưỡng.
 * GET ?date=Y-m-d (mặc định hôm nay, không nhận ngày tương lai). Mọi thao tác là form POST có CSRF, Post/Redirect/Get.
 * Số liệu tính ở máy chủ (includes/meal-log.php); dữ liệu luôn theo $_SESSION['user_id'].
 * Ghi món ăn không phải "tự sửa mục tiêu", nên vẫn dùng được khi đang có HLV quản lý dinh dưỡng.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/meal-log.php';

$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');
$date = (string)($_GET['date'] ?? $today);
$dt = DateTime::createFromFormat('Y-m-d', $date);
if ($dt === false || $dt->format('Y-m-d') !== $date || $date > $today) {
    $date = $today;
}
$ready = mealLogReady($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $postDate = (string)($_POST['date'] ?? $today);
    $flash = ['ok', ''];
    try {
        switch ($_POST['action'] ?? '') {
            case 'add':
                $kind = (string)($_POST['meal_kind'] ?? '');
                $food = (string)($_POST['food'] ?? '');
                $grams = str_replace(',', '.', (string)($_POST['grams'] ?? ''));
                // Nhớ bữa vừa chọn cho ngày này: tải lại trang vẫn chọn đúng bữa đó (xem mealDefaultKind)
                if (isset(MEAL_KINDS[$kind])) {
                    $_SESSION['food_last_kind'] = ['date' => $postDate, 'kind' => $kind];
                }
                try {
                    mealLogAdd($pdo, $userId, $postDate, $kind, $food, $grams);
                } catch (InvalidArgumentException $e) {
                    // Ghi lỗi: giữ lại thực phẩm và số gam vừa nhập để người dùng chỉ cần sửa chỗ sai
                    $_SESSION['food_draft'] = ['food' => mb_substr($food, 0, 30), 'grams' => mb_substr($grams, 0, 10)];
                    throw $e;
                }
                $flash = ['ok', 'Đã ghi món.'];
                break;
            case 'delete':
                $logId = (int)($_POST['id'] ?? 0);   // số nguyên; hàm tự kiểm tra món thuộc user này
                mealLogDelete($pdo, $userId, $logId);
                $flash = ['ok', 'Đã xóa món.'];
                break;
            case 'food_add':
                $n = fn(string $k) => str_replace(',', '.', (string)($_POST[$k] ?? ''));
                userFoodAdd($pdo, $userId, (string)($_POST['name'] ?? ''), $n('protein'), $n('carbs'), $n('fat'));
                $flash = ['ok', 'Đã thêm vào "Thực phẩm của tôi". Chọn nó trong ô thực phẩm để ghi.'];
                break;
            case 'food_delete':
                $foodId = (int)($_POST['id'] ?? 0);
                userFoodDelete($pdo, $userId, $foodId);
                $flash = ['ok', 'Đã xóa thực phẩm. Các món đã ghi trước đó vẫn được giữ.'];
                break;
            default:
                $flash = ['error', 'Thao tác không hợp lệ.'];
        }
    } catch (InvalidArgumentException $e) {
        $flash = ['error', $e->getMessage()];
    }
    $_SESSION['food_flash'] = $flash;
    $back = DateTime::createFromFormat('Y-m-d', $postDate) ? $postDate : $today;
    header('Location: /food-log.php' . ($back !== $today ? '?date=' . rawurlencode($back) : ''));
    exit;
}
$flash = $_SESSION['food_flash'] ?? null;
$draft = $_SESSION['food_draft'] ?? ['food' => '', 'grams' => ''];
unset($_SESSION['food_flash'], $_SESSION['food_draft']);
$defaultKind = mealDefaultKind($_SESSION['food_last_kind'] ?? null, $date, (int)date('G'));

$rows = $ready ? mealLogDay($pdo, $userId, $date) : [];
$target = mealTarget($pdo, $userId);
$sum = mealDaySummary($rows, $target);
$myFoods = $ready ? userFoods($pdo, $userId) : [];
$catalog = foodCatalog();
$num = fn(float $v, int $d = 0): string => number_format($v, $d, ',', '.');
$dayLabel = $date === $today ? 'Hôm nay' : ($date === date('Y-m-d', strtotime('-1 day')) ? 'Hôm qua' : date('d/m/Y', strtotime($date)));
$prevDay = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDay = date('Y-m-d', strtotime($date . ' +1 day'));

$pageTitle = 'Nhật ký ăn';
$pageCss = ['/css/food-log.css'];
include __DIR__ . '/includes/header.php';
?>
<div class="fl">
    <div class="pg-head">
        <div>
            <h1>Nhật ký ăn</h1>
            <p class="pg-sub">Ghi món đã ăn để thấy mình đã ăn đủ calo và đạm so với mục tiêu chưa.</p>
        </div>
        <a class="btn btn--ghost" href="/nutrition.php">Mục tiêu &amp; lịch ăn gợi ý</a>
    </div>

    <?php if ($flash && $flash[1] !== ''): ?>
        <div class="alert alert--<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <?php if (!$ready): ?>
        <div class="fl-empty"><p class="fl-empty__title">Chưa có bảng nhật ký ăn</p><p>Quản trị viên cần chạy <code>sql/meal_logs.sql</code> (hoặc <code>php tools/install.php --upgrade</code>).</p></div>
    <?php else: ?>

    <nav class="fl-days" aria-label="Chọn ngày">
        <a href="/food-log.php?date=<?= htmlspecialchars($prevDay) ?>" aria-label="Ngày trước">&larr;</a>
        <b><?= htmlspecialchars($dayLabel) ?></b>
        <?php if ($nextDay <= $today): ?><a href="/food-log.php<?= $nextDay !== $today ? '?date=' . htmlspecialchars($nextDay) : '' ?>" aria-label="Ngày sau">&rarr;</a><?php endif; ?>
        <form method="get" action="/food-log.php" class="fl-days__pick">
            <label for="fl-date" class="fl-sr">Chọn ngày</label>
            <input type="date" id="fl-date" name="date" value="<?= htmlspecialchars($date) ?>" max="<?= htmlspecialchars($today) ?>">
            <button type="submit" class="btn btn--ghost">Xem</button>
        </form>
    </nav>

    <section class="card fl-sum" aria-labelledby="fl-sum-title">
        <h2 id="fl-sum-title" class="card__head">Tổng <?= htmlspecialchars(mb_strtolower($dayLabel)) ?></h2>
        <?php if ($target === null): ?>
            <p class="fl-note">Bạn chưa có mục tiêu dinh dưỡng nên chỉ hiện tổng đã ăn. <a href="/nutrition.php">Tính mục tiêu</a>.</p>
        <?php endif; ?>
        <dl class="fl-bars">
            <?php foreach (['kcal' => ['Calo', 'kcal', 'target_kcal'], 'protein' => ['Đạm', 'g', 'protein_g'], 'carbs' => ['Tinh bột', 'g', 'carbs_g'], 'fat' => ['Chất béo', 'g', 'fat_g']] as $k => [$label, $unit, $tk]): ?>
                <div class="fl-bar">
                    <dt><?= htmlspecialchars($label) ?></dt>
                    <dd>
                        <b><?= htmlspecialchars($num($sum['total'][$k])) ?></b><?php if ($target): ?> / <?= htmlspecialchars($num((float)$target[$tk])) ?><?php endif; ?> <?= htmlspecialchars($unit) ?>
                        <?php if ($target): ?>
                            <span class="fl-meter" role="img" aria-label="<?= htmlspecialchars($label) ?> đạt <?= (int)$sum['percent'][$k] ?>% mục tiêu"><i class="<?= $sum['percent'][$k] > 110 ? 'is-over' : '' ?>" style="width: <?= (int)min(100, $sum['percent'][$k]) ?>%"></i></span>
                            <small><?= $sum['left'][$k] >= 0 ? 'còn ' . htmlspecialchars($num($sum['left'][$k])) . ' ' . htmlspecialchars($unit) : 'vượt ' . htmlspecialchars($num(-$sum['left'][$k])) . ' ' . htmlspecialchars($unit) ?></small>
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

    <div class="fl-grid">
        <section class="card" aria-labelledby="fl-add-title">
            <h2 id="fl-add-title" class="card__head">Ghi món</h2>
            <form method="post" action="/food-log.php" class="fl-form" data-fl-add>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                <label for="fl-kind">Bữa</label>
                <select id="fl-kind" name="meal_kind" required>
                    <?php foreach (MEAL_KINDS as $k => $label): ?><option value="<?= htmlspecialchars($k) ?>"<?= $k === $defaultKind ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
                </select>
                <label for="fl-food">Thực phẩm</label>
                <select id="fl-food" name="food" required>
                    <option value="">Chọn thực phẩm…</option>
                    <?php if ($myFoods): ?>
                        <optgroup label="Thực phẩm của tôi">
                            <?php foreach ($myFoods as $f): ?><option value="u:<?= (int)$f['id'] ?>"<?= $draft['food'] === 'u:' . (int)$f['id'] ? ' selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <optgroup label="Thực phẩm thường dùng (giá trị xấp xỉ)">
                        <?php foreach ($catalog as $key => $f): ?>
                            <option value="c:<?= htmlspecialchars($key) ?>"<?= $draft['food'] === 'c:' . $key ? ' selected' : '' ?><?= isset($f['unit']) ? ' data-unit="' . htmlspecialchars($f['unit'][0]) . '" data-unit-g="' . (int)$f['unit'][1] . '"' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <label for="fl-grams">Khối lượng (g)</label>
                <input type="number" id="fl-grams" name="grams" min="1" max="<?= MEAL_MAX_GRAMS ?>" step="1" inputmode="numeric" required placeholder="Ví dụ: 150" value="<?= htmlspecialchars($draft['grams']) ?>">
                <p class="fl-hint" data-fl-hint>Cân sau khi nấu chín (trừ yến mạch tính khô).</p>
                <button type="submit" class="btn">Ghi món</button>
            </form>
        </section>

        <section class="card" aria-labelledby="fl-list-title">
            <h2 id="fl-list-title" class="card__head">Đã ăn <?= htmlspecialchars(mb_strtolower($dayLabel)) ?></h2>
            <?php if (!$rows): ?>
                <div class="fl-empty fl-empty--inline">
                    <p class="fl-empty__title">Chưa ghi món nào</p>
                    <p>Chọn bữa, thực phẩm và khối lượng ở khung "Ghi món". Thực phẩm đóng gói thì thêm theo nhãn ở "Thực phẩm của tôi".</p>
                </div>
            <?php else: ?>
                <?php foreach (MEAL_KINDS as $kind => $label): ?>
                    <?php $items = array_filter($rows, fn($r) => $r['meal_kind'] === $kind); if (!$items) { continue; } ?>
                    <h3 class="fl-meal"><?= htmlspecialchars($label) ?> <small><?= htmlspecialchars($num($sum['byMeal'][$kind])) ?> kcal</small></h3>
                    <ul class="fl-items">
                        <?php foreach ($items as $r): ?>
                            <li>
                                <div><b><?= htmlspecialchars($r['food_name']) ?></b> <span><?= htmlspecialchars($num((float)$r['grams'])) ?> g</span>
                                    <small><?= htmlspecialchars($num((float)$r['kcal'])) ?> kcal · Đ <?= htmlspecialchars($num((float)$r['protein_g'], 1)) ?> g · TB <?= htmlspecialchars($num((float)$r['carbs_g'], 1)) ?> g · B <?= htmlspecialchars($num((float)$r['fat_g'], 1)) ?> g</small></div>
                                <form method="post" action="/food-log.php" data-confirm="Xóa món này khỏi nhật ký?">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                                    <button type="submit" class="fl-del" aria-label="Xóa <?= htmlspecialchars($r['food_name']) ?>">Xóa</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>

    <section class="card fl-foods" aria-labelledby="fl-foods-title">
        <h2 id="fl-foods-title" class="card__head">Thực phẩm của tôi</h2>
        <p class="fl-note">Thêm sản phẩm bạn hay ăn theo bảng thành phần trên nhãn (giá trị trên 100 g). Calo tính bằng 4 × đạm + 4 × tinh bột + 9 × béo.</p>
        <form method="post" action="/food-log.php" class="fl-form fl-form--row">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="food_add">
            <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
            <div><label for="uf-name">Tên</label><input id="uf-name" name="name" required minlength="2" maxlength="80" placeholder="Ví dụ: Sữa chua có đường X"></div>
            <div><label for="uf-p">Đạm (g/100 g)</label><input id="uf-p" name="protein" type="number" min="0" max="100" step="0.1" inputmode="decimal" required></div>
            <div><label for="uf-c">Tinh bột (g/100 g)</label><input id="uf-c" name="carbs" type="number" min="0" max="100" step="0.1" inputmode="decimal" required></div>
            <div><label for="uf-f">Béo (g/100 g)</label><input id="uf-f" name="fat" type="number" min="0" max="100" step="0.1" inputmode="decimal" required></div>
            <button type="submit" class="btn btn--ghost">Thêm</button>
        </form>
        <?php if ($myFoods): ?>
            <ul class="fl-items fl-items--foods">
                <?php foreach ($myFoods as $f): ?>
                    <li>
                        <div><b><?= htmlspecialchars($f['name']) ?></b>
                            <small>/100 g: Đ <?= htmlspecialchars($num((float)$f['protein_100g'], 1)) ?> · TB <?= htmlspecialchars($num((float)$f['carbs_100g'], 1)) ?> · B <?= htmlspecialchars($num((float)$f['fat_100g'], 1)) ?> g · <?= htmlspecialchars($num(mealPortion((float)$f['protein_100g'], (float)$f['carbs_100g'], (float)$f['fat_100g'], 100)['kcal'])) ?> kcal</small></div>
                        <form method="post" action="/food-log.php" data-confirm="Xóa thực phẩm này? Các món đã ghi vẫn giữ.">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="food_delete">
                            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                            <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                            <button type="submit" class="fl-del" aria-label="Xóa <?= htmlspecialchars($f['name']) ?>">Xóa</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <p class="fl-note">Số liệu chỉ để tham khảo, không thay thế tư vấn của chuyên gia dinh dưỡng. Giá trị thực phẩm thường dùng là xấp xỉ; món nấu ở nhà khác nhau tùy cách chế biến.</p>
</div>
<script src="/js/food-log.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
