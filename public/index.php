<?php
/**
 * index.php — trang chủ.
 *
 * Hai trạng thái:
 *   - Chưa đăng nhập: giới thiệu ngắn + nút Đăng ký / Đăng nhập.
 *   - Đã đăng nhập:   chào tên, kiểm tra hồ sơ đã đủ chưa
 *                     (thiếu thì nhắc vào /profile.php), và hiện lối tắt đến các chức năng.
 *
 * Vì sao phải nhắc hoàn thiện hồ sơ? TDEE/Macro (C) và lịch tập (B) đều cần
 * đủ 7 thông tin hồ sơ. Thiếu một thông tin là các chức năng đó không tính được.
 */

require_once __DIR__ . '/includes/auth.php'; // có session + isLoggedIn()
require_once __DIR__ . '/includes/db.php';   // có biến $pdo

$loggedIn       = isLoggedIn();

// Quản trị viên có trang chủ riêng (tổng quan), không dùng trang chủ của người dùng
if ($loggedIn && ($_SESSION['role'] ?? '') === 'ADMIN') {
    header('Location: /admin/dashboard.php');
    exit;
}
$missingFields  = []; // tên các mục hồ sơ còn thiếu (để hiển thị cho người dùng)

if ($loggedIn) {
    // Tên cột trong DB (bên trái) => chữ hiển thị khi báo thiếu (bên phải)
    $profileFields = [
        'age'              => 'Tuổi',
        'height_cm'        => 'Chiều cao',
        'weight_kg'        => 'Cân nặng',
        'gender'           => 'Giới tính',
        'activity_level'   => 'Mức độ vận động',
        'experience_level' => 'Trình độ tập luyện',
        'goal'             => 'Mục tiêu',
    ];

    // Lấy id từ SESSION (không lấy từ URL/form) để không xem được hồ sơ người khác
    $stmt = $pdo->prepare(
        "SELECT age, height_cm, weight_kg, gender, activity_level, experience_level, goal
         FROM users WHERE id = ?"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if ($row) {
        foreach ($profileFields as $column => $label) {
            // Cột chưa nhập có giá trị NULL (hoặc rỗng) → coi là thiếu.
            // Dùng === null thay vì empty() vì giá trị 0 không phải trường hợp thiếu.
            if ($row[$column] === null || $row[$column] === '') {
                $missingFields[] = $label;
            }
        }
    } else {
        // Session còn nhưng user đã bị xóa khỏi DB → đăng xuất cho an toàn
        $_SESSION = [];
        session_destroy();
        header('Location: /login.php');
        exit;
    }
}

$pageTitle = 'Trang chủ';
include __DIR__ . '/includes/header.php';
?>

<?php if (!$loggedIn): ?>

    <div class="card" style="max-width: 640px; margin: 2rem auto; text-align: center;">
        <h1>GymSmart</h1>
        <p>
            Thư viện bài tập có video hướng dẫn, lịch tập cá nhân hóa,
            tính TDEE/Macro và theo dõi tiến trình, tất cả trong một nơi.
        </p>
        <p>
            <a class="btn" href="/register.php">Đăng ký</a>
            <a class="btn" href="/login.php">Đăng nhập</a>
        </p>
    </div>

<?php else: ?>

    <div class="card" style="max-width: 720px; margin: 2rem auto;">
        <h1>Xin chào, <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></h1>

        <?php if (!empty($missingFields)): ?>
            <div style="border-left: 4px solid #d97706; padding: 0.5rem 1rem; margin: 1rem 0;">
                <p>
                    <strong>Hồ sơ của bạn chưa đầy đủ.</strong>
                    Còn thiếu: <?= htmlspecialchars(implode(', ', $missingFields)) ?>.
                    Cần đủ thông tin này để tính TDEE/Macro và gợi ý lịch tập.
                </p>
                <p><a class="btn" href="/profile.php">Hoàn thiện hồ sơ</a></p>
            </div>
        <?php else: ?>
            <p style="color: #15803d;">Hồ sơ của bạn đã đầy đủ.</p>
        <?php endif; ?>

        <h2>Bắt đầu</h2>
        <ul>
            <li><a href="/exercises.php">Bài tập &amp; Video</a>: xem và tìm bài tập, xem video hướng dẫn</li>
            <li><a href="/profile.php">Hồ sơ</a>: cập nhật chỉ số cơ thể, mục tiêu</li>
        </ul>
        <p>Các chức năng Lịch tập, TDEE/Macro và Tiến trình sẽ xuất hiện ở đây khi hoàn thành.</p>
    </div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>