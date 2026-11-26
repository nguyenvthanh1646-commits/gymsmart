<?php
/**
 * health.php — kiểm tra sức khỏe hệ thống và sao lưu database (G2, A-51). Dùng ở admin/health.php, admin/backup.php.
 *
 * Mỗi mục kiểm tra trả ['group','label','status' => ok|warn|fail,'detail']. KHÔNG bao giờ trả giá trị bí mật
 * (khóa Gemini, mật khẩu DB): chỉ báo "đã cấu hình" / "chưa cấu hình" và độ dài.
 * Các hàm thuần (đọc tên bảng từ schema.sql, tính trạng thái chung, viết câu INSERT) test ở test-health.php.
 */

/** Hàm thuần: tên các bảng được tạo trong một file SQL (CREATE TABLE [IF NOT EXISTS] tên). */
function healthTablesInSql(string $sql): array
{
    preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z_][a-z0-9_]*)`?/i', $sql, $m);
    return array_values(array_unique(array_map('strtolower', $m[1])));
}

/** Hàm thuần: bảng nào nằm trong file bổ sung nào (để báo "chạy sql/<file>" khi thiếu bảng). */
function healthTableSources(array $files): array
{
    $map = [];
    foreach ($files as $name => $sql) {
        foreach (healthTablesInSql($sql) as $t) {
            $map[$t] = $map[$t] ?? $name;
        }
    }
    return $map;
}

/** Hàm thuần: trạng thái chung = xấu nhất trong các mục (fail > warn > ok). */
function healthOverall(array $checks): string
{
    $st = array_column($checks, 'status');
    return in_array('fail', $st, true) ? 'fail' : (in_array('warn', $st, true) ? 'warn' : 'ok');
}

/**
 * Hàm thuần: một câu INSERT cho một dòng dữ liệu khi sao lưu. $quote là hàm escape chuỗi của PDO ($pdo->quote).
 * NULL giữ là NULL, số giữ nguyên, còn lại đi qua $quote (không tự ghép chuỗi).
 */
function healthInsertSql(string $table, array $row, callable $quote): string
{
    $cols = implode(', ', array_map(fn($c) => '`' . str_replace('`', '', $c) . '`', array_keys($row)));
    $vals = implode(', ', array_map(function ($v) use ($quote) {
        if ($v === null) {
            return 'NULL';
        }
        if (is_int($v) || is_float($v)) {
            return (string)$v;
        }
        return $quote((string)$v);
    }, array_values($row)));
    return 'INSERT INTO `' . str_replace('`', '', $table) . "` ($cols) VALUES ($vals);";
}

