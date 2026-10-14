<?php
/** Bài viết: số bước chân mỗi ngày. Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'bao-nhieu-buoc-moi-ngay',
    'title'    => 'Đi bao nhiêu bước mỗi ngày là đủ? Không nhất thiết 10.000',
    'category' => 'health',
    'tags'     => ['Đi bộ', 'Tuổi thọ'],
    'summary'  => 'Đi nhiều bước hơn gắn với nguy cơ tử vong thấp hơn, và lợi ích rõ từ khoảng 6.000 đến 8.000 bước/ngày ở người từ 60 tuổi.',
    'quick'    => 'Phân tích gộp 15 nghiên cứu theo dõi gần 47.500 người cho thấy nguy cơ tử vong giảm dần khi đi nhiều bước hơn, và lợi ích chững lại ở khoảng **6.000 đến 8.000 bước/ngày** (từ 60 tuổi) hoặc **8.000 đến 10.000 bước/ngày** (dưới 60 tuổi). Đây là nghiên cứu quan sát nên chỉ cho thấy mối liên quan.',
    'image'    => '/img/articles/bao-nhieu-buoc-moi-ngay.svg',
    'imageAlt' => 'Ảnh minh họa: đi bộ và số bước chân mỗi ngày',
    'date'     => '2026-10-14',
    'order'    => 9,
    'sources'  => [
        ['authors' => 'Paluch AE, Bajpai S, Bassett DR và cs.', 'year' => 2022, 'title' => 'Daily steps and all-cause mortality: a meta-analysis of 15 international cohorts', 'journal' => 'Lancet Public Health', 'detail' => '7(3):e219-e228', 'pmid' => '35247352', 'doi' => '10.1016/S2468-2667(21)00302-9'],
    ],
    'content'  => [
        ['p', 'Con số 10.000 bước quen thuộc đến từ chiến dịch quảng cáo, không phải từ nghiên cứu. Nghiên cứu dưới đây xem xét dữ liệu thật về số bước và tuổi thọ.'],

        ['h2', 'Nghiên cứu nói gì'],
        ['figure', ['src' => '/img/articles/fig-buoc-chan.svg', 'alt' => 'Biểu đồ nguy cơ tử vong theo bốn nhóm số bước chân mỗi ngày', 'caption' => 'Nguồn: Paluch và cs., Lancet Public Health 2022. 47.471 người, 3.013 ca tử vong. Nghiên cứu quan sát.']],
        ['p', 'Nhóm tác giả gộp 15 đoàn hệ quốc tế, gồm 47.471 người trưởng thành, theo dõi trung vị 7,1 năm, ghi nhận 3.013 ca tử vong. So với nhóm đi ít nhất (trung vị 3.553 bước/ngày), nguy cơ tử vong thấp hơn:'],
        ['table', ['head' => ['Trung vị bước/ngày', 'Tỷ số nguy cơ (HR)', 'Khoảng tin cậy 95%'], 'rows' => [
            ['3.553 (mốc so sánh)', '1,00', '–'],
            ['5.801', '0,60', '0,51 đến 0,71'],
            ['7.842', '0,55', '0,49 đến 0,62'],
            ['10.901', '0,47', '0,39 đến 0,57'],
        ]]],
        ['p', 'Lợi ích chững lại ở khoảng 6.000 đến 8.000 bước/ngày với người từ 60 tuổi, và 8.000 đến 10.000 bước/ngày với người dưới 60 tuổi.'],
        ['evidence', ['level' => 'fair', 'text' => 'Rất nhiều người, nhiều quốc gia, kết quả nhất quán, nhưng là nghiên cứu quan sát: không loại trừ được việc người khỏe thì đi nhiều hơn.']],

        ['h2', 'Cách áp dụng'],
        ['ul', [
            'Đang đi ít (dưới 4.000 bước): mục tiêu đầu tiên là thêm 1.000 bước mỗi ngày.',
            'Từ 60 tuổi: 6.000 đến 8.000 bước là mốc hợp lý, không cần ép lên 10.000.',
            'Chia nhỏ trong ngày: đi bộ sau bữa ăn, đỗ xe xa, đi cầu thang.',
        ]],
        ['tip', 'Nếu có bệnh tim, đau khớp hoặc đang hồi phục chấn thương, hãy hỏi bác sĩ về mức đi bộ phù hợp.'],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Paluch và cs., Lancet Public Health 2022', 'rows' => [
            ['Thiết kế phương pháp', 'part', 'Phân tích gộp 15 đoàn hệ có theo dõi dài. Đoàn hệ quan sát, không phải thử nghiệm ngẫu nhiên nên không chứng minh nhân quả.'],
            ['Tính lặp lại, kiểm chứng', 'ok', 'Nhiều đoàn hệ độc lập ở nhiều nước cho xu hướng giống nhau.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Lancet Public Health, có trên PubMed. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'part', 'Có công bố nguồn tài trợ (Trung tâm Kiểm soát và Phòng ngừa Dịch bệnh Hoa Kỳ). Phê duyệt đạo đức của từng đoàn hệ: chưa kiểm chứng.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'ok', 'Có HR kèm khoảng tin cậy 95% cho từng nhóm. P-value từng nhóm: chưa kiểm chứng.'],
        ]]],

        ['faq', [
            ['10.000 bước có vô ích không?', 'Không. Nguy cơ vẫn thấp hơn ở nhóm đi nhiều hơn, nhưng lợi ích tăng thêm sau khoảng 8.000 đến 10.000 bước là nhỏ dần.'],
            ['Đo bước bằng điện thoại có chính xác không?', 'Mỗi thiết bị lệch ít nhiều. Hãy theo dõi xu hướng của chính bạn thay vì so từng con số.'],
        ]],
        ['summary', [
            'Đi nhiều bước hơn gắn với nguy cơ tử vong thấp hơn (HR 0,47 ở nhóm khoảng 10.900 bước).',
            'Lợi ích chững lại quanh 6.000 đến 8.000 bước (từ 60 tuổi) hoặc 8.000 đến 10.000 (dưới 60).',
            'Là nghiên cứu quan sát: chỉ cho thấy mối liên quan.',
        ]],
    ],
];
