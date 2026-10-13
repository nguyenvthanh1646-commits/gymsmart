<?php
/**
 * includes/articles.php — kho bài viết kiến thức (protein, carb, fat...).
 *
 * MỖI BÀI VIẾT LÀ MỘT FILE trong thư mục content/articles/. Thêm bài mới = tạo thêm một file, không cần sửa code hay database.
 *
 * CÁCH THÊM BÀI MỚI
 *   1. Sao chép một file bài có sẵn (ví dụ content/articles/protein-bao-nhieu-la-du.php), đặt tên mới
 *      chỉ gồm chữ thường không dấu, số và dấu gạch ngang, ví dụ: nuoc-va-giac-ngu.php
 *   2. Sửa các trường:
 *        slug      : trùng tên file (không có .php), dùng trong đường dẫn /article.php?slug=...
 *        title     : tiêu đề
 *        category  : protein | carb | fat | general   (xem articleCategories() bên dưới)
 *        summary   : 1 đến 2 câu tóm tắt, hiện ở thẻ ngoài danh sách
 *        date      : ngày đăng, dạng YYYY-MM-DD
 *        order     : số thứ tự hiển thị, nhỏ hiện trước (bài cùng số thì bài mới hơn hiện trước)
 *        content   : danh sách khối nội dung, mỗi khối là [loại, giá trị]:
 *                      ['p',  'Đoạn văn']
 *                      ['h2', 'Tiêu đề mục']
 *                      ['ul', ['Ý 1', 'Ý 2']]          danh sách chấm
 *                      ['ol', ['Bước 1', 'Bước 2']]    danh sách số
 *                      ['tip', 'Lưu ý nổi bật']
 *                      ['table', ['head' => ['Cột 1', 'Cột 2'], 'rows' => [['a', 'b'], ['c', 'd']]]]
 *      Trong chữ có thể in đậm bằng **như thế này**. Không dùng HTML (mọi chữ đều được escape để an toàn).
 *   3. Lưu file, mở /articles.php là thấy bài mới.
 *
 * Muốn thêm chuyên mục mới: thêm một dòng vào articleCategories() và một màu trong css/articles.css.
 */

/** Chuyên mục: khóa => [tên hiển thị, chữ cái trên thẻ, tên class màu] */
function articleCategories(): array
{
    return [
        'protein' => ['Protein', 'P', 'ar-protein'],
        'carb'    => ['Carb (tinh bột)', 'C', 'ar-carb'],
        'fat'     => ['Chất béo', 'F', 'ar-fat'],
        'general' => ['Kiến thức chung', 'K', 'ar-general'],
    ];
}

/**
 * Đọc tất cả file trong content/articles/, trả về mảng [slug => bài viết], đã sắp xếp.
 * File lỗi hoặc thiếu trường bắt buộc sẽ bị bỏ qua (không làm sập trang).
 */
function loadArticles(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $categories = articleCategories();
    $list = [];
    foreach (glob(__DIR__ . '/../content/articles/*.php') ?: [] as $file) {
        $article = include $file;
        if (!is_array($article)) {
            continue;
        }
        $slug = $article['slug'] ?? '';
        // Slug chỉ gồm a-z, 0-9, gạch ngang: an toàn để đưa lên URL
        if (!is_string($slug) || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            continue;
        }
        if (empty($article['title']) || empty($article['content']) || !is_array($article['content'])) {
            continue;
        }
        if (!isset($categories[$article['category'] ?? ''])) {
            $article['category'] = 'general';
        }
        $article['summary'] = (string)($article['summary'] ?? '');
        $article['date']    = (string)($article['date'] ?? '');
        $article['order']   = (int)($article['order'] ?? 100);
        $list[$slug] = $article;
    }

    // Số thứ tự nhỏ trước; bằng nhau thì ngày mới hơn trước
    uasort($list, function ($a, $b) {
        return [$a['order'], $b['date']] <=> [$b['order'], $a['date']];
    });

    return $cache = $list;
}

/** Số phút đọc ước tính (khoảng 180 từ mỗi phút), tối thiểu 1 phút. */
function articleReadingMinutes(array $article): int
{
    $text = '';
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
        }
    }
}

/** Ngày YYYY-MM-DD → dd/mm/YYYY (sai định dạng thì trả về rỗng) */
function articleDate(string $date): string
{
    $t = strtotime($date);
    return $t ? date('d/m/Y', $t) : '';
}
