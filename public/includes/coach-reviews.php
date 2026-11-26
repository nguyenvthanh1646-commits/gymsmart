<?php
/**
 * coach-reviews.php — học viên đánh giá HLV (E2, A-49). Bảng coach_reviews (sql/coach_reviews.sql).
 *
 * Chống đánh giá giả / đánh giá cho vui:
 *   - Chỉ HỌC VIÊN của quan hệ đó mới viết được (mọi hàm nhận $clientId từ session, quan hệ phải có client_id = $clientId).
 *   - Quan hệ đã được duyệt (accepted_at có giá trị), trạng thái ACTIVE hoặc ENDED, đủ COACH_REVIEW_MIN_DAYS ngày tính từ lúc
 *     bắt đầu, và có ít nhất 1 buổi tập được ghi trong thời gian tập với HLV đó.
 *   - Mỗi quan hệ 1 đánh giá (UNIQUE trong DB), học viên sửa hoặc xóa được; ADMIN xóa được đánh giá vi phạm.
 * Phần quy tắc là hàm thuần (coachReviewEligibility, coachReviewValidate), test ở test-coach-reviews.php.
 * Khi hiển thị công khai chỉ hiện TÊN (từ cuối của họ tên), không hiện họ tên đầy đủ hay email của học viên.
 */
require_once __DIR__ . '/coach-db.php';

const COACH_REVIEW_MIN_DAYS = 14;
const COACH_REVIEW_MAX_LEN = 500;

/** DB đã có bảng coach_reviews chưa. */
function coachReviewsReady(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $pdo->query('SELECT 1 FROM coach_reviews LIMIT 1');
            $ready = true;
        } catch (PDOException $e) {
            $ready = false;
        }
    }
    return $ready;
}

/**
 * Hàm thuần: quan hệ này đã được đánh giá chưa.
 * @param array  $rel        ['status','accepted_at','ended_at']
 * @param int    $logsDuring số buổi tập học viên ghi trong thời gian tập với HLV
 * @param string $now        Y-m-d H:i:s
 * @return array ['ok' => bool, 'reason' => câu giải thích khi chưa được]
 */
function coachReviewEligibility(array $rel, int $logsDuring, string $now): array
{
    if (!in_array($rel['status'] ?? '', ['ACTIVE', 'ENDED'], true) || empty($rel['accepted_at'])) {
        return ['ok' => false, 'reason' => 'Chỉ đánh giá được HLV đã nhận bạn làm học viên.'];
    }
    $end = !empty($rel['ended_at']) && ($rel['status'] ?? '') === 'ENDED' ? strtotime((string)$rel['ended_at']) : strtotime($now);
    $days = (int)floor(($end - strtotime((string)$rel['accepted_at'])) / 86400);
    if ($days < COACH_REVIEW_MIN_DAYS) {
        $left = COACH_REVIEW_MIN_DAYS - $days;
        return ['ok' => false, 'reason' => ($rel['status'] === 'ENDED')
            ? 'Bạn tập với HLV này chưa đủ ' . COACH_REVIEW_MIN_DAYS . ' ngày nên chưa đánh giá được.'
            : "Đánh giá được sau khi tập cùng HLV đủ " . COACH_REVIEW_MIN_DAYS . " ngày (còn $left ngày)."];
    }
    if ($logsDuring < 1) {
        return ['ok' => false, 'reason' => 'Hãy ghi ít nhất 1 buổi tập trong thời gian tập với HLV rồi mới đánh giá.'];
    }
    return ['ok' => true, 'reason' => ''];
}

