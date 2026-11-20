<?php
/**
 * includes/coach-rules.php — quy tắc nghiệp vụ của phân hệ Huấn luyện viên (HLV).
 *
 * File này THUẦN: không đụng DB, không đọc $_POST/$_SESSION. Nhận dữ liệu vào, trả kết quả ra,
 * nên test được bằng test-coach.php mà không cần MySQL.
 * Phần truy vấn DB nằm ở includes/coach-db.php, phần phân quyền ở includes/coach.php.
 */

// ---------- Giới hạn độ dài (máy chủ kiểm tra; ô nhập trên trang dùng cùng số này cho maxlength) ----------
const COACH_MESSAGE_MAX  = 1000;  // tin nhắn
const COACH_FEEDBACK_MAX = 1000;  // nhận xét
const COACH_NOTE_MAX     = 500;   // lời nhắn khi gửi yêu cầu, lý do xin sửa, câu trả lời của HLV, ghi chú của admin
const COACH_UNLOCK_HOURS = 24;    // HLV duyệt yêu cầu sửa thì học viên được tự sửa trong bao lâu
const COACH_MSG_MIN_GAP  = 2;     // giây tối thiểu giữa 2 tin nhắn của cùng một người
const COACH_MSG_PER_HOUR = 60;    // số tin tối đa mỗi giờ của một người

/** Chuyên môn: mã lưu trong DB => nhãn hiển thị. Đơn đăng ký chỉ nhận đúng các mã này (whitelist). */
const COACH_SPECIALTIES = [
    'FAT_LOSS'    => 'Giảm mỡ',
    'HYPERTROPHY' => 'Tăng cơ',
    'STRENGTH'    => 'Sức mạnh',
    'BEGINNER'    => 'Người mới bắt đầu',
    'NUTRITION'   => 'Dinh dưỡng thể thao',
    'MOBILITY'    => 'Linh hoạt và phục hồi',
    'ENDURANCE'   => 'Sức bền, cardio',
    'WOMEN'       => 'Tập luyện cho nữ',
];
const COACH_SPECIALTIES_MAX = 4;

/** Nhãn trạng thái để hiển thị. */
const COACH_RELATION_LABELS = [
    'PENDING'  => 'Chờ HLV duyệt',
    'ACTIVE'   => 'Đang tập',
    'ENDED'    => 'Đã kết thúc',
    'REJECTED' => 'Bị từ chối',
];
const COACH_APPLICATION_LABELS = [
    'PENDING'  => 'Đang chờ duyệt',
    'APPROVED' => 'Đã duyệt',
    'REJECTED' => 'Bị từ chối',
];
const COACH_CHANGE_AREAS = [
    'PLAN'      => 'Lịch tập',
    'NUTRITION' => 'Dinh dưỡng',
];

/**
 * Làm sạch một ô chữ: bỏ khoảng trắng đầu cuối, đổi xuống dòng về "\n", bỏ ký tự điều khiển lạ
 * (giữ lại xuống dòng và tab). Không escape HTML ở đây: escape lúc IN RA bằng htmlspecialchars().
 */
function coachCleanText($raw): string
{
    if (!is_string($raw)) {
        return '';
    }
    $s = str_replace(["\r\n", "\r"], "\n", $raw);
    $s = preg_replace('/[^\P{C}\n\t]/u', '', $s) ?? '';   // \p{C} = ký tự điều khiển/vô hình; /u = đọc theo UTF-8
    $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? '';      // tối đa 1 dòng trống liên tiếp
    return trim($s);
}

/**
 * Kiểm tra một ô chữ bắt buộc/tùy chọn với độ dài cho phép.
 * Trả về null nếu hợp lệ, hoặc câu báo lỗi tiếng Việt.
 */
function coachTextError(string $value, string $label, int $min, int $max): ?string
{
    $len = mb_strlen($value);
    if ($min > 0 && $len === 0) {
        return $label . ' không được để trống.';
    }
    if ($len > 0 && $len < $min) {
        return $label . ' cần ít nhất ' . $min . ' ký tự (hiện có ' . $len . ').';
    }
    if ($len > $max) {
        return $label . ' tối đa ' . $max . ' ký tự (hiện có ' . $len . ').';
    }
    return null;
}

