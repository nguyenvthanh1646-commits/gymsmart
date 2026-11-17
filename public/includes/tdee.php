<?php
/**
 * includes/tdee.php
 * Các hàm tính toán dinh dưỡng: BMR, TDEE, macro.
 * File này chỉ chứa hàm (không có HTML) để nutrition.php và
 * api/calculate-tdee.php dùng chung, tránh viết lặp.
 */

/**
 * Tính BMR (calo cơ thể đốt khi nghỉ hoàn toàn) theo công thức Mifflin-St Jeor.
 *
 * @param float  $weightKg  Cân nặng (kg)
 * @param float  $heightCm  Chiều cao (cm)
 * @param int    $age       Tuổi (năm)
 * @param string $gender    MALE | FEMALE | OTHER
 * @return float BMR (kcal/ngày)
 * @throws InvalidArgumentException nếu dữ liệu không hợp lệ
 */
function calculateBMR($weightKg, $heightCm, $age, $gender) {
    // Whitelist: chỉ chấp nhận đúng 3 giá trị giới tính theo schema.
    // Tham số true ở cuối = so sánh chặt (cả kiểu dữ liệu).
    if (!in_array($gender, ['MALE', 'FEMALE', 'OTHER'], true)) {
        throw new InvalidArgumentException('Giới tính không hợp lệ');
    }

    // Chặn dữ liệu vô lý. Dùng throw thay vì return 0 để lỗi không bị
    // che đi (tránh lưu 0 kcal vào DB như một kết quả thật).
    // is_numeric: chặn chuỗi lạ ("abc", "70kg"); is_finite: chặn NAN/INF (so sánh với NAN luôn ra false nên lọt qua "<= 0").
    foreach ([$weightKg, $heightCm, $age] as $v) {
        if (!is_numeric($v) || !is_finite((float)$v)) {
            throw new InvalidArgumentException('Cân nặng, chiều cao, tuổi phải là số');
        }
    }
    if ($weightKg <= 0 || $heightCm <= 0 || $age <= 0) {
        throw new InvalidArgumentException('Cân nặng, chiều cao, tuổi phải > 0');
    }
    // Giới hạn trên rộng hơn form hồ sơ (300 kg, 250 cm, 100 tuổi) một chút, chỉ để chặn số vô lý
    if ($weightKg > 500 || $heightCm > 300 || $age > 120) {
        throw new InvalidArgumentException('Cân nặng, chiều cao hoặc tuổi vượt giới hạn hợp lý');
    }

    // Phần chung cho mọi giới tính: 10*kg + 6.25*cm - 5*tuổi
    $bmr = 10 * $weightKg + 6.25 * $heightCm - 5 * $age;

    // Cộng hằng số theo giới tính (theo nghiên cứu gốc Mifflin 1990)
    if ($gender === 'MALE') {
        $bmr += 5;
    } elseif ($gender === 'FEMALE') {
        $bmr -= 161;
    } else {
        // OTHER: code đang lấy trung bình hai hằng số: (5 + (-161)) / 2 = -78
        // Lý do chọn: ... (TODO: bạn tự điền sau khi đọc nguồn tham khảo)
        $bmr += (5 - 161) / 2;
    }

    // Số đo lệch nhau quá mức (ví dụ 1 kg, 1 cm, 120 tuổi) cho BMR âm: không có ý nghĩa thì báo lỗi
    if ($bmr <= 0) {
        throw new InvalidArgumentException('Chỉ số cơ thể không hợp lệ (BMR tính ra <= 0)');
    }

    return $bmr; // trả về cho nơi gọi hàm (VD: calculateTDEE)
}
/**
 * Tính TDEE (tổng calo đốt cả ngày) = BMR x hệ số hoạt động.
 *
 * @param float  $bmr           BMR (kcal/ngày), lấy từ calculateBMR()
 * @param string $activityLevel SEDENTARY | LIGHT | MODERATE | ACTIVE | EXTRA_ACTIVE
 * @return float TDEE (kcal/ngày)
 * @throws InvalidArgumentException nếu dữ liệu không hợp lệ
 */
