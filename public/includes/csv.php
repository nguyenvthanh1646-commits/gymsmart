<?php
/**
 * csv.php — xuất file CSV dùng chung (khu admin và trang tải dữ liệu cá nhân).
 *  - Thêm BOM UTF-8 ở đầu file để Excel hiển thị đúng tiếng Việt.
 *  - Ô bắt đầu bằng = + - @ (Tab, Enter) thì thêm dấu ' phía trước: chặn "CSV injection" (Excel chạy công thức trong ô).
 */

/** Ô bắt đầu bằng ký tự công thức thì thêm dấu ' để Excel coi là chữ thường. */
function csvSafe($value): string
{
    $text = (string)$value;
    if ($text !== '' && strpos("=+-@\t\r", $text[0]) !== false) {
        return "'" . $text;
    }
    return $text;
}

/**
 * Gửi header tải về và ghi CSV ra trình duyệt, rồi dừng.
 * @param string $filename tên file (chỉ chữ, số, dấu gạch), không có đuôi
 * @param array  $header   dòng tiêu đề
 * @param array  $rows     các dòng dữ liệu (mảng giá trị, đúng thứ tự cột)
 */
function csvDownload(string $filename, array $header, array $rows): void
{
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '', $filename);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, array_map('csvSafe', array_values($row)));
    }
    fclose($out);
    exit;
}