/**
 * Họ và tên hiển thị của HLV (học viên thấy ở trang Huấn luyện viên): làm sạch như coachCleanText rồi
 * gộp mọi khoảng trắng / xuống dòng thành một dấu cách, vì tên chỉ có một dòng.
 */
function coachNormalizeName($raw): string
{
    return trim(preg_replace('/\s+/u', ' ', coachCleanText($raw)) ?? '');
}

/** Lỗi của họ và tên đã chuẩn hóa, hoặc null. Tối đa 100 ký tự như trang đăng ký và trang Hồ sơ. */
function coachNameError(string $name): ?string
{
    return coachTextError($name, 'Họ và tên', 2, 100);
}

/** Đổi chuỗi mã chuyên môn trong DB ("FAT_LOSS,STRENGTH") thành danh sách nhãn; mã lạ bị bỏ qua. */
function coachSpecialtyLabels(string $csv): array
{
    $out = [];
    foreach (explode(',', $csv) as $code) {
        $code = trim($code);
        if (isset(COACH_SPECIALTIES[$code])) {
            $out[] = COACH_SPECIALTIES[$code];
        }
    }
    return $out;
}

/**
 * Kiểm tra đơn đăng ký làm HLV (cũng dùng cho trang HLV tự sửa hồ sơ, khi đó $requireAgree = false).
 * $in là mảng thô (thường là $_POST). Trả về ['data' => mảng đã làm sạch, 'errors' => [tên ô => lỗi]].
 */
function validateCoachApplication(array $in, bool $requireAgree = true): array
{
    $errors = [];
    $data = [
        'headline'       => coachCleanText($in['headline'] ?? ''),
        'bio'            => coachCleanText($in['bio'] ?? ''),
        'approach'       => coachCleanText($in['approach'] ?? ''),
        'certifications' => coachCleanText($in['certifications'] ?? ''),
        'availability'   => coachCleanText($in['availability'] ?? ''),
    ];

    if ($e = coachTextError($data['headline'], 'Chức danh', 10, 120)) $errors['headline'] = $e;
    if ($e = coachTextError($data['bio'], 'Giới thiệu bản thân', 80, 1500)) $errors['bio'] = $e;
    if ($e = coachTextError($data['approach'], 'Phương pháp huấn luyện', 40, 1000)) $errors['approach'] = $e;
    if ($e = coachTextError($data['certifications'], 'Chứng chỉ', 0, 1000)) $errors['certifications'] = $e;
    if ($e = coachTextError($data['availability'], 'Thời gian hỗ trợ', 0, 255)) $errors['availability'] = $e;
    if (!isset($errors['headline']) && strpos($data['headline'], "\n") !== false) {
        $errors['headline'] = 'Chức danh chỉ viết trên một dòng.';
    }
    if (!isset($errors['certifications']) && substr_count($data['certifications'], "\n") >= 10) {
        $errors['certifications'] = 'Ghi tối đa 10 chứng chỉ, mỗi dòng một chứng chỉ.';
    }

    // Chuyên môn: mảng checkbox, chỉ nhận mã trong whitelist, bỏ trùng
    $codes = $in['specialties'] ?? [];
    $codes = is_array($codes) ? array_values(array_unique(array_filter($codes, 'is_string'))) : [];
    $valid = array_values(array_filter($codes, fn($c) => isset(COACH_SPECIALTIES[$c])));
    if (count($valid) !== count($codes)) {
        $errors['specialties'] = 'Chuyên môn không hợp lệ.';
    } elseif (count($valid) === 0) {
        $errors['specialties'] = 'Chọn ít nhất 1 chuyên môn.';
    } elseif (count($valid) > COACH_SPECIALTIES_MAX) {
        $errors['specialties'] = 'Chọn tối đa ' . COACH_SPECIALTIES_MAX . ' chuyên môn để học viên dễ nhận ra thế mạnh của bạn.';
    }
    $data['specialties'] = implode(',', $valid);

    $years = filter_var($in['experience_years'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 50]]);
    if ($years === false) {
        $errors['experience_years'] = 'Số năm kinh nghiệm phải là số nguyên từ 0 đến 50.';
    }
    $data['experience_years'] = $years === false ? 0 : $years;

    $max = filter_var($in['max_clients'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);
    if ($max === false) {
        $errors['max_clients'] = 'Số học viên tối đa phải từ 1 đến 30.';
    }
    $data['max_clients'] = $max === false ? 10 : $max;

    if ($requireAgree) {
        if (($in['agree_truth'] ?? '') !== '1') {
            $errors['agree_truth'] = 'Bạn cần xác nhận thông tin là đúng sự thật.';
        }
        if (($in['agree_rules'] ?? '') !== '1') {
            $errors['agree_rules'] = 'Bạn cần đồng ý với quy tắc dành cho HLV.';
        }
    }
    return ['data' => $data, 'errors' => $errors];
}

