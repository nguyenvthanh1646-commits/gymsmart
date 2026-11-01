<?php
/**
 * includes/articles.php — kho bài viết kiến thức (dinh dưỡng, sức khỏe, tập luyện).
 *
 * MỖI BÀI VIẾT LÀ MỘT FILE trong thư mục content/articles/. Thêm bài mới = tạo thêm một file, không cần sửa code hay database.
 *
 * CÁCH THÊM BÀI MỚI
 *   1. Sao chép một file bài có sẵn (ví dụ content/articles/bao-nhieu-buoc-moi-ngay.php), đặt tên mới
 *      chỉ gồm chữ thường không dấu, số và dấu gạch ngang, ví dụ: nuoc-va-giac-ngu.php
 *   2. Sửa các trường:
 *        slug      : trùng tên file (không có .php), dùng trong đường dẫn /article.php?slug=...
 *        title     : tiêu đề
 *        mustRead  : (tuỳ chọn) true = bài nền tảng cần đọc trước khi tập, có nhãn riêng và hiện ở đầu trang Bài viết
 *        category  : protein | carb | fat | nutrition | training | health | general   (xem articleCategories())
 *        tags      : (tùy chọn) danh sách nhãn phụ hiện cạnh chuyên mục, ví dụ ['Khoa học thể thao']
 *        summary   : 1 đến 2 câu tóm tắt, hiện ở thẻ ngoài danh sách
 *        quick     : (tùy chọn) khung "Trả lời nhanh" đầu bài, 2 đến 3 câu
 *        image     : (tùy chọn) ảnh bìa, dạng /img/articles/ten-bai.svg (chỉ nhận đường dẫn trong /img/articles/)
 *        imageAlt  : mô tả ảnh bìa cho người dùng đọc màn hình
 *        date      : ngày đăng, dạng YYYY-MM-DD
 *        order     : số thứ tự hiển thị, nhỏ hiện trước (bài cùng số thì bài mới hơn hiện trước)
 *        sources   : (tùy chọn) danh sách nguồn tham khảo, mỗi nguồn là mảng:
 *                      ['authors' => 'Paluch AE và cs.', 'year' => 2022, 'title' => '...', 'journal' => '...',
 *                       'detail' => '7(3):e219-e228', 'pmid' => '35247352', 'doi' => '10.1016/S2468-2667(21)00302-9']
 *                    Chỉ ghi nghiên cứu CÓ THẬT và đã mở bản ghi ra kiểm tra. Link PubMed/DOI do code tự tạo từ pmid và doi.
 *        content   : danh sách khối nội dung, mỗi khối là [loại, giá trị]:
 *                      ['p',  'Đoạn văn']
 *                      ['h2', 'Tiêu đề mục']
 *                      ['ul', ['Ý 1', 'Ý 2']]          danh sách chấm
 *                      ['ol', ['Bước 1', 'Bước 2']]    danh sách số
 *                      ['tip', 'Lưu ý nổi bật']
 *                      ['table', ['head' => ['Cột 1', 'Cột 2'], 'rows' => [['a', 'b'], ['c', 'd']]]]
 *                      ['figure', ['src' => '/img/articles/x.svg', 'alt' => 'Mô tả', 'caption' => 'Chú thích, nguồn số liệu']]
 *                      ['evidence', ['level' => 'fair', 'text' => 'Giải thích ngắn']]       dòng "Mức độ bằng chứng"
 *                      ['study', ['title' => 'Đánh giá nghiên cứu', 'rows' => [['Tiêu chí', 'ok', 'Nội dung'], ...]]]
 *                      ['principles', [['Câu mệnh lệnh ngắn', 'Giải thích', 'fair'], ...]]   nguyên tắc đánh số
 *                      ['summary', ['Ý 1', 'Ý 2']]     mục "Tóm tắt"
 *                      ['faq', [['Câu hỏi?', 'Trả lời'], ...]]                              mục "Câu hỏi thường gặp"
 *      Mức bằng chứng (level): strong | fair | thin | weak | basic | unverified   (xem articleLevels())
 *      Trạng thái tiêu chí (study): ok | part | unk | na                          (xem articleStatuses())
 *      Trong chữ có thể in đậm bằng **như thế này**. Không dùng HTML (mọi chữ đều được escape để an toàn).
 *   3. Lưu file, mở /articles.php là thấy bài mới.
 *
 * Muốn thêm chuyên mục mới: thêm một dòng vào articleCategories() và một màu trong css/articles.css.
 */