/** Hàm thuần: kiểm tra số sao và lời nhận xét. Trả câu lỗi hoặc null. */
function coachReviewValidate($rating, string $comment): ?string
{
    $r = filter_var($rating, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    if ($r === false) {
        return 'Hãy chọn từ 1 đến 5 sao.';
    }
    if (mb_strlen(trim($comment)) > COACH_REVIEW_MAX_LEN) {
        return 'Nhận xét tối đa ' . COACH_REVIEW_MAX_LEN . ' ký tự.';
    }
    return null;
}

/** Hàm thuần: tên hiển thị công khai = "Học viên" + TÊN (từ cuối của họ tên Việt Nam), không lộ họ tên đầy đủ. */
function coachReviewerName(string $fullName): string
{
    $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    return $parts ? 'Học viên ' . end($parts) : 'Học viên';
}

/** Quan hệ (của chính học viên này) để đánh giá: đang tập hoặc đã kết thúc. null nếu không phải của họ. */
function coachReviewRelation(PDO $pdo, int $clientId, int $relationId): ?array
{
    $st = $pdo->prepare(
        "SELECT cc.id, cc.coach_id, cc.client_id, cc.status, cc.accepted_at, cc.ended_at, u.full_name AS coach_name
         FROM coach_clients cc JOIN users u ON u.id = cc.coach_id
         WHERE cc.id = ? AND cc.client_id = ? AND cc.status IN ('ACTIVE', 'ENDED')"
    );
    $st->execute([$relationId, $clientId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r === false ? null : $r;
}

/** Số buổi tập (số ngày có ghi nhật ký) trong thời gian của quan hệ. */
function coachReviewLogsDuring(PDO $pdo, array $rel): int
{
    $from = substr((string)$rel['accepted_at'], 0, 10);
    $to = !empty($rel['ended_at']) && $rel['status'] === 'ENDED' ? substr((string)$rel['ended_at'], 0, 10) : date('Y-m-d');
    $st = $pdo->prepare('SELECT COUNT(DISTINCT log_date) FROM workout_logs WHERE user_id = ? AND log_date BETWEEN ? AND ?');
    $st->execute([(int)$rel['client_id'], $from, $to]);
    return (int)$st->fetchColumn();
}

/** Trạng thái đánh giá của một quan hệ cho học viên: ['rel','eligible','reason','review' => ?array]. */
function coachReviewState(PDO $pdo, int $clientId, int $relationId): ?array
{
    $rel = coachReviewRelation($pdo, $clientId, $relationId);
    if ($rel === null) {
        return null;
    }
    $e = coachReviewEligibility($rel, coachReviewLogsDuring($pdo, $rel), coachNow());
    $st = $pdo->prepare('SELECT id, rating, comment, created_at, updated_at FROM coach_reviews WHERE coach_client_id = ?');
    $st->execute([$relationId]);
    return ['rel' => $rel, 'eligible' => $e['ok'], 'reason' => $e['reason'], 'review' => $st->fetch(PDO::FETCH_ASSOC) ?: null];
}

/** Học viên viết hoặc sửa đánh giá. Ném CoachException khi chưa đủ điều kiện hoặc dữ liệu sai. */
function coachReviewSave(PDO $pdo, int $clientId, int $relationId, $rating, string $comment): void
{
    if ($e = coachReviewValidate($rating, $comment)) {
        throw new CoachException($e);
    }
    $state = coachReviewState($pdo, $clientId, $relationId);
    if ($state === null) {
        throw new CoachException('Không tìm thấy quan hệ huấn luyện này trong tài khoản của bạn.');
    }
    if (!$state['eligible']) {
        throw new CoachException($state['reason']);
    }
    $now = coachNow();
    $comment = trim($comment);
    if ($state['review']) {
        $pdo->prepare('UPDATE coach_reviews SET rating = ?, comment = ?, updated_at = ? WHERE coach_client_id = ? AND client_id = ?')
            ->execute([(int)$rating, $comment, $now, $relationId, $clientId]);
    } else {
        $pdo->prepare('INSERT INTO coach_reviews (coach_client_id, coach_id, client_id, rating, comment, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$relationId, (int)$state['rel']['coach_id'], $clientId, (int)$rating, $comment, $now, $now]);
    }
    notify($pdo, (int)$state['rel']['coach_id'], 'coach.review', ($state['review'] ? 'Học viên sửa đánh giá: ' : 'Đánh giá mới: ') . (int)$rating . ' sao',
        coachReviewerName(notifName($pdo, $clientId)) . ($comment !== '' ? ': ' . notifExcerpt($comment) : '.'), '/coach/dashboard.php');
}

/** Học viên xóa đánh giá của chính mình. */
function coachReviewDelete(PDO $pdo, int $clientId, int $relationId): void
{
    $st = $pdo->prepare('DELETE FROM coach_reviews WHERE coach_client_id = ? AND client_id = ?');
    $st->execute([$relationId, $clientId]);
    if ($st->rowCount() === 0) {
        throw new CoachException('Không có đánh giá nào để xóa.');
    }
}

/** Điểm trung bình và số đánh giá theo HLV: [coach_id => ['avg' => float, 'count' => int]]. */
function coachRatingSummary(PDO $pdo, array $coachIds): array
{
    $ids = array_values(array_unique(array_map('intval', $coachIds)));
    if (!$ids) {
        return [];
    }
    $st = $pdo->prepare('SELECT coach_id, AVG(rating) AS avg_rating, COUNT(*) AS n FROM coach_reviews WHERE coach_id IN ('
        . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY coach_id'); // audit:ok (chỉ ghép dấu ?)
    $st->execute($ids);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int)$r['coach_id']] = ['avg' => round((float)$r['avg_rating'], 1), 'count' => (int)$r['n']];
    }
    return $out;
}

/** Đánh giá của một HLV, mới nhất trước; tên học viên đã rút gọn để hiện công khai. */
function coachReviewsForCoach(PDO $pdo, int $coachId, int $limit = 5): array
{
    $st = $pdo->prepare('SELECT r.rating, r.comment, r.updated_at, u.full_name FROM coach_reviews r JOIN users u ON u.id = r.client_id
        WHERE r.coach_id = ? ORDER BY r.updated_at DESC, r.id DESC LIMIT ' . max(1, min(50, $limit))); // audit:ok (số nguyên đã giới hạn)
    $st->execute([$coachId]);
    return array_map(fn($r) => ['rating' => (int)$r['rating'], 'comment' => (string)$r['comment'], 'date' => (string)$r['updated_at'],
        'name' => coachReviewerName((string)$r['full_name'])], $st->fetchAll(PDO::FETCH_ASSOC));
}

/** ADMIN: mọi đánh giá, mới nhất trước (có tên đầy đủ để xử lý vi phạm). */
function coachReviewsAdmin(PDO $pdo, int $limit = 100): array
{
    $st = $pdo->prepare('SELECT r.id, r.rating, r.comment, r.updated_at, c.full_name AS client_name, h.full_name AS coach_name
        FROM coach_reviews r JOIN users c ON c.id = r.client_id JOIN users h ON h.id = r.coach_id
        ORDER BY r.updated_at DESC, r.id DESC LIMIT ' . max(1, min(500, $limit))); // audit:ok
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** ADMIN xóa một đánh giá. Trả mô tả ngắn để ghi nhật ký. */
function coachReviewAdminDelete(PDO $pdo, int $reviewId): string
{
    $st = $pdo->prepare('SELECT r.rating, h.full_name AS coach_name FROM coach_reviews r JOIN users h ON h.id = r.coach_id WHERE r.id = ?');
    $st->execute([$reviewId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r === false) {
        throw new CoachException('Không tìm thấy đánh giá này.');
    }
    $pdo->prepare('DELETE FROM coach_reviews WHERE id = ?')->execute([$reviewId]);
    return (int)$r['rating'] . ' sao cho HLV "' . $r['coach_name'] . '"';
}
