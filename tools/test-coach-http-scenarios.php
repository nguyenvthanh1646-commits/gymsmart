<?php
/**
 * tools/test-coach-http-scenarios.php — các kịch bản, được tools/test-coach-http.php nạp (không chạy file này trực tiếp).
 * Mỗi phần bắt đầu bằng reseed() để có dữ liệu thử sạch: hlv.admin, hlv.coach1 (có user1), hlv.coach2 (user2 đang chờ), hlv.user3 (chưa có HLV).
 */
if (!function_exists('check')) {
    exit("Chạy bằng: php tools/test-coach-http.php\n");
}

// =====================================================================
section('A. Đơn đăng ký HLV → admin duyệt / từ chối / thu hồi');
// =====================================================================
$form = [
    'action' => 'submit', 'headline' => 'HLV thử nghiệm kịch bản tự động',
    'specialties' => ['STRENGTH'], 'experience_years' => '2', 'max_clients' => '4',
    'certifications' => '', 'availability' => '',
    'bio' => str_repeat('Hồ sơ thử nghiệm do kịch bản tự động tạo. ', 3),
    'approach' => 'Phương pháp thử nghiệm, chỉ để kiểm tra luồng.',
    'agree_truth' => '1', 'agree_rules' => '1',
];
$guest = new Web('guest');
check('Khách mở /coach-apply.php bị chuyển về đăng nhập', $guest->get('/coach-apply.php')->location === '/login.php');
check('Khách mở /admin/coach-applications.php bị chuyển về đăng nhập', $guest->get('/admin/coach-applications.php')->location === '/login.php');

$u3 = (new Web('a-u3'))->login('hlv.user3@gymsmart.test');
$u3->get('/coach-apply.php');
check('USER mở mẫu đơn (có form)', $u3->code === 200 && $u3->has('Gửi đơn đăng ký'));
$u3->post('/coach-apply.php', ['csrf_token' => $u3->csrf(), 'bio' => 'ngắn'] + $form);
check('Đơn lỗi: ở lại trang, báo lỗi dưới ô, giữ dữ liệu', $u3->code === 200 && $u3->has('Giới thiệu bản thân cần ít nhất 80 ký tự') && $u3->has('HLV thử nghiệm kịch bản tự động'));
check('Thiếu mã CSRF bị chặn 403', $u3->post('/coach-apply.php', $form)->code === 403);
$u3->get('/coach-apply.php');
check('Chuyên môn giả mạo bị từ chối', $u3->postFollow('/coach-apply.php', ['csrf_token' => $u3->csrf(), 'specialties' => ['HACK']] + $form)->has('Chuyên môn không hợp lệ'));
check('Nộp đơn hợp lệ: thấy "Đang chờ duyệt"', $u3->postFollow('/coach-apply.php', ['csrf_token' => $u3->csrf()] + $form)->has('Đơn của bạn đã được gửi'));
$appId = (int)q('SELECT MAX(id) FROM coach_applications WHERE user_id = ?', [uid('hlv.user3@gymsmart.test')]);
check('USER mở trang duyệt đơn bị 403', $u3->get('/admin/coach-applications.php')->code === 403);
$u1 = (new Web('a-u1'))->login('hlv.user1@gymsmart.test');
check('Học viên đang có HLV không nộp đơn được', $u1->get('/coach-apply.php')->has('Bạn đang là học viên của một HLV'));

$ad = (new Web('a-ad'))->login('hlv.admin@gymsmart.test');
check('Admin thấy đơn đang chờ', $ad->get('/admin/coach-applications.php')->has('HLV thử nghiệm kịch bản tự động'));
$ad->get('/admin/coach-applications.php?id=' . $appId);
check('Từ chối không ghi lý do bị chặn', $ad->postFollow('/admin/coach-applications.php', ['csrf_token' => $ad->csrf(), 'id' => $appId, 'action' => 'reject', 'note' => ''])->has('Hãy ghi lý do từ chối'));
check('Từ chối có lý do', $ad->postFollow('/admin/coach-applications.php', ['csrf_token' => $ad->csrf(), 'id' => $appId, 'action' => 'reject', 'note' => 'Bổ sung chứng chỉ <b>thật</b>.'])->has('Đã từ chối đơn'));
$u3->get('/coach-apply.php');
check('Người nộp thấy lý do, HTML đã escape', $u3->has('Bổ sung chứng chỉ &lt;b&gt;thật&lt;/b&gt;.') && !$u3->has('<b>thật</b>'));
$u3->postFollow('/coach-apply.php', ['csrf_token' => $u3->csrf()] + $form);
$appId2 = (int)q('SELECT MAX(id) FROM coach_applications WHERE user_id = ?', [uid('hlv.user3@gymsmart.test')]);
check('Nộp lại tạo đơn mới', $appId2 > $appId);
$ad->get('/admin/coach-applications.php?id=' . $appId2);
check('Admin duyệt đơn', $ad->postFollow('/admin/coach-applications.php', ['csrf_token' => $ad->csrf(), 'id' => $appId2, 'action' => 'approve', 'note' => ''])->has('giờ là huấn luyện viên'));
check('Vai trò trong DB thành COACH', q('SELECT role FROM users WHERE email = ?', ['hlv.user3@gymsmart.test']) === 'COACH');
check('Phiên cũ của người vừa được duyệt thấy vai trò mới ngay', $u3->get('/coach-apply.php')->has('Bạn đã là huấn luyện viên'));
check('Ghi nhật ký duyệt + từ chối', (int)q("SELECT COUNT(*) FROM admin_audit_log WHERE action IN ('coach.approve','coach.reject')") === 2);

$ad->get('/admin/users.php');
$ad->postFollow('/admin/users.php', ['csrf_token' => $ad->csrf(), 'action' => 'set_role', 'id' => uid('hlv.coach1@gymsmart.test'), 'role' => 'ADMIN']);
check('Đổi thẳng COACH → ADMIN bị chặn', $ad->has('đang là huấn luyện viên'));
$ad->postFollow('/admin/users.php', ['csrf_token' => $ad->csrf(), 'action' => 'set_role', 'id' => uid('hlv.user1@gymsmart.test'), 'role' => 'ADMIN']);
check('Đưa học viên đang có HLV lên ADMIN bị chặn', $ad->has('đang có (hoặc đang chờ) huấn luyện viên'));
$ad->postFollow('/admin/users.php', ['csrf_token' => $ad->csrf(), 'action' => 'set_role', 'id' => uid('hlv.user2@gymsmart.test'), 'role' => 'COACH']);
check('Cấp COACH bằng form đổi vai trò bị chặn', $ad->has('Vai trò không hợp lệ'));
$c2 = uid('hlv.coach2@gymsmart.test');
$ad->get('/admin/coaches.php');
check('Thu hồi HLV 2: từ chối 1 yêu cầu đang chờ', $ad->postFollow('/admin/coaches.php', ['csrf_token' => $ad->csrf(), 'action' => 'revoke_coach', 'id' => $c2])->has('từ chối 1 yêu cầu'));
check('HLV 2 về USER', q('SELECT role FROM users WHERE id = ?', [$c2]) === 'USER');
$co = (new Web('a-c1'))->login('hlv.coach1@gymsmart.test');
check('COACH mở /admin/ bị 403', $co->get('/admin/dashboard.php')->code === 403);

// =====================================================================
section('B. Luồng chính: yêu cầu → duyệt → giao lịch, macro → tập → nhận xét → nhắn tin → ngừng');
// =====================================================================
reseed();
$c1 = uid('hlv.coach1@gymsmart.test'); $c2 = uid('hlv.coach2@gymsmart.test');
$u1 = uid('hlv.user1@gymsmart.test');  $u2 = uid('hlv.user2@gymsmart.test'); $u3 = uid('hlv.user3@gymsmart.test');
$user  = (new Web('b-u3'))->login('hlv.user3@gymsmart.test');
$coach = (new Web('b-c2'))->login('hlv.coach2@gymsmart.test');
$other = (new Web('b-c1'))->login('hlv.coach1@gymsmart.test');
$admin = (new Web('b-ad'))->login('hlv.admin@gymsmart.test');

