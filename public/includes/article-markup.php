<?php
/**
 * includes/article-markup.php — đổi chữ admin gõ trong form soạn bài thành các "khối" mà renderArticleBlocks() đọc được.
 *
 * Admin KHÔNG gõ HTML. Cú pháp đơn giản (mỗi dòng):
 *   Đoạn văn thường                 các dòng liền nhau là một đoạn; dòng trống ngăn cách đoạn
 *   ## Tiêu đề mục
 *   - ý chấm                         các dòng "- " liền nhau thành danh sách chấm
 *   1. ý đánh số                     danh sách số
 *   > Lưu ý nổi bật
 *   [hinh] /img/articles/x.svg | Mô tả ảnh | Chú thích
 *   [bang-chung] fair | Giải thích ngắn           (mức: strong, fair, thin, weak, basic, unverified)
 *   [bang]                           các dòng sau (đến dòng trống) là bảng, ngăn ô bằng |, dòng đầu là tiêu đề cột
 *   ? Câu hỏi | Câu trả lời          câu hỏi thường gặp
 *   [tom-tat]                        các dòng "- " sau đó là mục Tóm tắt
 *   **chữ đậm**                      dùng được trong mọi đoạn
 * Mọi chữ vẫn được escape khi hiển thị (articleText), nên không chèn được mã HTML.
 */

require_once __DIR__ . '/articles.php';

function articleParseMarkup(string $src): array
{
    $blocks = [];
    $para = [];
    $mode = '';        // '', ul, ol, table, faq, summary
    $items = [];       // dữ liệu của chế độ đang mở

    $flush = function () use (&$blocks, &$para, &$mode, &$items) {
        if ($para) {
            $blocks[] = ['p', implode(' ', $para)];
            $para = [];
        }
        if ($mode === 'ul' || $mode === 'ol') {
            if ($items) { $blocks[] = [$mode, $items]; }
        } elseif ($mode === 'table') {
            if (count($items) >= 2) {
                $blocks[] = ['table', ['head' => array_shift($items), 'rows' => $items]];
            }
        } elseif ($mode === 'faq') {
            if ($items) { $blocks[] = ['faq', $items]; }
        } elseif ($mode === 'summary') {
            if ($items) { $blocks[] = ['summary', $items]; }
        }
        $mode = '';
        $items = [];
    };
    $cells = fn(string $line): array => array_map('trim', explode('|', $line));

    foreach (preg_split('/\R/u', $src) ?: [] as $raw) {
        $line = trim($raw);

        if ($line === '') {
            $flush();
            continue;
        }
        if (str_starts_with($line, '## ')) {
            $flush();
            $blocks[] = ['h2', trim(substr($line, 3))];
        } elseif (str_starts_with($line, '> ')) {
            $flush();
            $blocks[] = ['tip', trim(substr($line, 2))];
        } elseif (str_starts_with($line, '[hinh]')) {
            $flush();
            [$srcImg, $alt, $caption] = array_pad($cells(substr($line, 6)), 3, '');
            if (articleImageOk($srcImg)) {
                $blocks[] = ['figure', ['src' => $srcImg, 'alt' => $alt, 'caption' => $caption]];
            }
        } elseif (str_starts_with($line, '[bang-chung]')) {
            $flush();
            [$level, $text] = array_pad(array_map('trim', explode('|', substr($line, 12), 2)), 2, '');
            if (isset(articleLevels()[$level]) && $text !== '') {
                $blocks[] = ['evidence', ['level' => $level, 'text' => $text]];
            }
        } elseif ($line === '[bang]') {
            $flush();
            $mode = 'table';
        } elseif ($line === '[tom-tat]') {
            $flush();
            $mode = 'summary';
        } elseif ($mode === 'table') {
            $items[] = $cells($line);
        } elseif (str_starts_with($line, '? ')) {
            if ($mode !== 'faq') { $flush(); $mode = 'faq'; }
            [$q, $a] = array_pad(array_map('trim', explode('|', substr($line, 2), 2)), 2, '');
            if ($q !== '' && $a !== '') { $items[] = [$q, $a]; }
        } elseif (str_starts_with($line, '- ')) {
            if ($mode !== 'ul' && $mode !== 'summary') { $flush(); $mode = 'ul'; }
            $items[] = trim(substr($line, 2));
        } elseif (preg_match('/^\d+\.\s+(.*)$/u', $line, $m)) {
            if ($mode !== 'ol') { $flush(); $mode = 'ol'; }
            $items[] = $m[1];
        } else {
            if ($mode !== '') { $flush(); }
            $para[] = $line;
        }
    }
    $flush();
    return $blocks;
}

/**
 * Mỗi dòng một nguồn:  Tác giả | Năm | Tiêu đề | Tạp chí | Chi tiết (tập, trang) | PMID | DOI
 * Chỉ giữ PMID và DOI đúng định dạng (link PubMed/DOI do code tự tạo từ hai trường này).
 */
function articleParseSources(string $src): array
{
    $out = [];
    foreach (preg_split('/\R/u', $src) ?: [] as $line) {
        if (trim($line) === '') {
            continue;
        }
        [$authors, $year, $title, $journal, $detail, $pmid, $doi] = array_pad(array_map('trim', explode('|', $line)), 7, '');
        if ($title === '') {
            continue;
        }
        $out[] = [
            'authors' => $authors,
            'year'    => preg_match('/^\d{4}$/', $year) ? (int)$year : '',
            'title'   => $title,
            'journal' => $journal,
            'detail'  => $detail,
            'pmid'    => preg_match('/^[0-9]{1,9}$/', $pmid) ? $pmid : '',
            'doi'     => preg_match('/^10\.[0-9]{4,9}\/[-._;()\/:A-Za-z0-9]+$/', $doi) ? $doi : '',
        ];
    }
    return $out;
}
