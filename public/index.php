<?php
/**
 * index.php — trang chủ.
 *
 * Ba trạng thái:
 *   - ADMIN:            chuyển sang /admin/dashboard.php (có trang chủ riêng).
 *   - Chưa đăng nhập:   trang giới thiệu (hero, tính năng, 3 bước, lời kêu gọi đăng ký).
 *   - Đã đăng nhập:     bảng điều khiển cá nhân: hôm nay tập gì, tuần này ra sao, dinh dưỡng, cân nặng.
 *
 * Vì sao nhắc hoàn thiện hồ sơ? TDEE/Macro (C) và lịch tập (B) đều cần đủ 7 thông tin hồ sơ.
 */

require_once __DIR__ . '/includes/auth.php'; // có session + isLoggedIn()
require_once __DIR__ . '/includes/db.php';   // có biến $pdo
require_once __DIR__ . '/includes/logo.php';
require_once __DIR__ . '/includes/functions.php'; // exerciseImageSrc()
require_once __DIR__ . '/includes/csrf.php';      // mã bảo vệ cho form ghi nhanh

$loggedIn = isLoggedIn();

// Quản trị viên có trang chủ riêng (tổng quan), không dùng trang chủ của người dùng
if ($loggedIn && ($_SESSION['role'] ?? '') === 'ADMIN') {
    header('Location: /admin/dashboard.php');
    exit;
}

