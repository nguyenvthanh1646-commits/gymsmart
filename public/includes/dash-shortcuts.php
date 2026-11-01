<?php
/**
 * dash-shortcuts.php — lối tắt và nhắc các bài "Đọc trước khi tập" mà người dùng chưa đọc.
 * Nút chatbot mở widget chat có sẵn (js/home-user.js). Nó bị ẩn cho tới khi JS chạy, vì thiếu JS thì nút không làm được gì.
 */
?>
<section class="du-card du-card--shortcuts" aria-labelledby="du-shortcuts-title">
    <div class="du-card__head"><?php duCardTitle('bolt', 'du-shortcuts-title', 'Lối tắt'); ?></div>
    <ul class="du-shortcuts">
        <li><a href="/exercises.php"><span class="du-shortcuts__icon"><?= hmIcon('play', 22) ?></span><span><b>Thư viện bài tập</b><small>Video và hướng dẫn</small></span></a></li>
        <li><a href="/articles.php"><span class="du-shortcuts__icon"><?= hmIcon('book', 22) ?></span><span><b>Bài viết</b><small>Kiến thức có dẫn nguồn</small></span></a></li>
        <li data-chat-shortcut hidden><button type="button" data-open-chat><span class="du-shortcuts__icon"><?= hmIcon('chat', 22) ?></span><span><b>Hỏi chatbot AI</b><small>Hỏi nhanh về tập luyện</small></span></button></li>
    </ul>
</section>

<?php if (!empty($dash['unreadArticles'])): ?>
    <section class="du-card du-card--read" aria-labelledby="du-read-title">
        <div class="du-card__head">
            <?php duCardTitle('target', 'du-read-title', 'Đọc trước khi tập'); ?>
            <span class="du-card__meta">Còn <?= count($dash['unreadArticles']) ?> bài chưa đọc</span>
        </div>
        <ul class="du-reads">
            <?php foreach ($dash['unreadArticles'] as $slug => $article): ?>
                <li>
                    <a href="/article.php?slug=<?= htmlspecialchars(rawurlencode((string)$slug)) ?>">
                        <?php if (($article['image'] ?? '') !== ''): ?>
                            <img src="<?= htmlspecialchars($article['image']) ?>" alt="" width="1200" height="675" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <span><?= htmlspecialchars($article['title']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
