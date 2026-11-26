<?php
/**
 * tools/test-coach-http.php — chạy thử phân hệ HLV từ đầu đến cuối qua HTTP, như người dùng thật bấm trên trình duyệt
 * (đăng nhập, lấy mã CSRF trong trang, gửi form, gọi API), kèm các phép thử phá quyền.
 *
 * Chạy ở gốc repo (MySQL của XAMPP phải đang chạy, config.php đúng):
 *     C:\xampp\php\php.exe tools\test-coach-http.php
 *
 * Công cụ TỰ LÀM HẾT và KHÔNG đụng database gymsmart thật:
 *   1. Tạo database thử gymsmart_test_hlv (xóa bản cũ nếu có), import schema.sql + 2 file seed bài tập + coach_features.sql + coach_extras.sql
 *   2. Chạy tools/seed-coach-demo.php vào database thử
 *   3. Bật máy chủ PHP thử ở 127.0.0.1:8099 trỏ vào database thử (qua tools/test-db-prepend.php)
 *   4. Chạy các kịch bản, in [OK]/[FAIL], cuối cùng in "TẤT CẢ ĐỀU PASS" hoặc số bước lỗi
 *   5. Tắt máy chủ, xóa database thử (thêm --keep để giữ lại xem bằng phpMyAdmin), in lỗi PHP nếu có
 * Không gọi Gemini: các phép thử chatbot chỉ kiểm tra phần chặn quyền, xảy ra trước khi gọi AI.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Chỉ chạy từ dòng lệnh.');
}
if (!extension_loaded('curl')) {
    exit("Cần bật extension curl cho PHP dòng lệnh.\n");
}

const T_DB   = 'gymsmart_test_hlv';
const T_PORT = 8099;
const T_BASE = 'http://127.0.0.1:' . T_PORT;
$root = dirname(__DIR__);
$keep = in_array('--keep', $argv, true);
require $root . '/config.php';

// ---------------------------------------------------------------------
// Tiện ích
// ---------------------------------------------------------------------
$GLOBALS['fails'] = 0;
$GLOBALS['steps'] = 0;
function check(string $name, bool $ok, string $extra = ''): void
{
    $GLOBALS['steps']++;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . ($ok || $extra === '' ? '' : '  → ' . $extra) . PHP_EOL;
    if (!$ok) $GLOBALS['fails']++;
}
function section(string $title): void
{
    echo PHP_EOL . '== ' . $title . ' ==' . PHP_EOL;
}

function serverPdo(): PDO
{
    return new PDO('mysql:host=' . DB_HOST . (defined('DB_PORT') ? ';port=' . DB_PORT : '') . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $pdo = serverPdo();
        $pdo->exec('USE ' . T_DB);
    }
    return $pdo;
}
function q(string $sql, array $p = [])
{
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s->fetchColumn();
}
function uid(string $email): int
{
    return (int)q('SELECT id FROM users WHERE email = ?', [$email]);
}

/** Import 1 file .sql: bỏ CREATE DATABASE/USE và comment, chạy TỪNG câu để lỗi nào cũng hiện ra. */
function importSql(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    $sql = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
}

/** Chạy lệnh PHP dòng lệnh với database thử. */
function runPhp(string $script): string
{
    global $root;
    $cmd = [PHP_BINARY, '-d', 'auto_prepend_file=' . $root . '/tools/test-db-prepend.php', '-d', 'display_errors=0', $script];
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, array_merge(getenv(), ['GS_TEST_DB' => T_DB]));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    proc_close($p);
    return $out;
}
function reseed(): void
{
    global $root;
    $out = runPhp($root . '/tools/seed-coach-demo.php');
    if (strpos($out, 'Đã tạo DỮ LIỆU THỬ') === false) {
        throw new RuntimeException("Seed dữ liệu thử lỗi:\n" . $out);
    }
}

/** Trình duyệt giả: giữ cookie riêng, đọc mã CSRF trong trang. */
class Web
{
    private string $jar;
    public int $code = 0;
    public string $body = '';
    public ?string $location = null;
    public string $type = '';

