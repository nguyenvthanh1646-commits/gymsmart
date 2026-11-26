<?php
/**
 * home-guest.php — trang chủ khi chưa đăng nhập (index.php include file này). Hướng "Future sport": hero tối công nghệ + thể thao, các khối dưới nền sáng.
 *
 * Cần các biến index.php đã chuẩn bị: $guestStats (số liệu THẬT đếm từ DB/bài viết), $previewExercises, $articleTeasers,
 * $muscleLabels, $levelLabels và hàm hmIcon(). Không có con số viết tay: thiếu dữ liệu thì câu đó bị ẩn.
 * Mọi số liệu minh họa (bảng điều khiển, khung chat mẫu) đều gắn nhãn "Ví dụ".
 * Giao diện: css/home-guest.css (class "gh-"). Hành vi: js/home-guest.js (nạp ở cuối file này, chỉ trên trang khách).
 * Trang vẫn đọc được đầy đủ khi tắt JS: JS chỉ thêm chuyển động và công cụ tính calo.
 */
$ghExercises = (int)$guestStats['exercises'];
$ghArticles  = (int)$guestStats['sourcedArticles'];
?>
<section class="gh-hero" id="gh-hero" aria-labelledby="gh-title">
    <!-- Lớp trang trí (aria-hidden): lưới sàn phối cảnh, quầng sáng neon, 2 đường "dữ liệu" chạy chậm.
         Hiệu ứng chạy liên tục tự dừng khi cuộn qua hero, khi tab ẩn, và tắt hẳn khi prefers-reduced-motion. -->
    <div class="gh-hero__floor" aria-hidden="true"></div>
    <div class="gh-hero__glow" aria-hidden="true"></div>
    <svg class="gh-hero__data" viewBox="0 0 1440 600" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <path class="gh-data-base" d="M-20 470 C 220 430, 380 500, 600 420 S 980 330, 1180 360 S 1380 300, 1460 260"/>
        <path class="gh-data-base" d="M-20 540 C 260 520, 420 560, 700 500 S 1060 440, 1260 460 S 1400 420, 1460 400"/>
        <path class="gh-data gh-data--a" d="M-20 470 C 220 430, 380 500, 600 420 S 980 330, 1180 360 S 1380 300, 1460 260"/>
        <path class="gh-data gh-data--b" d="M-20 540 C 260 520, 420 560, 700 500 S 1060 440, 1260 460 S 1400 420, 1460 400"/>
    </svg>

    <div class="gh-wrap gh-hero__in">
        <div class="gh-hero__copy">
            <p class="gh-badge"><span class="gh-badge__dot" aria-hidden="true"></span>Lịch tập · Dinh dưỡng · Trợ lý AI</p>
            <h1 class="gh-hero__title" id="gh-title"><span>Tập đúng.</span> <span>Ăn đủ.</span> <span><span class="gh-grad">Tiến bộ</span> thật.</span></h1>
            <p class="gh-hero__lead">GymSmart gợi ý lịch tập, tính calo và macro theo cơ thể bạn, rồi ghi lại từng buổi để bạn thấy mình tiến bộ.</p>
            <div class="gh-hero__cta">
                <a class="gh-btn gh-btn--solid" href="/register.php">Tạo tài khoản miễn phí</a>
                <a class="gh-btn gh-btn--glass" href="#tinh-nang">Xem tính năng</a>
            </div>
            <p class="gh-hero__fine">Miễn phí · Đăng ký 3 bước · Không cần thẻ</p>
            <?php if ($ghExercises > 0): /* số liệu THẬT đếm từ DB và thư mục bài viết, không phải số người dùng */ ?>
                <dl class="gh-score">
                    <div><dt>bài tập có video</dt><dd><?= (int)$ghExercises ?></dd></div>
                    <div><dt>nhóm cơ</dt><dd><?= (int)$guestStats['muscleGroups'] ?></dd></div>
                    <?php if ($ghArticles > 0): ?><div><dt>bài viết có dẫn chứng</dt><dd><?= (int)$ghArticles ?></dd></div><?php endif; ?>
                </dl>
            <?php endif; ?>
        </div>

        <div class="gh-stage" id="gh-stage">
            <!-- Ảnh trang trí: alt rỗng. Ảnh màn hình đầu: không lazy-load, tải ưu tiên. Nguồn ảnh (ảnh nhóm cung cấp): assets/img/CREDITS.md -->
            <div class="gh-stage__photo" aria-hidden="true">
                <picture>
                    <source type="image/webp" srcset="/assets/img/hero/hero-duo.webp" width="450" height="492">
                    <img src="/assets/img/hero/hero-duo.jpg" alt="" width="450" height="492" fetchpriority="high" decoding="async">
                </picture>
                <span class="gh-stage__scan"></span>
            </div>
            <span class="gh-stage__stripes" aria-hidden="true"></span>

            <!-- MỘT bảng điều khiển mô phỏng, số liệu mẫu để hình dung sản phẩm -->
            <div class="gh-dash" id="gh-dash" role="group" aria-label="Bảng điều khiển ví dụ, không phải dữ liệu của người dùng nào">
                <div class="gh-dash__head">
                    <span><b>Hôm nay</b> · Thứ Tư</span>
                    <span class="gh-tag">Ví dụ</span>
                </div>
                <div class="gh-dash__row">
                    <div class="gh-kcal">
                        <svg viewBox="0 0 100 100" width="96" height="96" aria-hidden="true" focusable="false">
                            <circle class="gh-kcal__track" cx="50" cy="50" r="42"/>
                            <circle class="gh-kcal__value" cx="50" cy="50" r="42" pathLength="100"/>
                        </svg>
                        <p><b data-count="2650">2.650</b><small>kcal/ngày</small></p>
                    </div>
                    <ul class="gh-macro" aria-label="Macro mỗi ngày">
                        <li><span>Đạm</span><i style="--w:62%"></i><b>165 g</b></li>
                        <li><span>Tinh bột</span><i style="--w:88%"></i><b>330 g</b></li>
                        <li><span>Chất béo</span><i style="--w:40%"></i><b>74 g</b></li>
                    </ul>
                </div>
                <ol class="gh-week" aria-label="Tuần này: đã tập thứ Hai và thứ Tư, thứ Sáu có buổi tập">
                    <li class="is-done">T2</li><li>T3</li><li class="is-done is-today">T4</li><li>T5</li><li class="is-plan">T6</li><li>T7</li><li>CN</li>
                </ol>
                <div class="gh-ai">
                    <span class="gh-ai__av" aria-hidden="true">AI</span>
                    <p><small>GymSmart AI</small><span class="gh-ai__text" data-type>Mới tập ngực? Bắt đầu với Hít đất 3×10 và Ép ngực máy 3×12, nghỉ 90 giây giữa các set.</span></p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Dải từ khóa chạy ngang: trang trí (trùng nội dung khối tính năng) nên ẩn với trình đọc màn hình -->
