<?php
/** Bài viết: tần suất tập mỗi nhóm cơ. Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'tan-suat-tap-moi-nhom-co',
    'title'    => 'Tập mỗi nhóm cơ mấy buổi một tuần thì tăng cơ tốt nhất?',
    'category' => 'training',
    'tags'     => ['Tăng cơ', 'Phân tích gộp'],
    'summary'  => 'Tập mỗi nhóm cơ từ 2 buổi/tuần cho mức tăng cơ cao hơn 1 buổi/tuần, nhưng phần lớn lợi thế đó đến từ việc có thêm tổng số hiệp.',
    'quick'    => 'Một phân tích gộp 10 nghiên cứu cho thấy tập mỗi nhóm cơ **từ 2 buổi/tuần** tăng cơ nhiều hơn 1 buổi/tuần. Bản cập nhật năm 2019 (25 nghiên cứu) cho thấy khác biệt này biến mất khi **tổng số hiệp mỗi tuần bằng nhau**. Nghĩa là tần suất giúp chủ yếu vì nó cho bạn chia khối lượng thành nhiều buổi dễ chịu hơn.',
    'image'    => '/img/articles/tan-suat-tap-moi-nhom-co.svg',
    'imageAlt' => 'Ảnh minh họa: tập mỗi nhóm cơ ít nhất hai lần mỗi tuần',
    'date'     => '2026-10-14',
    'order'    => 6,
    'sources'  => [
        ['authors' => 'Schoenfeld BJ, Ogborn D, Krieger JW.', 'year' => 2016, 'title' => 'Effects of resistance training frequency on measures of muscle hypertrophy: a systematic review and meta-analysis', 'journal' => 'Sports Med', 'detail' => '46(11):1689-1697', 'pmid' => '27102172', 'doi' => '10.1007/s40279-016-0543-8'],
        ['authors' => 'Schoenfeld BJ, Grgic J, Krieger J.', 'year' => 2019, 'title' => 'How many times per week should a muscle be trained to maximize muscle hypertrophy? A systematic review and meta-analysis of studies examining the effects of resistance training frequency', 'journal' => 'J Sports Sci', 'detail' => '37(11):1286-1295', 'pmid' => '30558493', 'doi' => '10.1080/02640414.2018.1555906'],
    ],
    'content'  => [
        ['p', 'Câu hỏi “mỗi nhóm cơ nên tập mấy lần một tuần” quyết định cách bạn chia lịch tập: full-body 3 buổi, upper/lower 4 buổi hay push/pull/legs. Bài này tóm tắt những gì hai phân tích gộp trả lời được, và những gì chúng không trả lời.'],

        ['h2', 'Nghiên cứu nói gì'],
        ['figure', ['src' => '/img/articles/fig-tan-suat.svg', 'alt' => 'Biểu đồ hiệu ứng tăng cơ: 0,49 với tần suất cao hơn, 0,30 với tần suất thấp hơn', 'caption' => 'Nguồn: Schoenfeld và cs., Sports Med 2016 (10 nghiên cứu, P = 0,002). Bản cập nhật 2019 (25 nghiên cứu) thấy khác biệt biến mất khi cân bằng tổng số hiệp.']],
        ['p', 'Năm 2016, nhóm của Schoenfeld gộp 10 nghiên cứu kéo dài tối thiểu 4 tuần. Hiệu ứng lên tăng cơ là **0,49** khi tập mỗi nhóm cơ ít nhất 2 lần/tuần, so với **0,30** khi tập 1 lần/tuần (P = 0,002).'],
        ['p', 'Năm 2019, cùng nhóm gộp lại 25 nghiên cứu và so sánh khi **tổng khối lượng tập mỗi tuần được cân bằng**. Lúc này lợi thế của tần suất cao không còn đáng kể.'],
        ['evidence', ['level' => 'fair', 'text' => 'Có hai phân tích gộp nhưng gộp các nghiên cứu nhỏ, thời gian ngắn, và kết luận về tần suất đổi khi tính hay không tính tổng số hiệp.']],

        ['h2', 'Vậy tại sao vẫn nên chia lịch để mỗi nhóm cơ tập từ 2 lần/tuần'],
        ['ul', [
            '**Chia khối lượng:** 12 hiệp ngực dồn một buổi dễ làm bạn mệt, chất lượng hiệp cuối giảm. Chia thành 2 buổi, mỗi buổi 6 hiệp thường dễ giữ chất lượng hơn.',
            '**Luyện kỹ thuật thường xuyên:** động tác được lặp lại nhiều lần trong tuần.',
            '**Linh hoạt:** lỡ một buổi thì nhóm cơ đó vẫn còn buổi khác trong tuần.',
        ]],
        ['tip', 'Đây là lý do thực tế, không phải bằng chứng rằng tần suất tự nó làm cơ lớn hơn. Nếu lịch của bạn chỉ cho tập 1 buổi cho mỗi nhóm cơ nhưng đủ số hiệp, bạn vẫn đang làm đúng điều quan trọng.'],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Schoenfeld và cs., Sports Med 2016 và J Sports Sci 2019', 'rows' => [
            ['Thiết kế phương pháp', 'ok', 'Tổng quan hệ thống kèm phân tích gộp, có tiêu chí chọn bài (tối thiểu 4 tuần).'],
            ['Tính lặp lại, kiểm chứng', 'part', 'Hai phân tích gộp của cùng nhóm tác giả cho kết luận khác nhau tùy cách cân bằng khối lượng; chưa có nhóm độc lập kiểm lại trong bài này.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Sports Med và J Sports Sci, có trên PubMed. Chỉ số ảnh hưởng của tạp chí: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'unk', 'Bản tóm tắt không nêu nguồn tài trợ hay xung đột lợi ích. Phân tích gộp dùng dữ liệu đã công bố nên không có phê duyệt đạo đức riêng.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'part', 'Có hiệu ứng 0,49 so với 0,30 và P = 0,002 (bản 2016). Khoảng tin cậy và tổng số người: chưa kiểm chứng.'],
        ]]],

        ['h2', 'Cách áp dụng'],
        ['ul', [
            'Mới tập: full-body 3 buổi/tuần là đủ để mỗi nhóm cơ gặp 3 lần.',
            'Có thời gian 4 buổi: chia upper/lower, mỗi nhóm cơ 2 lần/tuần.',
            'Xem bài **Chia lịch tập để mỗi nhóm cơ được tập ít nhất 2 lần/tuần** để có mẫu lịch cụ thể.',
        ]],

        ['faq', [
            ['Tập 1 buổi/tuần mỗi nhóm cơ có tăng cơ không?', 'Có, vẫn tăng nếu bạn tập đủ hiệp và tăng dần mức tạ. Mức tăng trung bình thấp hơn trong phân tích 2016, nhưng khác biệt phần lớn biến mất khi tổng số hiệp bằng nhau.'],
            ['Tập mỗi nhóm cơ mỗi ngày có tốt hơn không?', 'Các nghiên cứu được gộp chủ yếu so sánh 1 đến 3 buổi/tuần; bài này không có đủ dữ liệu cho tần suất cao hơn. Cơ cần thời gian phục hồi nên không cần cố tập hằng ngày.'],
        ]],
        ['summary', [
            'Từ 2 buổi/tuần cho mỗi nhóm cơ tăng cơ nhiều hơn 1 buổi/tuần (0,49 so với 0,30, P = 0,002).',
            'Khi cân bằng tổng số hiệp, khác biệt không còn đáng kể.',
            'Hãy chọn tần suất cho phép bạn tập đủ hiệp, đều đặn và dễ duy trì.',
        ]],
    ],
];
