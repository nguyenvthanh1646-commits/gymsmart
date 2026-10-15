<?php
/** Bài viết nền tảng: thở bằng cơ hoành. Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'tho-bang-co-hoanh-truoc-khi-tap',
    'title'    => 'Thở bằng cơ hoành: học trước khi tập, bằng chứng nói gì',
    'category' => 'training',
    'tags'     => ['Nền tảng', 'Hơi thở', 'Kỹ thuật'],
    'mustRead' => true,
    'summary'  => 'Thở bằng cơ hoành là hít để bụng và sườn dưới nở ra. Nghiên cứu hiện có ủng hộ vai trò của cơ hoành với tư thế và một số lợi ích sức khỏe, nhưng chưa chứng minh giúp nâng tạ khỏe hơn.',
    'quick'    => 'Thở bằng cơ hoành là hít vào để **bụng và sườn dưới nở ra**, thay vì nâng vai và ngực. Cơ hoành vừa là cơ thở chính vừa tham gia giữ vững thân mình. Một bản tổng quan 2020 ghi nhận lợi ích ở huyết áp, căng thẳng, lo âu và bệnh phổi, nhưng phần lớn từ các nghiên cứu nhỏ. Các nguồn đã kiểm chứng trong bài này chưa chứng minh thở cơ hoành làm tăng sức mạnh hay giảm chấn thương khi tập tạ. Lý do nên học: đây là nền cho kỹ thuật gồng core.',
    'image'    => '/img/articles/tho-bang-co-hoanh-truoc-khi-tap.svg',
    'imageAlt' => 'Ảnh bìa: phổi và cơ hoành hạ xuống khi hít vào',
    'date'     => '2026-10-15',
    'order'    => -1,
    'sources'  => [
        ['authors' => 'Kolář P, Šulc J, Kynčl M và cs.', 'year' => 2012, 'title' => 'Postural function of the diaphragm in persons with and without chronic low back pain', 'journal' => 'J Orthop Sports Phys Ther', 'detail' => '42(4):352-362', 'doi' => '10.2519/jospt.2012.3830'],
        ['authors' => 'Hamasaki H.', 'year' => 2020, 'title' => 'Effects of diaphragmatic breathing on health: a narrative review', 'journal' => 'Medicines (Basel)', 'detail' => '7(10):65', 'doi' => '10.3390/medicines7100065'],
    ],
    'content'  => [
        ['p', 'Hầu hết chúng ta thở bằng ngực và vai mà không để ý. Khi tập, kiểu thở này làm bạn khó giữ thân mình vững và dễ mệt hơi. Học thở bằng cơ hoành trước là bước đệm cho kỹ thuật gồng core, nên bài này được đánh dấu "Đọc trước khi tập".'],

        ['h2', 'Cơ hoành là gì và làm gì'],
        ['p', 'Cơ hoành là cơ hình vòm nằm ngay dưới phổi, ngăn khoang ngực với khoang bụng. Khi hít vào, cơ hoành co lại và hạ xuống, phổi có chỗ nở ra; sườn dưới và bụng nở sang hai bên. Khi thở ra, cơ hoành thả lỏng và vồng lên.'],
        ['figure', ['src' => '/img/articles/fig-co-hoanh.svg', 'alt' => 'Sơ đồ cơ hoành khi thở ra và khi hít vào', 'caption' => 'Sơ đồ minh họa tự vẽ, đơn giản hóa, không theo tỷ lệ.']],
        ['evidence', ['level' => 'basic', 'text' => 'Kiến thức giải phẫu nền tảng, bài này không dẫn nghiên cứu riêng cho phần mô tả chuyển động.']],

        ['h2', 'Bằng chứng 1: cơ hoành còn tham gia giữ tư thế'],
        ['p', 'Kolář và cs. so sánh 18 người đau thắt lưng mạn tính với 29 người khỏe mạnh (nghiên cứu bệnh chứng). Họ đo vị trí, biên độ di chuyển và độ dốc của cơ hoành khi thở bình thường và khi gồng đẳng trường tay hoặc chân.'],
        ['ul', [
            'Khi chỉ thở bình thường: hai nhóm không khác nhau.',
            'Khi gồng đẳng trường tay hoặc chân: nhóm đau lưng có biên độ di chuyển của cơ hoành nhỏ hơn và vị trí khi hít vào cao hơn (gần đầu hơn).',
            'Các tác giả cho rằng cơ hoành ở người đau lưng mạn tính có vị trí và độ dốc bất thường, có thể liên quan đến tình trạng đau.',
            'Nghiên cứu cắt ngang nên không loại trừ chiều ngược lại: đau lưng có thể làm thay đổi cách cơ hoành hoạt động.',
        ]],
        ['evidence', ['level' => 'thin', 'text' => 'Một nghiên cứu bệnh chứng nhỏ (47 người), cho thấy mối liên quan chứ không chứng minh nguyên nhân. Không cho biết thở bằng cơ hoành có làm giảm đau lưng hay không.']],

        ['h2', 'Bằng chứng 2: thở bằng cơ hoành và sức khỏe nói chung'],
        ['p', 'Hamasaki (2020) tổng hợp các nghiên cứu về thở bằng cơ hoành trong một bài tổng quan tường thuật (không phải phân tích gộp). Một số kết quả được bài này nêu lại, tôi chưa mở từng nghiên cứu gốc:'],
        ['table', ['head' => ['Chủ đề', 'Kết quả được nêu', 'Quy mô và loại nghiên cứu'], 'rows' => [
            ['Huyết áp', 'Thở cơ hoành (có hoặc không kèm phản hồi sinh học điện cơ) làm giảm huyết áp tâm thu', '22 phụ nữ sau mãn kinh, tiền tăng huyết áp (Wang 2010)'],
            ['Căng thẳng', 'Giảm căng thẳng, chưa gộp phân tích', 'Tổng quan hệ thống 1 thử nghiệm ngẫu nhiên và 2 nghiên cứu bán thực nghiệm (Hopper 2019)'],
            ['Lo âu', 'Sau 8 tuần: giảm điểm lo âu, nhịp tim và nhịp thở', '30 người có lo âu (Chen 2017)'],
            ['Bệnh phổi tắc nghẽn mạn tính (COPD)', 'Giảm khó thở, tăng quãng đường đi bộ 6 phút', 'Tổng quan 16 thử nghiệm ngẫu nhiên (Holland 2012)'],
        ]]],
        ['evidence', ['level' => 'thin', 'text' => 'Tổng quan tường thuật, các nghiên cứu được trích phần lớn nhỏ. Kết quả dành cho người có huyết áp, lo âu hoặc bệnh phổi, chưa chắc áp dụng cho người khỏe đi tập.']],

        ['h2', 'Điều bằng chứng chưa trả lời'],
        ['p', 'Trong các nguồn đã kiểm chứng ở bài này, **chưa có kết quả cho thấy thở bằng cơ hoành làm tăng sức mạnh, tăng khối cơ hay giảm chấn thương khi tập tạ**. Lý do chính nên học vẫn là kỹ thuật: thở bụng giúp bạn hít đủ hơi để gồng core đúng, và là thói quen thở hiệu quả hơn thở bằng vai.'],

        ['h2', 'Cách luyện trong 3 bước'],
        ['figure', ['src' => '/img/articles/fig-tho-3-buoc.svg', 'alt' => 'Ba bước luyện thở bằng cơ hoành: đặt tay, hít bằng mũi, thở ra chậm', 'caption' => 'Hướng dẫn thực hành. Số giây chỉ là gợi ý để bắt đầu.']],
        ['ol', [
            '**Đặt tay:** nằm ngửa, gối đầu cao một chút, một tay lên ngực, một tay lên bụng trên rốn.',
            '**Hít bằng mũi:** hít chậm khoảng 4 giây. Tay ở bụng nâng lên, tay ở ngực gần như đứng yên. Cảm nhận sườn dưới nở sang hai bên.',
            '**Thở ra chậm:** thở ra qua miệng khoảng 6 giây, bụng xẹp lại. Lặp 5 đến 10 lần mỗi lượt, vài lượt mỗi ngày.',
        ]],
        ['p', 'Khi đã quen khi nằm, thử ngồi rồi đứng. Sau đó kết hợp với bài "Gồng core (bracing) đúng cách": hít bụng, siết 360°, rồi mới nâng.'],
        ['evidence', ['level' => 'basic', 'text' => 'Các bước và số giây là hướng dẫn thực hành thông dụng, bài này không dẫn nghiên cứu riêng cho từng con số.']],
        ['tip', 'Nếu thấy chóng mặt, tê ngón tay hoặc khó chịu, hãy dừng và thở bình thường. Người có hen, COPD, bệnh tim, đang mang thai hoặc sau phẫu thuật vùng ngực bụng nên hỏi bác sĩ trước khi tập các bài thở kéo dài.'],

        ['h2', 'Lỗi thường gặp'],
        ['ul', [
            'Hít bằng ngực: tay ở ngực nâng lên nhiều hơn tay ở bụng.',
            'Nâng vai mỗi lần hít.',
            'Ép bụng phình ra quá mức hoặc hít quá sâu đến căng tức.',
            'Thở quá nhanh nên thấy chóng mặt.',
        ]],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Kolář và cs., J Orthop Sports Phys Ther 2012', 'rows' => [
            ['Thiết kế phương pháp', 'part', 'Nghiên cứu bệnh chứng, 18 người đau thắt lưng mạn tính và 29 người khỏe. Thiết kế cắt ngang, không xác định nguyên nhân.'],
            ['Tính lặp lại, kiểm chứng', 'unk', 'Chưa tìm thấy nghiên cứu lặp lại trong các nguồn đã mở.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Journal of Orthopaedic & Sports Physical Therapy, tạp chí có phản biện. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'part', 'Tài trợ: quỹ Movement without Help (Praha, Cộng hòa Séc). Phê duyệt đạo đức: chưa kiểm chứng.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'unk', 'Chưa đọc được số liệu thống kê chi tiết (P, khoảng tin cậy).'],
        ]]],
        ['study', ['title' => 'Hamasaki, Medicines 2020', 'rows' => [
            ['Thiết kế phương pháp', 'part', 'Tổng quan tường thuật, không phải phân tích gộp. Cách tìm và chọn nghiên cứu: chưa kiểm chứng.'],
            ['Tính lặp lại, kiểm chứng', 'unk', 'Các nghiên cứu được trích chưa được tôi mở lại từng bài.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Medicines (MDPI), tạp chí truy cập mở có phản biện. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'unk', 'Tài trợ và xung đột lợi ích: chưa kiểm chứng (chưa đọc được phần này).'],
            ['Thống kê (p, KTC, hiệu ứng)', 'na', 'Bài tổng quan tường thuật, không tổng hợp thống kê.'],
        ]]],

        ['faq', [
            ['Thở bằng cơ hoành có giúp nâng tạ nặng hơn không?', 'Các nguồn đã kiểm chứng trong bài chưa chứng minh điều này. Bài chỉ nêu đây là nền cho kỹ thuật gồng core.'],
            ['Nên tập thở bao lâu?', 'Vài phút mỗi ngày để làm quen. Bài này không dẫn nghiên cứu về thời lượng tối ưu.'],
            ['Thở bụng khác thở ngực thế nào?', 'Thở bụng: bụng và sườn dưới nở khi hít vào. Thở ngực: ngực và vai nâng lên nhiều hơn.'],
        ]],
        ['summary', [
            'Thở bằng cơ hoành là hít để bụng và sườn dưới nở ra, không nâng vai.',
            'Cơ hoành còn tham gia giữ tư thế; ở người đau lưng mạn tính, cơ hoành di chuyển ít hơn và nằm cao hơn khi gồng tay chân (mối liên quan, chưa rõ nguyên nhân).',
            'Tổng quan 2020 ghi nhận lợi ích ở huyết áp, căng thẳng, lo âu và COPD từ các nghiên cứu nhỏ; chưa có bằng chứng ở đây về sức mạnh hay chấn thương khi tập tạ.',
            'Học trước khi tập để làm nền cho gồng core; dừng ngay nếu chóng mặt.',
        ]],
    ],
];
