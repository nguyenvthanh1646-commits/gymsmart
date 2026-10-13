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
    'date'     => '2026-10-13',
    'order'    => 1,
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
    ],
];
