<?php
/** Bài viết: tập kháng lực và nguy cơ bệnh. Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'tap-khang-luc-va-tuoi-tho',
    'title'    => 'Tập kháng lực không chỉ để to cơ: liên quan gì đến sức khỏe lâu dài?',
    'category' => 'health',
    'tags'     => ['Tập kháng lực', 'Phòng bệnh'],
    'summary'  => 'Người tập kháng lực có nguy cơ tử vong và bệnh mạn tính thấp hơn khoảng 10 đến 17%, lợi ích lớn nhất ở khoảng 30 đến 60 phút mỗi tuần.',
    'quick'    => 'Phân tích gộp 16 nghiên cứu theo dõi tiến cứu cho thấy tập tăng cường cơ bắp gắn với nguy cơ tử vong mọi nguyên nhân, bệnh tim mạch, ung thư và đái tháo đường **thấp hơn khoảng 10 đến 17%**. Lợi ích lớn nhất ở khoảng **30 đến 60 phút/tuần**. Đây là mối liên quan, chưa phải bằng chứng nhân quả.',
    'image'    => '/img/articles/tap-khang-luc-va-tuoi-tho.svg',
    'imageAlt' => 'Ảnh minh họa: tập kháng lực và sức khỏe lâu dài',
    'date'     => '2026-10-14',
    'order'    => 10,
    'sources'  => [
        ['authors' => 'Momma H, Kawakami R, Honda T và cs.', 'year' => 2022, 'title' => 'Muscle-strengthening activities are associated with lower risk and mortality in major non-communicable diseases: a systematic review and meta-analysis of cohort studies', 'journal' => 'Br J Sports Med', 'detail' => '56(13):755-763', 'pmid' => '35228201', 'doi' => '10.1136/bjsports-2021-105061'],
    ],
    'content'  => [
        ['p', 'Tập kháng lực (tạ, dây kháng lực, bodyweight) thường được nhắc đến vì hình thể. Nhưng nhiều nghiên cứu dịch tễ cho thấy nó còn liên quan đến việc phòng bệnh.'],

        ['h2', 'Nghiên cứu nói gì'],
        ['p', 'Tổng quan hệ thống và phân tích gộp 16 nghiên cứu đoàn hệ tiến cứu (đăng ký trước tại PROSPERO, mã CRD42020219808) cho thấy người có tập hoạt động tăng cường cơ bắp có nguy cơ **thấp hơn khoảng 10 đến 17%** ở các kết cục: tử vong mọi nguyên nhân, bệnh tim mạch, ung thư và đái tháo đường. Mức lợi ích lớn nhất ở khoảng 30 đến 60 phút mỗi tuần.'],
        ['evidence', ['level' => 'fair', 'text' => 'Gộp nhiều đoàn hệ tiến cứu có đăng ký trước, nhưng là nghiên cứu quan sát; người tập kháng lực cũng thường có lối sống lành mạnh hơn.']],

        ['h2', 'Ý nghĩa thực tế'],
        ['ul', [
            'Không cần tập nhiều: khoảng 30 đến 60 phút mỗi tuần đã nằm trong vùng lợi ích lớn nhất.',
            'Kết hợp với đi bộ hoặc cardio, không thay thế chúng.',
            'Tập kháng lực toàn thân 2 đến 3 buổi/tuần là cách dễ đạt mức này.',
        ]],
        ['tip', 'Bài này không nói loại bài tập, mức tạ hay độ tuổi cụ thể nào là tốt nhất. Người có bệnh nền nên hỏi bác sĩ trước khi bắt đầu.'],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Momma và cs., Br J Sports Med 2022', 'rows' => [
            ['Thiết kế phương pháp', 'ok', 'Tổng quan hệ thống + phân tích gộp 16 đoàn hệ tiến cứu, đăng ký PROSPERO CRD42020219808. Quan sát nên không chứng minh nhân quả.'],
            ['Tính lặp lại, kiểm chứng', 'part', 'Nhiều đoàn hệ cho hướng tương tự; mức nhất quán chi tiết: chưa kiểm chứng.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Br J Sports Med, có trên PubMed. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'part', 'Có công bố tài trợ (Bộ Y tế, Lao động và Phúc lợi Nhật Bản). Phê duyệt đạo đức: không áp dụng với phân tích gộp.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'part', 'Báo cáo mức giảm nguy cơ 10 đến 17%. Khoảng tin cậy, p-value và số người: chưa kiểm chứng trong bài này.'],
        ]]],

        ['faq', [
            ['Tập kháng lực có giúp sống lâu hơn không?', 'Nghiên cứu cho thấy mối liên quan với nguy cơ thấp hơn, chưa chứng minh nhân quả.'],
            ['Cần tập bao lâu mỗi tuần?', 'Lợi ích lớn nhất nằm ở khoảng 30 đến 60 phút/tuần theo phân tích này.'],
        ]],
        ['summary', [
            'Tập kháng lực liên quan nguy cơ thấp hơn khoảng 10 đến 17% với tử vong và một số bệnh mạn tính.',
            '30 đến 60 phút mỗi tuần là vùng lợi ích lớn nhất.',
            'Kết quả là mối liên quan, chưa phải nhân quả.',
        ]],
    ],
];
