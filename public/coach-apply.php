<?php
/**
 * coach-apply.php — mẫu đơn đăng ký làm Huấn luyện viên (HLV).
 *
 * Luồng: người dùng (USER) điền đơn → đơn ở trạng thái "Đang chờ duyệt" → ADMIN xem ở admin/coach-applications.php
 * → duyệt (vai trò thành COACH, hồ sơ HLV tạo từ đơn) hoặc từ chối kèm lý do → người dùng sửa và nộp lại.
 *
 * Các trường hợp trang xử lý:
 *   - ADMIN: không nộp được. COACH: đã là HLV, dẫn sang /coach/.
 *   - Đang có HLV hoặc đang chờ HLV duyệt: phải kết thúc trước (không vừa là học viên vừa là HLV).
 *   - Có đơn đang chờ: hiện tóm tắt đơn + nút rút đơn.
 *   - Đơn gần nhất bị từ chối: hiện lý do, form điền sẵn nội dung cũ để sửa.
 * Mọi kiểm tra nằm ở PHP (validateCoachApplication, coachSubmitApplication); thuộc tính maxlength/required chỉ để dễ dùng.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
requireLogin();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/coach.php';

$userId = (int)$_SESSION['user_id'];
refreshSessionRole($pdo);           // vừa được duyệt thì session cập nhật vai trò ngay
$role   = $_SESSION['role'] ?? 'USER';
$ready  = coachTablesReady($pdo);

$errors = [];
$old    = null;   // dữ liệu vừa gửi (khi có lỗi) để điền lại form

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        showCsrfError();
    }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'withdraw') {
            coachWithdrawApplication($pdo, $userId);
            coachFlash('ok', 'Đã rút đơn. Bạn có thể nộp lại bất cứ lúc nào.');
            header('Location: /coach-apply.php');
            exit;
        }
        if ($action === 'submit') {
            $v = validateCoachApplication($_POST);
            $errors = $v['errors'];
            $old = $v['data'] + ['specialties_list' => explode(',', $v['data']['specialties'])];
            if (!$errors) {
                coachSubmitApplication($pdo, $userId, $v['data']);
                coachFlash('ok', 'Đã gửi đơn. Quản trị viên sẽ xem xét và trả lời trên trang này.');
                header('Location: /coach-apply.php');
                exit;
            }
        }
    } catch (CoachException $e) {
        $errors['_form'] = $e->getMessage();
    }
}

$flash   = coachTakeFlash();
$latest  = $ready ? coachLatestApplication($pdo, $userId) : null;
$openRel = $ready ? coachOpenRelationForClient($pdo, $userId) : null;
$pending = $latest !== null && $latest['status'] === 'PENDING';

// Trạng thái của trang: quyết định khối nào hiện
if (!$ready)                    $state = 'not_ready';
elseif ($role === 'COACH')      $state = 'coach';
elseif ($role !== 'USER')       $state = 'admin';
elseif ($pending)               $state = 'pending';
elseif ($openRel !== null)      $state = 'has_coach';
else                            $state = 'form';

// Giá trị điền sẵn: dữ liệu vừa gửi lỗi > đơn bị từ chối gần nhất > rỗng
if ($old === null) {
    $src = ($latest !== null && $latest['status'] === 'REJECTED') ? $latest : [];
    $old = [
        'headline' => $src['headline'] ?? '', 'bio' => $src['bio'] ?? '', 'approach' => $src['approach'] ?? '',
        'certifications' => $src['certifications'] ?? '', 'availability' => $src['availability'] ?? '',
        'experience_years' => $src['experience_years'] ?? '', 'max_clients' => $src['max_clients'] ?? 8,
        'specialties_list' => isset($src['specialties']) ? explode(',', $src['specialties']) : [],
    ];
}

/** In thông báo lỗi của một ô (đã escape), kèm id để ô nhập trỏ tới bằng aria-describedby. */
function fieldError(array $errors, string $key): string
{
    return isset($errors[$key])
        ? '<p class="ch-err" id="err-' . htmlspecialchars($key) . '" role="alert">' . htmlspecialchars($errors[$key]) . '</p>'
        : '';
}
function invalidAttr(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . htmlspecialchars($key) . '"' : '';
}

