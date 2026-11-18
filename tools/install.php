<?php
/**
 * tools/install.php — dựng hoặc nâng cấp database GymSmart bằng MỘT lệnh (thay cho việc import từng file trong phpMyAdmin).
 *
 * Chạy ở gốc repo (MySQL của XAMPP phải đang chạy, đã có config.php):
 *   php tools/install.php                  Tạo database mới (tên DB_NAME trong config.php): schema.sql + 40 bài tập + ảnh/video.
 *                                          Database đã có bảng thì DỪNG, không làm gì (tránh ghi đè dữ liệu thật).
 *   php tools/install.php --upgrade        Database CŨ: chạy lại mọi file bổ sung trong sql/ (đều chạy lại được, không mất dữ liệu).
 *   php tools/install.php --demo           Như lệnh đầu, rồi nạp thêm tài khoản thử (tools/seed-*.php, email đuôi @gymsmart.test).
 *   php tools/install.php --db=gymsmart_demo --fresh --demo
 *                                          Làm việc trên database khác (ví dụ để chụp ảnh, thử nghiệm). --fresh XÓA database đó rồi tạo lại.
 *
 * An toàn: --fresh trên đúng database trong config.php phải kèm thêm --yes, vì sẽ mất toàn bộ dữ liệu.
 * File SQL được tách thành từng câu (theo dấu ";" cuối dòng) và chạy lần lượt, câu nào lỗi thì dừng và in rõ file + lỗi.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy từ dòng lệnh.');
}

$root = dirname(__DIR__);
require $root . '/config.php';

// Các file bổ sung (migration), theo đúng thứ tự phụ thuộc. Thêm file mới vào CUỐI danh sách.
const MIGRATIONS = [
    'admin_features.sql', 'article_reads.sql', 'body_metrics.sql', 'body_metrics_fix.sql', 'login_attempts.sql',
    'nutrition_preferences.sql', 'password_resets.sql', 'split_types.sql', 'users_avatar.sql',
    'coach_features.sql', 'coach_extras.sql', 'notifications.sql', 'weekly_summaries.sql', 'exercise_equipment.sql', 'coach_templates.sql',
];
const SEEDS = ['seed_exercises_v2.sql', 'seed_exercise_media.sql', 'exercise_equipment.sql'];   // dụng cụ phải điền SAU khi nạp bài tập
const DEMO_SCRIPTS = ['seed-coach-demo.php', 'seed-demo.php', 'seed-index-states.php'];

$args = array_slice($argv, 1);
$has = fn(string $flag): bool => in_array($flag, $args, true);
$dbName = DB_NAME;
foreach ($args as $a) {
    if (str_starts_with($a, '--db=')) {
        $dbName = substr($a, 5);
    }
}
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName)) {
    exit("Tên database không hợp lệ: chỉ gồm chữ, số, dấu gạch dưới.\n");
}
if ($has('--fresh') && $dbName === DB_NAME && !$has('--yes')) {
    exit("--fresh sẽ XÓA database '$dbName' trong config.php. Nếu chắc chắn, chạy lại kèm --yes.\n");
}

$port = defined('DB_PORT') ? DB_PORT : 3306;
$pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . $port . ';charset=utf8mb4', DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

/** Chạy một file .sql: bỏ dòng CREATE DATABASE/USE và chú thích, tách theo ";" cuối dòng. Trả số câu đã chạy. */
function runSqlFile(PDO $pdo, string $path): int
{
    $sql = str_replace("\r\n", "\n", (string)file_get_contents($path));
    $sql = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $n = 0;
    foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
        if (trim($stmt) === '') {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            fwrite(STDERR, "\nLỖI ở " . basename($path) . ': ' . $e->getMessage() . "\nCâu lệnh: " . mb_substr(trim($stmt), 0, 200) . "\n");
            exit(1);
        }
        $n++;
    }
    return $n;
}

$exists = (bool)$pdo->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $pdo->quote($dbName))->fetchColumn();
$tableCount = 0;
if ($exists) {
    $tableCount = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ' . $pdo->quote($dbName))->fetchColumn();
}

if ($has('--upgrade')) {
    if (!$exists) {
        exit("Chưa có database '$dbName'. Chạy 'php tools/install.php' để tạo mới.\n");
    }
    $pdo->exec("USE `$dbName`");
    echo "Nâng cấp database '$dbName' (chạy lại các file bổ sung, không xóa dữ liệu):\n";
    foreach (MIGRATIONS as $f) {
        echo sprintf("  %-28s %d câu\n", $f, runSqlFile($pdo, "$root/sql/$f"));
    }
    exit("Xong.\n");
}

if ($has('--fresh') && $exists) {
    $pdo->exec("DROP DATABASE `$dbName`");
    echo "Đã xóa database '$dbName'.\n";
    $exists = false;
    $tableCount = 0;
}
if ($tableCount > 0) {
    exit("Database '$dbName' đã có $tableCount bảng nên không tạo lại.\n"
        . "  Nâng cấp database cũ: php tools/install.php --upgrade\n"
        . "  Tạo lại từ đầu (MẤT dữ liệu): php tools/install.php --fresh" . ($dbName === DB_NAME ? ' --yes' : " --db=$dbName") . "\n");
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$dbName`");
echo "Tạo database '$dbName':\n";
foreach (array_merge(['schema.sql'], SEEDS) as $f) {
    echo sprintf("  %-28s %d câu\n", $f, runSqlFile($pdo, "$root/sql/$f"));
}

if ($has('--demo')) {
    echo "Nạp tài khoản thử (dữ liệu THỬ, email đuôi @gymsmart.test):\n";
    foreach (DEMO_SCRIPTS as $script) {
        // Chạy script con với biến môi trường GYMSMART_DB để db.php trỏ đúng database vừa tạo
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/tools/$script");
        $env = getenv();
        $env['GYMSMART_DB'] = $dbName;
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $code = proc_close($proc);
        echo "  -- $script\n" . preg_replace('/^/m', '     ', trim($out)) . "\n";
        if ($code !== 0) {
            exit("Script $script lỗi (mã $code).\n");
        }
    }
}
echo "Xong. Mở trang bằng: php -S localhost:8000 -t public\n";
