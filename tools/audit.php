<?php
/**
 * tools/audit.php — quét nhanh code PHP tìm lỗi bảo mật PHỔ BIẾN.
 *
 * Chạy (ở thư mục gốc project):   php tools/audit.php public
 *
 * Đây là công cụ TÌM CHỖ ĐÁNG NGHI bằng cách so khớp mẫu chữ (regex), không phải
 * bảo đảm an toàn. Báo "không có gì" không có nghĩa là code an toàn, và báo
 * "đáng nghi" chưa chắc là lỗi. Mỗi dòng báo ra: mở file, đọc, tự quyết định.
 * Người review vẫn phải đọc code bằng mắt (xem SECURITY_CHECKLIST.md).
 */

$root = $argv[1] ?? 'public';
if (!is_dir($root)) {
    fwrite(STDERR, "Không thấy thư mục '$root'. Chạy ở thư mục gốc project: php tools/audit.php public\n");
    exit(2);
}

// Trang công khai, không cần đăng nhập
$publicPages = ['index.php', 'login.php', 'register.php', 'logout.php', 'forgot-password.php', 'reset-password.php',
    'exercises.php', 'exercise-detail.php', 'articles.php', 'article.php'];   // 4 trang cuối: khách xem được (A-45), chỉ đọc

$findings = []; // [mức, file, số dòng, mô tả]

