<?php
/**
 * dash-shortcuts.php — lối tắt và nhắc các bài "Đọc trước khi tập" mà người dùng chưa đọc.
 * Nút chatbot mở widget chat có sẵn (js/home-user.js). Nó bị ẩn cho tới khi JS chạy, vì thiếu JS thì nút không làm được gì.
 */
?>
<section class="du-card du-card--shortcuts" aria-labelledby="du-shortcuts-title">
    <div class="du-card__head"><h2 id="du-shortcuts-title">Lối tắt</h2></div>
    <ul class="du-shortcuts">
        <li><a href="/exercises.php"><?= hmIcon('dumbbell', 22) ?><span>Thư viện bài tập</span></a></li>
        <li><a href="/articles.php"><?= hmIcon('book', 22) ?><span>Bài viết</span></a></li>
        <li data-chat-shortcut hidden><button type="button" data-open-chat><?= hmIcon('chat', 22) ?><span>Hỏi chatbot AI</span></button></li>
    </ul>
</section>

<?php if (!empty($dash['unreadArticles'])): ?>
    <section class="du-card du-card--read" aria-labelledby="du-read-title">
        <div class="du-card__head">
            <h2 id="du-read-title">Đọc trước khi tập</h2>
            <span class="du-card__meta">Còn <?= count($dash['unreadArticles']) ?> bài chưa đọc</span>
        </div>
        <ul class="du-reads">
            <?php foreach ($dash['unreadArticles'] as $slug => $article): ?>
                <li><a href="/article.php?slug=<?= htmlspecialchars(rawurlencode((string)$slug)) ?>"><?= htmlspecialchars($article['title']) ?> <span aria-hidden="true">&rarr;</span></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