// Nếu dự án có includes/db.php thì nạp để đọc bài do admin soạn (bảng articles). Phải nạp ở phạm vi toàn cục để có biến $pdo.
if (is_file(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}

/** Chuyên mục: khóa => [tên hiển thị, chữ cái trên thẻ, tên class màu] */
function articleCategories(): array
{
    return [
        'protein'   => ['Protein', 'P', 'ar-protein'],
        'carb'      => ['Carb (tinh bột)', 'C', 'ar-carb'],
        'fat'       => ['Chất béo', 'F', 'ar-fat'],
        'nutrition' => ['Dinh dưỡng', 'D', 'ar-nutrition'],
        'training'  => ['Tập luyện', 'T', 'ar-training'],
        'health'    => ['Sức khỏe', 'S', 'ar-health'],
        'general'   => ['Kiến thức chung', 'K', 'ar-general'],
    ];
}

/** Mức độ bằng chứng: khóa => [nhãn, giải thích ngắn, class màu] */
function articleLevels(): array
{
    return [
        'strong'     => ['Mạnh', 'nhiều thử nghiệm hoặc nhiều nhóm theo dõi, kết quả nhất quán', 'ar-ev--strong'],
        'fair'       => ['Khá chắc', 'có phân tích gộp hoặc thử nghiệm tốt nhưng còn giới hạn về đối tượng, thời gian hoặc thiết kế', 'ar-ev--fair'],
        'thin'       => ['Mỏng', 'ít nghiên cứu hoặc nghiên cứu nhỏ', 'ar-ev--thin'],
        'weak'       => ['Yếu', 'chủ yếu suy luận hoặc quan sát rời rạc', 'ar-ev--weak'],
        'basic'      => ['Kiến thức nền', 'đồng thuận chung, bài này không dẫn nghiên cứu riêng cho ý này', 'ar-ev--basic'],
        'unverified' => ['Chưa kiểm chứng', 'chưa mở được nguồn gốc để kiểm tra', 'ar-ev--unv'],
    ];
}

/** Trạng thái một tiêu chí chất lượng nghiên cứu: khóa => [nhãn, biểu tượng, class] */
function articleStatuses(): array
{
    return [
        'ok'   => ['Đạt', '✓', 'ar-st--ok'],
        'part' => ['Một phần', '◐', 'ar-st--part'],
        'unk'  => ['Chưa kiểm chứng', '?', 'ar-st--unk'],
        'na'   => ['Không áp dụng', '–', 'ar-st--na'],
    ];
}

/** Ảnh chỉ được nằm trong /img/articles/ với tên an toàn: tránh đường dẫn lạ, javascript:, http:... */
function articleImageOk($src): bool
{
    return is_string($src) && (bool)preg_match('#^/img/articles/[a-z0-9-]+\.(svg|png|jpg|jpeg|webp)$#', $src);
}

/** Chuẩn hóa một bài (từ file hoặc từ database): điền giá trị mặc định, bỏ giá trị không hợp lệ. Trả về null nếu thiếu trường bắt buộc. */
function articleNormalize(array $article): ?array
{
    $categories = articleCategories();
    $slug = $article['slug'] ?? '';
    // Slug chỉ gồm a-z, 0-9, gạch ngang: an toàn để đưa lên URL
    if (!is_string($slug) || !preg_match('/^[a-z0-9-]+$/', $slug)) {
        return null;
    }
    if (empty($article['title']) || empty($article['content']) || !is_array($article['content'])) {
        return null;
    }
    if (!isset($categories[$article['category'] ?? ''])) {
        $article['category'] = 'general';
    }
    $article['summary']  = (string)($article['summary'] ?? '');
    $article['quick']    = (string)($article['quick'] ?? '');
    $article['date']     = (string)($article['date'] ?? '');
    $article['order']    = (int)($article['order'] ?? 100);
    $article['mustRead'] = !empty($article['mustRead']); // true = bài nền tảng, hiện nhãn "Đọc trước khi tập"
    $article['imageAlt'] = (string)($article['imageAlt'] ?? '');
    $article['image']    = articleImageOk($article['image'] ?? null) ? $article['image'] : '';
    $article['tags']     = array_values(array_filter((array)($article['tags'] ?? []), 'is_string'));
    $article['sources']  = is_array($article['sources'] ?? null) ? $article['sources'] : [];
    return $article;
}

/** Bài viết dạng FILE trong content/articles/ (bài hệ thống, chỉ sửa bằng cách sửa file). Trả về [slug => bài], chưa sắp xếp. */
function loadFileArticles(): array
{
    $list = [];
    foreach (glob(__DIR__ . '/../content/articles/*.php') ?: [] as $file) {
        $article = include $file;
        if (!is_array($article)) {
            continue;
        }
        $article = articleNormalize($article);
        if ($article !== null) {
            $list[$article['slug']] = $article;
        }
    }
    return $list;
}

/** Biến một dòng trong bảng articles (do admin soạn) thành mảng bài viết cùng dạng với bài file. */
function articleFromDbRow(array $row): ?array
{
    require_once __DIR__ . '/article-markup.php';
    return articleNormalize([
        'slug'     => (string)$row['slug'],
        'title'    => (string)$row['title'],
        'category' => (string)$row['category'],
        'tags'     => array_values(array_filter(array_map('trim', explode(',', (string)($row['tags'] ?? ''))), 'strlen')),
        'summary'  => (string)($row['summary'] ?? ''),
        'quick'    => (string)($row['quick'] ?? ''),
        'image'    => (string)($row['image'] ?? ''),
        'imageAlt' => (string)($row['image_alt'] ?? ''),
        'date'     => (string)($row['published_date'] ?? ''),
        'order'    => (int)($row['sort_order'] ?? 100),
        'sources'  => articleParseSources((string)($row['sources'] ?? '')),
        'content'  => articleParseMarkup((string)($row['body'] ?? '')),
    ]);
}

/** Tìm một bài do admin soạn theo slug (kể cả bản nháp). Dùng cho xem trước. Không có bảng hoặc không thấy thì trả về null. */
function articleDbBySlug(string $slug): ?array
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM articles WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? articleFromDbRow($row) : null;
    } catch (Throwable $e) {
        return null; // bảng articles chưa được import: bỏ qua
    }
}