check('COACH vào trang chủ thì sang /coach/dashboard.php', $coach->get('/index.php')->location === '/coach/dashboard.php');
check('USER mở /coach/ bị 403', $user->get('/coach/dashboard.php')->code === 403);
check('ADMIN mở /coach/ bị 403', $admin->get('/coach/dashboard.php')->code === 403);

$user->get('/coaches.php');
check('Danh sách HLV không lộ email', $user->has('[Thử] Huấn Luyện Hai') && !$user->has('@gymsmart.test'));
$tok = $user->csrf();
check('Gửi yêu cầu tới HLV 2', $user->postFollow('/coaches.php', ['csrf_token' => $tok, 'action' => 'request', 'coach_id' => $c2, 'note' => 'Em muốn tăng cơ <script>alert(1)</script>'])->has('Đã gửi yêu cầu'));
check('Gửi tiếp tới HLV 1 bị chặn (mỗi học viên 1 HLV)', $user->postFollow('/coaches.php', ['csrf_token' => $tok, 'action' => 'request', 'coach_id' => $c1, 'note' => ''])->has('Mỗi học viên chỉ đăng ký 1 HLV'));
$rel = (int)q("SELECT id FROM coach_clients WHERE client_id = ? AND status = 'PENDING'", [$u3]);
$user->postFollow('/coaches.php', ['csrf_token' => $tok, 'action' => 'cancel', 'relation_id' => (int)q('SELECT id FROM coach_clients WHERE client_id = ?', [$u2])]);
check('Hủy yêu cầu của người khác (đổi relation_id) bị chặn', $user->has('Không tìm thấy yêu cầu này'));

$coach->get('/coach/dashboard.php');
check('HLV thấy lời nhắn đã escape, không thấy email', $coach->has('&lt;script&gt;') && !$coach->has('<script>alert(1)') && !$coach->has('hlv.user3@'));
$other->get('/coach/dashboard.php');
check('HLV khác duyệt yêu cầu không phải của mình bị chặn', $other->postFollow('/coach/dashboard.php', ['csrf_token' => $other->csrf(), 'action' => 'accept', 'relation_id' => $rel])->has('Không tìm thấy yêu cầu này'));
check('Duyệt không có CSRF bị 403', $coach->post('/coach/dashboard.php', ['action' => 'accept', 'relation_id' => $rel])->code === 403);
$coach->get('/coach/dashboard.php');
check('HLV duyệt', $coach->postFollow('/coach/dashboard.php', ['csrf_token' => $coach->csrf(), 'action' => 'accept', 'relation_id' => $rel])->has('Đã nhận'));

$coach->get("/coach/client-plan.php?id=$u3");
$coach->postFollow("/coach/client-plan.php?id=$u3", ['csrf_token' => $coach->csrf(), 'action' => 'generate', 'days_per_week' => 4, 'level' => 'INTERMEDIATE', 'split_type' => '']);
check('Tạo từ gợi ý ra BẢN NHÁP (học viên chưa thấy)', $coach->has('Đã tạo bản nháp') && (int)q("SELECT COUNT(*) FROM workout_plans WHERE user_id = ? AND status = 'ACTIVE'", [$u3]) === 0);
check('Học viên chưa thấy lịch (chờ HLV giao)', $user->get('/workout-plan.php')->has('chưa giao lịch tập'));
$coach->postFollow("/coach/client-plan.php?id=$u3&view=draft", ['csrf_token' => $coach->csrf(), 'action' => 'draft_publish']);
check('Giao bản nháp: thành lịch ACTIVE do HLV 2 giao', (int)q("SELECT assigned_by FROM workout_plans WHERE user_id = ? AND status = 'ACTIVE'", [$u3]) === $c2);
$planId = (int)q("SELECT id FROM workout_plans WHERE user_id = ? AND status = 'ACTIVE'", [$u3]);
$row = db()->query("SELECT id, exercise_id, day_of_week FROM workout_plan_exercises WHERE workout_plan_id = $planId ORDER BY day_of_week, order_index LIMIT 1")->fetch();
$coach->postFollow("/coach/client-plan.php?id=$u3", ['csrf_token' => $coach->csrf(), 'action' => 'update', 'row_id' => $row['id'], 'sets' => 5, 'reps' => 6]);
check('HLV sửa trực tiếp set × rep lịch đang giao', (int)q('SELECT sets FROM workout_plan_exercises WHERE id = ?', [$row['id']]) === 5);
check('Set 99 bị từ chối', $coach->postFollow("/coach/client-plan.php?id=$u3", ['csrf_token' => $coach->csrf(), 'action' => 'update', 'row_id' => $row['id'], 'sets' => 99, 'reps' => 6])->has('Số set phải từ 1 đến'));
$foreignRow = (int)q("SELECT wpe.id FROM workout_plan_exercises wpe JOIN workout_plans wp ON wp.id = wpe.workout_plan_id WHERE wp.user_id = ? AND wp.status = 'ACTIVE' LIMIT 1", [$u1]);
$coach->postFollow("/coach/client-plan.php?id=$u3", ['csrf_token' => $coach->csrf(), 'action' => 'remove', 'row_id' => $foreignRow]);
check('Xóa dòng lịch của học viên HLV khác (đổi row_id) bị chặn', (int)q('SELECT COUNT(*) FROM workout_plan_exercises WHERE id = ?', [$foreignRow]) === 1);

$coach->get("/coach/client-nutrition.php?id=$u3");
check('Macro quá giới hạn bị từ chối', $coach->postFollow("/coach/client-nutrition.php?id=$u3", ['csrf_token' => $coach->csrf(), 'protein_g' => 300, 'carbs_g' => 100, 'fat_g' => 10])->has('Chất béo phải'));
check('Đặt macro: 4×120 + 4×200 + 9×60 = 1.820 kcal', $coach->postFollow("/coach/client-nutrition.php?id=$u3", ['csrf_token' => $coach->csrf(), 'protein_g' => 120, 'carbs_g' => 200, 'fat_g' => 60])->has('1.820 kcal'));

$user->get('/workout-plan.php');
check('Học viên thấy nhãn "Do HLV ... giao", không có nút sửa', $user->has('Do HLV [Thử] Huấn Luyện Hai giao') && !$user->has('id="workout-plan-toggle-edit"'));
$utok = $user->csrf();
check('Học viên gọi thẳng API sửa lịch: 403', $user->post('/api/workout-plan-edit.php', ['csrf_token' => $utok, 'action' => 'update', 'row_id' => $row['id'], 'sets' => 1, 'reps' => 1])->code === 403);
check('Học viên gọi thẳng API tạo lịch: 403', $user->post('/api/workout-plan-generate.php', ['csrf_token' => $utok, 'days_per_week' => 3, 'level' => 'BEGINNER'])->code === 403);
$user->post('/api/log-workout.php', ['csrf_token' => $utok, 'row_id' => $row['id'], 'exercise_id' => $row['exercise_id'], 'sets' => 5, 'reps' => 6, 'weight' => 40]);
check('Học viên vẫn ghi log tập được', $user->code === 200 && ($user->json()['success'] ?? false) === true);
check('Dinh dưỡng dùng mục tiêu HLV (1.820 kcal)', $user->get('/nutrition.php')->has('1.820') && $user->has('Mục tiêu do HLV [Thử] Huấn Luyện Hai giao'));
check('Học viên đổi mục tiêu ở Dinh dưỡng bị chặn', $user->postFollow('/nutrition.php', ['csrf_token' => $user->csrf(), 'action' => 'goal', 'goal' => 'MAINTAIN'])->has('đang do HLV quản lý') && q('SELECT goal FROM users WHERE id = ?', [$u3]) === 'LOSE_FAT');