/**
 * Máy trạng thái của quan hệ HLV - học viên: từ trạng thái hiện tại + hành động → trạng thái mới.
 * Trả về null nếu hành động không được phép ở trạng thái đó (ví dụ duyệt một quan hệ đã kết thúc).
 *
 *   client: cancel (PENDING → ENDED, tự hủy yêu cầu), end (ACTIVE → ENDED)
 *   coach : accept (PENDING → ACTIVE), reject (PENDING → REJECTED), end (ACTIVE → ENDED)
 */
function coachNextStatus(string $current, string $actor, string $action): ?string
{
    $rules = [
        'client' => ['cancel' => ['PENDING' => 'ENDED'], 'end' => ['ACTIVE' => 'ENDED']],
        'coach'  => ['accept' => ['PENDING' => 'ACTIVE'], 'reject' => ['PENDING' => 'REJECTED'], 'end' => ['ACTIVE' => 'ENDED']],
    ];
    return $rules[$actor][$action][$current] ?? null;
}

/**
 * Kiểm tra mục tiêu dinh dưỡng HLV đặt tay. HLV nhập gam protein/carb/fat, máy chủ tự tính kcal = 4P + 4C + 9F
 * (1 g đạm hoặc tinh bột ≈ 4 kcal, 1 g chất béo ≈ 9 kcal), nên kcal và macro không thể lệch nhau.
 *
 * Giới hạn (an toàn cho người tập phổ thông, không thay thế tư vấn y tế):
 *   - tổng 1200-5000 kcal/ngày
 *   - protein 40-400 g; nếu biết cân nặng thì 0,8-3,3 g/kg
 *   - chất béo 20-250 g; nếu biết cân nặng thì tối thiểu 0,5 g/kg (quá thấp ảnh hưởng nội tiết)
 *   - tinh bột 0-800 g
 * Trả về ['kcal' => int, 'errors' => [tên ô => lỗi]].
 */
function validateCoachMacros($protein, $carbs, $fat, ?float $weightKg): array
{
    $errors = [];
    $p = filter_var($protein, FILTER_VALIDATE_INT);
    $c = filter_var($carbs, FILTER_VALIDATE_INT);
    $f = filter_var($fat, FILTER_VALIDATE_INT);

    if ($p === false || $p < 40 || $p > 400) $errors['protein_g'] = 'Protein phải là số nguyên từ 40 đến 400 g.';
    if ($c === false || $c < 0 || $c > 800)  $errors['carbs_g']   = 'Tinh bột phải là số nguyên từ 0 đến 800 g.';
    if ($f === false || $f < 20 || $f > 250) $errors['fat_g']     = 'Chất béo phải là số nguyên từ 20 đến 250 g.';
    if ($errors) {
        return ['kcal' => 0, 'errors' => $errors];
    }

    if ($weightKg !== null && $weightKg > 0) {
        $pPerKg = $p / $weightKg;
        if ($pPerKg < 0.8 || $pPerKg > 3.3) {
            $errors['protein_g'] = 'Protein đang là ' . number_format($pPerKg, 1, ',', '.') . ' g/kg; nên trong khoảng 0,8-3,3 g/kg cân nặng.';
        }
        if ($f / $weightKg < 0.5) {
            $errors['fat_g'] = 'Chất béo nên tối thiểu 0,5 g/kg cân nặng (' . (int)ceil($weightKg * 0.5) . ' g).';
        }
    }

    $kcal = 4 * $p + 4 * $c + 9 * $f;
    if ($kcal < 1200 || $kcal > 5000) {
        $errors['kcal'] = 'Tổng năng lượng tính ra là ' . number_format($kcal, 0, ',', '.') . ' kcal; phải trong khoảng 1.200-5.000 kcal.';
    }
    return ['kcal' => $kcal, 'errors' => $errors];
}

