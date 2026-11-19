<?php
// test-health.php — kiểm tra các hàm thuần của trang Sức khỏe hệ thống và sao lưu (includes/health.php).
// Chạy ở gốc repo: php test-health.php   (không cần DB)

require __DIR__ . '/public/includes/health.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}

$sql = "CREATE TABLE IF NOT EXISTS users (id INT);\ncreate table `Exercises` (id INT);\n-- CREATE TABLE trong comment vẫn bị đếm nên chỉ dùng cho file schema thật\nCREATE TABLE IF NOT EXISTS users (id INT);";
check('Đọc tên bảng: có IF NOT EXISTS, dấu `, chữ hoa, bỏ trùng', healthTablesInSql($sql) === ['users', 'exercises', 'trong']);
check('Bảng thật trong schema.sql đọc đủ (có users, notifications, coach_reviews)',
    count(array_intersect(['users', 'notifications', 'coach_reviews', 'meal_logs'], healthTablesInSql((string)file_get_contents(__DIR__ . '/sql/schema.sql')))) === 4);
$src = healthTableSources(['notifications.sql' => 'CREATE TABLE IF NOT EXISTS notifications (id INT);', 'meal_logs.sql' => 'CREATE TABLE user_foods (id INT); CREATE TABLE meal_logs (id INT);']);
check('Biết bảng nào nằm ở file bổ sung nào', $src === ['notifications' => 'notifications.sql', 'user_foods' => 'meal_logs.sql', 'meal_logs' => 'meal_logs.sql']);

check('Trạng thái chung: có fail là fail', healthOverall([['status' => 'ok'], ['status' => 'warn'], ['status' => 'fail']]) === 'fail');
check('Trạng thái chung: chỉ có warn là warn', healthOverall([['status' => 'ok'], ['status' => 'warn']]) === 'warn');
check('Trạng thái chung: toàn ok', healthOverall([['status' => 'ok']]) === 'ok');

$q = fn(string $s) => "'" . str_replace("'", "''", $s) . "'";   // giả lập PDO::quote
$ins = healthInsertSql('users', ['id' => 5, 'name' => "O'Neil", 'avatar' => null, 'w' => 72.5], $q);
check('Câu INSERT sao lưu: số giữ nguyên, NULL, chuỗi qua hàm quote', $ins === "INSERT INTO `users` (`id`, `name`, `avatar`, `w`) VALUES (5, 'O''Neil', NULL, 72.5);");
check('Tên cột/bảng có dấu ` bị bỏ dấu đó', str_contains(healthInsertSql('a`b', ['c`d' => 'x'], $q), 'INSERT INTO `ab` (`cd`)'));

echo $fails === 0 ? "TẤT CẢ ĐỀU PASS\n" : "CÓ $fails TEST SAI\n";
exit($fails === 0 ? 0 : 1);