/** Vẽ biểu tượng nét (SVG nhúng thẳng), màu theo chữ xung quanh (currentColor). */
function hmIcon(string $name, int $size = 24): string
{
    $paths = [
        'check'    => '<path d="M5 13l4 4L19 7"/>',
        'play'     => '<circle cx="12" cy="12" r="9"/><path d="M10 8.5l5.5 3.5-5.5 3.5z"/>',
        'dumbbell' => '<path d="M6.5 6.5v11M17.5 6.5v11M3.5 9v6M20.5 9v6M6.5 12h11"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'flame'    => '<path d="M12 3c1 3.5 5 5.5 5 10a5 5 0 01-10 0c0-2 1-3.5 2-4.5.3 1.2 1 2 2 2.3C10.5 8 11 5.5 12 3z"/>',
        'chart'    => '<path d="M4 19V5M4 19h16M8 15l3.5-4 3 2.5L19 8"/>',
        'user'     => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.6 3.7-5.5 7-5.5s6.2 1.9 7 5.5"/>',
        'bolt'     => '<path d="M13 3L5 13.5h6L10 21l8-10.5h-6z"/>',
        'book'     => '<path d="M5 4.5h10a3 3 0 013 3V20H8a3 3 0 01-3-3V4.5zM5 17a3 3 0 013-3h10"/>',
        'target'   => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>',
        'meal'     => '<path d="M7 3v7a2.5 2.5 0 005 0V3M9.5 3v18M17 3c-1.7 1-2.5 3.2-2.5 6 0 2 1 3.5 2.5 3.5V21"/>',
        'chat'     => '<path d="M4 5.5h16v11H10l-4.5 3.5V16.5H4z"/><path d="M8.5 11h.01M12 11h.01M15.5 11h.01"/>',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

// 7 tính năng ở trang khách: [biểu tượng, tiêu đề, mô tả]
$guestFeatures = [
    ['calendar', 'Lịch tập cá nhân hóa', 'Gợi ý số buổi, bài tập và số hiệp theo trình độ, mục tiêu và số ngày bạn có thể tập.'],
    ['play',     'Thư viện bài tập có ảnh, video', 'Xem kỹ thuật qua video, đọc hướng dẫn từng bước, lọc theo nhóm cơ, loại và độ khó.'],
    ['flame',    'Tính TDEE và macro', 'Tính nhu cầu năng lượng và lượng đạm, tinh bột, béo theo chỉ số và mục tiêu của bạn.'],
    ['meal',     'Lịch ăn gợi ý', 'Gợi ý chia bữa trong ngày theo mục tiêu dinh dưỡng và kiểu ăn bạn chọn.'],
    ['chart',    'Nhật ký và biểu đồ', 'Ghi lại buổi tập, cân nặng, số đo và xem biểu đồ thay đổi theo thời gian.'],
    ['book',     'Bài viết có dẫn nghiên cứu', 'Kiến thức về dinh dưỡng, sức khỏe và tập luyện, có trích nguồn để bạn tự kiểm tra.'],
    ['chat',     'Chatbot AI', 'Hỏi nhanh về bài tập và tập luyện, có thể dựa trên hồ sơ của bạn nếu bạn cho phép.'],
];

$muscleLabels = [
    'CHEST' => 'Ngực', 'BACK' => 'Lưng', 'SHOULDERS' => 'Vai', 'LEGS' => 'Chân',
    'ARMS' => 'Tay', 'ABS' => 'Bụng', 'FULL_BODY' => 'Toàn thân',
];
$levelLabels = ['BEGINNER' => 'Dễ', 'INTERMEDIATE' => 'Trung bình', 'ADVANCED' => 'Khó'];

$missingFields = [];  // tên các mục hồ sơ còn thiếu
$profileTotal  = 0;
$row           = null;

if ($loggedIn) {
    // Tên cột trong DB => chữ hiển thị khi báo thiếu
    $profileFields = [
        'age'              => 'Tuổi',
        'height_cm'        => 'Chiều cao',
        'weight_kg'        => 'Cân nặng',
        'gender'           => 'Giới tính',
        'activity_level'   => 'Mức độ vận động',
        'experience_level' => 'Trình độ tập luyện',
        'goal'             => 'Mục tiêu',
    ];
    $profileTotal = count($profileFields);

    // Lấy id từ SESSION (không lấy từ URL/form) để không xem được hồ sơ người khác
    // Lấy luôn CURDATE() trong truy vấn này để "hôm nay" khớp với ngày nhật ký được ghi (giờ của PHP và MySQL có thể lệch)
    $stmt = $pdo->prepare(
        "SELECT age, height_cm, weight_kg, gender, activity_level, experience_level, goal, CURDATE() AS today
         FROM users WHERE id = ?"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        // Session còn nhưng user đã bị xóa khỏi DB → đăng xuất cho an toàn
        $_SESSION = [];
        session_destroy();
        header('Location: /login.php');
        exit;
    }

    foreach ($profileFields as $column => $label) {
        // NULL hoặc rỗng = chưa nhập. Dùng === null thay vì empty() vì giá trị 0 không phải "thiếu".
        if ($row[$column] === null || $row[$column] === '') {
            $missingFields[] = $label;
        }
    }
}

$doneCount = $profileTotal - count($missingFields);
$firstName = '';
if ($loggedIn) {
    $parts = preg_split('/\s+/u', trim((string)($_SESSION['full_name'] ?? '')));
    $firstName = $parts ? end($parts) : '';
}

// Bài nền tảng (mustRead) dùng cho cả khách và người đã đăng nhập (mục "Đọc trước khi tập")
require_once __DIR__ . '/includes/articles.php';
$allArticles = loadArticles();
$mustReadArticles = array_filter($allArticles, fn($a) => $a['mustRead']);

// Dữ liệu bảng điều khiển: user_id lấy từ session
$dash = null;
if ($loggedIn) {
    require_once __DIR__ . '/includes/dashboard.php';
    $dash = loadDashboard($pdo, (int)$_SESSION['user_id'], $row['today'], $row['goal'], count($missingFields), $mustReadArticles);
}

// Số liệu thật và nội dung xem trước cho trang khách. Không có số viết tay: thiếu dữ liệu thì trang ẩn câu đó.
$guestStats = ['exercises' => 0, 'muscleGroups' => 0, 'sourcedArticles' => 0];
$previewExercises = [];
$articleTeasers = [];
if (!$loggedIn) {
    $guestStats = guestExerciseStats($pdo);
    $previewExercises = guestPreviewExercises($pdo);
    // Chỉ đếm bài có ghi nguồn thật (một số bài không có mục nguồn)
    $guestStats['sourcedArticles'] = count(array_filter($allArticles, fn($a) => !empty($a['sources'])));
    // Mục có tiêu đề "Đọc trước khi tập" nên đặt các bài nền tảng lên trước, còn chỗ mới thêm bài khác
    $articleTeasers = array_slice($mustReadArticles, 0, 3, true);
    foreach (['bao-nhieu-buoc-moi-ngay', 'protein-bao-nhieu-la-du', 'ta-nang-hay-ta-nhe'] as $s) {
        if (count($articleTeasers) >= 3) { break; }
        if (isset($allArticles[$s])) {
            $articleTeasers[$s] = $allArticles[$s];
        }
    }
    if (count($articleTeasers) < 3) { // thiếu bài mong muốn thì lấy bài đầu danh sách cho đủ 3
        foreach ($allArticles as $s => $a) {
            if (count($articleTeasers) >= 3) { break; }
            $articleTeasers[$s] = $articleTeasers[$s] ?? $a;
        }
    }
}

$pageTitle = 'Trang chủ';
$pageCss = [$loggedIn ? '/css/home-user.css' : '/css/home-guest.css'];
include __DIR__ . '/includes/header.php';
?>
<?php if (!$loggedIn): ?>

<!-- ================= KHÁCH CHƯA ĐĂNG NHẬP ================= -->
<?php include __DIR__ . '/includes/home-guest.php'; ?>

<?php else: ?>

<!-- ================= NGƯỜI DÙNG ĐÃ ĐĂNG NHẬP ================= -->
<?php include __DIR__ . '/includes/home-user.php'; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