// Ngày của buổi vừa ghi lấy từ DB: log ghi bằng CURDATE() của MySQL, có thể lệch ngày với date() của PHP nếu 2 bên khác múi giờ
$today = (string)q('SELECT MAX(log_date) FROM workout_logs WHERE user_id = ?', [$u3]);
$coach->get("/coach/client.php?id=$u3");
check('HLV nhận xét buổi tập hôm nay', $coach->postFollow("/coach/client.php?id=$u3", ['csrf_token' => $coach->csrf(), 'action' => 'feedback', 'log_date' => $today, 'content' => 'Tốt lắm <b>giữ lưng thẳng</b>'])->has('Đã gửi nhận xét'));
check('Nhận xét ngày không tập bị chặn', $coach->postFollow("/coach/client.php?id=$u3", ['csrf_token' => $coach->csrf(), 'action' => 'feedback', 'log_date' => '2020-01-01', 'content' => 'x x'])->has('không có buổi tập nào'));
check('Nhận xét 1001 ký tự bị chặn', $coach->postFollow("/coach/client.php?id=$u3", ['csrf_token' => $coach->csrf(), 'action' => 'feedback', 'log_date' => '', 'content' => str_repeat('a', 1001)])->has('tối đa 1000 ký tự'));
check('Trang chủ học viên báo nhận xét mới', $user->get('/index.php')->has('1 nhận xét mới'));
check('Học viên đọc nhận xét đã escape', $user->get('/my-coach.php?tab=feedback')->has('Tốt lắm &lt;b&gt;giữ lưng thẳng&lt;/b&gt;'));

$user->get('/my-coach.php?tab=messages');
$mt = $user->csrf();
check('Học viên gửi tin', ($user->post('/api/coach-message-send.php', ['csrf_token' => $mt, 'with' => $c2, 'content' => 'Chào thầy <img src=x onerror=alert(1)>'])->json()['success'] ?? false) === true);
check('Gửi liên tục dưới 2 giây: 429', $user->post('/api/coach-message-send.php', ['csrf_token' => $mt, 'with' => $c2, 'content' => 'Tin liền'])->code === 429);
check('Nhắn HLV không phải của mình: 404', $user->post('/api/coach-message-send.php', ['csrf_token' => $mt, 'with' => $c1, 'content' => 'x'])->code === 404);
check('Gửi tin không CSRF: 403', $user->post('/api/coach-message-send.php', ['with' => $c2, 'content' => 'x'])->code === 403);
$j = $coach->get("/api/coach-messages.php?with=$u3")->json();
check('HLV đọc tin qua API, nội dung giữ nguyên (JS gán textContent)', ($j['data']['messages'][0]['content'] ?? '') === 'Chào thầy <img src=x onerror=alert(1)>');
$coach->get("/coach/messages.php?id=$u3");
check('HLV trả lời', $coach->post('/api/coach-message-send.php', ['csrf_token' => $coach->csrf(), 'with' => $u3, 'content' => 'Chào em'])->code === 200);
check('Học viên nhận được 2 tin', count($user->get("/api/coach-messages.php?with=$c2")->json()['data']['messages'] ?? []) === 2);
check('HLV khác đọc trộm tin nhắn: 404', $other->get("/api/coach-messages.php?with=$u3")->code === 404);

$user->get('/workout-plan.php');
check('Học viên xin sửa lịch', $user->postFollow('/my-coach.php', ['csrf_token' => $user->csrf(), 'action' => 'change', 'area' => 'PLAN', 'reason' => 'Tuần này em đau vai'])->has('Đã gửi yêu cầu sửa lịch tập'));
$reqId = (int)q("SELECT MAX(id) FROM coach_change_requests WHERE status = 'PENDING' AND area = 'PLAN'");
$coach->get("/coach/client.php?id=$u3");
check('HLV duyệt yêu cầu sửa', $coach->postFollow("/coach/client.php?id=$u3", ['csrf_token' => $coach->csrf(), 'action' => 'resolve_change', 'request_id' => $reqId, 'decision' => 'approve', 'reply' => 'Ok'])->has('Đã cho phép học viên tự sửa'));
check('Trong hạn mở khóa: API sửa lịch 200', $user->get('/workout-plan.php')->post('/api/workout-plan-edit.php', ['csrf_token' => $user->csrf(), 'action' => 'update', 'row_id' => $row['id'], 'sets' => 3, 'reps' => 8])->code === 200);

check('HLV 1 mở hồ sơ học viên của HLV 2: 404', $other->get("/coach/client.php?id=$u3")->code === 404);
check('HLV 1 mở giao lịch học viên của HLV 2: 404', $other->get("/coach/client-plan.php?id=$u3")->code === 404);
check('HLV 1 mở đặt macro học viên của HLV 2: 404', $other->get("/coach/client-nutrition.php?id=$u3")->code === 404);
check('HLV 1 lấy số đo học viên của HLV 2: 404', $other->get("/api/body-metrics.php?client_id=$u3")->code === 404);
check('?id=abc: 404', $other->get('/coach/client.php?id=abc')->code === 404);
check('?id=1 OR 1=1: 404', $other->get('/coach/client.php?id=' . urlencode('1 OR 1=1'))->code === 404);
check('USER dùng ?client_id= của API số đo: 404', $user->get("/api/body-metrics.php?client_id=$u1")->code === 404);

$user->get('/my-coach.php');
check('Học viên ngừng tập', $user->postFollow('/my-coach.php', ['csrf_token' => $user->csrf(), 'action' => 'end', 'relation_id' => $rel])->has('Đã ngừng tập với HLV'));
check('HLV mở lại hồ sơ học viên: 404 ngay', $coach->get("/coach/client.php?id=$u3")->code === 404);
check('HLV đọc tin cũ qua API: 404', $coach->get("/api/coach-messages.php?with=$u3")->code === 404);
check('Học viên tự sửa lịch lại được', $user->get('/workout-plan.php')->has('id="workout-plan-toggle-edit"'));

$admin->get('/admin/coaches.php');
check('Admin thu hồi HLV 1 đang có học viên', $admin->postFollow('/admin/coaches.php', ['csrf_token' => $admin->csrf(), 'action' => 'revoke_coach', 'id' => $c1])->has('Kết thúc 1 học viên'));
check('Phiên cũ của HLV 1 mở /coach/: 403', $other->get('/coach/dashboard.php')->code === 403);
check('Lịch HLV đã giao vẫn còn với học viên 1', (int)q("SELECT COUNT(*) FROM workout_plans WHERE user_id = ? AND status = 'ACTIVE' AND assigned_by = ?", [$u1, $c1]) === 1);

// =====================================================================
section('C. Phần mở rộng: tỉ lệ tập, admin xem quan hệ, sửa nhận xét, đã xem, bản nháp, chatbot, lịch sử, chờ lâu, chứng chỉ');
// =====================================================================
reseed();
$c1 = uid('hlv.coach1@gymsmart.test'); $c2 = uid('hlv.coach2@gymsmart.test');
$u1 = uid('hlv.user1@gymsmart.test');  $u2 = uid('hlv.user2@gymsmart.test'); $u3 = uid('hlv.user3@gymsmart.test');
$coach = (new Web('c-c1'))->login('hlv.coach1@gymsmart.test');
$coach2 = (new Web('c-c2'))->login('hlv.coach2@gymsmart.test');
$user = (new Web('c-u1'))->login('hlv.user1@gymsmart.test');
$admin = (new Web('c-ad'))->login('hlv.admin@gymsmart.test');

// Mục 2: tỉ lệ tập đúng lịch
check('Danh sách học viên có cột "Tuần này" dạng đã tập/lịch', $coach->get('/coach/clients.php')->has('Tuần này') && preg_match('#<b class="adm-mono">\d+/\d+</b> buổi#', $coach->body) === 1);

// Mục 3: admin xem quan hệ HLV
check('Admin xem học viên 1: thấy đang tập với HLV 1', $admin->get("/admin/user-detail.php?id=$u1")->has('Đang tập với HLV') && $admin->has('[Thử] Huấn Luyện Một'));
check('Admin xem HLV 1: thấy là HLV của 1 học viên', $admin->get("/admin/user-detail.php?id=$c1")->has('Là HLV của 1 học viên'));

