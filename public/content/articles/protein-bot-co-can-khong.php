<?php
/** Bài viết: bổ sung protein (whey). Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'protein-bot-co-can-khong',
    'title'    => 'Bột protein (whey) có thực sự cần để tăng cơ không?',
    'category' => 'protein',
    'tags'     => ['Thực phẩm bổ sung', 'Phân tích gộp'],
    'summary'  => 'Bổ sung protein khi tập kháng lực có tăng thêm một ít cơ và sức mạnh, nhưng mức tăng nhỏ và không tăng thêm khi đã vượt khoảng 1,6 g/kg/ngày.',
    'quick'    => 'Phân tích gộp 49 thử nghiệm (1.863 người): bổ sung protein cho thêm **+0,30 kg** khối nạc và **+2,49 kg** sức mạnh 1RM so với không bổ sung. Trên khoảng **1,62 g/kg/ngày** thì không thấy thêm lợi ích về khối nạc. Bột protein tiện lợi, không phải điều kiện bắt buộc.',
    'image'    => '/img/articles/protein-bot-co-can-khong.svg',
    'imageAlt' => 'Ảnh minh họa: bột protein và thực phẩm giàu đạm',
    'date'     => '2026-10-14',
    'order'    => 13,
    'sources'  => [
        ['authors' => 'Morton RW, Murphy KT, McKellar SR và cs.', 'year' => 2018, 'title' => 'A systematic review, meta-analysis and meta-regression of the effect of protein supplementation on resistance training-induced gains in muscle mass and strength in healthy adults', 'journal' => 'Br J Sports Med', 'detail' => '52(6):376-384', 'pmid' => '28698222', 'doi' => '10.1136/bjsports-2017-097608'],
    ],
    'content'  => [
        ['p', 'Quảng cáo thường nói bột protein là thứ không thể thiếu. Phân tích gộp lớn nhất cho thấy câu chuyện khiêm tốn hơn.'],

        ['h2', 'Nghiên cứu nói gì'],
        ['figure', ['src' => '/img/articles/fig-protein-bot.svg', 'alt' => 'Biểu đồ mức tăng thêm sức mạnh và khối nạc khi bổ sung protein', 'caption' => 'Nguồn: Morton và cs., Br J Sports Med 2018. 49 thử nghiệm, 1.863 người. Mức tăng nhỏ nhưng có ý nghĩa thống kê.']],
        ['p', 'Tổng quan hệ thống và phân tích gộp 49 thử nghiệm ngẫu nhiên có đối chứng, 1.863 người, tìm trên Medline, Embase, CINAHL và SportDiscus. So với không bổ sung, nhóm bổ sung protein tăng thêm:'],
        ['table', ['head' => ['Chỉ số', 'Mức tăng thêm', 'Khoảng tin cậy 95%'], 'rows' => [
            ['Sức mạnh tối đa (1RM)', '+2,49 kg', '0,64 đến 4,33'],
            ['Khối nạc', '+0,30 kg', '0,09 đến 0,52'],
        ]]],
        ['p', 'Phân tích hồi quy cho thấy trên khoảng **1,62 g protein/kg/ngày** thì không tăng thêm khối nạc.'],
        ['evidence', ['level' => 'strong', 'text' => 'Gộp gần 50 thử nghiệm ngẫu nhiên; mức tăng có ý nghĩa thống kê nhưng nhỏ.']],

        ['h2', 'Áp dụng'],
        ['ul', [
            'Ăn đủ khoảng 1,6 g/kg/ngày từ thực phẩm là đã đạt gần đỉnh lợi ích.',
            'Bột protein hữu ích khi bạn khó ăn đủ đạm từ bữa chính, hoặc cần bữa phụ gọn.',
            'Không cần uống thêm khi đã đủ lượng.',
        ]],
        ['tip', 'Tác giả cuối của bài này nhận tài trợ và thù lao từ National Dairy Council Hoa Kỳ, tổ chức này cũng tài trợ một số thử nghiệm được gộp. Hãy đọc kết quả với sự thận trọng đó.'],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Morton và cs., Br J Sports Med 2018', 'rows' => [
            ['Thiết kế phương pháp', 'ok', 'Phân tích gộp 49 thử nghiệm ngẫu nhiên có đối chứng, tìm kiếm 4 cơ sở dữ liệu.'],
            ['Tính lặp lại, kiểm chứng', 'ok', 'Gộp nhiều thử nghiệm độc lập cho hướng kết quả cùng chiều; bài nằm trong PMC (mở) để kiểm tra.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Br J Sports Med, có trên PubMed. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'part', 'Có tuyên bố xung đột lợi ích (National Dairy Council tài trợ tác giả cuối và một số thử nghiệm được gộp). Phân tích gộp không có phê duyệt đạo đức riêng.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'part', 'Có KTC 95% và mức chênh bằng kg. P-value cụ thể: chưa kiểm chứng.'],
        ]]],

        ['faq', [
            ['Whey có tốt hơn đạm từ thực phẩm không?', 'Bài này gộp cả các dạng bổ sung và không kết luận một loại vượt trội. Thực phẩm thật vẫn đạt được mức tương tự.'],
            ['Ăn nhiều đạm có hại thận không?', 'Bài này không đánh giá. Người có bệnh thận hãy hỏi bác sĩ.'],
        ]],
        ['summary', [
            'Bổ sung protein cho thêm +0,30 kg khối nạc và +2,49 kg sức mạnh.',
            'Trên khoảng 1,62 g/kg/ngày không thấy thêm lợi ích khối nạc.',
            'Bột protein là tiện lợi, không bắt buộc.',
        ]],
    ],
];