<div class="gh-marquee" id="gh-marquee" aria-hidden="true">
    <div class="gh-marquee__band">
        <div class="gh-marquee__track">
            <?php for ($copy = 0; $copy < 2; $copy++): $dup = $copy === 1; /* bản lặp để chạy vòng liền mạch */ ?>
                <span<?= $dup ? ' class="gh-dup"' : '' ?>>Lịch tập AI</span><span<?= $dup ? ' class="gh-dup"' : '' ?>>TDEE &amp; Macro</span> <?php /* audit:ok chỉ in chữ viết sẵn */ ?>
                <?php if ($ghExercises > 0): ?><span<?= $dup ? ' class="gh-dup"' : '' ?>><?= (int)$ghExercises ?> bài tập có video</span><?php endif; ?>
                <span<?= $dup ? ' class="gh-dup"' : '' ?>>Biểu đồ tiến trình</span><span<?= $dup ? ' class="gh-dup"' : '' ?>>Chatbot 24/7</span><span<?= $dup ? ' class="gh-dup"' : '' ?>>Bài viết có dẫn chứng</span> <?php /* audit:ok chỉ in chữ viết sẵn */ ?>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- ===== Công cụ tính calo nhanh: tính ngay trong trình duyệt, KHÔNG gửi dữ liệu lên máy chủ ===== -->