function calculateTDEE($bmr, $activityLevel) {
    // Mảng kết hợp: tên mức hoạt động => hệ số (theo Harris-Benedict/Mifflin)
    $factors = [
        'SEDENTARY'   => 1.2,    // ít vận động
        'LIGHT'       => 1.375,  // tập nhẹ 1-3 buổi/tuần
        'MODERATE'    => 1.55,   // tập vừa 3-5 buổi/tuần
        'ACTIVE'      => 1.725,  // tập nặng 6-7 buổi/tuần
        'EXTRA_ACTIVE'=> 1.9,    // tập rất nặng/lao động nặng
    ];

    // Whitelist: chỉ chấp nhận khóa có trong mảng
    if (!is_string($activityLevel) || !array_key_exists($activityLevel, $factors)) {
        throw new InvalidArgumentException('Mức độ hoạt động không hợp lệ');
    }

    // Kiểm tra BMR là số dương
    if (!is_numeric($bmr) || !is_finite((float)$bmr) || $bmr <= 0) {
        throw new InvalidArgumentException('BMR phải là số > 0');
    }

    return $bmr * $factors[$activityLevel];
}



/**
 * Tính calo mục tiêu và chia macro (gram) theo goal.
 * Quy đổi: protein 4 kcal/g, carb 4 kcal/g, fat 9 kcal/g.
 *
 * @param float  $tdee     TDEE (kcal/ngày)
 * @param float  $weightKg Cân nặng (kg)
 * @param string $goal     LOSE_FAT | MAINTAIN | GAIN_MUSCLE
 * @param string|null $experience BEGINNER | INTERMEDIATE | ADVANCED (trình độ tập luyện); null = coi như người mới
 * @param string $pace     gentle | standard | fast (nhịp mục tiêu, xem goalPaces); mặc định standard giữ đúng kết quả cũ
 * @return array ['target_kcal', 'protein_g', 'carbs_g', 'fat_g']
 * @throws InvalidArgumentException nếu dữ liệu không hợp lệ
 */
function calculateTargets($tdee, $weightKg, $goal, $experience = null, $pace = 'standard') {
    // Thông số theo goal: g chất béo/kg. Hệ số calo so với TDEE nằm ở goalPaces().
    // TODO: bạn tìm nguồn (VD: ACSM) rồi ghi lý do chọn từng số vào comment.
    $profiles = [
        'LOSE_FAT'    => ['fat_per_kg' => 0.8],
        'MAINTAIN'    => ['fat_per_kg' => 0.9],
        'GAIN_MUSCLE' => ['fat_per_kg' => 1.0],
    ];
    $paces = goalPaces();

    // Protein (g/kg cân nặng) theo TRÌNH ĐỘ tập luyện, không theo mục tiêu:
    //   người mới tập  : 1.6 g/kg (phân tích tổng hợp Morton 2018: lợi ích tăng cơ gần như đạt đỉnh quanh mức ~1.6 g/kg/ngày)
    //   có kinh nghiệm : 1.8 g/kg (nằm trong khoảng 1.4-2.0 g/kg ISSN khuyến nghị cho người tập luyện; tập lâu cần nhiều hơn một chút để duy trì/xây cơ)
    // Cao hơn mức này thường không mang lại thêm lợi ích rõ rệt mà còn đẩy calo ra khỏi tinh bột.
    $proteinByExperience = [
        'BEGINNER'     => 1.6,
        'INTERMEDIATE' => 1.8,
        'ADVANCED'     => 1.8,
    ];
    if ($experience === null || $experience === '') {
        $experience = 'BEGINNER'; // chưa chọn trình độ thì dùng mức an toàn của người mới
    }
    if (!is_string($experience) || !array_key_exists($experience, $proteinByExperience)) {
        throw new InvalidArgumentException('Trình độ tập luyện không hợp lệ');
    }

    // Whitelist goal
    if (!is_string($goal) || !array_key_exists($goal, $profiles)) {
        throw new InvalidArgumentException('Mục tiêu không hợp lệ');
    }
    // Whitelist nhịp mục tiêu
    if (!is_string($pace) || !array_key_exists($pace, $paces[$goal])) {
        throw new InvalidArgumentException('Nhịp mục tiêu không hợp lệ');
    }
    // Kiểm tra số đầu vào
    if (!is_numeric($tdee) || !is_finite((float)$tdee) || $tdee <= 0
        || !is_numeric($weightKg) || !is_finite((float)$weightKg) || $weightKg <= 0) {
        throw new InvalidArgumentException('TDEE và cân nặng phải là số > 0');
    }

    $p = $profiles[$goal];

    // Calo mục tiêu = TDEE điều chỉnh theo mục tiêu
    $targetKcal = $tdee * $paces[$goal][$pace];

    // Protein (theo trình độ) và fat (theo mục tiêu) đều tính theo cân nặng
    $proteinG = $proteinByExperience[$experience] * $weightKg;
    $fatG     = $p['fat_per_kg'] * $weightKg;

    // Carb = phần calo còn lại sau khi trừ protein và fat, chia 4 kcal/g
    $carbsG = ($targetKcal - $proteinG * 4 - $fatG * 9) / 4;

    // Nếu calo còn lại âm (cân nặng lớn, calo thấp) thì đặt carb = 0
    if ($carbsG < 0) {
        $carbsG = 0;
    }

    // Làm tròn 1 chữ số thập phân; tên khóa khớp cột bảng nutrition_targets
    return [
        'target_kcal' => round($targetKcal, 1),
        'protein_g'   => round($proteinG, 1),
        'carbs_g'     => round($carbsG, 1),
        'fat_g'       => round($fatG, 1),
    ];
}


