<?php
/**
 * Bài viết: Protein.
 * Cách thêm bài mới: sao chép file này, đổi tên (chỉ chữ thường, số, dấu gạch ngang), sửa nội dung.
 * Xem hướng dẫn đầy đủ trong includes/articles.php.
 */
return [
    'slug'     => 'protein-bao-nhieu-la-du',
    'title'    => 'Protein: cơ thể cần gì và ăn bao nhiêu là đủ',
    'category' => 'protein',
    'summary'  => 'Protein xây cơ, giúp no lâu và giữ cơ khi giảm mỡ. Người mới tập chỉ cần khoảng 1,6 g/kg, người có kinh nghiệm khoảng 1,8 g/kg.',
    'tags'     => ['Tăng cơ', 'Phân tích gộp'],
    'quick'    => 'Mức khuyến nghị của GymSmart là khoảng **1,6 g/kg/ngày** (người mới) đến **1,8 g/kg** (người có kinh nghiệm). Phân tích gộp 49 thử nghiệm cho thấy trên khoảng 1,62 g/kg/ngày thì không thấy thêm lợi ích về khối nạc.',
    'image'    => '/img/articles/protein-bao-nhieu-la-du.svg',
    'imageAlt' => 'Ảnh minh họa: thực phẩm giàu protein',
    'date'     => '2026-10-14',
    'order'    => 1,
    'sources'  => [
        ['authors' => 'Morton RW, Murphy KT, McKellar SR và cs.', 'year' => 2018, 'title' => 'A systematic review, meta-analysis and meta-regression of the effect of protein supplementation on resistance training-induced gains in muscle mass and strength in healthy adults', 'journal' => 'Br J Sports Med', 'detail' => '52(6):376-384', 'pmid' => '28698222', 'doi' => '10.1136/bjsports-2017-097608'],
    ],
    'content'  => [
        ['p', 'Protein (chất đạm) là nguyên liệu để cơ thể sửa chữa và xây dựng các mô, trong đó có cơ bắp. Mỗi gram protein cung cấp 4 kcal.'],

        ['h2', 'Protein làm gì trong cơ thể'],
        ['ul', [
            '**Sửa chữa và xây cơ:** sau buổi tập kháng lực, cơ bị tổn thương nhỏ và cần protein để phục hồi, lớn lên.',
            '**Tạo enzyme, hormone, kháng thể:** nhiều hoạt động sống của cơ thể phụ thuộc vào protein.',
            '**No lâu hơn:** protein giúp bạn đỡ đói hơn tinh bột và chất béo, rất hữu ích khi giảm mỡ.',
            '**Giữ cơ khi ăn ít calo:** khi thâm hụt calo, đủ protein giúp giảm mỡ mà ít mất cơ.',
            '**Tốn năng lượng để tiêu hóa:** khoảng 20 đến 30% calo của protein bị dùng cho chính việc tiêu hóa nó.',
        ]],

        ['h2', 'Ăn bao nhiêu là đủ'],
        ['p', 'GymSmart tính protein theo cân nặng và trình độ tập luyện trong hồ sơ của bạn:'],
        ['ul', [
            '**Người mới tập:** khoảng **1,6 g protein cho mỗi kg cân nặng** mỗi ngày.',
            '**Người đã có kinh nghiệm:** khoảng **1,8 g/kg** mỗi ngày.',
        ]],
        ['table', [
            'head' => ['Cân nặng', 'Người mới (1,6 g/kg)', 'Có kinh nghiệm (1,8 g/kg)'],
            'rows' => [
                ['50 kg', '80 g', '90 g'],
                ['60 kg', '96 g', '108 g'],
                ['70 kg', '112 g', '126 g'],
                ['80 kg', '128 g', '144 g'],
                ['90 kg', '144 g', '162 g'],
            ],
        ]],
        ['tip', 'Nhiều hơn không phải lúc nào cũng tốt hơn. Các nghiên cứu cho thấy lợi ích tăng cơ gần như đạt đỉnh quanh 1,6 g/kg mỗi ngày. Ăn thêm nhiều protein chỉ tốn tiền và chiếm chỗ của tinh bột, thứ cho bạn sức để tập.'],

        ['h2', 'Nguồn protein tốt'],
        ['table', [
            'head' => ['Thực phẩm (đã nấu chín)', 'Protein trong 100 g'],
            'rows' => [
                ['Ức gà', 'khoảng 31 g'],
                ['Thịt bò nạc', 'khoảng 26 g'],
                ['Tôm', 'khoảng 24 g'],
                ['Cá hồi', 'khoảng 22 g'],
                ['Trứng gà', 'khoảng 13 g'],
                ['Sữa chua Hy Lạp không đường', 'khoảng 10 g'],
                ['Đậu lăng', 'khoảng 9 g'],
                ['Đậu hũ', 'khoảng 8 g'],
            ],
        ]],
        ['p', 'Số liệu là ước lượng, thay đổi theo cách chế biến. Nên kết hợp cả nguồn động vật và thực vật.'],

        ['h2', 'Chia protein trong ngày thế nào'],
        ['p', 'Nên chia đều cho 3 đến 5 bữa, mỗi bữa khoảng 20 đến 40 g (tương đương 0,3 đến 0,4 g/kg), thay vì dồn hết vào bữa tối. Trang Dinh dưỡng của GymSmart đã chia sẵn theo từng bữa.'],

        ['h2', 'Những hiểu lầm hay gặp'],
        ['ul', [
            '**"Phải uống whey mới tăng cơ":** whey chỉ là cách tiện lợi. Thực phẩm thật hoàn toàn đủ nếu bạn ăn đủ lượng.',
            '**"Ăn nhiều đạm hại thận":** ở người thận khỏe, các nghiên cứu chưa chứng minh tác hại ở mức ăn của người tập thông thường.',
            '**"Ăn nhiều đạm là có cơ":** cơ chỉ lớn khi bạn tập kháng lực đều và ăn đủ calo.',
        ]],
        ['tip', 'Nếu bạn có bệnh thận, gout hoặc đang điều trị bệnh, hãy hỏi bác sĩ trước khi thay đổi lượng protein.'],

        ['h2', 'Cơ sở khoa học (chỉ để tham khảo)'],
        ['p', 'Mức 1,6 g/kg gần với điểm mà phân tích gộp của Morton và cs. (49 thử nghiệm, 1.863 người) thấy lợi ích về khối nạc ngừng tăng thêm: khoảng 1,62 g/kg/ngày. Bài này nói về người khỏe mạnh tập kháng lực.'],
        ['evidence', ['level' => 'strong', 'text' => 'Nhiều thử nghiệm ngẫu nhiên được gộp. Các ý còn lại trong bài (vai trò protein, mức no, hiểu lầm) là kiến thức nền, không dẫn nghiên cứu riêng.']],
        ['study', ['title' => 'Morton và cs., Br J Sports Med 2018', 'rows' => [
            ['Thiết kế phương pháp', 'ok', 'Phân tích gộp 49 thử nghiệm ngẫu nhiên có đối chứng, tìm trên 4 cơ sở dữ liệu.'],
            ['Tính lặp lại, kiểm chứng', 'ok', 'Gộp nhiều thử nghiệm độc lập; bài mở (PMC) để kiểm tra.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Br J Sports Med, có trên PubMed. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'part', 'Có tuyên bố xung đột lợi ích: tác giả cuối nhận tài trợ từ National Dairy Council Hoa Kỳ, tổ chức cũng tài trợ một số thử nghiệm được gộp.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'part', 'Có KTC 95% (khối nạc +0,30 kg, 0,09 đến 0,52). P-value cụ thể: chưa kiểm chứng.'],
        ]]],
        ['faq', [
            ['Người tập nặng có cần hơn 1,8 g/kg không?', 'Bài này không kết luận điều đó. Nếu bạn muốn ăn nhiều hơn vì no hoặc khẩu vị thì không sao, nhưng không có bằng chứng ở đây rằng sẽ tăng thêm cơ.'],
            ['Có cần bột protein không?', 'Xem bài **Bột protein (whey) có thực sự cần để tăng cơ không?**'],
        ]],
        ['summary', [
            'Khoảng 1,6 g/kg/ngày cho người mới, 1,8 g/kg cho người có kinh nghiệm.',
            'Trên khoảng 1,62 g/kg không thấy thêm lợi ích khối nạc trong phân tích gộp.',
            'Chia đều 3 đến 5 bữa; người có bệnh thận, gout nên hỏi bác sĩ.',
        ]],
    ],
];
