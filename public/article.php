<?php
/**
 * article.php — đọc một bài viết: /article.php?slug=protein-bao-nhieu-la-du
 * Slug chỉ được dùng để TRA trong danh sách bài đã nạp (loadArticles), không bao giờ ghép vào đường dẫn file,
 * nên không thể đọc file lạ bằng cách sửa URL.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/articles.php';

requireLogin();

$slug = $_GET['slug'] ?? '';
$all = loadArticles();

if (!is_string($slug) || !isset($all[$slug])) {
    showError(404, 'Không tìm thấy bài viết', 'Bài viết này không tồn tại hoặc đã bị gỡ.', [['/articles.php', 'Xem tất cả bài viết']]);
}

$categories = articleCategories();
$a = $all[$slug];
[$catLabel, $catLetter, $catClass] = $categories[$a['category']];

// Bài liên quan: cùng chuyên mục trước, thiếu thì lấy bài khác, tối đa 2 bài (trừ bài đang đọc)
$related = [];
foreach ($all as $s => $other) {
    if ($s !== $slug && $other['category'] === $a['category']) {
        $related[$s] = $other;
    }
}
foreach ($all as $s => $other) {
    if ($s !== $slug && !isset($related[$s])) {
        $related[$s] = $other;
    }
}
$related = array_slice($related, 0, 2, true);

$pageTitle = $a['title'];
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/articles.css">

<article class="ar-read">
    <a class="ar-back" href="/articles.php">&larr; Tất cả bài viết</a>

    <header class="ar-head">
        <span class="ar-tag <?= htmlspecialchars($catClass) ?>"><?= htmlspecialchars($catLabel) ?></span>
        <h1><?= htmlspecialchars($a['title']) ?></h1>
        <p class="ar-lead"><?= htmlspecialchars($a['summary']) ?></p>
        <p class="ar-meta"><?= articleReadingMinutes($a) ?> phút đọc<?= $a['date'] !== '' ? ' · ' . htmlspecialchars(articleDate($a['date'])) : '' ?></p>
    </header>

    <div class="ar-body">
        <?php renderArticleBlocks($a['content']); ?>
    </div>

    <div class="ar-cta">
        <div>
            <b>Áp dụng vào bữa ăn của bạn</b>
            <p>Xem calo, macro và lịch ăn gợi ý tính riêng cho hồ sơ của bạn.</p>
        </div>
        <a class="btn" href="/nutrition.php">Mở trang Dinh dưỡng</a>
    </div>

    <?php if (!empty($related)): ?>
        <section class="ar-related">
            <h2>Đọc tiếp</h2>
            <div class="ar-grid ar-grid--2">
                <?php foreach ($related as $s => $r):
                    [$rLabel, $rLetter, $rClass] = $categories[$r['category']];
                ?>
                    <a class="ar-card" href="/article.php?slug=<?= htmlspecialchars(rawurlencode($s)) ?>">
                        <span class="ar-card__cover <?= htmlspecialchars($rClass) ?>" aria-hidden="true"><?= htmlspecialchars($rLetter) ?></span>
                        <span class="ar-card__body">
                            <span class="ar-tag <?= htmlspecialchars($rClass) ?>"><?= htmlspecialchars($rLabel) ?></span>
                            <h2><?= htmlspecialchars($r['title']) ?></h2>
                            <span class="ar-card__meta"><?= articleReadingMinutes($r) ?> phút đọc</span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>

<?php include __DIR__ . '/includes/footer.php'; ?>