/**
 * Tất cả bài người đọc thấy: bài file + bài admin đã ĐĂNG (bảng articles), đã sắp xếp.
 * Bảng articles chưa import hoặc lỗi database thì chỉ dùng bài file (trang vẫn chạy).
 */
function loadArticles(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $list = loadFileArticles();

    global $pdo;
    if ($pdo instanceof PDO) {
        try {
            $rows = $pdo->query("SELECT * FROM articles WHERE status = 'PUBLISHED'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $article = articleFromDbRow($row);
                if ($article !== null && !isset($list[$article['slug']])) { // bài file luôn được ưu tiên khi trùng slug
                    $list[$article['slug']] = $article;
                }
            }
        } catch (Throwable $e) {
            // chưa import sql/admin_features.sql: bỏ qua
        }
    }

    // Số thứ tự nhỏ trước; bằng nhau thì ngày mới hơn trước
    uasort($list, function ($a, $b) {
        return [$a['order'], $b['date']] <=> [$b['order'], $a['date']];
    });

    return $cache = $list;
}

/** Ghi nhận 1 lượt xem bài (đếm theo ngày, mỗi phiên đăng nhập chỉ đếm 1 lần cho mỗi bài). Lỗi thì bỏ qua, không ảnh hưởng người đọc. */
function articleRecordView(string $slug): void
{
    global $pdo;
    if (!($pdo instanceof PDO) || isset($_SESSION['ar_seen'][$slug])) {
        return;
    }
    $_SESSION['ar_seen'][$slug] = true;
    try {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare('UPDATE article_views SET views = views + 1 WHERE slug = ? AND view_date = ?');
        $stmt->execute([$slug, $today]);
        if ($stmt->rowCount() === 0) {
            $pdo->prepare('INSERT INTO article_views (slug, view_date, views) VALUES (?, ?, 1)')->execute([$slug, $today]);
        }
    } catch (Throwable $e) {
        // bảng article_views chưa import hoặc 2 lượt xem trùng cùng lúc: bỏ qua
    }
}

/**
 * Ghi nhận người đang đăng nhập đã mở một bài nền tảng, để trang chủ không nhắc lại bài đó.
 * INSERT IGNORE: đã có dòng thì bỏ qua. user_id lấy từ session, không lấy từ request.
 */