// Mục 4: sửa / xóa nhận xét
$fb = (int)q('SELECT MAX(f.id) FROM coach_feedback f JOIN coach_clients cc ON cc.id = f.coach_client_id WHERE cc.client_id = ?', [$u1]);
$coach->get("/coach/client.php?id=$u1");
check('HLV sửa nhận xét', $coach->postFollow("/coach/client.php?id=$u1", ['csrf_token' => $coach->csrf(), 'action' => 'feedback_edit', 'feedback_id' => $fb, 'content' => 'Nhận xét đã sửa'])->has('Đã sửa nhận xét'));
check('Sửa xong: có updated_at, học viên được báo lại (read_at = NULL)', q('SELECT content FROM coach_feedback WHERE id = ?', [$fb]) === 'Nhận xét đã sửa' && q('SELECT read_at FROM coach_feedback WHERE id = ?', [$fb]) === null && q('SELECT updated_at FROM coach_feedback WHERE id = ?', [$fb]) !== null);
check('Học viên thấy nhãn "HLV đã sửa"', $user->get('/my-coach.php?tab=feedback')->has('(HLV đã sửa)'));
$coach2->get('/coach/dashboard.php');
$coach2->post("/coach/client.php?id=$u1", ['csrf_token' => $coach2->csrf(), 'action' => 'feedback_delete', 'feedback_id' => $fb]);
check('HLV khác xóa nhận xét (qua trang học viên không phải của mình): 404', $coach2->code === 404 && (int)q('SELECT COUNT(*) FROM coach_feedback WHERE id = ?', [$fb]) === 1);
check('HLV xóa nhận xét của mình', $coach->postFollow("/coach/client.php?id=$u1", ['csrf_token' => $coach->csrf(), 'action' => 'feedback_delete', 'feedback_id' => $fb])->has('Đã xóa nhận xét') && (int)q('SELECT COUNT(*) FROM coach_feedback WHERE id = ?', [$fb]) === 0);

// Mục 5: "Đã xem"
$coach->get("/coach/messages.php?id=$u1");
$sent = $coach->post('/api/coach-message-send.php', ['csrf_token' => $coach->csrf(), 'with' => $u1, 'content' => 'Tin để thử đã xem'])->json()['item']['id'] ?? 0;
check('Chưa đọc thì seenUpTo nhỏ hơn tin vừa gửi', ($coach->get("/api/coach-messages.php?with=$u1")->json()['data']['seenUpTo'] ?? -1) < $sent);
$user->get("/api/coach-messages.php?with=$c1");
check('Học viên mở khung chat xong: seenUpTo = tin vừa gửi', ($coach->get("/api/coach-messages.php?with=$u1")->json()['data']['seenUpTo'] ?? 0) === $sent);

// Mục 8: lịch sử dinh dưỡng có client_id
check('HLV lấy lịch sử dinh dưỡng của học viên mình: 200', $coach->get("/api/nutrition-history.php?client_id=$u1")->code === 200 && count($coach->json()['data'] ?? []) >= 1);
check('HLV khác lấy lịch sử dinh dưỡng: 404', $coach2->get("/api/nutrition-history.php?client_id=$u1")->code === 404);

// Mục 10: bản nháp
$activeBefore = (int)q("SELECT id FROM workout_plans WHERE user_id = ? AND status = 'ACTIVE'", [$u1]);
$coach->get("/coach/client-plan.php?id=$u1&view=draft");
check('Chép lịch hiện tại thành bản nháp', $coach->postFollow("/coach/client-plan.php?id=$u1&view=draft", ['csrf_token' => $coach->csrf(), 'action' => 'draft_copy'])->has('Đã chép lịch hiện tại'));
$draftId = (int)q("SELECT id FROM workout_plans WHERE user_id = ? AND status = 'DRAFT'", [$u1]);
$draftRow = (int)q('SELECT MIN(id) FROM workout_plan_exercises WHERE workout_plan_id = ?', [$draftId]);
$coach->postFollow("/coach/client-plan.php?id=$u1&view=draft", ['csrf_token' => $coach->csrf(), 'action' => 'draft_update', 'row_id' => $draftRow, 'sets' => 6, 'reps' => 5]);
check('Sửa bản nháp: chỉ bản nháp đổi', (int)q('SELECT sets FROM workout_plan_exercises WHERE id = ?', [$draftRow]) === 6);
$activeRow = (int)q('SELECT MIN(id) FROM workout_plan_exercises WHERE workout_plan_id = ?', [$activeBefore]);
check('Dùng thao tác bản nháp lên dòng của lịch đang dùng bị chặn', $coach->postFollow("/coach/client-plan.php?id=$u1&view=draft", ['csrf_token' => $coach->csrf(), 'action' => 'draft_remove', 'row_id' => $activeRow])->has('Không tìm thấy bài này trong bản nháp'));
check('Học viên không thấy bản nháp', !$user->get('/workout-plan.php')->has('Bản nháp') && (int)q("SELECT id FROM workout_plans WHERE user_id = ? AND status = 'ACTIVE'", [$u1]) === $activeBefore);
check('Giao bản nháp', $coach->postFollow("/coach/client-plan.php?id=$u1&view=draft", ['csrf_token' => $coach->csrf(), 'action' => 'draft_publish'])->has('Đã giao bản nháp'));
check('Sau khi giao: bản nháp thành ACTIVE, lịch cũ ARCHIVED, tên bỏ chữ "Bản nháp"',
    (int)q("SELECT id FROM workout_plans WHERE user_id = ? AND status = 'ACTIVE'", [$u1]) === $draftId
    && q('SELECT status FROM workout_plans WHERE id = ?', [$activeBefore]) === 'ARCHIVED'
    && strpos((string)q('SELECT name FROM workout_plans WHERE id = ?', [$draftId]), 'Bản nháp') === false);

// Mục 9: chatbot chế độ HLV (chỉ thử phần chặn quyền, trước khi gọi AI)
$chat = function (Web $w, array $body): Web {
    $w->get('/profile.php');
    return $w->req('POST', '/api/chatbot.php', json_encode($body), ['Content-Type: application/json', 'X-CSRF-Token: ' . $w->csrf()]);
};
check('USER dùng chế độ coach của chatbot: 403', $chat($user, ['message' => 'Xin chào', 'mode' => 'coach'])->code === 403);
check('HLV hỏi về học viên không phải của mình: 403', $chat($coach, ['message' => 'Xin chào', 'mode' => 'coach', 'client_id' => $u3])->code === 403);
check('client_id sai kiểu: 400', $chat($coach2, ['message' => 'Xin chào', 'mode' => 'coach', 'client_id' => 'abc'])->code === 400);
check('Widget trong /coach/ là "Trợ lý HLV"', $coach->get("/coach/client.php?id=$u1")->has('Trợ lý HLV') && $coach->has('"clientId":' . $u1));

// Mục 6: lịch sử sau khi ngừng tập
$relU1 = (int)q("SELECT id FROM coach_clients WHERE client_id = ? AND status = 'ACTIVE'", [$u1]);
$user->get('/my-coach.php');
$user->postFollow('/my-coach.php', ['csrf_token' => $user->csrf(), 'action' => 'end', 'relation_id' => $relU1]);
check('Ngừng tập xong thấy mục "HLV trước đây"', $user->has('HLV trước đây'));
check('Mở lịch sử: thấy tin nhắn cũ, không có ô gửi', $user->get("/my-coach.php?history=$relU1")->code === 200 && $user->has('Tin để thử đã xem') && !$user->has('id="ch-form"'));
check('Lịch sử id không tồn tại: 404', $user->get('/my-coach.php?history=999999')->code === 404);
$u2web = (new Web('c-u2'))->login('hlv.user2@gymsmart.test');
check('Người khác mở lịch sử của học viên 1: 404', $u2web->get("/my-coach.php?history=$relU1")->code === 404);

// Mục 7: yêu cầu chờ quá lâu
db()->prepare("UPDATE coach_clients SET created_at = ? WHERE client_id = ? AND status = 'PENDING'")->execute([date('Y-m-d H:i:s', strtotime('-10 days')), $u2]);
check('Học viên chờ 10 ngày: được nhắc chọn HLV khác', $u2web->get('/coaches.php')->has('HLV chưa trả lời sau 10 ngày'));
check('Trang chủ học viên cũng nhắc', $u2web->get('/index.php')->has('Hủy và chọn HLV khác'));
check('HLV thấy nhãn "Đã chờ 10 ngày"', $coach2->get('/coach/dashboard.php')->has('Đã chờ 10 ngày'));