<section class="gh-section gh-calc-sec" id="tinh-calo" aria-labelledby="gh-calc-title">
    <div class="gh-wrap gh-calc">
        <div class="gh-calc__intro" data-reveal>
            <p class="gh-eyebrow">Thử ngay, không cần tài khoản</p>
            <h2 id="gh-calc-title">Mỗi ngày bạn cần bao nhiêu calo?</h2>
            <p>Dùng đúng công thức Mifflin–St Jeor như trang Dinh dưỡng của GymSmart. Dữ liệu bạn nhập chỉ nằm trong trình duyệt, không gửi đi đâu.</p>
        </div>
        <form class="gh-calc__card" id="gh-calc" novalidate data-reveal>
            <fieldset class="gh-seg">
                <legend>Giới tính</legend>
                <label><input type="radio" name="gender" value="MALE" checked><span>Nam</span></label>
                <label><input type="radio" name="gender" value="FEMALE"><span>Nữ</span></label>
                <label><input type="radio" name="gender" value="OTHER"><span>Khác</span></label>
            </fieldset>
            <div class="gh-calc__grid">
                <label class="gh-field">Tuổi<input type="number" name="age" min="10" max="100" inputmode="numeric" value="22" required></label>
                <label class="gh-field">Chiều cao (cm)<input type="number" name="height" min="100" max="250" step="0.1" inputmode="decimal" value="170" required></label>
                <label class="gh-field">Cân nặng (kg)<input type="number" name="weight" min="30" max="300" step="0.1" inputmode="decimal" value="65" required></label>
            </div>
            <label class="gh-field">Mức vận động
                <select name="activity">
                    <option value="1.2">Ít vận động</option>
                    <option value="1.375">Nhẹ (1-3 buổi/tuần)</option>
                    <option value="1.55" selected>Vừa (3-5 buổi/tuần)</option>
                    <option value="1.725">Nhiều (6-7 buổi/tuần)</option>
                    <option value="1.9">Rất nhiều</option>
                </select>
            </label>
            <fieldset class="gh-seg">
                <legend>Mục tiêu</legend>
                <label><input type="radio" name="goal" value="0.8" checked><span>Giảm mỡ</span></label>
                <label><input type="radio" name="goal" value="1"><span>Duy trì</span></label>
                <label><input type="radio" name="goal" value="1.1"><span>Tăng cơ</span></label>
            </fieldset>
            <!-- type="button": khi tắt JS, bấm nút không gửi form (không đưa chỉ số cơ thể lên máy chủ qua URL). JS đổi thành submit. -->
            <button class="gh-btn gh-btn--dark" type="button" id="gh-calc-go">Tính calo</button>
            <noscript><p class="gh-calc__note">Công cụ này cần bật JavaScript. Bạn vẫn có thể tạo tài khoản để tính trong trang Dinh dưỡng.</p></noscript>
            <div class="gh-calc__out" id="gh-calc-out" aria-live="polite" hidden>
                <p class="gh-calc__err" id="gh-calc-err" hidden></p>
                <dl id="gh-calc-res">
                    <div><dt>TDEE (duy trì cân nặng)</dt><dd><b id="gh-r-tdee">—</b> kcal</dd></div>
                    <div class="is-main"><dt>Calo mục tiêu mỗi ngày</dt><dd><b id="gh-r-target">—</b> kcal</dd></div>
                    <div><dt>Protein gợi ý (người mới, 1,6 g/kg)</dt><dd><b id="gh-r-protein">—</b> g</dd></div>
                </dl>
                <p class="gh-calc__note">Ước tính để tham khảo, không thay thế tư vấn của bác sĩ hay chuyên gia dinh dưỡng.</p>
                <a class="gh-btn gh-btn--solid" href="/register.php">Tạo tài khoản để lưu và nhận lịch tập</a>
            </div>
        </form>
    </div>
</section>