$pageTitle = 'Đăng ký làm huấn luyện viên';
$pageCss = ['/css/coach.css'];
include __DIR__ . '/includes/header.php';
?>
<div class="ch">
    <header class="pg-head">
        <div>
            <h1>Đăng ký làm huấn luyện viên</h1>
            <p class="pg-sub">Trở thành HLV trên GymSmart để giao lịch tập, đặt mục tiêu dinh dưỡng và theo dõi học viên. Mọi đơn đều được quản trị viên xem xét trước khi kích hoạt.</p>
        </div>
        <a class="btn btn--ghost" href="/coaches.php">Xem danh sách HLV</a>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert--<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <div class="ch-layout">
        <div>
        <?php if ($state === 'not_ready'): ?>
            <div class="ch-empty">
                <div class="ch-empty__ico" aria-hidden="true">!</div>
                <h2>Tính năng HLV chưa được bật</h2>
                <p>Cơ sở dữ liệu chưa có bảng của phân hệ HLV. Quản trị viên cần import file <code>sql/coach_features.sql</code>.</p>
                <a class="btn" href="/index.php">Về trang chủ</a>
            </div>

        <?php elseif ($state === 'coach'): ?>
            <div class="card ch-status">
                <div class="ch-status__ico" aria-hidden="true">✓</div>
                <div>
                    <h2>Bạn đã là huấn luyện viên</h2>
                    <p>Quản lý học viên, giao lịch và trả lời tin nhắn trong khu HLV.</p>
                    <a class="btn" href="/coach/dashboard.php">Vào khu HLV</a>
                </div>
            </div>

        <?php elseif ($state === 'admin'): ?>
            <div class="card ch-status">
                <div class="ch-status__ico" aria-hidden="true">i</div>
                <div>
                    <h2>Tài khoản quản trị không đăng ký làm HLV</h2>
                    <p>Hãy dùng một tài khoản người dùng thường để nộp đơn.</p>
                    <a class="btn btn--ghost" href="/admin/coach-applications.php">Xem các đơn đang chờ</a>
                </div>
            </div>

        <?php elseif ($state === 'has_coach'): ?>
            <div class="card ch-status">
                <div class="ch-status__ico" aria-hidden="true">i</div>
                <div>
                    <h2>Bạn đang là học viên của một HLV</h2>
                    <p>Bạn đang <?= $openRel['status'] === 'ACTIVE' ? 'tập với' : 'chờ duyệt từ' ?> HLV <b><?= htmlspecialchars($openRel['coach_name']) ?></b>.
                        Một người không thể vừa là học viên vừa là huấn luyện viên. Hãy kết thúc trước khi nộp đơn.</p>
                    <a class="btn btn--ghost" href="/my-coach.php">Xem HLV của tôi</a>
                </div>
            </div>

        <?php elseif ($state === 'pending'): ?>
            <div class="card">
                <div class="ch-status">
                    <div class="ch-status__ico" aria-hidden="true">⏳</div>
                    <div>
                        <span class="ch-badge ch-badge--warn">Đang chờ duyệt</span>
                        <h2 style="margin-top:8px">Đơn của bạn đã được gửi</h2>
                        <p>Gửi lúc <?= htmlspecialchars(date('H:i d/m/Y', strtotime($latest['created_at']))) ?>. Kết quả sẽ hiện ngay trên trang này.</p>
                    </div>
                </div>
                <dl class="ch-kv">
                    <dt>Chức danh</dt><dd><?= htmlspecialchars($latest['headline']) ?></dd>
                    <dt>Chuyên môn</dt><dd><?= htmlspecialchars(implode(', ', coachSpecialtyLabels($latest['specialties']))) ?></dd>
                    <dt>Kinh nghiệm</dt><dd><?= (int)$latest['experience_years'] ?> năm</dd>
                    <dt>Nhận tối đa</dt><dd><?= (int)$latest['max_clients'] ?> học viên</dd>
                    <?php if ($latest['certifications'] !== ''): ?><dt>Chứng chỉ</dt><dd><?= htmlspecialchars($latest['certifications']) ?></dd><?php endif; ?>
                </dl>
                <form method="post" action="/coach-apply.php" class="form-actions" data-confirm="Rút đơn đăng ký HLV? Bạn có thể nộp lại sau.">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="withdraw">
                    <button type="submit" class="btn btn--ghost" data-busy-text="Đang xử lý…">Rút đơn</button>
                </form>
            </div>

        <?php else: /* form */ ?>
            <?php if ($latest !== null && $latest['status'] === 'REJECTED' && $latest['admin_note'] !== ''): ?>
                <div class="alert alert--note" role="note">
                    <div><b>Đơn trước chưa được duyệt.</b> Lý do: <?= htmlspecialchars($latest['admin_note']) ?><br>Nội dung đơn cũ đã được điền sẵn bên dưới để bạn chỉnh sửa.</div>
                </div>
            <?php elseif ($latest !== null && $latest['status'] === 'APPROVED'): ?>
                <div class="alert alert--note" role="note"><div>Vai trò HLV trước đây của bạn đã bị thu hồi. Bạn có thể nộp đơn mới.</div></div>
            <?php endif; ?>

            <?php if (isset($errors['_form'])): ?>
                <div class="alert alert--error" role="alert"><?= htmlspecialchars($errors['_form']) ?></div>
            <?php elseif ($errors): ?>
                <div class="alert alert--error" role="alert">Vui lòng kiểm tra lại <?= count($errors) ?> mục được đánh dấu bên dưới.</div>
            <?php endif; ?>

            <form class="card" method="post" action="/coach-apply.php" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="submit">

                <fieldset class="form-sec">
                    <h2><i>1</i>Thông tin chuyên môn</h2>
                    <div class="field<?= isset($errors['headline']) ? ' is-invalid' : '' ?>">
                        <label for="headline">Chức danh hiển thị</label>
                        <input id="headline" name="headline" maxlength="120" required value="<?= htmlspecialchars((string)$old['headline']) ?>"
                               placeholder="Ví dụ: HLV thể hình, chuyên giảm mỡ cho người mới"<?= invalidAttr($errors, 'headline') ?>>
                        <small>10–120 ký tự, viết trên một dòng. Học viên thấy dòng này đầu tiên.</small>
                        <?= fieldError($errors, 'headline') ?>
                    </div>
                    <div class="field<?= isset($errors['specialties']) ? ' is-invalid' : '' ?>">
                        <fieldset class="ch-picks" aria-describedby="sp-hint<?= isset($errors['specialties']) ? ' err-specialties' : '' ?>">
                            <legend class="ch-legend">Chuyên môn (chọn 1–<?= COACH_SPECIALTIES_MAX ?>)</legend>
                            <?php foreach (COACH_SPECIALTIES as $code => $label): ?>
                                <label class="ch-pick">
                                    <input type="checkbox" name="specialties[]" value="<?= htmlspecialchars($code) ?>"<?= in_array($code, $old['specialties_list'], true) ? ' checked' : '' ?>>
                                    <span><?= htmlspecialchars($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <small id="sp-hint">Chọn đúng thế mạnh để học viên phù hợp tìm tới bạn.</small>
                        <?= fieldError($errors, 'specialties') ?>
                    </div>
                    <div class="form-grid">
                        <div class="field<?= isset($errors['experience_years']) ? ' is-invalid' : '' ?>">
                            <label for="experience_years">Số năm kinh nghiệm huấn luyện</label>
                            <input id="experience_years" name="experience_years" type="number" inputmode="numeric" min="0" max="50" required
                                   value="<?= htmlspecialchars((string)$old['experience_years']) ?>"<?= invalidAttr($errors, 'experience_years') ?>>
                            <?= fieldError($errors, 'experience_years') ?>
                        </div>
                        <div class="field<?= isset($errors['max_clients']) ? ' is-invalid' : '' ?>">
                            <label for="max_clients">Số học viên tối đa</label>
                            <input id="max_clients" name="max_clients" type="number" inputmode="numeric" min="1" max="30" required
                                   value="<?= htmlspecialchars((string)$old['max_clients']) ?>"<?= invalidAttr($errors, 'max_clients') ?>>
                            <small>1–30. Sửa lại được sau khi được duyệt.</small>
                            <?= fieldError($errors, 'max_clients') ?>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-sec">
                    <h2><i>2</i>Chứng chỉ</h2>
                    <div class="field<?= isset($errors['certifications']) ? ' is-invalid' : '' ?>">
                        <label for="certifications">Chứng chỉ, bằng cấp liên quan (không bắt buộc)</label>
                        <textarea id="certifications" name="certifications" maxlength="1000" rows="4"
                                  placeholder="Mỗi dòng một chứng chỉ: Tên chứng chỉ – Nơi cấp – Năm"<?= invalidAttr($errors, 'certifications') ?>><?= htmlspecialchars((string)$old['certifications']) ?></textarea>
                        <small>Chỉ ghi chứng chỉ bạn thật sự có. Quản trị viên có thể liên hệ để xác minh.</small>
                        <span class="ch-count" data-count="certifications" aria-live="polite"></span>
                        <?= fieldError($errors, 'certifications') ?>
                    </div>
                </fieldset>

                <fieldset class="form-sec">
                    <h2><i>3</i>Giới thiệu và phương pháp</h2>
                    <div class="field<?= isset($errors['bio']) ? ' is-invalid' : '' ?>">
                        <label for="bio">Giới thiệu bản thân</label>
                        <textarea id="bio" name="bio" maxlength="1500" rows="6" required
                                  placeholder="Bạn đã huấn luyện ai, trong bao lâu, thế mạnh là gì…"<?= invalidAttr($errors, 'bio') ?>><?= htmlspecialchars((string)$old['bio']) ?></textarea>
                        <small>Ít nhất 80 ký tự. Hiện trên thẻ HLV cho học viên đọc.</small>
                        <span class="ch-count" data-count="bio" aria-live="polite"></span>
                        <?= fieldError($errors, 'bio') ?>
                    </div>
                    <div class="field<?= isset($errors['approach']) ? ' is-invalid' : '' ?>">
                        <label for="approach">Phương pháp huấn luyện</label>
                        <textarea id="approach" name="approach" maxlength="1000" rows="4" required
                                  placeholder="Bạn xây lịch, theo dõi tiến độ và điều chỉnh cho học viên thế nào?"<?= invalidAttr($errors, 'approach') ?>><?= htmlspecialchars((string)$old['approach']) ?></textarea>
                        <small>Ít nhất 40 ký tự.</small>
                        <span class="ch-count" data-count="approach" aria-live="polite"></span>
                        <?= fieldError($errors, 'approach') ?>
                    </div>
                    <div class="field<?= isset($errors['availability']) ? ' is-invalid' : '' ?>">
                        <label for="availability">Thời gian có thể hỗ trợ (không bắt buộc)</label>
                        <input id="availability" name="availability" maxlength="255" value="<?= htmlspecialchars((string)$old['availability']) ?>"
                               placeholder="Ví dụ: Tối thứ 2–6, trả lời tin nhắn trong 24 giờ"<?= invalidAttr($errors, 'availability') ?>>
                        <?= fieldError($errors, 'availability') ?>
                    </div>
                </fieldset>

                <fieldset class="form-sec">
                    <h2><i>4</i>Cam kết</h2>
                    <ul class="ch-rules">
                        <li>Chỉ xem và dùng dữ liệu của học viên đang tập với mình, để huấn luyện.</li>
                        <li>Không hứa kết quả, không khuyên dùng thuốc hay chất cấm; vấn đề sức khỏe thì khuyên học viên gặp bác sĩ.</li>
                        <li>Giao lịch và mục tiêu dinh dưỡng trong giới hạn an toàn; trả lời yêu cầu của học viên kịp thời.</li>
                        <li>Quản trị viên có thể thu hồi vai trò HLV nếu vi phạm.</li>
                    </ul>
                    <label class="ch-check">
                        <input type="checkbox" name="agree_truth" value="1"<?= ($_POST['agree_truth'] ?? '') === '1' ? ' checked' : '' ?><?= invalidAttr($errors, 'agree_truth') ?>>
                        <span>Tôi xác nhận thông tin trong đơn là đúng sự thật.</span>
                    </label>
                    <?= fieldError($errors, 'agree_truth') ?>
                    <label class="ch-check">
                        <input type="checkbox" name="agree_rules" value="1"<?= ($_POST['agree_rules'] ?? '') === '1' ? ' checked' : '' ?><?= invalidAttr($errors, 'agree_rules') ?>>
                        <span>Tôi đồng ý với các quy tắc dành cho huấn luyện viên ở trên.</span>
                    </label>
                    <?= fieldError($errors, 'agree_rules') ?>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn" data-busy-text="Đang gửi đơn…">Gửi đơn đăng ký</button>
                </div>
            </form>
        <?php endif; ?>
        </div>

        <aside class="ch-side">
            <section class="card">
                <h2 class="card__head">Quy trình</h2>
                <p class="card__sub">Thường mất 1–3 ngày làm việc.</p>
                <ol class="ch-steps">
                    <li class="<?= in_array($state, ['pending', 'coach'], true) ? 'is-done' : 'is-now' ?>"><b>Nộp đơn</b><span>Điền thông tin chuyên môn và cam kết.</span></li>
                    <li class="<?= $state === 'coach' ? 'is-done' : ($state === 'pending' ? 'is-now' : '') /* audit:ok chỉ là tên class viết sẵn */ ?>"><b>Quản trị viên xem xét</b><span>Có thể từ chối kèm lý do để bạn bổ sung.</span></li>
                    <li class="<?= $state === 'coach' ? 'is-done' : '' ?>"><b>Kích hoạt khu HLV</b><span>Bạn xuất hiện trong danh sách HLV và bắt đầu nhận học viên.</span></li>
                </ol>
            </section>
            <section class="card">
                <h2 class="card__head">HLV làm được gì?</h2>
                <ul class="ch-rules" style="margin:0">
                    <li>Duyệt học viên muốn tập với mình.</li>
                    <li>Giao và chỉnh lịch tập từ thư viện bài tập.</li>
                    <li>Đặt mục tiêu calo, macro cho học viên.</li>
                    <li>Xem nhật ký, số đo; nhận xét và nhắn tin.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
<script src="/js/coach.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