/** Số kcal tương ứng 1 kg cân nặng thay đổi. Đây là con số xấp xỉ quen dùng, thực tế khác nhau tùy người. */
const KCAL_PER_KG = 7700;

/**
 * Nhịp mục tiêu: hệ số calo so với TDEE theo từng mục tiêu.
 * standard là hệ số cũ (giảm 20%, tăng 10%). Duy trì luôn bằng TDEE nên ba nhịp như nhau.
 */
function goalPaces(): array
{
    return [
        'LOSE_FAT'    => ['gentle' => 0.90, 'standard' => 0.80, 'fast' => 0.75],
        'MAINTAIN'    => ['gentle' => 1.00, 'standard' => 1.00, 'fast' => 1.00],
        'GAIN_MUSCLE' => ['gentle' => 1.05, 'standard' => 1.10, 'fast' => 1.15],
    ];
}

/**
 * Số kg thay đổi dự kiến mỗi tuần nếu ăn đúng calo mục tiêu.
 * Âm là giảm cân. Ví dụ ăn dưới TDEE 500 kcal/ngày: -500 * 7 / 7700 = -0,45 kg/tuần.
 */
function expectedWeeklyChange(float $targetKcal, float $tdee): float
{
    return ($targetKcal - $tdee) * 7 / KCAL_PER_KG;
}

/**
 * Chia calo khác nhau giữa ngày tập và ngày nghỉ mà TỔNG CALO CẢ TUẦN KHÔNG ĐỔI.
 *
 * Gọi K là calo mục tiêu, n là số ngày tập, s là mức chênh (0,10 = ngày tập ăn hơn ngày nghỉ 10% của K):
 *   ngày tập = K * (1 + s * (7 - n) / 7)
 *   ngày nghỉ = K * (1 - s * n / 7)
 * Cộng lại: n * ngày tập + (7 - n) * ngày nghỉ = 7 * K, phần s tự triệt tiêu.
 *
 * Protein và chất béo giữ nguyên, phần chênh chỉ nằm ở tinh bột (4 kcal/g) vì tinh bột là nhiên liệu chính khi tập nặng.
 * Mức 10% là lựa chọn của nhóm để dễ theo, không phải con số chuẩn. Ngày nghỉ không giảm quá 40% lượng tinh bột,
 * nên với kiểu ăn ít tinh bột, mức chênh tự nhỏ lại.
 *
 * @return array|null ['train' => macro, 'rest' => macro] hoặc null khi không chia được (0 hoặc 7 ngày tập, không có tinh bột để chia)
 */
function splitTrainingRestDays(array $macros, int $trainingDays, float $shift = 0.10): ?array
{
    if ($trainingDays < 1 || $trainingDays > 6) {
        return null;
    }
    $kcal = (float)$macros['target_kcal'];
    $carbs = (float)$macros['carbs_g'];
    $restDrop = $kcal * $shift * $trainingDays / 7;       // số kcal ngày nghỉ bị bớt
    $maxDrop = $carbs * 4 * 0.40;
    if ($maxDrop <= 0) {
        return null;
    }
    if ($restDrop > $maxDrop) {
        $restDrop = $maxDrop;
    }
    $trainGain = $restDrop * (7 - $trainingDays) / $trainingDays;   // cả tuần: n * trainGain = (7 - n) * restDrop

    $withDelta = fn(float $delta): array => [
        'target_kcal' => round($kcal + $delta, 1),
        'protein_g'   => $macros['protein_g'],
        'carbs_g'     => round($carbs + $delta / 4, 1),
        'fat_g'       => $macros['fat_g'],
    ];
    return ['train' => $withDelta($trainGain), 'rest' => $withDelta(-$restDrop)];
}

