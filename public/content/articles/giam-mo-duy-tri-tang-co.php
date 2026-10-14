<?php
return [
    'slug'     => 'giam-mo-duy-tri-tang-co',
    'title'    => 'Giảm mỡ, duy trì hay tăng cơ: ăn thế nào cho đúng',
    'category' => 'general',
    'summary'  => 'Mỗi mục tiêu cần một mức calo khác nhau. Giảm mỡ vừa phải, tăng cơ từ từ và giữ protein ổn định sẽ bền hơn so với làm quá tay.',
    'tags'     => ['Mục tiêu', 'Kiến thức nền'],
    'quick'    => 'Giảm mỡ thì ăn ít hơn TDEE khoảng 20%, tăng cơ thì nhiều hơn khoảng 10%, và cả hai đều cần giữ protein đủ và tập kháng lực đều.',
    'image'    => '/img/articles/giam-mo-duy-tri-tang-co.svg',
    'imageAlt' => 'Ảnh minh họa: ba mục tiêu giảm mỡ, duy trì, tăng cơ',
    'date'     => '2026-10-14',
    'order'    => 5,
    'sources'  => [
        ['authors' => 'Morton RW, Murphy KT, McKellar SR và cs.', 'year' => 2018, 'title' => 'A systematic review, meta-analysis and meta-regression of the effect of protein supplementation on resistance training-induced gains in muscle mass and strength in healthy adults', 'journal' => 'Br J Sports Med', 'detail' => '52(6):376-384', 'pmid' => '28698222', 'doi' => '10.1136/bjsports-2017-097608'],
    ],
    'content'  => [
        ['p', 'Cả ba mục tiêu đều dùng chung một nguyên tắc: điều chỉnh calo so với TDEE, giữ protein đủ và tập kháng lực đều. Khác nhau ở mức calo và tốc độ thay đổi cân nặng.'],

        ['table', [
            'head' => ['Mục tiêu', 'Calo so với TDEE', 'Tốc độ cân nặng hợp lý'],
            'rows' => [
                ['Giảm mỡ', 'ít hơn khoảng 20%', 'giảm khoảng 0,5 đến 1% cân nặng mỗi tuần'],
                ['Duy trì', 'bằng TDEE', 'giữ ổn định'],
                ['Tăng cơ', 'nhiều hơn khoảng 10%', 'tăng khoảng 0,25 đến 0,5% cân nặng mỗi tuần'],
            ],
        ]],

        ['h2', 'Giảm mỡ'],
        ['ul', [
            'Thâm hụt vừa phải để vẫn đủ sức tập và ít mất cơ. Cắt quá sâu dễ mệt, thèm ăn và ăn bù.',
            'Giữ protein ở mức 1,6 đến 1,8 g/kg để bảo vệ cơ.',
            'Ăn nhiều rau xanh để no lâu với ít calo.',
        ]],

        ['h2', 'Tăng cơ'],
        ['ul', [
            'Ăn dư một chút (khoảng 10%). Dư quá nhiều chỉ làm tăng mỡ.',
            'Tập kháng lực đều và tăng dần mức tạ. Không tập thì ăn nhiều cũng không lên cơ.',
            'Bữa trước và sau tập nên có cả protein và tinh bột.',
        ]],

        ['h2', 'Những thứ làm nên khác biệt ngoài chế độ ăn'],
        ['ul', [
            '**Ngủ 7 đến 9 giờ mỗi đêm:** thiếu ngủ làm giảm phục hồi và tăng cảm giác đói.',
            '**Uống đủ nước:** khát đôi khi bị nhầm với đói.',
            '**Kiên trì:** kết quả đến từ nhiều tuần làm đều, không phải vài ngày ăn kiêng khắt khe.',
        ]],
        ['tip', 'Đổi mục tiêu ngay trên trang Dinh dưỡng để xem calo và macro thay đổi thế nào. Nếu bạn có bệnh nền hoặc đang mang thai, hãy hỏi ý kiến bác sĩ hoặc chuyên gia dinh dưỡng trước khi ăn theo bất kỳ kế hoạch nào.'],

        ['evidence', ['level' => 'basic', 'text' => 'Các mức 20% và 10% so với TDEE, tốc độ thay đổi cân nặng và lời khuyên ngủ, uống nước là hướng dẫn thực hành phổ biến; bài này chưa dẫn nghiên cứu riêng cho từng con số. Riêng ý protein ở mức khoảng 1,6 g/kg có cơ sở từ phân tích gộp của Morton và cs. (xem bài Protein).']],
        ['faq', [
            ['Có nên vừa giảm mỡ vừa tăng cơ?', 'Người mới tập có thể thấy cả hai phần nào, nhưng tốc độ chậm; hãy chọn mục tiêu chính và theo dõi.'],
            ['Cân nặng không đổi thì sao?', 'Theo dõi trung bình 2 đến 3 tuần rồi điều chỉnh lượng ăn một chút thay vì thay đổi lớn.'],
        ]],
        ['summary', [
            'Giảm mỡ: ít hơn TDEE khoảng 20%. Tăng cơ: nhiều hơn khoảng 10%.',
            'Giữ protein đủ và tập kháng lực đều.',
            'Kiên trì nhiều tuần hiệu quả hơn ăn kiêng khắc nghiệt vài ngày.',
        ]],
    ],
];