function articleRecordRead(string $slug): void
{
    global $pdo;
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if (!($pdo instanceof PDO) || $userId <= 0) {
        return;
    }
    try {
        $pdo->prepare('INSERT IGNORE INTO article_reads (user_id, slug) VALUES (?, ?)')->execute([$userId, $slug]);
    } catch (Throwable $e) {
        // bảng article_reads chưa import: bỏ qua, trang chủ sẽ vẫn nhắc đọc
    }
}

/** Số phút đọc ước tính (khoảng 180 từ mỗi phút), tối thiểu 1 phút. */
function articleReadingMinutes(array $article): int
{
    $text = (string)($article['quick'] ?? '');
    foreach ($article['content'] as $block) {
        $value = $block[1] ?? '';
        if (is_array($value)) {
            $flat = [];
            array_walk_recursive($value, function ($v) use (&$flat) { $flat[] = (string)$v; });
            $value = implode(' ', $flat);
        }
        $text .= ' ' . $value;
    }
    $words = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
    return max(1, (int)ceil($words / 180));
}

/** Escape chữ rồi đổi **đậm** thành <strong>. Kết quả an toàn để in ra HTML. */
function articleText(string $text): string
{
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe);
}

/** Nhãn "Bằng chứng: ..." (huy hiệu màu + chữ) cho một mức; mức lạ thì coi là "Chưa kiểm chứng". */
function articleLevelBadge(string $level): string
{
    $levels = articleLevels();
    if (!isset($levels[$level])) {
        $level = 'unverified';
    }
    [$label, , $class] = $levels[$level];
    return '<span class="ar-ev ' . htmlspecialchars($class) . '">' . htmlspecialchars($label) . '</span>';
}

/** In nội dung bài viết từ danh sách khối. Loại khối lạ bị bỏ qua. */
function renderArticleBlocks(array $blocks): void
{
    foreach ($blocks as $block) {
        if (!is_array($block) || count($block) < 2) {
            continue;
        }
        [$type, $value] = $block;
        switch ($type) {
            case 'p':
                echo '<p>' . articleText((string)$value) . "</p>\n";
                break;
            case 'h2':
                echo '<h2>' . articleText((string)$value) . "</h2>\n";
                break;
            case 'ul':
            case 'ol':
                echo '<' . $type . '>';
                foreach ((array)$value as $item) {
                    echo '<li>' . articleText((string)$item) . '</li>';
                }
                echo '</' . $type . ">\n";
                break;
            case 'tip':
                echo '<aside class="ar-tip"><b>Lưu ý</b><p>' . articleText((string)$value) . "</p></aside>\n";
                break;
            case 'table':
                echo '<div class="ar-table-wrap"><table class="ar-table"><thead><tr>';
                foreach ((array)($value['head'] ?? []) as $cell) {
                    echo '<th>' . articleText((string)$cell) . '</th>';
                }
                echo '</tr></thead><tbody>';
                foreach ((array)($value['rows'] ?? []) as $row) {
                    echo '<tr>';
                    foreach ((array)$row as $cell) {
                        echo '<td>' . articleText((string)$cell) . '</td>';
                    }
                    echo '</tr>';
                }
                echo "</tbody></table></div>\n";
                break;
            case 'figure':
                $v = (array)$value;
                if (!articleImageOk($v['src'] ?? null)) {
                    break;
                }
                echo '<figure class="ar-fig"><img src="' . htmlspecialchars($v['src']) . '" alt="' . htmlspecialchars((string)($v['alt'] ?? '')) . '" loading="lazy">';
                if (!empty($v['caption'])) {
                    echo '<figcaption>' . articleText((string)$v['caption']) . '</figcaption>';
                }
                echo "</figure>\n";
                break;
            case 'evidence':
                $v = (array)$value;
                echo '<p class="ar-evline"><b>Mức độ bằng chứng:</b> ' . articleLevelBadge((string)($v['level'] ?? ''));
                if (!empty($v['text'])) {
                    echo ' ' . articleText((string)$v['text']);
                }
                echo "</p>\n";
                break;
            case 'study':
                $v = (array)$value;
                $statuses = articleStatuses();
                echo '<section class="ar-study"><h3>' . articleText((string)($v['title'] ?? 'Đánh giá nghiên cứu')) . '</h3><ul>';
                foreach ((array)($v['rows'] ?? []) as $row) {
                    $row = (array)$row;
                    $st = isset($statuses[$row[1] ?? '']) ? $row[1] : 'unk';
                    [$stLabel, $stIcon, $stClass] = $statuses[$st];
                    echo '<li><span class="ar-st ' . htmlspecialchars($stClass) . '"><i aria-hidden="true">' . htmlspecialchars($stIcon) . '</i> ' . htmlspecialchars($stLabel) . '</span>';
                    echo '<div><b>' . articleText((string)($row[0] ?? '')) . '</b><p>' . articleText((string)($row[2] ?? '')) . '</p></div></li>';
                }
                echo "</ul></section>\n";
                break;
            case 'principles':
                echo '<ol class="ar-principles">';
                foreach ((array)$value as $item) {
                    $item = (array)$item;
                    echo '<li><b>' . articleText((string)($item[0] ?? '')) . '</b><p>' . articleText((string)($item[1] ?? '')) . '</p>';
                    echo '<span class="ar-evtag">Bằng chứng: ' . articleLevelBadge((string)($item[2] ?? 'unverified')) . '</span></li>';
                }
                echo "</ol>\n";
                break;
            case 'summary':
                echo '<h2>Tóm tắt</h2><ul class="ar-summary">';
                foreach ((array)$value as $item) {
                    echo '<li>' . articleText((string)$item) . '</li>';
                }
                echo "</ul>\n";
                break;
            case 'faq':
                echo '<h2>Câu hỏi thường gặp</h2>';
                foreach ((array)$value as $qa) {
                    $qa = (array)$qa;
                    echo '<h3>' . articleText((string)($qa[0] ?? '')) . '</h3><p>' . articleText((string)($qa[1] ?? '')) . '</p>';
                }
                echo "\n";
                break;
        }
    }
}

