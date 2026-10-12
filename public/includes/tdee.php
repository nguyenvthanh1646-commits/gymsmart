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