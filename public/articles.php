<?php
/**
 * articles.php — danh sách bài viết kiến thức. Mỗi bài là một file trong content/articles/ (xem includes/articles.php).
 * Lọc theo chuyên mục bằng ?cat=protein (chỉ chấp nhận khóa có trong articleCategories()).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/articles.php';

requireLogin();

$categories = articleCategories();
$all = loadArticles();

$cat = $_GET['cat'] ?? '';
if (!is_string($cat) || !isset($categories[$cat])) {
    $cat = ''; // không hợp lệ thì hiện tất cả
}
$articles = $cat === '' ? $all : array_filter($all, fn($a) => $a['category'] === $cat);

$pageTitle = 'Bài viết';
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/articles.css">

<div class="ar">
    <div class="pg-head">
        <div>
            <h1>Bài viết</h1>
            <p class="pg-sub">Kiến thức dinh dưỡng, sức khỏe và tập luyện, có dẫn nghiên cứu và nêu rõ mức độ bằng chứng. Chỉ để tham khảo, không thay thế tư vấn y tế.</p>
        </div>
        <span class="pg-count"><b><?= count($articles) ?></b> bài viết</span>
    </div>

    <div class="chips" role="group" aria-label="Chuyên mục">
        <a class="chip<?= $cat === '' ? ' is-active' : '' ?>" href="/articles.php">Tất cả</a>
        <?php foreach ($categories as $key => [$label]): ?>
            <a class="chip<?= $cat === $key ? ' is-active' : '' ?>" href="/articles.php?cat=<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($articles)): ?>
        <div class="empty">
            <p>Chưa có bài viết nào trong chuyên mục này.</p>
            <a class="btn btn--ghost" href="/articles.php">Xem tất cả bài viết</a>
        </div>
    <?php else: ?>
        <div class="ar-grid ar-grid--cards">
            <?php foreach ($articles as $slug => $a):
                [$catLabel, $catLetter, $catClass] = $categories[$a['category']];
            ?>
                <a class="ar-card ar-card--img" href="/article.php?slug=<?= htmlspecialchars(rawurlencode($slug)) ?>">
                    <span class="ar-card__media <?= htmlspecialchars($catClass) ?>">
                        <?php if ($a['image'] !== ''): ?>
                            <img src="<?= htmlspecialchars($a['image']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="ar-card__letter" aria-hidden="true"><?= htmlspecialchars($catLetter) ?></span>
                        <?php endif; ?>
                        <span class="ar-card__over">
                            <span class="ar-tags">
                                <span class="ar-tag ar-tag--glass"><?= htmlspecialchars($catLabel) ?></span>
                                <?php foreach (array_slice($a['tags'], 0, 1) as $tag): ?>
                                    <span class="ar-tag ar-tag--glass"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </span>
                            <h2><?= htmlspecialchars($a['title']) ?></h2>
                        </span>
                    </span>
                    <span class="ar-card__body">
                        <span class="ar-card__sum"><?= htmlspecialchars($a['summary']) ?></span>
                        <span class="ar-card__meta"><?= articleReadingMinutes($a) ?> phút đọc<?= $a['date'] !== '' ? ' · ' . htmlspecialchars(articleDate($a['date'])) : '' ?><b class="ar-card__more">Đọc tiếp →</b></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
