<?php
// public/includes/video.php
// Hàm dùng chung để nhúng video YouTube một cách an toàn (trang chi tiết bài tập, trang lịch tập...).
// Dùng: require_once __DIR__ . '/includes/video.php';  rồi  $embed = youtubeEmbedUrl($exercise['video_url']);

if (!function_exists('youtubeEmbedUrl')) {
    /**
     * Đổi link YouTube (hoặc chính mã 11 ký tự) thành link nhúng.
     * Không hợp lệ thì trả null. Link nhúng luôn được dựng lại từ mã video đã kiểm tra,
     * không in nguyên chuỗi người dùng nhập ra HTML.
     */
    function youtubeEmbedUrl(?string $url): ?string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return null;
        }
        $id = null;
        if (preg_match('~^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])~', $url, $m)) {
            $id = $m[1];
        } elseif (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            $id = $url; // cho phép lưu thẳng mã video
        }
        return $id !== null ? 'https://www.youtube-nocookie.com/embed/' . $id : null;
    }
}