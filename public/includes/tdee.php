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