// Mục 11: file chứng chỉ trong mẫu đơn HLV
$u3web = (new Web('c-u3'))->login('hlv.user3@gymsmart.test');
$tmp = sys_get_temp_dir();
$png = $tmp . '/gs-cert.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$fake = $tmp . '/gs-cert-fake.png';
file_put_contents($fake, "<?php echo 'hack'; ?>");
$big = $tmp . '/gs-cert-big.png';
file_put_contents($big, file_get_contents($png) . str_repeat("\0", 3 * 1024 * 1024 + 10));
$apply = function (string $file) use ($u3web, $form) {
    $u3web->get('/coach-apply.php');
    $data = ['csrf_token' => $u3web->csrf(), 'action' => 'submit', 'headline' => $form['headline'], 'specialties[0]' => 'STRENGTH',
             'experience_years' => '2', 'max_clients' => '4', 'certifications' => '', 'availability' => '', 'bio' => $form['bio'],
             'approach' => $form['approach'], 'agree_truth' => '1', 'agree_rules' => '1',
             'certificate_file' => new CURLFile($file, 'image/png', 'chung-chi.png')];
    return $u3web->upload('/coach-apply.php', $data)->follow();
};
check('Mẫu đơn có ô tải chứng chỉ', $u3web->get('/coach-apply.php')->has('name="certificate_file"'));
check('File PHP đổi đuôi .png bị từ chối (kiểm tra nội dung, không tin đuôi)', $apply($fake)->has('chỉ nhận ảnh JPG, PNG, WebP hoặc file PDF'));
check('File trên 3 MB bị từ chối', $apply($big)->has('tối đa 3 MB'));
check('Nộp đơn kèm ảnh PNG hợp lệ', $apply($png)->has('Đã gửi 1 file chứng chỉ'));
$certApp = (int)q('SELECT MAX(id) FROM coach_applications WHERE user_id = ?', [$u3]);
$certName = (string)q('SELECT certificate_file FROM coach_applications WHERE id = ?', [$certApp]);
check('Tên file do máy chủ đặt, nằm ngoài public/', preg_match('/^c\d+-[a-f0-9]{16}\.png$/', $certName) === 1 && is_file($root . '/storage/certificates/' . $certName));
check('Mở file qua URL thường không được (không nằm trong public/)', $guest->get('/storage/certificates/' . $certName)->code === 404 && $guest->get('/../storage/certificates/' . $certName)->code === 404);
check('Người nộp mở trang xem chứng chỉ của admin: 403', $u3web->get('/admin/certificate.php?id=' . $certApp)->code === 403);
check('Admin xem được chứng chỉ (image/png, nosniff)', $admin->get('/admin/certificate.php?id=' . $certApp)->code === 200 && strpos($admin->type, 'image/png') === 0);
check('Trang duyệt đơn có nút xem chứng chỉ', $admin->get('/admin/coach-applications.php?id=' . $certApp)->has('Xem file chứng chỉ đính kèm'));
db()->prepare('UPDATE coach_applications SET certificate_file = ? WHERE id = ?')->execute(['../../config.php', $certApp]);
check('Tên file lạ trong DB (../../config.php) không đọc được: 404', $admin->get('/admin/certificate.php?id=' . $certApp)->code === 404);
// Dọn file thử
foreach (glob($root . '/storage/certificates/c' . $u3 . '-*') as $f) {
    @unlink($f);
}
@unlink($png); @unlink($fake); @unlink($big);

// =====================================================================
section('D. Giá tập, buổi thử, lịch hẹn, gói (A-54) + mã VietQR (A-56): hết buổi bị khóa → mua gói → HLV xác nhận tiền → mở khóa');
// =====================================================================
// Dữ liệu thử: HLV 1 bán 300.000đ/buổi, 2.400.000đ/tháng, 2 buổi thử; học viên 1 đã tập 1 buổi thử, còn 1 buổi thử đã xác nhận ngày mai.
reseed();
$u1 = uid('hlv.user1@gymsmart.test');
$c1 = uid('hlv.coach1@gymsmart.test');
$rel1 = (int)q("SELECT id FROM coach_clients WHERE client_id = ? AND status = 'ACTIVE'", [$u1]);
$coach1 = (new Web('d-c1'))->login('hlv.coach1@gymsmart.test');
$coach2 = (new Web('d-c2'))->login('hlv.coach2@gymsmart.test');
$u1web = (new Web('d-u1'))->login('hlv.user1@gymsmart.test');
$u3web = (new Web('d-u3'))->login('hlv.user3@gymsmart.test');

$u3web->get('/coaches.php');
check('Danh sách HLV hiện số buổi thử và giá', $u3web->has('Tập thử miễn phí 2 buổi đầu') && $u3web->has('300.000đ/buổi · 2.400.000đ/tháng'));
check('Hướng dẫn thanh toán KHÔNG hiện ở danh sách công khai', !$u3web->has('ngân hàng ABC'));

$coach1->get('/coach/profile.php');
check('Hồ sơ HLV có form giá', $coach1->has('Giá và buổi tập thử') && $coach1->has('name="trial_sessions"'));
$priceForm = ['action' => 'pricing', 'price_per_session' => '20.000', 'price_per_month' => '2.400.000', 'trial_sessions' => '3', 'payment_note' => 'Chuyển khoản thử 0000'];
$coach1->post('/coach/profile.php', ['csrf_token' => $coach1->csrf()] + $priceForm);
check('Giá dưới mức tối thiểu: báo lỗi, không lưu', $coach1->code === 200 && $coach1->has('Giá 1 buổi phải từ') && (int)q('SELECT price_per_session FROM coach_profiles WHERE user_id = ?', [$c1]) === 300000);
$coach1->get('/coach/profile.php');
$coach1->postFollow('/coach/profile.php', ['csrf_token' => $coach1->csrf(), 'price_per_session' => '350.000'] + $priceForm);
check('Lưu giá 350.000đ/buổi, 3 buổi thử', (int)q('SELECT price_per_session FROM coach_profiles WHERE user_id = ?', [$c1]) === 350000
    && (int)q('SELECT trial_sessions FROM coach_profiles WHERE user_id = ?', [$c1]) === 3 && $coach1->has('Đã lưu giá'));
check('Học viên cũ giữ số buổi thử đã chụp (2), không thành 3', (int)q('SELECT trial_sessions FROM coach_clients WHERE id = ?', [$rel1]) === 2);
check('Nhật ký hoạt động ghi coach.pricing', (int)q("SELECT COUNT(*) FROM admin_audit_log WHERE action = 'coach.pricing' AND admin_id = ?", [$c1]) === 1);

// VietQR (A-56): tài khoản nhận chuyển khoản nằm chung form giá
$coach1->get('/coach/profile.php');
check('Form giá có phần tài khoản VietQR (danh sách ngân hàng viết sẵn)', $coach1->has('name="bank_bin"') && $coach1->has('value="970436"') && $coach1->has('name="bank_holder"'));
$bankBad = ['bank_bin' => '123456', 'bank_account' => '12ab56', 'bank_holder' => 'Nguyễn Văn HLV', 'price_per_session' => '400.000'];
$coach1->post('/coach/profile.php', ['csrf_token' => $coach1->csrf()] + $bankBad + $priceForm);
check('TK sai (BIN lạ, số TK có chữ, tên có dấu): báo đủ 3 lỗi, không lưu cả giá', $coach1->code === 200
    && $coach1->has('Ngân hàng không có trong danh sách') && $coach1->has('Số tài khoản chỉ gồm chữ số') && $coach1->has('in hoa không dấu')
    && q('SELECT bank_bin FROM coach_profiles WHERE user_id = ?', [$c1]) === null && (int)q('SELECT price_per_session FROM coach_profiles WHERE user_id = ?', [$c1]) === 350000);
check('Gửi lại form lỗi: tên chủ TK điền lại đúng chữ đã gõ (đã escape)', $coach1->has('value="NGUYễN VăN HLV"'));
$coach1->post('/coach/profile.php', ['bank_bin' => '970436', 'bank_account' => '0123456789', 'bank_holder' => 'NGUYEN VAN HLV', 'price_per_session' => '350.000'] + $priceForm);
check('Lưu TK không có CSRF: 403, không lưu', $coach1->code === 403 && q('SELECT bank_bin FROM coach_profiles WHERE user_id = ?', [$c1]) === null);
$coach1->get('/coach/profile.php');
$coach1->postFollow('/coach/profile.php', ['csrf_token' => $coach1->csrf(), 'bank_bin' => '970436', 'bank_account' => '0123 456 789', 'bank_holder' => '  nguyen  van hlv ', 'price_per_session' => '350.000'] + $priceForm);
check('Lưu TK hợp lệ: số TK bỏ khoảng trắng, tên in hoa', q('SELECT bank_bin FROM coach_profiles WHERE user_id = ?', [$c1]) === '970436'
    && q('SELECT bank_account FROM coach_profiles WHERE user_id = ?', [$c1]) === '0123456789' && q('SELECT bank_holder FROM coach_profiles WHERE user_id = ?', [$c1]) === 'NGUYEN VAN HLV');