<!-- ===== Tính năng: lưới bento ===== -->
<section class="gh-section" id="tinh-nang" aria-labelledby="gh-features-title">
    <div class="gh-wrap">
        <div class="gh-head" data-reveal>
            <h2 id="gh-features-title">Một nơi. Cả hành trình tập.</h2>
            <p>Hồ sơ của bạn quyết định lịch tập và dinh dưỡng; nhật ký cho thấy bạn tiến bộ ra sao.</p>
        </div>
        <ul class="gh-bento">
            <li class="gh-tile gh-tile--lib" data-reveal>
                <span class="gh-tile__ic"><?= hmIcon('play', 22) ?></span>
                <h3>Thư viện bài tập có video</h3>
                <p><?= htmlspecialchars($ghExercises > 0 ? $ghExercises . ' bài tập thuộc ' . (int)$guestStats['muscleGroups'] . ' nhóm cơ' : 'Bài tập theo nhóm cơ') ?>, mỗi bài có video, các bước thực hiện và lỗi thường gặp.</p>
                <?php if ($previewExercises): ?>
                    <div class="gh-thumbs">
                        <?php foreach (array_slice($previewExercises, 0, 3) as $ex): if (($imgSrc = exerciseImageSrc($ex['image_url'])) === null) continue; ?>
                            <a href="/exercise-detail.php?id=<?= (int)$ex['id'] ?>" class="gh-thumb">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" width="800" height="448" loading="lazy" decoding="async">
                                <span><?= htmlspecialchars($ex['name']) ?><small><?= htmlspecialchars($muscleLabels[$ex['muscle_group']] ?? $ex['muscle_group']) ?> · <?= htmlspecialchars($levelLabels[$ex['difficulty']] ?? $ex['difficulty']) ?></small></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($ghExercises > 0): ?><span class="gh-tile__num" aria-hidden="true"><?= (int)$ghExercises ?></span><?php endif; ?>
            </li>
            <li class="gh-tile gh-tile--dark" data-reveal>
                <span class="gh-tile__ic"><?= hmIcon('chat', 22) ?></span>
                <h3>Chatbot 24/7</h3>
                <p>Hỏi về bài tập, dinh dưỡng; trả lời dựa trên hồ sơ nếu bạn cho phép.</p>
            </li>
            <li class="gh-tile" data-reveal>
                <span class="gh-tile__ic"><?= hmIcon('flame', 22) ?></span>
                <h3>TDEE &amp; Macro</h3>
                <p>Calo và đạm, tinh bột, chất béo theo cơ thể và mục tiêu.</p>
                <div class="gh-mini-macro" aria-hidden="true"><i style="--w:62%"></i><i style="--w:88%"></i><i style="--w:40%"></i></div>
            </li>
            <li class="gh-tile gh-tile--wide" data-reveal>
                <span class="gh-tile__ic"><?= hmIcon('calendar', 22) ?></span>
                <h3>Lịch tập cá nhân hóa</h3>
                <p>Chia buổi theo mục tiêu, trình độ và số ngày bạn có; ghi log từng bài ngay trong lịch.</p>
                <ol class="gh-mini-week" aria-hidden="true"><li class="on">T2</li><li>T3</li><li class="on">T4</li><li>T5</li><li class="on">T6</li><li>T7</li><li>CN</li></ol>
            </li>
            <li class="gh-tile" data-reveal>
                <span class="gh-tile__ic"><?= hmIcon('meal', 22) ?></span>
                <h3>Lịch ăn gợi ý</h3>
                <p>Chia bữa theo mục tiêu, đổi món, danh sách đi chợ.</p>
            </li>
            <li class="gh-tile" data-reveal>
                <span class="gh-tile__ic"><?= hmIcon('chart', 22) ?></span>
                <h3>Biểu đồ tiến trình</h3>
                <p>Cân nặng, số đo theo thời gian.</p>
                <svg class="gh-spark" viewBox="0 0 200 60" aria-hidden="true" focusable="false"><path d="M4 50 C 40 44, 60 48, 92 32 S 150 24, 196 8"/></svg>
            </li>
            <li class="gh-tile gh-tile--wide" data-reveal>
                <span class="gh-tile__ic"><?= hmIcon('book', 22) ?></span>
                <h3>Bài viết có dẫn chứng</h3>
                <p><?= htmlspecialchars($ghArticles > 0 ? $ghArticles . ' bài viết có trích nguồn nghiên cứu để bạn tự kiểm tra.' : 'Kiến thức tập luyện và dinh dưỡng có trích nguồn để bạn tự kiểm tra.') ?></p>
            </li>
        </ul>
    </div>
</section>

<!-- ===== Cách hoạt động: đường nối chạy theo khi cuộn ===== -->
<section class="gh-section gh-section--alt" aria-labelledby="gh-steps-title">
    <div class="gh-wrap">
        <div class="gh-head" data-reveal><h2 id="gh-steps-title">Bắt đầu trong 3 bước</h2></div>
        <ol class="gh-steps" id="gh-steps">
            <li data-reveal>
                <span class="gh-steps__n" aria-hidden="true">1</span>
                <h3>Đăng ký</h3>
                <p>Tạo tài khoản miễn phí bằng email và mật khẩu.</p>
            </li>
            <li data-reveal>
                <span class="gh-steps__n" aria-hidden="true">2</span>
                <h3>Nhập chỉ số và mục tiêu</h3>
                <p>Tuổi, chiều cao, cân nặng, mức vận động, trình độ và mục tiêu của bạn.</p>
            </li>
            <li data-reveal>
                <span class="gh-steps__n" aria-hidden="true">3</span>
                <h3>Tập, ăn, theo dõi</h3>
                <p>Nhận lịch tập và thực đơn, ghi lại từng buổi, xem biểu đồ thay đổi.</p>
            </li>
        </ol>
    </div>
</section>