function add(array &$f, string $level, string $file, int $line, string $msg): void
{
    $f[] = [$level, $file, $line, $msg];
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path  = str_replace('\\', '/', $file->getPathname());
    $base  = basename($path);
    // includes/ (hàm dùng chung) và content/ (file dữ liệu bài viết, chỉ return mảng) không phải trang người dùng mở trực tiếp
    $inInc = strpos($path, '/includes/') !== false || strpos($path, '/content/') !== false;
    $code  = file_get_contents($path);
    $lines = explode("\n", $code);

    // ----- 1. Trang chưa chặn người chưa đăng nhập -----
    // API trả JSON không dùng requireLogin() (hàm đó chuyển hướng sang trang HTML) mà tự trả 401:
    // chấp nhận apiBoot() hoặc đoạn kiểm tra $_SESSION['user_id'] / isLoggedIn() có mã 401 ngay sau đó.
    $apiGuard = strpos($path, '/api/') !== false
        && preg_match('/apiBoot\s*\(|(empty\(\s*\$_SESSION\[.user_id.\]\s*\)|!\s*isLoggedIn\s*\(\s*\))\s*\)\s*\{[\s\S]{0,200}?401/', $code);
    if (!$inInc && !in_array($base, $publicPages, true) && !$apiGuard
        && !preg_match('/requireLogin\s*\(|requireAdmin\s*\(|requireCoach\s*\(|requireExpert\s*\(/', $code)) {
        add($findings, 'CAO', $path, 1, 'Không thấy requireLogin()/requireAdmin()/requireCoach()/requireExpert(): người chưa đăng nhập có vào được không?');
    }

    // ----- 2. Trang admin phải có requireAdmin -----
    if (strpos($path, '/admin/') !== false && !preg_match('/requireAdmin\s*\(/', $code)) {
        add($findings, 'CAO', $path, 1, 'Nằm trong /admin/ nhưng không có requireAdmin().');
    }

    // ----- 2b. Trang khu HLV phải có requireCoach (đọc lại vai trò trong DB) -----
    if (strpos($path, '/coach/') !== false && !preg_match('/requireCoach\s*\(/', $code)) {
        add($findings, 'CAO', $path, 1, 'Nằm trong /coach/ nhưng không có requireCoach().');
    }
    // ----- 2b'. Trang khu chuyên gia sức khỏe phải có requireExpert (A-55) -----
    if (strpos($path, '/expert/') !== false && !preg_match('/requireExpert\s*\(/', $code)) {
        add($findings, 'CAO', $path, 1, 'Nằm trong /expert/ nhưng không có requireExpert().');
    }
    // ----- 2c. Trang HLV xem dữ liệu một học viên phải kiểm tra quan hệ ACTIVE -----
    if (strpos($path, '/coach/') !== false && preg_match('/\$_GET\[.id.\]/', $code) && !preg_match('/requireCoachClient\s*\(|coachActiveRelationId\s*\(|canCoachAccessClient\s*\(/', $code)) {
        add($findings, 'CAO', $path, 1, 'Trang HLV đọc ?id= nhưng không thấy requireCoachClient()/canCoachAccessClient(): HLV đổi id có xem được học viên người khác không?');
    }

    // ----- 3. Form POST mà không có mã CSRF -----
    if (!$inInc && stripos($code, 'method="post"') !== false && !preg_match('/csrfField\s*\(|csrf_token/', $code)) {
        add($findings, 'TRUNG BÌNH', $path, 1, 'Có form POST nhưng không thấy mã CSRF (csrfField/verifyCsrf).');
    }

    foreach ($lines as $i => $line) {
        $n = $i + 1;
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '//') === 0 || strpos($trim, '*') === 0 || strpos($trim, '#') === 0) {
            continue; // bỏ dòng chú thích
        }

        // Dòng đã được người review xác nhận an toàn: thêm chữ "audit:ok" vào dòng đó để công cụ bỏ qua
        if (strpos($line, 'audit:ok') !== false) {
            continue;
        }

        // ----- 4. SQL ghép chuỗi (nguy cơ SQL Injection) -----
        if (preg_match('/->\s*(query|exec)\s*\(.*\$/', $line)) {
            add($findings, 'CAO', $path, $n, 'query()/exec() có biến bên trong: nếu biến lấy từ người dùng thì bị SQL Injection. Dùng prepare() + execute().');
        }
        if (preg_match('/prepare\s*\(\s*["\'][^"\']*(\$|["\']\s*\.)/', $line)) {
            add($findings, 'CAO', $path, $n, 'prepare() mà câu SQL có chèn biến/ghép chuỗi: giá trị phải đi qua dấu ? chứ không ghép vào câu SQL.');
        }
        if (preg_match('/(SELECT|INSERT|UPDATE|DELETE)\b[^;]*\$_(GET|POST|REQUEST|COOKIE)/i', $line)) {
            add($findings, 'CAO', $path, $n, 'Câu SQL chứa trực tiếp $_GET/$_POST.');
        }

        // ----- 5. In dữ liệu ra HTML mà không escape (nguy cơ XSS) -----
        if (preg_match('/<\?=\s*\$/', $line) || preg_match('/\becho\s+\$/', $line)) {
            // Bỏ qua biểu thức chọn giữa hai chữ cố định (điều kiện ? 'A' : 'B') vì không chứa dữ liệu người dùng
            $literalTernary = preg_match('/<\?=[^?]*\?\s*[\'"][^\'"]*[\'"]\s*:\s*[\'"][^\'"]*[\'"]\s*\?>/', $line);
            if (!$literalTernary && !preg_match('/htmlspecialchars|\(int\)|intval|number_format|coachNum\s*\(|count\s*\(|csrfField|json_encode/', $line)) {
                add($findings, 'TRUNG BÌNH', $path, $n, 'In biến ra HTML không qua htmlspecialchars(): nếu dữ liệu do người dùng nhập thì bị XSS.');
            }
        }
        if (preg_match('/\$_(GET|POST|REQUEST)\b[^;]*\b(echo|print)\b|\b(echo|print)\b[^;]*\$_(GET|POST|REQUEST)/', $line)) {
            add($findings, 'CAO', $path, $n, 'In thẳng $_GET/$_POST ra trang.');
        }

        // ----- 6. Tin id người dùng từ form/URL -----
        if (preg_match('/\$_(GET|POST|REQUEST)\s*\[\s*[\'"]user_id[\'"]\s*\]/', $line)) {
            add($findings, 'CAO', $path, $n, 'Lấy user_id từ form/URL: phải lấy từ $_SESSION, nếu không ai cũng giả được người khác.');
        }

        // ----- 7. Băm mật khẩu yếu -----
        if (preg_match('/\b(md5|sha1)\s*\(/i', $line)) {
            add($findings, 'CAO', $path, $n, 'md5()/sha1() không dùng để băm mật khẩu. Dùng password_hash()/password_verify().');
        }

        // ----- 8. Include file theo dữ liệu người dùng -----
        if (preg_match('/\b(include|require)(_once)?\b[^;]*\$_(GET|POST|REQUEST)/', $line)) {
            add($findings, 'CAO', $path, $n, 'include/require theo giá trị người dùng gửi lên (lỗi LFI).');
        }

        // ----- 9. Chuyển hướng theo dữ liệu người dùng -----
        if (preg_match('/header\s*\(\s*[\'"]Location:[^;]*\$_(GET|POST|REQUEST)/i', $line)) {
            add($findings, 'TRUNG BÌNH', $path, $n, 'header(Location) theo giá trị người dùng (open redirect).');
        }

        // ----- 10. Code gỡ lỗi còn sót -----
        if (preg_match('/\b(var_dump|print_r|phpinfo|var_export)\s*\(|display_errors[\'"]?\s*[,=]\s*[\'"]?(1|on|true)/i', $line)) {
            add($findings, 'THẤP', $path, $n, 'Code gỡ lỗi (var_dump/print_r/phpinfo/display_errors) còn sót: lộ thông tin khi chạy thật.');
        }

        // ----- 11. Mật khẩu/khóa viết cứng trong code -----
        if (preg_match('/(password|passwd|secret|api[_-]?key)\s*=\s*[\'"][^\'"]{4,}[\'"]/i', $line) && $base !== 'config.example.php') {
            add($findings, 'TRUNG BÌNH', $path, $n, 'Có vẻ viết cứng mật khẩu/khóa trong code: để vào config.php (không commit).');
        }

        // ----- 12. Hàm nguy hiểm -----
        if (preg_match('/\b(eval|exec|shell_exec|system|passthru|unserialize)\s*\(/', $line) && !preg_match('/->\s*exec\s*\(/', $line)) {
            add($findings, 'CAO', $path, $n, 'Hàm nguy hiểm (eval/exec/system/unserialize): hầu như không cần trong dự án này.');
        }
    }
}

