<?php
/** Bài viết: ăn trong khung giờ. Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'nhin-an-gian-doan',
    'title'    => 'Nhịn ăn gián đoạn (ăn trong khung giờ) có giúp giảm cân hơn không?',
    'category' => 'nutrition',
    'tags'     => ['Giảm cân', 'Thử nghiệm ngẫu nhiên'],
    'summary'  => 'Thử nghiệm 12 tháng trên người béo phì: ăn trong khung giờ cộng giảm calo không giảm cân nhiều hơn rõ rệt so với chỉ giảm calo.',
    'quick'    => 'Trong thử nghiệm ngẫu nhiên 12 tháng trên 139 người béo phì, nhóm ăn trong khung giờ giảm **8,0 kg**, nhóm chỉ giảm calo giảm **6,3 kg**. Chênh 1,8 kg **không có ý nghĩa thống kê** (P = 0,11). Yếu tố quyết định vẫn là ăn ít calo hơn.',
    'image'    => '/img/articles/nhin-an-gian-doan.svg',
    'imageAlt' => 'Ảnh minh họa: ăn trong khung giờ cố định',
    'date'     => '2026-10-14',
    'order'    => 12,
    'sources'  => [
        ['authors' => 'Liu D, Huang Y, Huang C và cs.', 'year' => 2022, 'title' => 'Calorie restriction with or without time-restricted eating in weight loss', 'journal' => 'N Engl J Med', 'detail' => '386(16):1495-1504', 'pmid' => '35443107', 'doi' => '10.1056/NEJMoa2114833'],
    ],
    'content'  => [
        ['p', 'Ăn trong khung giờ (time-restricted eating) là chỉ ăn trong một khoảng, ví dụ 8 giờ mỗi ngày, rồi nhịn phần còn lại. Nhiều người tin cách này đốt mỡ riêng. Một thử nghiệm lớn đã thử kiểm tra.'],

        ['h2', 'Nghiên cứu nói gì'],
        ['figure', ['src' => '/img/articles/fig-nhin-an.svg', 'alt' => 'Biểu đồ thay đổi cân nặng sau 12 tháng của hai nhóm', 'caption' => 'Nguồn: Liu và cs., N Engl J Med 2022. 139 người béo phì, 118 người hoàn thành. P = 0,11: chưa kết luận được khác biệt.']],
        ['p', 'Thử nghiệm ngẫu nhiên có đối chứng, 139 người béo phì, 118 người (84,9%) hoàn thành 12 tháng; đăng ký ClinicalTrials.gov NCT03745612. Cả hai nhóm đều được giảm calo. Cân nặng thay đổi:'],
        ['table', ['head' => ['Nhóm', 'Thay đổi cân nặng 12 tháng', 'Khoảng tin cậy 95%'], 'rows' => [
            ['Khung giờ ăn + giảm calo', '-8,0 kg', '-9,6 đến -6,4'],
            ['Chỉ giảm calo', '-6,3 kg', '-7,8 đến -4,7'],
            ['Chênh lệch', '-1,8 kg', '-4,0 đến 0,4 (P = 0,11)'],
        ]]],
        ['p', 'Khoảng tin cậy của chênh lệch chạm mốc 0 nên không kết luận được cách nào hơn.'],
        ['evidence', ['level' => 'fair', 'text' => 'Thử nghiệm ngẫu nhiên đăng ký trước, nhưng một nghiên cứu duy nhất, người béo phì, chỉ ở một quốc gia, và 15% bỏ cuộc.']],

        ['h2', 'Ý nghĩa thực tế'],
        ['ul', [
            'Nếu khung giờ ăn giúp bạn ăn ít hơn dễ dàng hơn thì đó là lý do tốt để dùng.',
            'Nếu bạn thấy mệt, đói, mất ngủ hoặc ăn bù, không có lý do ép bản thân.',
            'Không dùng cho người có tiền sử rối loạn ăn uống, tiểu đường dùng thuốc, mang thai hoặc cho con bú nếu chưa hỏi bác sĩ.',
        ]],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Liu và cs., N Engl J Med 2022', 'rows' => [
            ['Thiết kế phương pháp', 'ok', 'Thử nghiệm ngẫu nhiên có đối chứng, đăng ký ClinicalTrials.gov NCT03745612, 139 người.'],
            ['Tính lặp lại, kiểm chứng', 'unk', 'Là một thử nghiệm đơn lẻ; chưa kiểm chứng việc lặp lại bởi nhóm độc lập.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên N Engl J Med, có trên PubMed. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'part', 'Có công bố tài trợ (Dự án Nghiên cứu Phát triển Trọng điểm Quốc gia Trung Quốc và các quỹ khác). Phê duyệt đạo đức: chưa kiểm chứng, cần mở bài gốc.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'ok', 'Có KTC 95% cho từng nhóm và chênh lệch, P = 0,11.'],
        ]]],

        ['faq', [
            ['Nhịn ăn gián đoạn có vô ích không?', 'Không. Chỉ là chưa thấy lợi thế rõ rệt về cân nặng so với chỉ giảm calo trong thử nghiệm này.'],
            ['Kết quả có áp dụng cho người gầy không?', 'Không biết. Người tham gia đều là người béo phì.'],
        ]],
        ['summary', [
            'Ăn trong khung giờ + giảm calo: -8,0 kg; chỉ giảm calo: -6,3 kg; chênh không có ý nghĩa (P = 0,11).',
            'Tổng calo vẫn là yếu tố chính.',
            'Chọn cách bạn duy trì được và an toàn với sức khỏe của mình.',
        ]],
    ],
];