check('Nhật ký chỉ ghi 4 số cuối của TK', (int)q("SELECT COUNT(*) FROM admin_audit_log WHERE action = 'coach.pricing' AND detail LIKE '%****6789%'") === 1
    && (int)q("SELECT COUNT(*) FROM admin_audit_log WHERE detail LIKE '%0123456789%'") === 0);

$u1web->get('/my-coach.php?tab=sessions');
check('Học viên mở tab Buổi tập & gói: thấy quyền tập và buổi sắp tới', $u1web->code === 200 && $u1web->has('Quyền tập của bạn') && $u1web->has('còn 0/2') && $u1web->has('Đã xác nhận'));
check('Hết suất thử (1 buổi đang giữ chỗ): không hiện form đặt buổi', $u1web->has('Bạn chưa còn buổi nào để đặt'));
$later = date('Y-m-d', strtotime('+2 days'));
$u1web->postFollow('/my-coach.php?tab=sessions', ['csrf_token' => $u1web->csrf(), 'action' => 'book', 'date' => $later, 'time' => '08:00', 'note' => '']);
check('Cố gửi form đặt buổi khi hết suất: máy chủ chặn', $u1web->has('mua gói') && (int)q("SELECT COUNT(*) FROM coach_sessions WHERE coach_client_id = ? AND status = 'REQUESTED'", [$rel1]) === 0);
check('Gửi form không có CSRF: 403', $u1web->post('/my-coach.php?tab=sessions', ['action' => 'book', 'date' => $later, 'time' => '08:00'])->code === 403);

$sid = (int)q("SELECT id FROM coach_sessions WHERE coach_client_id = ? AND status = 'CONFIRMED'", [$rel1]);
$u3web->get('/my-coach.php');
$u3web->post('/my-coach.php', ['csrf_token' => $u3web->csrf(), 'action' => 'session_cancel', 'session_id' => $sid]);
check('Người khác gửi id buổi của học viên 1 để hủy: không được', q('SELECT status FROM coach_sessions WHERE id = ?', [$sid]) === 'CONFIRMED');
$coach2->get('/coach/sessions.php');
$coach2->post('/coach/sessions.php', ['csrf_token' => $coach2->csrf(), 'action' => 'cancel', 'session_id' => $sid]);
check('HLV khác hủy buổi của HLV 1: không được', q('SELECT status FROM coach_sessions WHERE id = ?', [$sid]) === 'CONFIRMED');
check('Học viên mở /coach/sessions.php: 403', $u1web->get('/coach/sessions.php')->code === 403);

// Buổi thử cuối đã qua giờ → HLV ghi vắng → hết buổi
db()->prepare('UPDATE coach_sessions SET starts_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', time() - 3 * 3600), $sid]);
$coach1->get('/coach/sessions.php');
check('HLV thấy buổi cần đánh dấu', $coach1->has('Cần đánh dấu') && $coach1->has('Vắng mặt'));
$coach1->postFollow('/coach/sessions.php', ['csrf_token' => $coach1->csrf(), 'action' => 'no_show', 'session_id' => $sid]);
check('HLV ghi vắng: báo học viên vừa hết buổi', q('SELECT status FROM coach_sessions WHERE id = ?', [$sid]) === 'NO_SHOW' && $coach1->has('vừa dùng hết buổi'));
check('Nhật ký ghi coach.session_absent', (int)q("SELECT COUNT(*) FROM admin_audit_log WHERE action = 'coach.session_absent'") === 1);
check('Bảng quyền tập: học viên 1 "Hết buổi"', $coach1->has('Hết buổi'));

$coach1->get('/coach/client.php?id=' . $u1);
check('Hồ sơ học viên: dải cảnh báo hết buổi', $coach1->has('Học viên đã hết buổi tập'));
$fbBefore = (int)q('SELECT COUNT(*) FROM coach_feedback WHERE coach_client_id = ?', [$rel1]);
$coach1->postFollow('/coach/client.php?id=' . $u1, ['csrf_token' => $coach1->csrf(), 'action' => 'feedback', 'log_date' => '', 'content' => 'Nhận xét khi đã khóa']);
check('Khóa: gửi nhận xét bị chặn ở máy chủ', (int)q('SELECT COUNT(*) FROM coach_feedback WHERE coach_client_id = ?', [$rel1]) === $fbBefore && $coach1->has('hết buổi'));
$nutBefore = (int)q('SELECT COUNT(*) FROM nutrition_targets WHERE user_id = ?', [$u1]);
$coach1->get('/coach/client-nutrition.php?id=' . $u1);
$coach1->postFollow('/coach/client-nutrition.php?id=' . $u1, ['csrf_token' => $coach1->csrf(), 'protein_g' => '140', 'carbs_g' => '200', 'fat_g' => '60']);
check('Khóa: đặt dinh dưỡng bị chặn', (int)q('SELECT COUNT(*) FROM nutrition_targets WHERE user_id = ?', [$u1]) === $nutBefore && $coach1->has('hết buổi'));
$coach1->get('/coach/client-plan.php?id=' . $u1);
$coach1->postFollow('/coach/client-plan.php?id=' . $u1, ['csrf_token' => $coach1->csrf(), 'action' => 'adopt']);
check('Khóa: tiếp quản / sửa lịch đang dùng bị chặn', $coach1->has('hết buổi'));
check('Học viên thấy thông báo đã dùng hết buổi', (int)q("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'coach.billing'", [$u1]) === 1
    && $u1web->get('/my-coach.php')->has('Bạn đã dùng hết buổi tập'));

// Mua gói
$u1web->get('/my-coach.php?tab=sessions');
check('Tab gói hiện giá mới của HLV (gói 4 buổi = 1.400.000đ)', $u1web->has('1.400.000đ'));
$u1web->postFollow('/my-coach.php?tab=sessions', ['csrf_token' => $u1web->csrf(), 'action' => 'order_create', 'pack' => 'SESSIONS:5']);
check('Gói 5 buổi (không có trong danh sách) bị chặn', $u1web->has('Số lượng không hợp lệ') && (int)q('SELECT COUNT(*) FROM coach_orders') === 0);
$u1web->postFollow('/my-coach.php?tab=sessions', ['csrf_token' => $u1web->csrf(), 'action' => 'order_create', 'pack' => 'SESSIONS:4']);
$oid = (int)q("SELECT id FROM coach_orders WHERE coach_client_id = ? AND status = 'PENDING'", [$rel1]);
check('Học viên chọn gói 4 buổi: đơn chờ 1.400.000đ, thấy hướng dẫn thanh toán', $oid > 0 && (int)q('SELECT amount FROM coach_orders WHERE id = ?', [$oid]) === 1400000
    && $u1web->has('Chuyển khoản thử 0000'));