<!-- ===== Chatbot: khung chat mẫu ===== -->
<section class="gh-section" aria-labelledby="gh-bot-title">
    <div class="gh-wrap gh-bot">
        <div class="gh-bot__copy" data-reveal>
            <p class="gh-eyebrow">GymSmart AI</p>
            <h2 id="gh-bot-title">Hỏi gì cũng được, trả lời theo hồ sơ của bạn</h2>
            <ul class="gh-checks">
                <li><?= hmIcon('check', 18) ?>Biết mục tiêu, trình độ và lịch tập của bạn nếu bạn bật "Dùng hồ sơ của tôi".</li>
                <li><?= hmIcon('check', 18) ?>Gợi ý bài tập có sẵn trong thư viện, kèm đường dẫn tới video.</li>
                <li><?= hmIcon('check', 18) ?>Tiếng Việt, ngắn gọn, dùng được ngay giữa buổi tập.</li>
            </ul>
            <p class="gh-warn"><?= hmIcon('target', 18) ?>Chatbot chỉ để tham khảo, không thay thế bác sĩ. Đau hay chấn thương, hãy đi khám.</p>
        </div>
        <div class="gh-chat" data-reveal role="group" aria-label="Đoạn chat ví dụ">
            <div class="gh-chat__head"><span class="gh-ai__av" aria-hidden="true">AI</span><b>GymSmart AI</b><span class="gh-tag">Ví dụ</span></div>
            <p class="gh-msg gh-msg--me">Mình tập 3 buổi/tuần, muốn giảm mỡ bụng thì nên ăn bao nhiêu?</p>
            <p class="gh-msg">Theo hồ sơ của bạn, calo mục tiêu khoảng <b>1.990 kcal</b>/ngày và nên ăn đủ khoảng <b>104 g</b> protein. Không có bài tập nào chỉ đốt mỡ bụng; giữ lịch 3 buổi đều đặn và ăn đúng mức calo là quan trọng nhất.</p>
            <p class="gh-msg gh-msg--me">Hôm nay đau vai nhẹ có tập được không?</p>
            <p class="gh-msg">Bạn nên bỏ các bài đẩy qua đầu hôm nay và chọn bài chân hoặc lưng nhẹ. Nếu đau kéo dài hoặc sưng, hãy đi khám.</p>
        </div>
    </div>
</section>

<!-- ===== Bài viết thật: "Đọc trước khi tập" ===== -->
<section class="gh-section gh-section--alt" id="kien-thuc" aria-labelledby="gh-know-title">
    <div class="gh-wrap">
        <div class="gh-head" data-reveal>
            <h2 id="gh-know-title">Đọc trước khi tập</h2>
            <p><?php if ($ghArticles > 0): ?><?= (int)$ghArticles ?> bài viết có dẫn nguồn nghiên cứu. <?php endif; ?>Chỉ để tham khảo, không thay tư vấn y tế.</p>
        </div>
        <?php if (empty($articleTeasers)): ?>
            <p class="gh-empty">Bài viết đang được cập nhật. Bạn quay lại sau nhé.</p>
        <?php else: ?>
            <ul class="gh-articles">
                <?php foreach ($articleTeasers as $s => $a): ?>
                    <li data-reveal>
                        <a class="gh-article" href="/article.php?slug=<?= htmlspecialchars(rawurlencode($s)) ?>">
                            <?php if ($a['image'] !== ''): ?>
                                <img class="gh-article__img" src="<?= htmlspecialchars($a['image']) ?>" alt="" width="1200" height="675" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <div class="gh-article__body">
                                <?php if ($a['mustRead']): ?><span class="gh-article__tag">Đọc trước khi tập</span><?php endif; ?>
                                <h3><?= htmlspecialchars($a['title']) ?></h3>
                                <p class="gh-article__summary"><?= htmlspecialchars($a['summary']) ?></p>
                                <span class="gh-link">Đăng nhập để đọc <span aria-hidden="true">&rarr;</span></span>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<!-- ===== Kêu gọi cuối trang ===== -->
<section class="gh-final" id="gh-final" aria-labelledby="gh-final-title">
    <div class="gh-wrap gh-final__in" data-reveal>
        <h2 id="gh-final-title">Buổi tập đầu tiên bắt đầu từ hôm nay.</h2>
        <p>Tạo tài khoản miễn phí, nhập chỉ số cơ thể và nhận lịch tập của riêng bạn.</p>
        <a class="gh-btn gh-btn--dark" href="/register.php">Tạo tài khoản miễn phí</a>
    </div>
</section>

<!-- Điện thoại: thanh nút dính đáy, hiện sau khi cuộn qua hero, ẩn khi tới phần kêu gọi cuối trang -->
<div class="gh-sticky" id="gh-sticky" hidden>
    <a class="gh-btn gh-btn--solid" href="/register.php">Tạo tài khoản miễn phí</a>
</div>

<script src="/js/home-guest.js"></script>