/** Ngày YYYY-MM-DD → dd/mm/YYYY (sai định dạng thì trả về rỗng) */
function articleDate(string $date): string
{
    $t = strtotime($date);
    return $t ? date('d/m/Y', $t) : '';
}

/**
 * Link tới nguồn: chỉ tạo từ pmid (chỉ số) và doi (đúng dạng 10.xxxx/...), nên không thể chèn link lạ.
 * Trả về mảng ['pubmed' => url|'', 'doi' => url|''].
 */
function articleSourceLinks(array $s): array
{
    $pmid = (string)($s['pmid'] ?? '');
    $doi  = (string)($s['doi'] ?? '');
    return [
        'pubmed' => preg_match('/^[0-9]{1,9}$/', $pmid) ? 'https://pubmed.ncbi.nlm.nih.gov/' . $pmid . '/' : '',
        'doi'    => preg_match('#^10\.[0-9]{4,9}/[-._;()/:A-Za-z0-9]+$#', $doi) ? 'https://doi.org/' . $doi : '',
    ];
}

/** In danh sách "Nguồn tham khảo" (danh sách số, đánh số theo thứ tự trong bài). */
function renderArticleSources(array $sources): void
{
    if (empty($sources)) {
        return;
    }
    echo '<ol class="ar-sources">';
    foreach ($sources as $s) {
        if (!is_array($s)) {
            continue;
        }
        $links = articleSourceLinks($s);
        echo '<li>' . htmlspecialchars((string)($s['authors'] ?? '')) . ' (' . htmlspecialchars((string)($s['year'] ?? '')) . '). ';
        echo '<i>' . htmlspecialchars((string)($s['title'] ?? '')) . '</i> ';
        echo htmlspecialchars((string)($s['journal'] ?? ''));
        if (!empty($s['detail'])) {
            echo ', ' . htmlspecialchars((string)$s['detail']);
        }
        echo '.';
        if ($links['pubmed'] !== '') {
            echo ' <a href="' . htmlspecialchars($links['pubmed']) . '" target="_blank" rel="noopener noreferrer">PubMed ' . htmlspecialchars((string)$s['pmid']) . '</a>';
        }
        if ($links['doi'] !== '') {
            echo ' <a href="' . htmlspecialchars($links['doi']) . '" target="_blank" rel="noopener noreferrer">DOI</a>';
        }
        echo '</li>';
    }
    echo "</ol>\n";
}
