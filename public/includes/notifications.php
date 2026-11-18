<?php
/**
 * notifications.php — thông báo trong ứng dụng (chuông trên thanh menu, A-44). Bảng notifications (sql/notifications.sql).
 *
 * Nơi phát sinh sự kiện (chủ yếu includes/coach-db.php) gọi notify() NGAY TRONG transaction của thao tác chính, nên
 * thao tác lỗi thì không có thông báo "ma". notify() không bao giờ làm hỏng thao tác chính: DB cũ chưa có bảng thì bỏ qua.
 * Nội dung (title/body) do code viết sẵn, chỉ ghép tên người và đoạn trích ngắn; khi in ra HTML luôn qua htmlspecialchars,
 * trong JS luôn gán bằng textContent. link chỉ nhận đường dẫn nội bộ bắt đầu bằng "/" (chặn link ra ngoài, javascript:...).
 * Mọi hàm đọc/sửa nhận $userId lấy từ session ở nơi gọi, nên không đọc hay đánh dấu được thông báo của người khác.
 */

const NOTIF_LIST_LIMIT = 15;
const NOTIF_KEEP = 200;   // mỗi người giữ tối đa 200 thông báo gần nhất

/** Đường dẫn hợp lệ: nội bộ, bắt đầu bằng 1 dấu "/", không có ký tự lạ. */
function notifSafeLink(string $link): string
{
    return preg_match('#^/(?!/)[A-Za-z0-9/_.?=&%-]*$#', $link) ? $link : '';
}

/**
 * Tạo thông báo cho $userId. $dedupe = true: nếu đã có thông báo CHƯA ĐỌC cùng type + link thì cập nhật nội dung và thời gian
 * thay vì thêm dòng mới (vd 10 tin nhắn liên tiếp chỉ thành 1 thông báo "tin nhắn mới").
 * Trả về true nếu ghi được.
 */
function notify(PDO $pdo, int $userId, string $type, string $title, string $body = '', string $link = '', bool $dedupe = false): bool
{
    if ($userId <= 0) {
        return false;
    }
    $title = mb_substr(trim($title), 0, 160);
    $body = mb_substr(trim(preg_replace('/\s+/u', ' ', $body)), 0, 300);
    $link = notifSafeLink($link);
    $now = date('Y-m-d H:i:s');
    try {
        if ($dedupe) {
            $st = $pdo->prepare('SELECT id FROM notifications WHERE user_id = ? AND type = ? AND link = ? AND read_at IS NULL ORDER BY id DESC LIMIT 1');
            $st->execute([$userId, $type, $link]);
            $id = $st->fetchColumn();
            if ($id !== false) {
                $pdo->prepare('UPDATE notifications SET title = ?, body = ?, created_at = ? WHERE id = ?')->execute([$title, $body, $now, (int)$id]);
                return true;
            }
        }
        $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $type, $title, $body, $link, $now]);
        return true;
    } catch (PDOException $e) {
        error_log('notify: ' . $e->getMessage());   // thường do DB cũ chưa có bảng notifications
        return false;
    }
}

/** Tên hiển thị của một người (để ghép vào nội dung thông báo). */
function notifName(PDO $pdo, int $userId): string
{
    $st = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
    $st->execute([$userId]);
    return (string)($st->fetchColumn() ?: 'Ai đó');
}

/** Đoạn trích ngắn của một nội dung dài (tin nhắn, nhận xét). */
function notifExcerpt(string $text, int $max = 80): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
}

function notifUnreadCount(PDO $pdo, int $userId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

/** Thông báo mới nhất trước: [['id','type','title','body','link','read' => bool,'created_at'], ...] */
function notifList(PDO $pdo, int $userId, int $limit = NOTIF_LIST_LIMIT, int $offset = 0): array
{
    $st = $pdo->prepare('SELECT id, type, title, body, link, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT '
        . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset)); // audit:ok (số nguyên đã ép kiểu)
    $st->execute([$userId]);
    return array_map(fn($r) => ['id' => (int)$r['id'], 'type' => $r['type'], 'title' => $r['title'], 'body' => $r['body'],
        'link' => notifSafeLink((string)$r['link']), 'read' => $r['read_at'] !== null, 'created_at' => $r['created_at']], $st->fetchAll(PDO::FETCH_ASSOC));
}

function notifCount(PDO $pdo, int $userId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

/** Đánh dấu đã đọc một thông báo (chỉ của chính mình). Trả về link của nó để chuyển trang. */
function notifMarkRead(PDO $pdo, int $userId, int $id): ?string
{
    $st = $pdo->prepare('SELECT link FROM notifications WHERE id = ? AND user_id = ?');
    $st->execute([$id, $userId]);
    $link = $st->fetchColumn();
    if ($link === false) {
        return null;
    }
    $pdo->prepare('UPDATE notifications SET read_at = ? WHERE id = ? AND user_id = ? AND read_at IS NULL')->execute([date('Y-m-d H:i:s'), $id, $userId]);
    return notifSafeLink((string)$link);
}

/** Đánh dấu đã đọc tất cả. Trả số thông báo vừa đánh dấu. */
function notifMarkAllRead(PDO $pdo, int $userId): int
{
    $st = $pdo->prepare('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL');
    $st->execute([date('Y-m-d H:i:s'), $userId]);
    return $st->rowCount();
}

/**
 * Người dùng đã mở đúng trang của sự kiện (vd khung chat với HLV): đánh dấu đã đọc các thông báo cùng type + link,
 * để chuông không còn báo điều họ đã xem. Lỗi (chưa có bảng) thì bỏ qua.
 */
function notifMarkReadWhere(PDO $pdo, int $userId, string $type, string $link): void
{
    try {
        $pdo->prepare('UPDATE notifications SET read_at = ? WHERE user_id = ? AND type = ? AND link = ? AND read_at IS NULL')
            ->execute([date('Y-m-d H:i:s'), $userId, $type, notifSafeLink($link)]);
    } catch (PDOException $e) {
        error_log('notifMarkReadWhere: ' . $e->getMessage());
    }
}

/** Xóa bớt thông báo cũ, giữ NOTIF_KEEP cái mới nhất của người này. */
function notifPrune(PDO $pdo, int $userId): void
{
    $st = $pdo->prepare('SELECT id FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1 OFFSET ' . (int)NOTIF_KEEP); // audit:ok
    $st->execute([$userId]);
    $cut = $st->fetchColumn();
    if ($cut !== false) {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ? AND id <= ?')->execute([$userId, (int)$cut]);
    }
}
