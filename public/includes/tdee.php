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
    if ($weightKg <= 0 || $heightCm <= 0 || $age <= 0) {
        throw new InvalidArgumentException('Cân nặng, chiều cao, tuổi phải > 0');
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
    if (!is_numeric($bmr) || $bmr <= 0) {
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
 * @return array ['target_kcal', 'protein_g', 'carbs_g', 'fat_g']
 * @throws InvalidArgumentException nếu dữ liệu không hợp lệ
 */
function calculateTargets($tdee, $weightKg, $goal, $experience = null) {
    // Thông số theo goal: hệ số calo so với TDEE và g chất béo/kg
    // TODO: bạn tìm nguồn (VD: ACSM) rồi ghi lý do chọn từng số vào comment.
    $profiles = [
        'LOSE_FAT'    => ['kcal_factor' => 0.80, 'fat_per_kg' => 0.8], // giảm 20%
        'MAINTAIN'    => ['kcal_factor' => 1.00, 'fat_per_kg' => 0.9],
        'GAIN_MUSCLE' => ['kcal_factor' => 1.10, 'fat_per_kg' => 1.0], // tăng 10%
    ];

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
    // Kiểm tra số đầu vào
    if (!is_numeric($tdee) || $tdee <= 0 || !is_numeric($weightKg) || $weightKg <= 0) {
        throw new InvalidArgumentException('TDEE và cân nặng phải là số > 0');
    }

    $p = $profiles[$goal];

    // Calo mục tiêu = TDEE điều chỉnh theo mục tiêu
    $targetKcal = $tdee * $p['kcal_factor'];

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