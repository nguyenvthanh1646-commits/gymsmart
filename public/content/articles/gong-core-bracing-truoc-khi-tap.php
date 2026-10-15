<?php
/** Bài viết nền tảng: gồng core (bracing). Xem hướng dẫn thêm bài trong includes/articles.php. */
return [
    'slug'     => 'gong-core-bracing-truoc-khi-tap',
    'title'    => 'Gồng core (bracing) đúng cách trước khi nâng tạ',
    'category' => 'training',
    'tags'     => ['Nền tảng', 'Cột sống', 'Kỹ thuật'],
    'mustRead' => true,
    'summary'  => 'Gồng core là siết đều thành bụng 360° quanh cột sống, khác với hóp rốn. Một nghiên cứu nhỏ cho thấy gồng bụng ổn định cột thắt lưng hơn hóp bụng.',
    'quick'    => 'Gồng core là **siết đều toàn bộ thành bụng 360°** (trước, hai bên, lưng) như chuẩn bị đón một cú đấm, không phải hóp rốn vào. Trong một nghiên cứu phòng thí nghiệm trên 8 nam khỏe mạnh, gồng bụng cho độ ổn định cột thắt lưng cao hơn hóp bụng (P < 0,001). Mẫu rất nhỏ, nên hãy xem đây là bằng chứng bước đầu cho một kỹ thuật được huấn luyện viên dùng rộng rãi. Học kỹ thuật này với tạ rất nhẹ trước khi lên tạ nặng.',
    'image'    => '/img/articles/gong-core-bracing-truoc-khi-tap.svg',
    'imageAlt' => 'Ảnh bìa: gồng core 360 độ quanh cột sống',
    'date'     => '2026-10-15',
    'order'    => -2,
    'sources'  => [
        ['authors' => 'Grenier SG, McGill SM.', 'year' => 2007, 'title' => 'Quantification of lumbar stability by using 2 different abdominal activation strategies', 'journal' => 'Arch Phys Med Rehabil', 'detail' => '88(1):54-62'],
        ['authors' => 'Blazek D, Stastny P, Maszczyk A và cs.', 'year' => 2019, 'title' => 'Systematic review of intra-abdominal and intrathoracic pressures initiated by the Valsalva manoeuvre during high-intensity resistance exercises', 'journal' => 'Biol Sport', 'detail' => '36(4):373-386', 'doi' => '10.5114/biolsport.2019.88759'],
        ['authors' => 'MacDougall JD, Tuxen D, Sale DG, Moroz JR, Sutton JR.', 'year' => 1985, 'title' => 'Arterial blood pressure response to heavy resistance exercise', 'journal' => 'J Appl Physiol', 'detail' => '58(3):785-790', 'doi' => '10.1152/jappl.1985.58.3.785'],
    ],
    'content'  => [
        ['p', 'Khi squat, deadlift hay đẩy tạ qua đầu, cột sống thắt lưng phải giữ thân mình vững trong lúc chịu tải. Gồng core là cách tạo độ cứng cho thân mình bằng chính các cơ thành bụng và lưng. Đây là kỹ thuật nên học trước khi tăng mức tạ, và là lý do bài này được đánh dấu "Đọc trước khi tập".'],

        ['h2', 'Gồng bụng khác hóp bụng thế nào'],
        ['figure', ['src' => '/img/articles/fig-core-cat-ngang.svg', 'alt' => 'Sơ đồ mặt cắt ngang thân người so sánh hóp bụng và gồng bụng', 'caption' => 'Sơ đồ minh họa tự vẽ, không theo tỷ lệ. Khái niệm theo Grenier và McGill, Arch Phys Med Rehabil 2007.']],
        ['ul', [
            '**Hóp bụng (hollowing):** kéo rốn vào phía cột sống, chủ yếu làm việc ở thành bụng phía trước.',
            '**Gồng bụng (bracing):** siết đồng thời thành bụng phía trước, hai bên sườn và cơ lưng, như khi bạn chuẩn bị đón một cú đấm vào bụng. Bụng không thụt vào mà cứng và hơi căng ra mọi hướng.',
        ]],
        ['evidence', ['level' => 'basic', 'text' => 'Mô tả cách thực hiện theo đồng thuận chung của huấn luyện viên, không phải kết quả của một nghiên cứu riêng.']],

        ['h2', 'Nghiên cứu nói gì về độ ổn định cột sống'],
        ['p', 'Grenier và McGill so sánh hai cách kích hoạt cơ bụng trên 8 nam giới khỏe mạnh, tuổi 20 đến 33, không đau lưng trong năm trước. Họ đo chỉ số ổn định cột thắt lưng trong bốn điều kiện: không tải, tải hai tay, tải tay trái và tải tay phải. Họ cũng chạy thêm một mô phỏng máy tính về hóp bụng và gồng bụng lý tưởng.'],
        ['figure', ['src' => '/img/articles/fig-core-on-dinh.svg', 'alt' => 'Biểu đồ độ ổn định cột thắt lưng khi gồng bụng và hóp bụng ở bốn điều kiện tải', 'caption' => 'Nguồn: Grenier và McGill, Arch Phys Med Rehabil 2007. Trung bình 8 nam khỏe mạnh, đơn vị Nm/rad.']],
        ['table', ['head' => ['Điều kiện', 'Hóp bụng (Nm/rad)', 'Gồng bụng (Nm/rad)'], 'rows' => [
            ['Không tải', '474,6', '511,3'],
            ['Tải hai tay', '495,6', '527,3'],
            ['Tải tay trái', '517,7', '555,0'],
            ['Tải tay phải', '533,4', '546,1'],
        ]]],
        ['ul', [
            'Gồng bụng cho độ ổn định cao hơn hóp bụng, khác biệt có ý nghĩa thống kê (P < 0,001).',
            'Lực nén lên cột sống không khác biệt có ý nghĩa giữa hai cách (P = 0,54).',
            'Trong mô phỏng, gồng bụng tăng ổn định 32% so với hóp bụng, kèm lực nén tăng 15%.',
            'Các tác giả kết luận không thấy cơ sở cơ học để chỉ dùng hóp bụng hay chỉ dùng cơ bụng ngang nhằm tăng ổn định, nhưng nói rõ điều này không loại trừ lợi ích khác của hóp bụng khi tập kiểm soát vận động.',
        ]],
        ['evidence', ['level' => 'thin', 'text' => 'Một nghiên cứu phòng thí nghiệm, chỉ 8 nam trẻ khỏe mạnh, đo độ ổn định chứ không đo chấn thương hay hiệu suất khi tập. Chưa thể kết luận gồng bụng giúp giảm chấn thương.']],

        ['h2', 'Vì sao nên học gồng trước khi lên tạ nặng'],
        ['p', 'Một tổng quan hệ thống 2019 gộp 16 nghiên cứu (từ 1.125 bài tìm được) về áp lực trong ổ bụng khi tập nặng. Áp lực này càng cao ở các bài nặng, nhiều nhóm cơ:'],
        ['figure', ['src' => '/img/articles/fig-core-ap-luc.svg', 'alt' => 'Biểu đồ áp lực trong ổ bụng khi squat, deadlift và đẩy ngực', 'caption' => 'Nguồn: Blazek và cs., Biol Sport 2019, tổng quan hệ thống 16 nghiên cứu.']],
        ['ul', [
            'Cao nhất: squat, trên 200 mmHg.',
            'Tiếp theo: deadlift, slide row và đạp đùi (leg press), 161 đến 176 mmHg.',
            'Thấp nhất: đẩy ngực tạ đòn, trung bình 79 ± 44 mmHg.',
        ]],
        ['p', 'Các tác giả đề xuất đẩy ngực và slide row cho người mới và người tăng huyết áp, và khuyên người chưa quen tập nặng nên tránh squat, deadlift nặng cho đến khi đã thích nghi. Vì vậy: học gồng ở tạ rất nhẹ, rồi tăng dần.'],
        ['evidence', ['level' => 'fair', 'text' => 'Tổng quan hệ thống có tìm trên 3 cơ sở dữ liệu. Quy mô từng nghiên cứu gộp và độ nhất quán giữa chúng: chưa kiểm chứng. Áp lực đo được phụ thuộc cả tư thế lẫn mức tạ.']],

        ['h2', 'Gồng core và nín thở: cẩn thận với huyết áp'],
        ['p', 'Gồng bụng là việc siết cơ. Nín thở (nghiệm pháp Valsalva) là một lựa chọn riêng, nhiều người làm cùng lúc khi nâng nặng. Nín thở làm huyết áp tăng thêm. MacDougall và cs. đo huyết áp trực tiếp trong động mạch ở 5 vận động viên thể hình khi nâng đến kiệt sức:'],
        ['figure', ['src' => '/img/articles/fig-core-huyet-ap.svg', 'alt' => 'Biểu đồ huyết áp đỉnh khi cuốn tạ và đạp đùi', 'caption' => 'Nguồn: MacDougall và cs., J Appl Physiol 1985. 5 người, đo trực tiếp bằng ống thông động mạch.']],
        ['ul', [
            'Đạp đùi hai chân: huyết áp đỉnh trung bình nhóm 320/250 mmHg, một người vượt 480/350 mmHg.',
            'Cuốn tạ một tay đến kiệt sức: trung bình 255/190 mmHg.',
            'Áp lực khi nín thở (đo ở miệng) cho thấy một phần mức tăng đến từ nghiệm pháp Valsalva.',
        ]],
        ['evidence', ['level' => 'thin', 'text' => 'Chỉ 5 người, đã tập luyện nhiều và nâng đến kiệt sức. Cho thấy huyết áp có thể tăng rất mạnh khi nâng nặng, không đại diện cho mọi buổi tập.']],
        ['tip', 'Nếu bạn bị tăng huyết áp, bệnh tim, thoát vị, đang mang thai, vừa phẫu thuật vùng bụng hoặc đang đau lưng, hãy hỏi bác sĩ trước khi tập nặng hoặc nín thở khi nâng. Khi nâng, chỉ giữ hơi trong đoạn khó của một lần lặp rồi thở ra, đừng nín thở kéo dài.'],

        ['h2', 'Cách gồng core trong 4 bước'],
        ['figure', ['src' => '/img/articles/fig-core-4-buoc.svg', 'alt' => 'Bốn bước gồng core: hít bụng, siết 360 độ, giữ khi nâng, thở ra', 'caption' => 'Hướng dẫn thực hành theo đồng thuận của huấn luyện viên.']],
        ['ol', [
            '**Hít bụng:** hít bằng mũi, để bụng và sườn dưới nở ra. Tập thở bằng cơ hoành trước sẽ giúp bước này dễ hơn (xem bài "Thở bằng cơ hoành").',
            '**Siết 360°:** siết bụng như chuẩn bị đón một cú đấm, cảm nhận cả phía trước, hai bên và lưng cùng căng.',
            '**Giữ khi nâng:** giữ thân mình cứng suốt đoạn khó nhất, lưng ở tư thế trung tính.',
            '**Thở ra:** thở ra sau khi qua điểm khó nhất, thả lỏng, rồi lặp lại bước 1 cho lần tiếp theo.',
        ]],
        ['p', 'Cách tập thử an toàn: đứng thẳng, gồng và để người khác (hoặc bạn tự dùng ngón tay) ấn nhẹ vào hông. Nếu hông cứng không lõm vào, bạn đang gồng đúng.'],
        ['evidence', ['level' => 'basic', 'text' => 'Các bước và cách tập thử là hướng dẫn thực hành thông dụng, bài này không dẫn nghiên cứu riêng cho từng bước.']],

        ['h2', 'Lỗi thường gặp'],
        ['ul', [
            'Hóp rốn vào thay vì siết đều mọi hướng.',
            'Ưỡn lưng quá mức để "cho thẳng", thay vì giữ lưng trung tính.',
            'Hít bằng ngực và nâng vai thay vì để bụng và sườn nở.',
            'Nín thở quá lâu, kéo qua cả nhiều lần lặp.',
            'Chỉ nhớ gồng khi đã lên tạ nặng, thay vì tập trước với tạ nhẹ cho thành thói quen.',
        ]],

        ['h2', 'Đánh giá chất lượng nghiên cứu (chỉ để tham khảo)'],
        ['study', ['title' => 'Grenier và McGill, Arch Phys Med Rehabil 2007', 'rows' => [
            ['Thiết kế phương pháp', 'part', 'Thiết kế so sánh trong cùng người (8 nam, 20 đến 33 tuổi) cộng mô phỏng máy tính. Mẫu nhỏ, không có người đau lưng, không theo dõi chấn thương.'],
            ['Tính lặp lại, kiểm chứng', 'unk', 'Chưa tìm thấy nghiên cứu lặp lại trong các nguồn đã mở.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Arch Phys Med Rehabil, tạp chí có phản biện. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'part', 'Tài trợ: Hội đồng Nghiên cứu Khoa học Tự nhiên và Kỹ thuật Canada; tác giả báo cáo không có lợi ích thương mại. Phê duyệt đạo đức: chưa kiểm chứng.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'part', 'Có trung bình ± độ lệch chuẩn và P < 0,001 cho khác biệt gồng và hóp. Khoảng tin cậy 95%: chưa kiểm chứng.'],
        ]]],
        ['study', ['title' => 'Blazek và cs., Biol Sport 2019', 'rows' => [
            ['Thiết kế phương pháp', 'ok', 'Tổng quan hệ thống, tìm trên PubMed, Scopus, Web of Science đến 1/11/2018, chọn 16 trong 1.125 bài.'],
            ['Tính lặp lại, kiểm chứng', 'unk', 'Độ nhất quán giữa các nghiên cứu gộp: chưa kiểm chứng.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Biology of Sport, tạp chí có phản biện. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'unk', 'Tài trợ và xung đột lợi ích: chưa kiểm chứng (chưa đọc được phần này).'],
            ['Thống kê (p, KTC, hiệu ứng)', 'part', 'Có giá trị áp lực kèm độ lệch chuẩn cho bài đẩy ngực. Không phải phân tích gộp nên không có hiệu ứng tổng hợp.'],
        ]]],
        ['study', ['title' => 'MacDougall và cs., J Appl Physiol 1985', 'rows' => [
            ['Thiết kế phương pháp', 'part', 'Đo huyết áp trực tiếp trong động mạch (phương pháp chính xác) nhưng chỉ 5 người, không có nhóm đối chứng.'],
            ['Tính lặp lại, kiểm chứng', 'unk', 'Chưa tìm thấy nghiên cứu lặp lại trong các nguồn đã mở.'],
            ['Phản biện độc lập', 'ok', 'Đăng trên Journal of Applied Physiology. Chỉ số ảnh hưởng: chưa kiểm chứng.'],
            ['Minh bạch, đạo đức', 'unk', 'Tài trợ và phê duyệt đạo đức: chưa kiểm chứng.'],
            ['Thống kê (p, KTC, hiệu ứng)', 'part', 'Báo cáo trung bình nhóm và giá trị cao nhất của một người. P-value và khoảng tin cậy: chưa kiểm chứng.'],
        ]]],

        ['faq', [
            ['Gồng core khác tập cơ bụng thế nào?', 'Tập cơ bụng (gập bụng, plank) là tập sức mạnh cho cơ. Gồng core là kỹ thuật dùng cơ đó để giữ thân mình cứng trong lúc nâng tạ. Hai thứ bổ trợ nhau.'],
            ['Có cần đai lưng không?', 'Bài này không dẫn nghiên cứu nào về đai lưng nên không đưa ra kết luận. Hãy học gồng đúng trước.'],
            ['Gồng bụng có làm bụng to ra không?', 'Gồng chỉ là siết cơ trong lúc tập. Không có nguồn nào trong bài này nói về thay đổi vòng bụng.'],
        ]],
        ['summary', [
            'Gồng core là siết đều thành bụng 360°, khác hóp rốn.',
            'Nghiên cứu nhỏ (8 nam) cho thấy gồng bụng ổn định cột thắt lưng hơn hóp bụng; chưa chứng minh giảm chấn thương.',
            'Áp lực ổ bụng và huyết áp rất cao khi nâng nặng; người có bệnh tim mạch, tăng huyết áp, thoát vị hoặc mang thai cần hỏi bác sĩ.',
            'Học kỹ thuật với tạ rất nhẹ trước, rồi mới tăng tạ.',
        ]],
    ],
];