/**
 * So tốc độ thay đổi cân nặng thực tế với kế hoạch rồi gợi ý chỉnh calo.
 *
 * Từng bước:
 *   1. Chỉ lấy các lần đo trong 28 ngày tính tới lần đo mới nhất (số cũ hơn có thể thuộc mục tiêu khác).
 *   2. Cần ít nhất 3 lần đo, cách nhau tổng cộng từ 14 ngày, và lần gần nhất không quá 14 ngày trước.
 *   3. Dùng hồi quy tuyến tính để lấy độ dốc (kg/ngày), vì cân nặng dao động theo nước và bữa ăn,
 *      so hai đầu mút thì một lần đo lệch cũng làm sai kết quả.
 *   4. Lệch quá 0,15 kg/tuần so với kế hoạch mới gợi ý chỉnh: quy phần lệch ra kcal/ngày,
 *      mỗi lần chỉnh tối đa 250 kcal, làm tròn bội số 50, và không gợi ý ăn thấp hơn $floorKcal.
 *
 * TDEE thực tế chỉ đúng khi người dùng ăn gần với calo mục tiêu, nên trang phải nói rõ đây là ước tính.
 *
 * @param array  $metrics    [['recorded_date' => 'Y-m-d', 'weight_kg' => số]], thứ tự bất kỳ
 * @param float  $floorKcal  mức calo thấp nhất được phép gợi ý
 * @param string $today      ngày hiện tại 'Y-m-d'
 * @return array status: need_more | stale | on_track | above_plan | below_plan
 */
function analyzeWeightProgress(array $metrics, float $targetKcal, float $tdee, float $floorKcal, string $today): array
{
    $dayNumber = fn(string $date): int => (int)floor((new DateTimeImmutable($date . ' UTC'))->getTimestamp() / 86400);

    $points = [];
    foreach ($metrics as $metric) {
        $points[$dayNumber((string)$metric['recorded_date'])] = (float)$metric['weight_kg'];
    }
    ksort($points);
    $newest = $points ? array_key_last($points) : null;
    if ($newest !== null) {
        $points = array_filter($points, fn(int $day) => $day >= $newest - 28, ARRAY_FILTER_USE_KEY);
    }

    $count = count($points);
    $span = $count > 0 ? $newest - array_key_first($points) : 0;
    $result = ['status' => 'need_more', 'count' => $count, 'span_days' => $span, 'need_count' => 3, 'need_days' => 14];
    if ($count < 3 || $span < 14) {
        return $result;
    }
    if ($dayNumber($today) - $newest > 14) {
        $result['status'] = 'stale';
        return $result;
    }

    // Hồi quy tuyến tính: độ dốc = tổng (x - x tb)(y - y tb) / tổng (x - x tb)^2
    $meanX = array_sum(array_keys($points)) / $count;
    $meanY = array_sum($points) / $count;
    $numerator = $denominator = 0.0;
    foreach ($points as $x => $y) {
        $numerator += ($x - $meanX) * ($y - $meanY);
        $denominator += ($x - $meanX) ** 2;
    }
    $actualPerWeek = $numerator / $denominator * 7;
    $plannedPerWeek = expectedWeeklyChange($targetKcal, $tdee);
    $gap = $actualPerWeek - $plannedPerWeek;

    $status = 'on_track';
    $adjust = 0;
    $limited = false;
    if (abs($gap) > 0.15) {
        $status = $gap > 0 ? 'above_plan' : 'below_plan';   // above: cân nặng đang cao hơn kế hoạch nên cần bớt calo
        $wanted = max(-250, min(250, -$gap * KCAL_PER_KG / 7));
        $adjust = (int)(round($wanted / 50) * 50);
        if ($adjust < 0 && $targetKcal + $adjust < $floorKcal) {
            $adjust = min(0, (int)(ceil(($floorKcal - $targetKcal) / 50) * 50));
            $limited = true;
        }
    }

    $result['status'] = $status;
    return $result + [
        'actual_per_week'  => $actualPerWeek,
        'planned_per_week' => $plannedPerWeek,
        'estimated_tdee'   => $targetKcal - $actualPerWeek * KCAL_PER_KG / 7,
        'adjust_kcal'      => $adjust,
        'suggested_kcal'   => $targetKcal + $adjust,
        'limited'          => $limited,
    ];
}