$qrSrc = 'src="https://img.vietqr.io/image/970436-0123456789-compact2.png?amount=1400000&amp;addInfo=GS' . $oid . '&amp;accountName=NGUYEN%20VAN%20HLV"';
check('Đơn chờ: hiện ảnh VietQR đúng số tiền của đơn, nội dung GS<mã đơn>, URL đã escape', $u1web->has($qrSrc) && $u1web->has('referrerpolicy="no-referrer"'));
check('Đơn chờ: có dòng chữ chuyển tay (số TK, chủ TK, nội dung)', $u1web->has('0123456789') && $u1web->has('NGUYEN VAN HLV') && $u1web->has('<b>GS' . $oid . '</b>'));
$ch = curl_init(T_BASE . '/login.php');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_NOBODY => true]);
$hdr = (string)curl_exec($ch);
curl_close($ch);
check('CSP img-src cho phép img.vietqr.io', preg_match('/^Content-Security-Policy:.*img-src[^;]*https:\/\/img\.vietqr\.io/mi', $hdr) === 1);
check('HLV thấy nội dung CK GS<mã đơn> ở đơn chờ', $coach1->get('/coach/sessions.php')->has('nội dung CK <b>GS' . $oid . '</b>'));
check('Người chưa có HLV không thấy TK của HLV 1', !$u3web->get('/my-coach.php?tab=sessions')->has('0123456789') && !$u3web->has('img.vietqr.io'));
$u2web = (new Web('d-u2'))->login('hlv.user2@gymsmart.test');
check('Học viên đang chờ HLV khác không thấy TK của HLV 1', !$u2web->get('/my-coach.php?tab=sessions')->has('0123456789'));
check('HLV khác không thấy TK của HLV 1', !$coach2->get('/coach/sessions.php')->has('0123456789') && !$coach2->has('NGUYEN VAN HLV') && !$coach2->get('/coach/profile.php')->has('NGUYEN VAN HLV'));
db()->prepare('UPDATE coach_profiles SET bank_account = ? WHERE user_id = ?')->execute(['123&amount=1', $c1]);
$u1web->get('/my-coach.php?tab=sessions');
check('Số TK trong DB bị sửa bậy: không hiện QR, quay về hướng dẫn chữ', !$u1web->has('img.vietqr.io') && $u1web->has('Chuyển khoản thử 0000'));
db()->prepare('UPDATE coach_profiles SET bank_account = ? WHERE user_id = ?')->execute(['0123456789', $c1]);
$u1web->get('/my-coach.php?tab=sessions');
$u1web->postFollow('/my-coach.php?tab=sessions', ['csrf_token' => $u1web->csrf(), 'action' => 'order_create', 'pack' => 'MONTH:1']);
check('Đơn thứ hai khi đang chờ: bị chặn', $u1web->has('đơn chờ') && (int)q('SELECT COUNT(*) FROM coach_orders') === 1);
$coach2->get('/coach/sessions.php');
$coach2->post('/coach/sessions.php', ['csrf_token' => $coach2->csrf(), 'action' => 'order_paid', 'order_id' => $oid]);
check('HLV khác xác nhận tiền đơn của HLV 1: không được', q('SELECT status FROM coach_orders WHERE id = ?', [$oid]) === 'PENDING');
check('Bảng điều khiển HLV 1 nhắc đơn chờ xác nhận tiền', $coach1->get('/coach/dashboard.php')->has('1 đơn chờ xác nhận tiền'));
$coach1->get('/coach/sessions.php');
$coach1->postFollow('/coach/sessions.php', ['csrf_token' => $coach1->csrf(), 'action' => 'order_paid', 'order_id' => $oid]);
check('HLV 1 bấm "Đã nhận tiền": đơn PAID, ghi nhật ký', q('SELECT status FROM coach_orders WHERE id = ?', [$oid]) === 'PAID'
    && (int)q("SELECT COUNT(*) FROM admin_audit_log WHERE action = 'coach.order_paid'") === 1);

// Mở khóa: đặt buổi bằng gói, HLV xác nhận, nhận xét lại được
$u1web->get('/my-coach.php?tab=sessions');
check('Học viên thấy gói 4 buổi và form đặt buổi', $u1web->has('còn 4/4 buổi') && $u1web->has('name="time"'));
check('Đơn đã thanh toán: không còn mã VietQR', !$u1web->has('img.vietqr.io'));
$u1web->postFollow('/my-coach.php?tab=sessions', ['csrf_token' => $u1web->csrf(), 'action' => 'book', 'date' => date('Y-m-d', strtotime('-1 day')), 'time' => '18:00', 'note' => '']);
check('Đặt buổi ngày đã qua: bị chặn', $u1web->has('ít nhất 2 giờ'));
$u1web->postFollow('/my-coach.php?tab=sessions', ['csrf_token' => $u1web->csrf(), 'action' => 'book', 'date' => $later, 'time' => '08:00', 'note' => '<script>alert(1)</script>']);
$newSid = (int)q("SELECT id FROM coach_sessions WHERE coach_client_id = ? AND status = 'REQUESTED'", [$rel1]);
check('Đặt buổi sau khi mua gói: tính vào gói buổi', $newSid > 0 && q('SELECT billing FROM coach_sessions WHERE id = ?', [$newSid]) === 'PACK');
$coach1->get('/coach/sessions.php');
check('Lời nhắn của học viên được escape trên trang HLV', $coach1->has('&lt;script&gt;alert(1)&lt;/script&gt;') && !$coach1->has('<script>alert(1)</script>'));
$coach1->postFollow('/coach/sessions.php', ['csrf_token' => $coach1->csrf(), 'action' => 'confirm', 'session_id' => $newSid]);
check('HLV xác nhận buổi mới', q('SELECT status FROM coach_sessions WHERE id = ?', [$newSid]) === 'CONFIRMED');
$coach1->get('/coach/client.php?id=' . $u1);
$coach1->postFollow('/coach/client.php?id=' . $u1, ['csrf_token' => $coach1->csrf(), 'action' => 'feedback', 'log_date' => '', 'content' => 'Nhận xét sau khi mua gói']);
check('Hết khóa: HLV nhận xét lại được', (int)q('SELECT COUNT(*) FROM coach_feedback WHERE coach_client_id = ?', [$rel1]) === $fbBefore + 1 && !$coach1->has('Học viên đã hết buổi tập'));

// Ngừng tập: buổi sắp tới bị hủy
$u1web->get('/my-coach.php');
$u1web->postFollow('/my-coach.php', ['csrf_token' => $u1web->csrf(), 'action' => 'end', 'relation_id' => $rel1]);
check('Học viên ngừng tập: buổi đã xác nhận chuyển Đã hủy', q('SELECT status FROM coach_sessions WHERE id = ?', [$newSid]) === 'CANCELLED');

// =====================================================================
section('E. Tư vấn sức khỏe (A-55): đơn chuyên gia → duyệt → hỏi riêng → nhận → trả lời → đóng → thu hồi');
// =====================================================================
reseed();
$u1 = uid('hlv.user1@gymsmart.test'); $u2 = uid('hlv.user2@gymsmart.test'); $u3 = uid('hlv.user3@gymsmart.test');
$guest = new Web('e-guest');
$admin = (new Web('e-admin'))->login('hlv.admin@gymsmart.test');
$coach1 = (new Web('e-c1'))->login('hlv.coach1@gymsmart.test');
$u1web = (new Web('e-u1'))->login('hlv.user1@gymsmart.test');
$u2web = (new Web('e-u2'))->login('hlv.user2@gymsmart.test');
$u3web = (new Web('e-u3'))->login('hlv.user3@gymsmart.test');
check('Khách mở /consult.php bị chuyển về đăng nhập', $guest->get('/consult.php')->location === '/login.php');
check('Người dùng mở Tư vấn sức khỏe: có khung nhắc gọi 115', $u2web->get('/consult.php')->code === 200 && $u2web->has('gọi ngay 115'));

$png = sys_get_temp_dir() . '/gs-exp-cert.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$expForm = ['action' => 'submit', 'profession' => 'PHYSIO', 'title' => 'Kỹ thuật viên vật lý trị liệu thử', 'workplace' => 'Nơi công tác thử',
    'license_no' => 'TEST-12345', 'experience_years' => '5', 'topics[0]' => 'INJURY',
    'bio' => str_repeat('Hồ sơ chuyên gia do kịch bản tự động tạo để kiểm tra. ', 2), 'agree_truth' => '1', 'agree_rules' => '1'];