/** Số chỗ còn nhận của HLV (không âm). */
function coachSeatsLeft(int $maxClients, int $activeClients): int
{
    return max(0, $maxClients - $activeClients);
}

/**
 * Kiểm tra nhịp gửi tin: $secondsSinceLast = số giây kể từ tin trước của người này (null = chưa từng gửi),
 * $sentLastHour = số tin đã gửi trong 60 phút qua. Trả về null nếu được gửi, ngược lại là câu báo lỗi.
 */
function coachMessageRateError(?int $secondsSinceLast, int $sentLastHour): ?string
{
    if ($secondsSinceLast !== null && $secondsSinceLast < COACH_MSG_MIN_GAP) {
        return 'Bạn gửi hơi nhanh, đợi một chút rồi gửi tiếp.';
    }
    if ($sentLastHour >= COACH_MSG_PER_HOUR) {
        return 'Bạn đã gửi ' . COACH_MSG_PER_HOUR . ' tin trong một giờ qua. Hãy thử lại sau.';
    }
    return null;
}

// ---------------------------------------------------------------------
// Mở rộng (a-36): tỉ lệ tập đúng lịch, yêu cầu chờ quá lâu
// ---------------------------------------------------------------------
const COACH_PENDING_STALE_DAYS = 7;   // yêu cầu chờ HLV quá số ngày này thì nhắc học viên có thể chọn HLV khác

/**
 * Học viên tập đều tới đâu trong TUẦN HIỆN TẠI (thứ Hai → Chủ nhật chứa $today).
 *   $planWeekdays: các thứ có buổi tập trong lịch ACTIVE (1 = thứ Hai ... 7 = Chủ nhật)
 *   $loggedDates : các ngày có ghi log (Y-m-d), trùng hay ngoài tuần đều được, hàm tự lọc
 * Trả về:
 *   planned_week   số buổi trong lịch cả tuần
 *   planned_so_far số buổi lẽ ra đã tập tính tới hôm nay (kể cả hôm nay)
 *   done           số NGÀY trong tuần (tới hôm nay) có ghi log, kể cả ngày ngoài lịch (tập bù vẫn tính)
 *   percent        done / planned_so_far (tối đa 100), null nếu chưa tới buổi nào
 */
function coachWeekCompliance(array $planWeekdays, array $loggedDates, string $today): array
{
    $todayN = (int)date('N', strtotime($today));
    $monday = date('Y-m-d', strtotime($today . ' -' . ($todayN - 1) . ' days'));
    $days = array_values(array_unique(array_map('intval', $planWeekdays)));
    $days = array_values(array_filter($days, fn($d) => $d >= 1 && $d <= 7));
    $plannedSoFar = count(array_filter($days, fn($d) => $d <= $todayN));
    $done = count(array_unique(array_filter($loggedDates, fn($d) => is_string($d) && $d >= $monday && $d <= $today)));
    return [
        'planned_week'   => count($days),
        'planned_so_far' => $plannedSoFar,
        'done'           => $done,
        'percent'        => $plannedSoFar > 0 ? (int)min(100, round($done / $plannedSoFar * 100)) : null,
    ];
}

/** Yêu cầu gửi lúc $createdAt đã chờ quá COACH_PENDING_STALE_DAYS ngày tính tới $now chưa. Trả về số ngày đã chờ, hoặc 0 nếu chưa quá. */
function coachPendingStaleDays(string $createdAt, string $now): int
{
    $days = (int)floor((strtotime($now) - strtotime($createdAt)) / 86400);
    return $days >= COACH_PENDING_STALE_DAYS ? $days : 0;
}
