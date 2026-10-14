<?php
/** Bài viết: tạ nhẹ hay tạ nặng. Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'ta-nang-hay-ta-nhe',
    'title'    => 'Tạ nhẹ hay tạ nặng: cái nào tăng cơ tốt hơn?',
    'category' => 'training',
    'tags'     => ['Tăng cơ', 'Phân tích gộp'],
    'summary'  => 'Khi tập đến sát kiệt sức, tạ nhẹ và tạ nặng tăng cơ tương đương; tạ nặng cho sức mạnh tối đa tăng nhiều hơn.',
    'quick'    => 'Phân tích gộp 21 nghiên cứu: nếu mọi hiệp tập **đến sát kiệt sức**, tạ nhẹ (từ 60% 1RM trở xuống) tăng cơ **tương đương** tạ nặng. Tạ nặng vẫn cho sức mạnh tối đa (1RM) tăng nhiều hơn.',
    'image'    => '/img/articles/ta-nang-hay-ta-nhe.svg',
    'imageAlt' => 'Ảnh minh họa: so sánh tập với tạ nhẹ và tạ nặng',
    'date'     => '2026-10-14',
    'order'    => 8,
    'sources'  => [
        ['authors' => 'Schoenfeld BJ, Grgic J, Ogborn D, Krieger JW.', 'year' => 2017, 'title' => 'Strength and hypertrophy adaptations between low- vs. high-load resistance training: a systematic review and meta-analysis', 'journal' => 'J Strength Cond Res', 'detail' => '31(12):3508-3523', 'pmid' => '28834797', 'doi' => '10.1519/JSC.0000000000002200'],
    ],
    'content'  => [
        ['p', 'Nhiều người nghĩ muốn to phải nâng thật nặng. Phân tích gộp dưới đây cho thấy bức tranh tinh tế hơn.'],

        ['h2', 'Nghiên cứu nói gì'],
        ['figure', ['src' => '/img/articles/fig-ta-nang.svg', 'alt' => 'Tóm tắt kết luận: tạ nặng tăng sức mạnh 1RM nhiều hơn, tăng cơ tương đương', 'caption' => 'Nguồn: Schoenfeld và cs., J Strength Cond Res 2017 (21 nghiên cứu, mọi hiệp tập đến sát kiệt sức). Đây là hình minh họa kết luận, không phải số đo.']],
        ['p', 'Gộp 21 nghiên cứu kéo dài tối thiểu 6 tuần, trong đó “tạ nhẹ” là từ 60% 1RM trở xuống. Kết luận: sức mạnh tối đa (1RM) tăng nhiều hơn với tạ nặng; sức mạnh đẳng trường không khác biệt có ý nghĩa; **kích thước cơ tăng tương đương** giữa hai cách.'],
        ['evidence', ['level' => 'fair', 'text' => 'Phân tích gộp 21 nghiên cứu, nhưng số liệu định lượng chưa được kiểm chứng trong bài này và các nghiên cứu có thời gian ngắn.']],

        ['h2', 'Điều kiện quan trọng: tập đến sát kiệt sức'],
        ['p', 'Kết luận trên chỉ đúng khi mỗi hiệp tạ nhẹ được thực hiện đến gần mức không làm thêm được cái nào nữa. Nâng tạ nhẹ rồi dừng sớm khi còn dư sức thì không tạo kích thích tương tự.'],
        ['ul', [
            '**Tạ nhẹ phù hợp khi:** đau khớp, chưa quen tạ nặng, tập ở nhà ít dụng cụ.',
            '**Tạ nặng phù hợp khi:** bạn muốn tăng sức mạnh tối đa hoặc ít thời gian cho mỗi hiệp.',
            '**Đan xen cả hai** cũng là lựa chọn hợp lý để đỡ chán và đỡ mỏi khớp.',
        ]],
        ['tip', 'Tập đến sát kiệt sức với tạ nặng cần kỹ thuật tốt và người bảo hộ. Người mới nên dừng khi còn khoảng 1 đến 3 cái trong tay.'],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Schoenfeld và cs., J Strength Cond Res 2017', 'rows' => [
            ['Thiết kế phương pháp', 'ok', 'Phân tích gộp 21 nghiên cứu, tối thiểu 6 tuần, tiêu chí rõ ràng về mức tạ.'],
            ['Tính lặp lại, kiểm chứng', 'unk', 'Chưa kiểm chứng.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên J Strength Cond Res, có trên PubMed. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'unk', 'Chưa kiểm chứng nguồn tài trợ; phân tích gộp không có phê duyệt đạo đức riêng.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'unk', 'Bản tóm tắt không nêu số liệu định lượng. Hãy mở bài gốc để xem p-value, khoảng tin cậy và độ lớn hiệu ứng.'],
        ]]],

        ['faq', [
            ['Tạ nhẹ có an toàn hơn không?', 'Thường ít gánh nặng lên khớp hơn, nhưng tập đến sát kiệt sức với bất kỳ mức tạ nào đều cần kỹ thuật đúng.'],
            ['Tập tạ nhẹ có cần nhiều cái hơn không?', 'Có. Để đến sát kiệt sức với tạ nhẹ, mỗi hiệp thường dài hơn nhiều so với tạ nặng.'],
        ]],
        ['summary', [
            'Tập đến sát kiệt sức thì tạ nhẹ và tạ nặng tăng cơ tương đương.',
            'Tạ nặng cho 1RM tăng nhiều hơn.',
            'Chọn mức tạ bạn tập đúng kỹ thuật và duy trì được.',
        ]],
    ],
];