// ----- 13. config.php phải nằm trong .gitignore -----
$gitignore = dirname($root) . '/.gitignore';
if (!is_file($gitignore)) {
    $gitignore = '.gitignore';
}
if (is_file($gitignore) && !preg_match('/^\/?(public\/)?(includes\/)?config\.php\s*$/m', file_get_contents($gitignore))) {
    add($findings, 'CAO', $gitignore, 1, 'Không thấy config.php trong .gitignore: nguy cơ đẩy mật khẩu DB lên GitHub.');
}

// ----- In kết quả -----
$order = ['CAO' => 0, 'TRUNG BÌNH' => 1, 'THẤP' => 2];
usort($findings, function ($a, $b) use ($order) {
    return [$order[$a[0]], $a[1], $a[2]] <=> [$order[$b[0]], $b[1], $b[2]];
});

if (empty($findings)) {
    echo "Không phát hiện mẫu đáng nghi nào. (Vẫn phải review bằng mắt, xem SECURITY_CHECKLIST.md)\n";
    exit(0);
}

$counts = ['CAO' => 0, 'TRUNG BÌNH' => 0, 'THẤP' => 0];
foreach ($findings as [$level, $file, $line, $msg]) {
    $counts[$level]++;
    echo "[$level] $file:$line\n    $msg\n";
}
echo "\nTổng: {$counts['CAO']} mức CAO, {$counts['TRUNG BÌNH']} mức TRUNG BÌNH, {$counts['THẤP']} mức THẤP.\n";
echo "Nhắc lại: đây là danh sách chỗ ĐÁNG NGHI, hãy mở từng chỗ ra đọc rồi mới kết luận.\n";
exit($counts['CAO'] > 0 ? 1 : 0);
