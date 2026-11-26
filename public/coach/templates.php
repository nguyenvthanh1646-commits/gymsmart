<?php
/**
 * coach/templates.php — Mẫu lịch tập của HLV (E1, A-48): xem các mẫu, giao một mẫu cho nhiều học viên cùng lúc, xóa mẫu.
 * Tạo mẫu ở trang "Giao lịch" của một học viên (nút "Lưu làm mẫu"). Mọi thao tác là form POST có CSRF, Post/Redirect/Get.
 * Quyền: requireCoach() (đọc lại vai trò trong DB); mẫu lọc theo coach_id của người đăng nhập; giao cho học viên nào thì
 * coachTemplateApply() kiểm tra lại quan hệ ACTIVE cho từng người, không tin danh sách id gửi lên.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coach.php';
require_once __DIR__ . '/../includes/coach-templates.php';
require_once __DIR__ . '/../includes/audit-log.php';

requireCoach();
$coachId = (int)$_SESSION['user_id'];
$ready = coachTemplatesReady($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $tplId = (int)filter_var($_POST['template_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    try {
        if (($_POST['action'] ?? '') === 'delete') {
            coachTemplateDelete($pdo, $coachId, $tplId);
            coachFlash('ok', 'Đã xóa mẫu. Lịch đã giao trước đó vẫn ở lại với học viên.');
        } elseif (($_POST['action'] ?? '') === 'apply') {
            $ids = is_array($_POST['clients'] ?? null) ? $_POST['clients'] : [];
            $res = coachTemplateApply($pdo, $coachId, $tplId, $ids);
            foreach ($res['applied'] as $clientId => $name) {
                auditLog($pdo, 'coach.assign_plan', 'client', $clientId, 'Giao lịch từ mẫu cho "' . $name . '"');
            }
            $msg = $res['applied'] ? 'Đã giao lịch cho ' . count($res['applied']) . ' học viên: ' . implode(', ', $res['applied']) . '.' : 'Chưa giao được cho ai.';
            if ($res['skipped']) {
                $msg .= ' Bỏ qua ' . count($res['skipped']) . ' người không còn tập với bạn.';
            }
            if (!empty($res['locked'])) {
                $msg .= ' Bỏ qua ' . count($res['locked']) . ' người đã hết buổi tập (chờ học viên mua gói).';
            }
            coachFlash($res['applied'] ? 'ok' : 'error', $msg);
        } else {
            coachFlash('error', 'Thao tác không hợp lệ.');
        }
    } catch (PDOException $e) {
        throw $e;   // lỗi DB: không in câu SQL ra trang
    } catch (InvalidArgumentException | RuntimeException $e) {
        coachFlash('error', $e->getMessage());
    }
    header('Location: /coach/templates.php');
    exit;
}

$flash = coachTakeFlash();
$templates = $ready ? coachTemplates($pdo, $coachId) : [];
$clients = coachActiveClients($pdo, $coachId);
$dayShort = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];

$pageTitle = 'Mẫu lịch tập';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/admin-style.php';
$coachNavActive = 'templates';
include __DIR__ . '/../includes/coach-nav.php';
?>
<div class="adm-wrap">
    <div class="adm-head">
        <div>
            <h1>Mẫu lịch tập</h1>
            <p class="adm-sub">Lưu một lịch hay dùng làm mẫu, rồi giao cho nhiều học viên một lần. Lịch cũ của học viên được lưu trữ.</p>
        </div>
        <a class="adm-btn adm-btn-ghost" href="/coach/clients.php">Học viên</a>
    </div>

    <?php if ($flash): ?>
        <div class="adm-toast adm-toast-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <?php if (!$ready): ?>
        <div class="adm-panel adm-card-pad"><div class="adm-empty">Chưa có bảng mẫu lịch. Quản trị viên cần chạy <code>sql/coach_templates.sql</code> (hoặc <code>php tools/install.php --upgrade</code>).</div></div>
    <?php elseif (!$templates): ?>
        <div class="adm-panel adm-card-pad tpl-empty">
            <h2>Chưa có mẫu nào</h2>
            <p class="adm-sub">Mở <b>Học viên → Giao lịch</b> của một học viên, chỉnh lịch cho vừa ý rồi bấm <b>Lưu làm mẫu</b>. Mẫu sẽ hiện ở đây để giao cho người khác.</p>
            <a class="adm-btn" href="/coach/clients.php">Chọn học viên</a>
        </div>
    <?php else: ?>
        <div class="tpl-grid">
            <?php foreach ($templates as $t): ?>
                <?php $full = coachTemplate($pdo, $coachId, (int)$t['id']); $byDay = []; foreach ($full['items'] as $it) { $byDay[(int)$it['day_of_week']][] = $it['name']; } ?>
                <section class="adm-panel adm-card-pad tpl-card" aria-labelledby="tpl-<?= (int)$t['id'] ?>">
                    <h2 id="tpl-<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></h2>
                    <p class="adm-sub"><?= htmlspecialchars(WORKOUT_SPLIT_LABELS[$t['split_type']] ?? $t['split_type']) ?> · <?= (int)$t['days'] ?> buổi/tuần · <?= (int)$t['exercises'] ?> bài · sửa lúc <?= htmlspecialchars(date('d/m/Y', strtotime($t['updated_at']))) ?></p>
                    <ul class="tpl-days">
                        <?php foreach ($byDay as $d => $names): ?>
                            <li><b><?= htmlspecialchars($dayShort[$d] ?? (string)$d) ?></b> <?= htmlspecialchars(implode(', ', $names)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($clients): ?>
                        <form method="post" action="/coach/templates.php" class="tpl-apply" data-confirm="Giao mẫu này cho các học viên đã chọn? Lịch đang dùng của họ sẽ được lưu trữ.">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="apply">
                            <input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>">
                            <fieldset>
                                <legend>Giao cho học viên</legend>
                                <?php foreach ($clients as $c): ?>
                                    <label class="tpl-check"><input type="checkbox" name="clients[]" value="<?= (int)$c['client_id'] ?>"> <?= htmlspecialchars($c['full_name']) ?></label>
                                <?php endforeach; ?>
                            </fieldset>
                            <button type="submit" class="adm-btn">Giao cho học viên đã chọn</button>
                        </form>
                    <?php else: ?>
                        <p class="adm-sub">Bạn chưa có học viên đang tập để giao mẫu này.</p>
                    <?php endif; ?>
                    <form method="post" action="/coach/templates.php" data-confirm="Xóa mẫu &quot;<?= htmlspecialchars($t['name']) ?>&quot;? Lịch đã giao vẫn ở lại với học viên.">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>">
                        <button type="submit" class="adm-btn adm-btn-ghost adm-btn-sm tpl-del">Xóa mẫu</button>
                    </form>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script src="/js/coach.js" defer></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
