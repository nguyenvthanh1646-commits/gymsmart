<?php
/** Bài viết: số hiệp mỗi tuần. Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'so-hiep-moi-tuan-de-tang-co',
    'title'    => 'Mỗi tuần nên tập bao nhiêu hiệp cho mỗi nhóm cơ?',
    'category' => 'training',
    'tags'     => ['Tăng cơ', 'Khối lượng tập'],
    'summary'  => 'Tập nhiều hiệp hơn mỗi tuần thì tăng cơ nhiều hơn một chút, nhưng mỗi hiệp thêm chỉ đóng góp rất nhỏ.',
    'quick'    => 'Phân tích gộp 15 nghiên cứu cho thấy mỗi hiệp thêm mỗi tuần ứng với mức tăng cơ cao hơn khoảng **0,37%** (P = 0,002). Quan hệ có thật nhưng nhỏ, nên thêm hiệp chỉ đáng khi bạn còn sức phục hồi.',
    'image'    => '/img/articles/so-hiep-moi-tuan-de-tang-co.svg',
    'imageAlt' => 'Ảnh minh họa: số hiệp tập mỗi tuần cho một nhóm cơ',
    'date'     => '2026-10-14',
    'order'    => 7,
    'sources'  => [
        ['authors' => 'Schoenfeld BJ, Ogborn D, Krieger JW.', 'year' => 2017, 'title' => 'Dose-response relationship between weekly resistance training volume and increases in muscle mass: a systematic review and meta-analysis', 'journal' => 'J Sports Sci', 'detail' => '35(11):1073-1082', 'pmid' => '27433992', 'doi' => '10.1080/02640414.2016.1210197'],
    ],
    'content'  => [
        ['p', 'Hiệp (set) là số lần bạn lặp một nhóm động tác liên tiếp rồi nghỉ. Tổng số hiệp mỗi tuần cho một nhóm cơ được gọi là khối lượng tập (volume). Đây là thứ bạn điều chỉnh được nhiều nhất trong giáo án.'],

        ['h2', 'Nghiên cứu nói gì'],
        ['figure', ['src' => '/img/articles/fig-so-hiep.svg', 'alt' => 'Mỗi hiệp thêm mỗi tuần tương ứng tăng thêm 0,37% mức tăng cơ', 'caption' => 'Nguồn: Schoenfeld và cs., J Sports Sci 2017 (15 nghiên cứu, 34 nhóm điều trị). Chia thành 3 mức (dưới 5, 5 đến 9, từ 10 hiệp) chỉ cho xu hướng (P = 0,074).']],
        ['p', 'Phân tích gộp 15 nghiên cứu (34 nhóm điều trị) ghi nhận hiệu ứng +0,023 cho mỗi hiệp thêm mỗi tuần, tương đương tăng cơ thêm khoảng **0,37%** (P = 0,002). Khi chia thành 3 mức khối lượng, chỉ thấy xu hướng (P = 0,074).'],
        ['evidence', ['level' => 'fair', 'text' => 'Một phân tích gộp có hồi quy liều đáp ứng, nhưng các nghiên cứu nhỏ, đa số kéo dài vài tuần, và hiệu ứng mỗi hiệp rất nhỏ.']],

        ['h2', 'Hiểu con số này thế nào'],
        ['ul', [
            '**Càng nhiều hiệp, càng nhiều cơ**, nhưng mức tăng thêm nhỏ dần so với công sức bỏ ra.',
            '**Không có “con số thần kỳ”:** bài không cho biết một mức hiệp tối ưu cho mọi người.',
            '**Phục hồi là giới hạn:** thêm hiệp mà ngủ kém, ăn thiếu thì dễ mệt và đau hơn là lên cơ.',
        ]],
        ['tip', 'Cách làm thực tế: bắt đầu với mức bạn hồi phục tốt, mỗi vài tuần thêm 1 đến 2 hiệp cho nhóm cơ bạn muốn phát triển. Nếu sức tập giảm, lùi lại.'],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Schoenfeld và cs., J Sports Sci 2017', 'rows' => [
            ['Thiết kế phương pháp', 'ok', 'Tổng quan hệ thống, phân tích gộp kèm hồi quy, 15 nghiên cứu, 34 nhóm điều trị.'],
            ['Tính lặp lại, kiểm chứng', 'unk', 'Chưa kiểm chứng việc nhóm độc lập có lặp lại kết quả này.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên J Sports Sci, có trên PubMed. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'unk', 'Chưa kiểm chứng nguồn tài trợ và xung đột lợi ích; phân tích gộp không có phê duyệt đạo đức riêng.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'part', 'Có hiệu ứng +0,023 mỗi hiệp và P = 0,002. Khoảng tin cậy và số người: chưa kiểm chứng.'],
        ]]],

        ['faq', [
            ['Vậy tập càng nhiều hiệp càng tốt?', 'Không hẳn. Hiệu ứng mỗi hiệp nhỏ, còn sức phục hồi của bạn có hạn. Tăng từ từ và theo dõi cảm giác.'],
            ['Một hiệp tập bao nhiêu cái?', 'Bài này không so sánh số cái trong hiệp. Xem bài **Tạ nhẹ hay tạ nặng** để biết vai trò của mức tạ.'],
        ]],
        ['summary', [
            'Mỗi hiệp thêm mỗi tuần ứng với khoảng +0,37% tăng cơ (P = 0,002).',
            'Quan hệ có thật nhưng nhỏ, đừng đánh đổi giấc ngủ và phục hồi để thêm hiệp.',
            'Tăng khối lượng từ từ, chỉ khi bạn còn phục hồi tốt.',
        ]],
    ],
];
