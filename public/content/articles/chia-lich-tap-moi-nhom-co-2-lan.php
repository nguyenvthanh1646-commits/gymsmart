<?php
/** Bài viết: cách chia lịch tập. Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'chia-lich-tap-moi-nhom-co-2-lan',
    'title'    => 'Chia lịch tập để mỗi nhóm cơ được tập ít nhất 2 lần/tuần',
    'category' => 'training',
    'tags'     => ['Lịch tập', 'Người mới'],
    'summary'  => 'Từ 2 đến 6 buổi mỗi tuần đều có cách chia để mỗi nhóm cơ gặp tạ ít nhất 2 lần: toàn thân, trên/dưới, hay đẩy/kéo/chân.',
    'quick'    => 'Với **2 đến 3 buổi/tuần** hãy tập toàn thân. Với **4 buổi** chia trên/dưới. Với **5 đến 6 buổi** chia đẩy/kéo/chân. Cả ba cách đều cho mỗi nhóm cơ chính tập ít nhất 2 lần/tuần. Lý do chọn kiểu này: chia đều khối lượng ra nhiều buổi thường dễ chịu hơn dồn vào một buổi.',
    'image'    => '/img/articles/chia-lich-tap-moi-nhom-co-2-lan.svg',
    'imageAlt' => 'Ảnh minh họa: lịch tập trong tuần',
    'date'     => '2026-10-14',
    'order'    => 14,
    'sources'  => [
        ['authors' => 'Schoenfeld BJ, Ogborn D, Krieger JW.', 'year' => 2016, 'title' => 'Effects of resistance training frequency on measures of muscle hypertrophy: a systematic review and meta-analysis', 'journal' => 'Sports Med', 'detail' => '46(11):1689-1697', 'pmid' => '27102172', 'doi' => '10.1007/s40279-016-0543-8'],
    ],
    'content'  => [
        ['p', 'Bài **Tập mỗi nhóm cơ mấy buổi một tuần** cho thấy tần suất từ 2 buổi/tuần có lợi thế nhỏ, phần lớn nhờ chia được khối lượng. Bài này biến điều đó thành lịch tập cụ thể.'],

        ['h2', 'Mỗi cách chia, nhóm cơ nào tập mấy lần'],
        ['table', ['head' => ['Số buổi/tuần', 'Cách chia', 'Ngực', 'Lưng', 'Vai', 'Chân', 'Tay', 'Bụng'], 'rows' => [
            ['2', 'Toàn thân x2', '2', '2', '2', '2', '2', '2'],
            ['3', 'Toàn thân x3', '3', '3', '3', '3', '3', '3'],
            ['4', 'Trên / Dưới x2', '2', '2', '2', '2', '2', '2'],
            ['5', 'Trên, Dưới, Đẩy, Kéo, Chân', '2', '2', '2', '2', '3', '3'],
            ['6', 'Đẩy / Kéo / Chân x2', '2', '2', '2', '2', '4', '4'],
        ]]],
        ['evidence', ['level' => 'basic', 'text' => 'Bảng là cách sắp xếp lịch, không phải kết quả nghiên cứu. Lý do nên tập từ 2 lần/tuần xem bài về tần suất.']],

        ['h2', 'Ví dụ 3 buổi toàn thân'],
        ['table', ['head' => ['Nhóm', 'Buổi A', 'Buổi B', 'Buổi C'], 'rows' => [
            ['Ngực', 'Hít đất', 'Đẩy ghế nghiêng', 'Đẩy tạ đòn'],
            ['Lưng', 'Kéo cáp xuống', 'Kéo cáp ngồi', 'Kéo tạ đơn một tay'],
            ['Vai', 'Đẩy vai ngồi', 'Nâng sang ngang', 'Face pull'],
            ['Chân', 'Goblet squat', 'Gập đùi sau + Hip thrust', 'Đạp đùi'],
            ['Tay', 'Cuốn búa + Đẩy cáp', 'Cuốn tạ đòn', 'Duỗi tay trên đầu'],
            ['Bụng', 'Gập bụng', 'Plank', 'Nâng chân'],
        ]]],
        ['tip', 'Đổi bài giữa các buổi cùng nhóm cơ như bảng trên để cơ gặp nhiều kiểu chuyển động và khớp đỡ mỏi vì một động tác.'],

        ['h2', 'Chọn cách chia nào'],
        ['principles', [
            ['Bắt đầu bằng toàn thân', 'Mới tập hoặc chỉ có 2 đến 3 buổi thì toàn thân gọn nhất.', 'basic'],
            ['Chia trên/dưới khi có 4 buổi', 'Mỗi buổi ngắn hơn, mỗi nhóm cơ vẫn 2 lần/tuần.', 'basic'],
            ['Đẩy/kéo/chân khi tập 5 đến 6 buổi', 'Cho phép nhiều bài hơn mỗi buổi, nhưng cần ngủ và ăn đủ.', 'basic'],
            ['Không nhất thiết cứng nhắc', 'Bỏ lỡ một buổi vẫn còn buổi khác trong tuần cho nhóm cơ đó.', 'basic'],
            ['Tăng tải từ từ', 'Thêm tạ hoặc hiệp theo từng tuần, theo bài về số hiệp.', 'fair'],
        ]],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Schoenfeld và cs., Sports Med 2016 (cơ sở cho nguyên tắc 2 lần/tuần)', 'rows' => [
            ['Thiết kế phương pháp', 'ok', 'Tổng quan hệ thống + phân tích gộp 10 nghiên cứu, tối thiểu 4 tuần.'],
            ['Tính lặp lại, kiểm chứng', 'part', 'Bản cập nhật 2019 của cùng nhóm thấy khác biệt biến mất khi cân bằng số hiệp.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Sports Med, có trên PubMed. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'unk', 'Chưa kiểm chứng tài trợ; không có phê duyệt đạo đức riêng với phân tích gộp.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'part', 'Hiệu ứng 0,49 so với 0,30, P = 0,002; khoảng tin cậy: chưa kiểm chứng.'],
        ]]],

        ['faq', [
            ['Người mới nên chọn cách nào?', 'Toàn thân 3 buổi/tuần là gọn nhất. GymSmart cũng xếp lịch theo số buổi bạn chọn.'],
            ['Có cần ngày nghỉ giữa hai buổi cùng nhóm cơ không?', 'Nên cách nhau ít nhất một ngày để phục hồi; các cách chia trong bảng đã xếp sẵn như vậy.'],
        ]],
        ['summary', [
            '2 đến 3 buổi: toàn thân. 4 buổi: trên/dưới. 5 đến 6 buổi: đẩy/kéo/chân.',
            'Mỗi nhóm cơ chính đều được tập ít nhất 2 lần/tuần.',
            'Lý do chính là chia khối lượng ra nhiều buổi, không phải phép màu của tần suất.',
        ]],
    ],
];
