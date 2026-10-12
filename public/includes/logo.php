<?php
/**
 * logo.php — logo GymSmart dạng SVG chèn thẳng vào trang (không cần file ảnh,
 * không phụ thuộc đường dẫn, sắc nét ở mọi kích thước).
 *
 * Dùng:
 *   require_once __DIR__ . '/logo.php';
 *   echo gsLogoMark(40);                    // chỉ biểu tượng chữ G + tạ đòn
 *   echo gsLogo('dark');                    // biểu tượng + chữ GymSmart (nền sáng)
 *   echo gsLogo('light', true);             // dùng trên nền tối, có kèm slogan
 *
 * Bản file .svg để làm ảnh đại diện, favicon, slide... nằm trong thư mục brand/.
 */

if (!function_exists('gsLogoMark')) {
    /** Biểu tượng vuông bo góc: chữ G có thanh tạ làm nét ngang. $size tính bằng pixel. */
    function gsLogoMark(int $size = 36): string
    {
        $size = max(12, min(512, $size));
        return '<svg class="gs-mark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="' . $size . '" height="' . $size . '" role="img" aria-label="GymSmart">'
            . '<defs><linearGradient id="gsg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff8a3d"/><stop offset="1" stop-color="#ff4b0f"/></linearGradient></defs>'
            . '<rect width="64" height="64" rx="16" fill="url(#gsg)"/>'
            . '<path d="M42.5 20.5 A15.5 15.5 0 1 0 46 32" fill="none" stroke="#fff" stroke-width="5.5" stroke-linecap="round"/>'
            . '<rect x="30" y="30" width="18" height="4" rx="2" fill="#fff"/>'
            . '<rect x="44" y="22" width="5" height="20" rx="2.5" fill="#fff"/>'
            . '<rect x="50.5" y="26" width="4" height="12" rx="2" fill="#fff"/>'
            . '</svg>';
    }
}

if (!function_exists('gsLogo')) {
    /**
     * Logo ngang: biểu tượng + chữ. Chữ là HTML nên dùng đúng font của website.
     * $theme: 'dark' = chữ tối (nền sáng), 'light' = chữ trắng (nền tối).
     */
    function gsLogo(string $theme = 'dark', bool $withTagline = false, int $size = 38): string
    {
        $theme = $theme === 'light' ? 'light' : 'dark';
        return '<span class="gs-logo gs-logo--' . $theme . '">'
            . gsLogoMark($size)
            . '<span class="gs-logo__text"><span class="gs-logo__name">Gym<b>Smart</b></span>'
            . ($withTagline ? '<span class="gs-logo__tag">Train Harder &amp; Smarter</span>' : '')
            . '</span></span>';
    }
}
