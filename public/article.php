<?php
/**
 * article.php — đọc một bài viết: /article.php?slug=bao-nhieu-buoc-moi-ngay
 * Slug chỉ được dùng để TRA trong danh sách bài đã nạp (loadArticles), không bao giờ ghép vào đường dẫn file,
 * nên không thể đọc file lạ bằng cách sửa URL.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/articles.php';

requireLogin();

$slug = $_GET['slug'] ?? '';
$all = loadArticles();

// Admin xem trước bản nháp: /article.php?slug=...&preview=1 (người dùng thường không thấy bản nháp)
$isPreview = false;
if (is_string($slug) && !isset($all[$slug]) && ($_GET['preview'] ?? '') === '1' && ($_SESSION['role'] ?? '') === 'ADMIN') {
    $draft = articleDbBySlug($slug);
    if ($draft !== null) {
        $all[$slug] = $draft;
        $isPreview = true;
    }
}

if (!is_string($slug) || !isset($all[$slug])) {
    showError(404, 'Không tìm thấy bài viết', 'Bài viết này không tồn tại hoặc đã bị gỡ.', [['/articles.php', 'Xem tất cả bài viết']]);
}

if (!$isPreview) {
    articleRecordView($slug); // đếm lượt xem cho trang thống kê của admin
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
// Các bài nền tảng khác (nhãn "Đọc trước khi tập"), để gợi ý đọc cả bộ
$mustOthers = array_filter($all, fn($x, $k) => $x['mustRead'] && $k !== $slug, ARRAY_FILTER_USE_BOTH);

$pageTitle = $a['title'];
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/articles.css">

<article class="ar-read">
    <a class="ar-back" href="/articles.php">&larr; Tất cả bài viết</a>

    <?php if ($a['image'] !== ''): ?>
        <figure class="ar-hero">
            <img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['imageAlt']) ?>">
        </figure>
    <?php endif; ?>

    <header class="ar-head">
        <div class="ar-tags">
            <span class="ar-tag <?= htmlspecialchars($catClass) ?>"><?= htmlspecialchars($catLabel) ?></span>
            <?php foreach ($a['tags'] as $tag): ?>
                <span class="ar-tag ar-tag--plain"><?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
        </div>
        <h1><?= htmlspecialchars($a['title']) ?></h1>
        <p class="ar-meta"><?= articleReadingMinutes($a) ?> phút đọc<?= $a['date'] !== '' ? ' · ' . htmlspecialchars(articleDate($a['date'])) : '' ?></p>
    </header>

    <?php if ($a['mustRead']): ?>
        <aside class="ar-must">
            <b>Đọc trước khi bắt đầu tập</b>
            <p>Đây là bài kỹ thuật nền tảng. Hãy đọc và tập thử với không tạ hoặc tạ rất nhẹ trước khi vào chương trình tập chính.</p>
            <?php foreach ($mustOthers as $ms => $m): ?>
                <p class="ar-must__next">Bài nền tảng còn lại: <a href="/article.php?slug=<?= htmlspecialchars(rawurlencode($ms)) ?>"><?= htmlspecialchars($m['title']) ?></a></p>
            <?php endforeach; ?>
        </aside>
    <?php endif; ?>

    <?php if ($a['quick'] !== ''): ?>
        <aside class="ar-quick">
            <b>Trả lời nhanh:</b>
            <p><?= articleText($a['quick']) ?></p>
        </aside>
    <?php else: ?>
        <p class="ar-lead"><?= htmlspecialchars($a['summary']) ?></p>
    <?php endif; ?>

    <p class="ar-ref-note"><b>Chỉ để tham khảo.</b> Nội dung là thông tin chung, không thay thế tư vấn của bác sĩ hoặc chuyên gia dinh dưỡng.</p>

    <div class="ar-body">
        <?php renderArticleBlocks($a['content']); ?>
    </div>

    <?php if (!empty($a['sources'])): ?>
        <section class="ar-refs">
            <h2>Nguồn tham khảo</h2>
            <?php renderArticleSources($a['sources']); ?>
            <details class="ar-legend">
                <summary>Cách đọc “Mức độ bằng chứng”</summary>
                <ul>
                    <?php foreach (articleLevels() as [$lvLabel, $lvDesc]): ?>
                        <li><b><?= htmlspecialchars($lvLabel) ?>:</b> <?= htmlspecialchars($lvDesc) ?>.</li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <p class="ar-verify">Các con số nghiên cứu trong bài lấy từ bản ghi PubMed và Europe PMC của từng bài báo. Chỗ nào ghi “Chưa kiểm chứng” nghĩa là bản tóm tắt không nêu thông tin đó, bạn hãy mở bài gốc để xem. Thấy sai sót, hãy báo cho nhóm GymSmart để sửa.</p>
        </section>
    <?php endif; ?>

    <p class="ar-disclaimer">Bài viết chỉ mang tính thông tin và tham khảo, không thay thế chẩn đoán, tư vấn hay điều trị y khoa. Nếu bạn có bệnh nền (tim mạch, tiểu đường, bệnh thận, rối loạn ăn uống...), đang mang thai, đang dùng thuốc hoặc chấn thương, hãy hỏi bác sĩ trước khi thay đổi chế độ ăn hoặc tập luyện. Kết quả nghiên cứu mô tả nhóm người tham gia, không đảm bảo cho từng cá nhân.</p>

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
                        <?php if ($r['image'] !== ''): ?>
                            <img class="ar-card__thumb" src="<?= htmlspecialchars($r['image']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="ar-card__cover <?= htmlspecialchars($rClass) ?>" aria-hidden="true"><?= htmlspecialchars($rLetter) ?></span>
                        <?php endif; ?>
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