/** Chạy toàn bộ kiểm tra. $root là thư mục gốc repo. */
function healthChecks(PDO $pdo, string $root): array
{
    $c = [];
    $add = function (string $group, string $label, string $status, string $detail) use (&$c) {
        $c[] = ['group' => $group, 'label' => $label, 'status' => $status, 'detail' => $detail];
    };

    // ---- PHP ----
    $add('PHP', 'Phiên bản PHP', version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'fail', PHP_VERSION . ' (cần 8.0 trở lên)');
    foreach (['pdo_mysql' => 'Kết nối MySQL', 'mbstring' => 'Chữ tiếng Việt', 'curl' => 'Gọi Gemini (chatbot)', 'gd' => 'Xử lý ảnh đại diện', 'fileinfo' => 'Kiểm tra file tải lên'] as $ext => $why) {
        $add('PHP', "Extension $ext", extension_loaded($ext) ? 'ok' : ($ext === 'pdo_mysql' || $ext === 'mbstring' ? 'fail' : 'warn'),
            (extension_loaded($ext) ? 'Đã bật' : 'Chưa bật: mở php.ini, bỏ dấu ; trước extension=' . $ext . ' rồi khởi động lại Apache') . " ($why)");
    }

    // ---- Database ----
    $ver = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $add('Database', 'Kết nối database', 'ok', 'Đang nối tới ' . $ver);
    $existing = array_map('strtolower', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
    $files = [];
    foreach (glob($root . '/sql/*.sql') as $f) {
        if (!str_starts_with(basename($f), 'seed')) {
            $files[basename($f)] = (string)file_get_contents($f);
        }
    }
    $expected = healthTablesInSql($files['schema.sql'] ?? '');
    $sources = healthTableSources(array_diff_key($files, ['schema.sql' => 1]));
    $missing = array_values(array_diff($expected, $existing));
    if ($missing === []) {
        $add('Database', 'Bảng dữ liệu', 'ok', 'Đủ ' . count($expected) . ' bảng như sql/schema.sql');
    } else {
        $how = array_map(fn($t) => $t . (isset($sources[$t]) ? ' (chạy sql/' . $sources[$t] . ')' : ''), $missing);
        $add('Database', 'Bảng dữ liệu', 'warn', 'Thiếu ' . count($missing) . ' bảng: ' . implode(', ', $how) . '. Cách nhanh: php tools/install.php --upgrade');
    }
    $n = (int)$pdo->query('SELECT COUNT(*) FROM exercises')->fetchColumn();
    $add('Database', 'Dữ liệu bài tập', $n >= 40 ? 'ok' : 'warn', "$n bài tập" . ($n < 40 ? ' (dữ liệu mẫu có 40 bài: sql/seed_exercises_v2.sql)' : ''));

    // ---- Múi giờ ----
    $phpNow = date('Y-m-d H:i');
    $dbNow = substr((string)$pdo->query('SELECT NOW()')->fetchColumn(), 0, 16);
    $add('Cấu hình', 'Múi giờ PHP và MySQL', $phpNow === $dbNow ? 'ok' : 'warn',
        'PHP ' . date_default_timezone_get() . " ($phpNow), MySQL ($dbNow)" . ($phpNow === $dbNow ? '' : ': lệch giờ làm sai "hôm nay"'));

    // ---- Gemini (không hiện khóa) ----
    $key = defined('AI_API_KEY') ? (string)AI_API_KEY : '';
    $add('Cấu hình', 'Khóa Gemini (AI_API_KEY)', $key !== '' ? 'ok' : 'warn',
        $key !== '' ? 'Đã cấu hình (' . strlen($key) . ' ký tự, không hiển thị)' : 'Chưa cấu hình trong config.php: chatbot và tổng kết tuần sẽ không chạy');
    $add('Cấu hình', 'Model Gemini', 'ok', defined('AI_MODEL') && AI_MODEL !== '' ? (string)AI_MODEL : 'Dùng model mặc định trong includes/ai.php');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $add('Cấu hình', 'HTTPS', $https ? 'ok' : 'warn', $https ? 'Đang chạy HTTPS, cookie phiên có cờ Secure' : 'Đang chạy HTTP (bình thường khi chạy thử trên máy; khi đưa lên mạng nên dùng HTTPS)');

    // ---- Thư mục ghi file ----
    foreach (['public/uploads/avatars' => 'Ảnh đại diện', 'storage/certificates' => 'Chứng chỉ HLV (ngoài public/)'] as $dir => $why) {
        $p = $root . '/' . $dir;
        $ok = is_dir($p) && is_writable($p);
        $add('Thư mục', $dir, $ok ? 'ok' : 'fail', ($ok ? 'Ghi được' : (is_dir($p) ? 'Không có quyền ghi' : 'Chưa có thư mục')) . " ($why)");
    }
    $add('Thư mục', 'config.php nằm ngoài public/', is_file($root . '/config.php') && !is_file($root . '/public/config.php') ? 'ok' : 'fail',
        'Không mở được config.php bằng đường dẫn web');
    return $c;
}

/**
 * Ghi bản sao lưu toàn bộ database ra luồng $out (php://output khi tải về): cấu trúc từng bảng (SHOW CREATE TABLE) rồi dữ liệu.
 * Chỉ dùng cho MySQL/MariaDB. Tắt kiểm tra khóa ngoại trong file để nhập lại được theo bất kỳ thứ tự bảng nào.
 */
function healthWriteBackup(PDO $pdo, $out): int
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    fwrite($out, "-- Sao lưu GymSmart lúc " . date('Y-m-d H:i:s') . " (" . count($tables) . " bảng)\n-- CÓ chứa mã băm mật khẩu và dữ liệu cá nhân: giữ file này kín.\n");
    fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
    $quote = fn(string $s) => $pdo->quote($s);
    $rows = 0;
    foreach ($tables as $t) {
        $t = (string)$t;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) {
            continue;   // tên bảng lạ: bỏ qua, không ghép vào câu SQL
        }
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM); // audit:ok (tên bảng lấy từ SHOW TABLES và đã kiểm tra ký tự)
        fwrite($out, "DROP TABLE IF EXISTS `$t`;\n" . $create[1] . ";\n\n");
        $st = $pdo->query("SELECT * FROM `$t`"); // audit:ok
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            fwrite($out, healthInsertSql($t, $r, $quote) . "\n");
            $rows++;
        }
        fwrite($out, "\n");
    }
    fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
    return $rows;
}
