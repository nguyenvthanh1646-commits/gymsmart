<?php
return [
    'slug'     => 'calo-bmr-tdee-la-gi',
    'title'    => 'Calo, BMR và TDEE: con số đầu tiên bạn cần biết',
    'category' => 'general',
    'summary'  => 'BMR là calo cơ thể đốt khi nghỉ, TDEE là tổng calo đốt mỗi ngày. Hiểu hai con số này là bước đầu để giảm mỡ hay tăng cơ.',
    'tags'     => ['Năng lượng', 'Kiến thức nền'],
    'quick'    => '**BMR** là calo cơ thể đốt khi nghỉ, **TDEE** là tổng calo đốt mỗi ngày (BMR x hệ số vận động). Đây là ước lượng, có thể lệch khoảng 10% so với thực tế.',
    'image'    => '/img/articles/calo-bmr-tdee-la-gi.svg',
    'imageAlt' => 'Ảnh minh họa: tính calo và năng lượng tiêu hao',
    'date'     => '2026-10-14',
    'order'    => 4,
    'content'  => [
        ['p', 'Calo (kcal) là đơn vị đo năng lượng. Cân nặng của bạn thay đổi theo cân bằng năng lượng: ăn vào nhiều hơn lượng đốt thì tăng cân, ít hơn thì giảm cân, bằng nhau thì giữ nguyên.'],

        ['h2', 'BMR là gì'],
        ['p', '**BMR** (Basal Metabolic Rate, năng lượng chuyển hóa cơ bản) là số calo cơ thể đốt khi nằm nghỉ cả ngày, chỉ để duy trì sự sống: thở, tim đập, giữ thân nhiệt, hoạt động của não và các cơ quan. BMR thường chiếm khoảng 60 đến 70% tổng năng lượng tiêu hao.'],
        ['p', 'GymSmart tính BMR theo công thức **Mifflin-St Jeor** từ cân nặng, chiều cao, tuổi và giới tính.'],

        ['h2', 'TDEE là gì'],
        ['p', '**TDEE** (Total Daily Energy Expenditure, tổng năng lượng tiêu hao mỗi ngày) là toàn bộ calo bạn đốt trong một ngày, gồm:'],
        ['ul', [
            '**BMR:** phần lớn nhất.',
            '**Tập luyện:** các buổi tập có chủ đích.',
            '**Sinh hoạt hằng ngày:** đi lại, làm việc, việc nhà.',
            '**Tiêu hóa thức ăn:** khoảng 10% tổng calo.',
        ]],
        ['p', 'Cách tính: **TDEE = BMR × hệ số vận động**.'],
        ['table', [
            'head' => ['Mức vận động', 'Hệ số'],
            'rows' => [
                ['Ít vận động (ngồi nhiều, hầu như không tập)', '1,2'],
                ['Vận động nhẹ (1 đến 3 buổi/tuần)', '1,375'],
                ['Vận động vừa (3 đến 5 buổi/tuần)', '1,55'],
                ['Vận động nhiều (6 đến 7 buổi/tuần)', '1,725'],
                ['Rất nhiều (tập nặng hằng ngày hoặc lao động nặng)', '1,9'],
            ],
        ]],

        ['h2', 'Từ TDEE đến calo mục tiêu'],
        ['ul', [
            '**Giảm mỡ:** ăn ít hơn TDEE khoảng 20%.',
            '**Duy trì:** ăn bằng TDEE.',
            '**Tăng cơ:** ăn nhiều hơn TDEE khoảng 10%.',
        ]],
        ['tip', 'Các con số này chỉ là ước lượng, có thể lệch khoảng 10% so với thực tế. Hãy theo dõi cân nặng trung bình mỗi tuần trong 2 đến 3 tuần rồi điều chỉnh nếu chưa thấy thay đổi như mong muốn.'],

        ['evidence', ['level' => 'basic', 'text' => 'Công thức Mifflin-St Jeor và các hệ số vận động là cách tính phổ biến. Bài này chưa dẫn nghiên cứu gốc và chưa kiểm chứng độ chính xác ở từng nhóm người; hãy xem là điểm xuất phát rồi điều chỉnh theo cân nặng thực tế.']],
        ['faq', [
            ['Vì sao kết quả có thể lệch?', 'Mỗi người có chuyển hóa và mức vận động khác nhau; công thức chỉ cho con số trung bình.'],
            ['Nên theo dõi trong bao lâu?', 'Khoảng 2 đến 3 tuần cân nặng trung bình mỗi tuần, rồi điều chỉnh lượng ăn nếu chưa đúng mục tiêu.'],
        ]],
        ['summary', [
            'BMR là calo khi nghỉ, TDEE là tổng calo đốt mỗi ngày.',
            'Giảm mỡ ăn ít hơn TDEE khoảng 20%, tăng cơ nhiều hơn khoảng 10%.',
            'Đây là ước lượng, hãy điều chỉnh theo thực tế.',
        ]],
    ],
];
