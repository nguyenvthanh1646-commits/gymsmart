<?php
/**
 * coach-review-card.php — ô "Đánh giá HLV" ở trang HLV của tôi (A-49). Include sau khi đặt:
 *   $reviewState    kết quả coachReviewState() (null thì không in gì)
 *   $reviewRedirect trang quay về sau khi gửi form (my-coach.php hoặc my-coach.php?history=...)
 * Form gửi POST tới chính my-coach.php (action review_save / review_delete, có CSRF); máy chủ kiểm tra lại mọi điều kiện.
 */
if (empty($reviewState)) {
    return;
}
$rv = $reviewState['review'];
$rvRel = (int)$reviewState['rel']['id'];
?>
<section class="card ch-review" aria-labelledby="h-review-<?= $rvRel ?>">
    <h2 class="card__head" id="h-review-<?= $rvRel ?>">Đánh giá HLV</h2>
    <?php if (!$reviewState['eligible'] && !$rv): ?>
        <p class="ch-muted ch-small" style="margin:0"><?= htmlspecialchars($reviewState['reason']) ?></p>
    <?php else: ?>
        <?php if ($rv): ?>
            <p class="ch-review__mine"><span class="ch-stars" aria-label="<?= (int)$rv['rating'] ?> trên 5 sao"><?= str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']) ?></span>
                <span class="ch-muted ch-small">Bạn đánh giá ngày <?= htmlspecialchars(date('d/m/Y', strtotime($rv['updated_at']))) ?></span></p>
            <?php if ($rv['comment'] !== ''): ?><p class="ch-small" style="margin:0 0 var(--s-3)"><?= htmlspecialchars($rv['comment']) ?></p><?php endif; ?>
        <?php else: ?>
            <p class="card__sub">Đánh giá giúp học viên khác chọn HLV. HLV thấy số sao và nhận xét, kèm tên (không có họ) của bạn.</p>
        <?php endif; ?>
        <?php if ($reviewState['eligible']): ?>
            <details<?= $rv ? '' : ' open' ?>>
                <summary class="ch-small"><?= $rv ? 'Sửa đánh giá' : 'Viết đánh giá' ?></summary>
                <form method="post" action="/my-coach.php" class="ch-review__form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="review_save">
                    <input type="hidden" name="relation_id" value="<?= $rvRel ?>">
                    <input type="hidden" name="back" value="<?= htmlspecialchars($reviewRedirect) ?>">
                    <fieldset class="ch-rate">
                        <legend>Số sao</legend>
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <label><input type="radio" name="rating" value="<?= $s ?>" required<?= (int)($rv['rating'] ?? 0) === $s ? ' checked' : '' ?>> <?= $s ?> ★</label>
                        <?php endfor; ?>
                    </fieldset>
                    <label for="rv-comment-<?= $rvRel ?>" class="ch-small">Nhận xét (không bắt buộc, tối đa <?= COACH_REVIEW_MAX_LEN ?> ký tự)</label>
                    <textarea id="rv-comment-<?= $rvRel ?>" name="comment" rows="3" maxlength="<?= COACH_REVIEW_MAX_LEN ?>"><?= htmlspecialchars((string)($rv['comment'] ?? '')) ?></textarea>
                    <button type="submit" class="btn" data-busy-text="Đang lưu…"><?= $rv ? 'Lưu đánh giá' : 'Gửi đánh giá' ?></button>
                </form>
            </details>
        <?php endif; ?>
        <?php if ($rv): ?>
            <form method="post" action="/my-coach.php" data-confirm="Xóa đánh giá của bạn?">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="review_delete">
                <input type="hidden" name="relation_id" value="<?= $rvRel ?>">
                <input type="hidden" name="back" value="<?= htmlspecialchars($reviewRedirect) ?>">
                <button type="submit" class="btn btn--ghost ch-review__del">Xóa đánh giá</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