$u3web->get('/expert-apply.php');
check('Mẫu đơn chuyên gia có ô số chứng chỉ và file', $u3web->has('name="license_no"') && $u3web->has('name="certificate_file"'));
$u3web->upload('/expert-apply.php', ['csrf_token' => $u3web->csrf()] + $expForm);
check('Đơn không có file chứng chỉ: báo lỗi, chưa có đơn', $u3web->has('chứng chỉ hành nghề') && (int)q('SELECT COUNT(*) FROM expert_applications WHERE user_id = ?', [$u3]) === 0);
$u3web->get('/expert-apply.php');
$u3web->upload('/expert-apply.php', ['csrf_token' => $u3web->csrf(), 'certificate_file' => new CURLFile($png, 'image/png', 'cchn.png')] + $expForm)->follow();
$expApp = (int)q('SELECT id FROM expert_applications WHERE user_id = ? AND status = ?', [$u3, 'PENDING']);
check('Nộp đơn kèm file: đơn đang chờ', $expApp > 0 && $u3web->has('Đang chờ duyệt'));
check('Người nộp không mở được file chứng chỉ của trang admin (403)', $u3web->get('/admin/expert-certificate.php?id=' . $expApp)->code === 403);
$admin->get('/admin/expert-applications.php?id=' . $expApp);
check('Admin thấy số chứng chỉ trong đơn', $admin->has('TEST-12345'));
check('Admin mở được file chứng chỉ (image/png)', $admin->get('/admin/expert-certificate.php?id=' . $expApp)->code === 200 && strpos($admin->type, 'image/png') === 0);
$admin->get('/admin/expert-applications.php?id=' . $expApp);
$admin->postFollow('/admin/expert-applications.php', ['csrf_token' => $admin->csrf(), 'id' => $expApp, 'action' => 'approve', 'note' => '']);
check('Admin duyệt: vai trò EXPERT, ghi nhật ký', q('SELECT role FROM users WHERE id = ?', [$u3]) === 'EXPERT' && (int)q("SELECT COUNT(*) FROM admin_audit_log WHERE action = 'expert.approve'") === 1);
check('Bấm thông báo duyệt vào khu chuyên gia được (vai trò đọc lại từ DB)', $u3web->get('/expert/dashboard.php')->code === 200);
check('Sau đó mở trang chủ được chuyển tới khu chuyên gia', $u3web->get('/index.php')->location === '/expert/dashboard.php');
check('Danh sách công khai: có nhãn đã kiểm tra, KHÔNG lộ số chứng chỉ', $u2web->get('/consult.php')->has('đã kiểm tra chứng chỉ hành nghề') && !$u2web->has('TEST-12345'));
check('HLV mở khu chuyên gia: 403', $coach1->get('/expert/dashboard.php')->code === 403);

// Hỏi riêng
$askForm = ['topic' => 'INJURY', 'title' => 'Đau ngực khi chạy bộ dốc', 'body' => 'Mỗi lần chạy dốc khoảng 10 phút thì bị đau ngực nhẹ, nghỉ là hết. <script>alert(1)</script>'];
$u2web->get('/consult-ask.php');
$u2web->post('/consult-ask.php', ['csrf_token' => $u2web->csrf()] + $askForm);
check('Câu hỏi có dấu hiệu cấp cứu mà chưa xác nhận: nhắc gọi 115, chưa lưu', $u2web->code === 200 && $u2web->has('Có thể cần cấp cứu')
    && (int)q('SELECT COUNT(*) FROM health_questions WHERE user_id = ?', [$u2]) === 0);
check('Gửi câu hỏi không có CSRF: 403', $u2web->post('/consult-ask.php', $askForm + ['emergency_ack' => '1'])->code === 403);
$u2web->get('/consult-ask.php');
$u2web->postFollow('/consult-ask.php', ['csrf_token' => $u2web->csrf(), 'emergency_ack' => '1'] + $askForm);
$hq = (int)q('SELECT id FROM health_questions WHERE user_id = ?', [$u2]);
check('Đã xác nhận không khẩn cấp: câu hỏi được lưu, không tick chia sẻ thì không có số liệu', $hq > 0 && q('SELECT profile_snapshot FROM health_questions WHERE id = ?', [$hq]) === null
    && q('SELECT red_flags FROM health_questions WHERE id = ?', [$hq]) === 'Đau hoặc tức ngực');
check('Nội dung câu hỏi được escape', $u2web->has('&lt;script&gt;alert(1)&lt;/script&gt;') && !$u2web->has('<script>alert(1)</script>'));
check('Người dùng khác mở câu hỏi của người ta: 404', $u1web->get('/consult-question.php?id=' . $hq)->code === 404);
check('Admin cũng không đọc được câu hỏi (404 ở trang người hỏi, 403 ở khu chuyên gia)', $admin->get('/consult-question.php?id=' . $hq)->code === 404
    && $admin->get('/expert/question.php?id=' . $hq)->code === 403);
check('Chuyên gia được báo câu hỏi mới đúng chủ đề', (int)q("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'expert.question_new'", [$u3]) === 1);

$u3web->get('/expert/dashboard.php');
check('Chuyên gia thấy câu hỏi trong hàng chờ kèm cảnh báo dấu hiệu, chỉ thấy tên người hỏi', $u3web->has('Đau ngực khi chạy bộ dốc') && $u3web->has('Có dấu hiệu cần lưu ý')
    && !$u3web->has('[Thử] Học Viên Bình') && !$u3web->has('hlv.user2@'));
$u3web->postFollow('/expert/dashboard.php', ['csrf_token' => $u3web->csrf(), 'action' => 'claim', 'question_id' => $hq]);
check('Chuyên gia nhận câu hỏi', (int)q('SELECT expert_id FROM health_questions WHERE id = ?', [$hq]) === $u3 && $u3web->has('Gửi câu trả lời'));
$u3web->postFollow('/expert/question.php?id=' . $hq, ['csrf_token' => $u3web->csrf(), 'action' => 'message', 'body' => 'Đau ngực khi gắng sức cần đi khám tim mạch sớm. Tạm dừng chạy dốc cho tới khi khám.']);
check('Chuyên gia trả lời, người hỏi được báo', (int)q('SELECT COUNT(*) FROM health_messages WHERE question_id = ? AND sender_id = ?', [$hq, $u3]) === 1
    && (int)q("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'expert.reply'", [$u2]) === 1);
$u2web->get('/consult-question.php?id=' . $hq);
check('Người hỏi thấy câu trả lời', $u2web->has('đi khám tim mạch sớm') && $u2web->has('Đóng câu hỏi'));
$u2web->postFollow('/consult-question.php?id=' . $hq, ['csrf_token' => $u2web->csrf(), 'action' => 'close', 'helpful' => '1']);
check('Người hỏi đóng kèm "hữu ích"', q('SELECT status FROM health_questions WHERE id = ?', [$hq]) === 'CLOSED' && (int)q('SELECT helpful FROM health_questions WHERE id = ?', [$hq]) === 1);

// Câu sai chủ đề: chuyên gia chấn thương không xem được
$u1web->get('/consult-ask.php');
$u1web->postFollow('/consult-ask.php', ['csrf_token' => $u1web->csrf(), 'topic' => 'NUTRITION', 'title' => 'Ăn gì trước buổi tập sáng', 'body' => 'Em tập lúc 6 giờ sáng, nên ăn gì trước khi tập để không bị mệt ạ?', 'share_profile' => '1']);
$hq2 = (int)q('SELECT id FROM health_questions WHERE user_id = ?', [$u1]);
check('Tick chia sẻ: câu hỏi có số liệu hồ sơ', $hq2 > 0 && str_contains((string)q('SELECT profile_snapshot FROM health_questions WHERE id = ?', [$hq2]), 'Tuổi'));
check('Chuyên gia chấn thương mở câu dinh dưỡng: 404', $u3web->get('/expert/question.php?id=' . $hq2)->code === 404);
$u3web->get('/expert/dashboard.php');
$u3web->post('/expert/dashboard.php', ['csrf_token' => $u3web->csrf(), 'action' => 'claim', 'question_id' => $hq2]);
check('Chuyên gia chấn thương gửi form nhận câu dinh dưỡng: không được', q('SELECT status FROM health_questions WHERE id = ?', [$hq2]) === 'OPEN');

// Thu hồi
$admin->get('/admin/experts.php');
$admin->postFollow('/admin/experts.php', ['csrf_token' => $admin->csrf(), 'action' => 'revoke', 'expert_id' => $u3]);
check('Admin thu hồi: vai trò về USER, ghi nhật ký', q('SELECT role FROM users WHERE id = ?', [$u3]) === 'USER' && (int)q("SELECT COUNT(*) FROM admin_audit_log WHERE action = 'expert.revoke'") === 1);
check('Đã bị thu hồi: khu chuyên gia 403', $u3web->get('/expert/dashboard.php')->code === 403);
foreach (glob($root . '/storage/certificates/c' . $u3 . '-*') as $f) {
    @unlink($f);
}
@unlink($png);
