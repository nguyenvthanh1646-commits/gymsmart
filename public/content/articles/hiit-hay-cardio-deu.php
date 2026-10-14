<?php
/** Bài viết: HIIT và cardio đều. Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'hiit-hay-cardio-deu',
    'title'    => 'HIIT hay cardio cường độ vừa: cái nào giảm mỡ tốt hơn?',
    'category' => 'training',
    'tags'     => ['Giảm mỡ', 'Cardio'],
    'summary'  => 'HIIT và cardio đều giảm mỡ tương đương nhau; HIIT tốn ít thời gian hơn khoảng 40%.',
    'quick'    => 'Phân tích gộp 13 nghiên cứu cho thấy HIIT và cardio cường độ vừa **giảm mỡ toàn thân tương đương** (SMD -0,82 và -0,85), trong khi HIIT cần ít hơn khoảng **40% thời gian tập**. Cách tốt nhất là cách bạn duy trì được.',
    'image'    => '/img/articles/hiit-hay-cardio-deu.svg',
    'imageAlt' => 'Ảnh minh họa: HIIT và cardio cường độ vừa',
    'date'     => '2026-10-14',
    'order'    => 11,
    'sources'  => [
        ['authors' => 'Wewege M, van den Berg R, Ward RE và cs.', 'year' => 2017, 'title' => 'The effects of high-intensity interval training vs. moderate-intensity continuous training on body composition in overweight and obese adults: a systematic review and meta-analysis', 'journal' => 'Obes Rev', 'detail' => '18(6):635-646', 'pmid' => '28401638', 'doi' => '10.1111/obr.12532'],
    ],
    'content'  => [
        ['p', 'HIIT (tập ngắt quãng cường độ cao) xen kẽ đoạn gắng sức với đoạn nghỉ. Cardio cường độ vừa (MICT) là chạy hoặc đạp xe đều ở nhịp vừa phải. Cái nào giảm mỡ tốt hơn?'],

        ['h2', 'Nghiên cứu nói gì'],
        ['figure', ['src' => '/img/articles/fig-hiit.svg', 'alt' => 'Biểu đồ mức giảm mỡ của HIIT và cardio đều gần như bằng nhau', 'caption' => 'Nguồn: Wewege và cs., Obes Rev 2017. 13 nghiên cứu, người thừa cân/béo phì 18 đến 45 tuổi. Chỉ số 60% suy ra từ mức “ít hơn khoảng 40%”.']],
        ['p', 'Phân tích gộp 13 nghiên cứu (sàng từ 1.334 bài), trung bình 10 tuần, 3 buổi/tuần, ở người trưởng thành 18 đến 45 tuổi. Mức giảm mỡ toàn thân tương đương nhau (SMD -0,82 với HIIT, -0,85 với cardio đều), nhưng HIIT tốn ít hơn khoảng 40% thời gian tập.'],
        ['evidence', ['level' => 'fair', 'text' => 'Phân tích gộp các nghiên cứu nhỏ, thời gian ngắn, đối tượng thừa cân hoặc béo phì tuổi 18 đến 45.']],

        ['h2', 'Chọn thế nào'],
        ['ul', [
            '**Ít thời gian:** HIIT cho kết quả tương đương trong thời gian ngắn hơn.',
            '**Mới tập hoặc có vấn đề khớp, tim mạch:** cardio nhẹ đến vừa dễ và an toàn hơn để bắt đầu.',
            '**Thích gì tập nấy:** hiệu quả đến từ việc đều đặn nhiều tuần.',
        ]],
        ['tip', 'HIIT đòi hỏi thể lực và khởi động kỹ. Nếu bạn có bệnh tim, huyết áp cao hoặc lâu không vận động, hãy hỏi bác sĩ trước khi tập cường độ cao.'],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Wewege và cs., Obes Rev 2017', 'rows' => [
            ['Thiết kế phương pháp', 'ok', 'Tổng quan hệ thống + phân tích gộp, tìm kiếm có hệ thống (sàng 1.334 bài, giữ 13 nghiên cứu).'],
            ['Tính lặp lại, kiểm chứng', 'unk', 'Chưa kiểm chứng.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Obes Rev, có trên PubMed. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'unk', 'Chưa kiểm chứng tài trợ và xung đột lợi ích; phân tích gộp không có phê duyệt đạo đức riêng.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'part', 'Có độ lớn hiệu ứng chuẩn hóa (SMD). Khoảng tin cậy, p-value và số người: chưa kiểm chứng.'],
        ]]],

        ['faq', [
            ['HIIT có đốt mỡ nhiều hơn không?', 'Theo phân tích này, mức giảm mỡ tương đương, chỉ thời gian tập ít hơn.'],
            ['Tập HIIT mấy buổi một tuần?', 'Các nghiên cứu được gộp tập khoảng 3 buổi/tuần. Bài không đánh giá tần suất khác.'],
        ]],
        ['summary', [
            'HIIT và cardio đều giảm mỡ tương đương (SMD -0,82 và -0,85).',
            'HIIT tốn ít hơn khoảng 40% thời gian.',
            'Chọn loại bạn tập đều được.',
        ]],
    ],
];