    public function __construct(string $name)
    {
        $this->jar = sys_get_temp_dir() . '/gs-test-' . $name . '-' . getmypid() . '.txt';
        @unlink($this->jar);
    }
    public function __destruct()
    {
        @unlink($this->jar);
    }
    public function req(string $method, string $path, $data = null, array $headers = []): self
    {
        $ch = curl_init(T_BASE . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);   // mảng có CURLFile = multipart; chuỗi = gửi nguyên
        }
        $raw = (string)curl_exec($ch);
        $this->code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->location = preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $hs), $m) ? $m[1] : null;
        $this->body = substr($raw, $hs);
        curl_close($ch);
        return $this;
    }
    public function get(string $path): self { return $this->req('GET', $path); }
    public function post(string $path, array $data): self { return $this->req('POST', $path, http_build_query($data)); }
    public function upload(string $path, array $data): self { return $this->req('POST', $path, $data); }
    public function follow(): self
    {
        for ($i = 0; $i < 5 && $this->location; $i++) {
            $this->get($this->location);
        }
        return $this;
    }
    public function postFollow(string $path, array $data): self { return $this->post($path, $data)->follow(); }
    public function csrf(): string
    {
        return preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $this->body, $m) ? $m[1]
            : (preg_match('/data-csrf="([a-f0-9]+)"/', $this->body, $m) ? $m[1] : '');
    }
    public function login(string $email): self
    {
        $this->get('/login.php');
        return $this->post('/login.php', ['email' => $email, 'password' => 'Test@12345', 'csrf_token' => $this->csrf()]);
    }
    public function has(string $t): bool { return strpos($this->body, $t) !== false; }
    public function json(): array { $j = json_decode($this->body, true); return is_array($j) ? $j : []; }
}

// ---------------------------------------------------------------------
// 1-3. Dựng database thử và máy chủ thử
// ---------------------------------------------------------------------
echo "Dựng database thử " . T_DB . " (database gymsmart thật không bị đụng tới)...\n";
$srv = serverPdo();
$srv->exec('DROP DATABASE IF EXISTS ' . T_DB);
$srv->exec('CREATE DATABASE ' . T_DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$srv->exec('USE ' . T_DB);
foreach (['schema.sql', 'seed_exercises_v2.sql', 'seed_exercise_media.sql', 'coach_features.sql', 'coach_extras.sql'] as $f) {
    importSql($srv, $root . '/sql/' . $f);
}
reseed();

$errLog = sys_get_temp_dir() . '/gs-test-php-errors-' . getmypid() . '.log';
@unlink($errLog);
$server = proc_open(
    [PHP_BINARY, '-d', 'auto_prepend_file=' . $root . '/tools/test-db-prepend.php', '-d', 'display_errors=0', '-d', 'log_errors=1',
     '-d', 'error_log=' . $errLog, '-S', '127.0.0.1:' . T_PORT, '-t', $root . '/public'],
    [1 => ['file', sys_get_temp_dir() . '/gs-test-server.out', 'w'], 2 => ['file', sys_get_temp_dir() . '/gs-test-server.out', 'a']],
    $pipes, $root, array_merge(getenv(), ['GS_TEST_DB' => T_DB]), ['bypass_shell' => true]
);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', T_PORT); $i++) {
    usleep(100000);
}
echo "Máy chủ thử: " . T_BASE . "\n";

$cleanup = function () use ($server, $srv, $keep, $errLog) {
    proc_terminate($server);
    proc_close($server);
    if (!$keep) {
        $srv->exec('DROP DATABASE IF EXISTS ' . T_DB);
    }
    $errors = is_file($errLog) ? array_filter(file($errLog), fn($l) => strpos($l, 'already defined') === false) : [];
    @unlink($errLog);
    echo PHP_EOL . 'Lỗi PHP trong log máy chủ thử: ' . count($errors) . PHP_EOL;
    foreach (array_slice($errors, 0, 10) as $l) echo '  ' . $l;
    return count($errors);
};

try {
    require __DIR__ . '/test-coach-http-scenarios.php';
} catch (Throwable $e) {
    check('Kịch bản chạy hết không bị dừng giữa chừng', false, get_class($e) . ': ' . $e->getMessage());
}
$phpErrors = $cleanup();

echo PHP_EOL . $GLOBALS['steps'] . ' bước. ' . ($GLOBALS['fails'] === 0 && $phpErrors === 0 ? 'TẤT CẢ ĐỀU PASS' : 'CÓ ' . $GLOBALS['fails'] . ' BƯỚC FAIL, ' . $phpErrors . ' LỖI PHP') . PHP_EOL;
exit($GLOBALS['fails'] === 0 && $phpErrors === 0 ? 0 : 1);
