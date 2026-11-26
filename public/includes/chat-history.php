<?php
/**
 * chat-history.php — lưu lịch sử hội thoại GymSmart AI vào bảng chatbot_sessions / chatbot_messages (đã có trong schema.sql).
 * Chỉ lưu chế độ người tập (mode "user"); trợ lý quản trị và trợ lý HLV KHÔNG lưu vì câu trả lời có dữ liệu tổng hợp
 * hoặc dữ liệu của học viên khác.
 *
 * Một người có nhiều "cuộc trò chuyện" (chatbot_sessions). Bấm "Cuộc trò chuyện mới" trong khung chat thì mở cuộc mới;
 * chỉ giữ CHAT_KEEP_SESSIONS cuộc gần nhất, cuộc cũ hơn tự xóa. Người dùng xóa được toàn bộ bằng nút "Xóa lịch sử".
 * Mọi hàm nhận $userId lấy từ session ở nơi gọi (không bao giờ từ request), nên không đọc được hội thoại của người khác.
 * Thời gian ghi bằng PHP (date) để chạy được cả trên SQLite khi test.
 */

const CHAT_KEEP_SESSIONS = 5;
const CHAT_LOAD_LIMIT = 40;
const CHAT_MAX_LEN = 4000;   // cắt câu trả lời quá dài trước khi lưu

/** Id cuộc trò chuyện mới nhất của user, hoặc null. */
function chatLatestSessionId(PDO $pdo, int $userId): ?int
{
    $st = $pdo->prepare('SELECT id FROM chatbot_sessions WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$userId]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int)$id;
}

/** Ghi một lượt hỏi - đáp vào cuộc trò chuyện mới nhất (chưa có thì tạo). Trả id cuộc trò chuyện. */
function chatHistoryAppend(PDO $pdo, int $userId, string $question, string $answer): int
{
    $now = date('Y-m-d H:i:s');
    $sid = chatLatestSessionId($pdo, $userId);
    if ($sid === null) {
        $sid = chatHistoryStartNew($pdo, $userId, $question);
    }
    $ins = $pdo->prepare('INSERT INTO chatbot_messages (session_id, role, content, created_at) VALUES (?, ?, ?, ?)');
    $ins->execute([$sid, 'USER', mb_substr($question, 0, CHAT_MAX_LEN), $now]);
    $ins->execute([$sid, 'ASSISTANT', mb_substr($answer, 0, CHAT_MAX_LEN), $now]);
    // Cuộc trò chuyện chưa có tiêu đề (mở bằng nút "mới") thì lấy câu hỏi đầu tiên làm tiêu đề
    $pdo->prepare("UPDATE chatbot_sessions SET updated_at = ?, title = COALESCE(NULLIF(title, ''), ?) WHERE id = ?")
        ->execute([$now, mb_substr($question, 0, 80), $sid]);
    return $sid;
}

/** Mở cuộc trò chuyện mới (dùng khi bấm "Cuộc trò chuyện mới"); cuộc mới nhất còn trống thì dùng lại. Xóa bớt cuộc cũ. */
function chatHistoryStartNew(PDO $pdo, int $userId, string $title = ''): int
{
    $latest = chatLatestSessionId($pdo, $userId);
    if ($latest !== null) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM chatbot_messages WHERE session_id = ?');
        $st->execute([$latest]);
        if ((int)$st->fetchColumn() === 0) {
            return $latest;
        }
    }
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO chatbot_sessions (user_id, title, created_at, updated_at) VALUES (?, ?, ?, ?)')
        ->execute([$userId, mb_substr($title, 0, 80), $now, $now]);
    $sid = (int)$pdo->lastInsertId();

    // Chỉ giữ CHAT_KEEP_SESSIONS cuộc gần nhất (LIMIT/OFFSET là hằng số nguyên, không do người dùng nhập)
    $st = $pdo->prepare('SELECT id FROM chatbot_sessions WHERE user_id = ? ORDER BY id DESC LIMIT 100 OFFSET ' . (int)CHAT_KEEP_SESSIONS); // audit:ok
    $st->execute([$userId]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $old) {
        chatDeleteSession($pdo, (int)$old);
    }
    return $sid;
}

/** Tin nhắn của cuộc trò chuyện mới nhất, cũ đến mới: [['who' => 'me'|'bot', 'text', 'at' => mili giây]] */
function chatHistoryLoad(PDO $pdo, int $userId, int $limit = CHAT_LOAD_LIMIT): array
{
    $sid = chatLatestSessionId($pdo, $userId);
    if ($sid === null) {
        return [];
    }
    $st = $pdo->prepare('SELECT role, content, created_at FROM chatbot_messages WHERE session_id = ? ORDER BY id DESC LIMIT ' . (int)$limit); // audit:ok
    $st->execute([$sid]);
    $rows = array_reverse($st->fetchAll(PDO::FETCH_ASSOC));
    return array_map(fn($r) => ['who' => $r['role'] === 'USER' ? 'me' : 'bot', 'text' => (string)$r['content'],
        'at' => strtotime((string)$r['created_at']) * 1000], $rows);
}

/** Số câu hỏi user đã gửi trong $seconds giây qua (giới hạn tần suất theo TÀI KHOẢN, không reset khi đăng nhập lại). */
function chatHistoryCountRecent(PDO $pdo, int $userId, int $seconds): int
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM chatbot_messages m JOIN chatbot_sessions s ON s.id = m.session_id
         WHERE s.user_id = ? AND m.role = 'USER' AND m.created_at >= ?"
    );
    $st->execute([$userId, date('Y-m-d H:i:s', time() - $seconds)]);
    return (int)$st->fetchColumn();
}

/** Xóa toàn bộ lịch sử chat của user. Trả số cuộc trò chuyện đã xóa. */
function chatHistoryClear(PDO $pdo, int $userId): int
{
    $st = $pdo->prepare('SELECT id FROM chatbot_sessions WHERE user_id = ?');
    $st->execute([$userId]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        chatDeleteSession($pdo, (int)$id);
    }
    return count($ids);
}

/** Xóa tin nhắn rồi xóa cuộc trò chuyện (không dựa vào ON DELETE CASCADE để chạy được cả trên SQLite). */
function chatDeleteSession(PDO $pdo, int $sessionId): void
{
    $pdo->prepare('DELETE FROM chatbot_messages WHERE session_id = ?')->execute([$sessionId]);
    $pdo->prepare('DELETE FROM chatbot_sessions WHERE id = ?')->execute([$sessionId]);
}
