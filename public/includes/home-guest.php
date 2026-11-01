<?php
/**
 * home-guest.php — giao diện trang chủ khi chưa đăng nhập (index.php include file này).
 * Cần sẵn các biến index.php đã chuẩn bị. Thẻ trong hero chỉ là ví dụ minh họa, có ghi rõ "Ví dụ".
 */
?>
<script src="/js/home-guest.js" defer></script>

<section class="gh-hero" id="gh-hero" aria-labelledby="gh-title">
    <!-- Ảnh trang trí nên alt rỗng. Ảnh chính của màn hình đầu: không lazy-load, tải ưu tiên.
         Nguồn ảnh ghi ở assets/img/CREDITS.md -->
    <picture>
        <source media="(max-width: 720px)" type="image/webp" srcset="/assets/img/hero/hero-800.webp" width="800" height="1000">
        <source media="(max-width: 720px)" type="image/jpeg" srcset="/assets/img/hero/hero-800.jpg" width="800" height="1000">
        <source type="image/webp" srcset="/assets/img/hero/hero-1920.webp" width="1920" height="1080">
        <img class="gh-hero__photo" src="/assets/img/hero/hero-1920.jpg" alt="" width="1920" height="1080" fetchpriority="high" decoding="async">
    </picture>
    <div class="gh-hero__shade" aria-hidden="true"></div>

    <!-- Lớp công nghệ vẽ bằng CSS/SVG, chỉ để trang trí -->
    <div class="gh-tech" aria-hidden="true">
        <div class="gh-tech__dots"></div>
        <svg class="gh-tech__ring" viewBox="0 0 400 400" focusable="false">
            <circle class="gh-ring-ticks" cx="200" cy="200" r="190"/>
            <circle class="gh-ring-arc" cx="200" cy="200" r="168"/>
        </svg>
        <svg class="gh-tech__hud" viewBox="0 0 1440 800" preserveAspectRatio="xMaxYMid slice" focusable="false">
            <path d="M760 96h150l28 28h120"/>
            <path d="M700 690h96l30-30h170"/>
            <path d="M1058 124v70"/>
            <circle class="gh-hud-node" cx="760" cy="96" r="4"/>
            <circle class="gh-hud-node gh-hud-node--late" cx="1058" cy="194" r="4"/>
            <circle class="gh-hud-node" cx="996" cy="660" r="4"/>
        </svg>
        <svg class="gh-tech__line" viewBox="0 0 1440 120" preserveAspectRatio="none" focusable="false">
            <polyline class="gh-line-base" points="0,104 160,98 320,100 480,84 640,88 800,66 960,62 1120,42 1280,36 1440,14"/>
            <polyline class="gh-line-run" pathLength="100" points="0,104 160,98 320,100 480,84 640,88 800,66 960,62 1120,42 1280,36 1440,14"/>
        </svg>
    </div>

    <div class="gh-wrap gh-hero__in">
        <div class="gh-hero__copy">
            <p class="gh-hero__eyebrow" lang="en">Train Harder &amp; Smarter</p>
            <h1 class="gh-hero__title" id="gh-title">Lịch tập và dinh dưỡng được may đo cho riêng bạn</h1>
            <p class="gh-hero__lead">Nhập chỉ số cơ thể và mục tiêu, GymSmart gợi ý lịch tập, tính calo và macro, rồi giúp bạn ghi lại từng buổi để thấy mình tiến bộ.</p>
            <div class="gh-hero__cta">
                <a class="gh-btn gh-btn--solid" href="/register.php">Tạo tài khoản miễn phí</a>
                <a class="gh-btn gh-btn--line" href="#tinh-nang">Xem tính năng</a>
            </div>
            <p class="gh-hero__fine">Miễn phí. Đăng ký 3 bước rồi tạo lịch tập đầu tiên.</p>
        </div>

        <!-- Số liệu mẫu để hình dung sản phẩm, không phải dữ liệu của người dùng nào -->
        <div class="gh-cards" role="group" aria-label="Ví dụ minh họa, không phải dữ liệu người dùng thật">
            <div class="gh-card gh-card--kcal">
                <span class="gh-card__tag">Ví dụ</span>
                <p class="gh-card__name">Calo mục tiêu</p>
                <div class="gh-kcal">
                    <svg class="gh-kcal__ring" viewBox="0 0 48 48" width="48" height="48" aria-hidden="true" focusable="false">
                        <circle class="gh-kcal__track" cx="24" cy="24" r="20"/>
                        <circle class="gh-kcal__value" cx="24" cy="24" r="20" pathLength="100"/>
                    </svg>
                    <p><b>2.650</b> kcal/ngày</p>
                </div>
            </div>
            <div class="gh-card gh-card--macro">
                <span class="gh-card__tag">Ví dụ</span>
                <p class="gh-card__name">Macro</p>
                <ul class="gh-macro">
                    <li><span>Đạm</span><b>165 g</b><i class="gh-macro__bar gh-macro__bar--protein"></i></li>
                    <li><span>Tinh bột</span><b>330 g</b><i class="gh-macro__bar gh-macro__bar--carb"></i></li>
                    <li><span>Béo</span><b>74 g</b><i class="gh-macro__bar gh-macro__bar--fat"></i></li>
                </ul>
            </div>
            <div class="gh-card gh-card--plan">
                <span class="gh-card__tag">Ví dụ</span>
                <p class="gh-card__name">Lịch tập hôm nay</p>
                <ul class="gh-plan">
                    <li><span>Đẩy ngực tạ đòn</span><b>4 × 8</b></li>
                    <li><span>Kéo cáp ngồi</span><b>3 × 10</b></li>
                    <li><span>Đẩy vai tạ đơn ngồi</span><b>3 × 10</b></li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="sec" id="tinh-nang">
    <div class="hm-wrap">
        <div class="sec-head">
            <h2>Mọi thứ bạn cần để tập đúng cách</h2>
            <p>Hồ sơ của bạn quyết định lịch tập, lịch tập tạo ra buổi ghi log, log tạo ra biểu đồ tiến trình.</p>
        </div>
        <div class="bento">
            <?php
            $cards = $features;
            $cards[] = ['icon' => 'book', 'title' => 'Bài viết có nguồn nghiên cứu', 'href' => '/articles.php',
                'text' => 'Dinh dưỡng, sức khỏe và tập luyện, nêu rõ mức độ bằng chứng. Chỉ để tham khảo.', 'ready' => true];
            foreach ($cards as $i => $f):
                $tag = $f['ready'] ? 'a' : 'div';
                $wide = $i === 1 ? ' bento-card--wide' : '';
            ?>
                <<?= $tag ?> class="bento-card<?= $wide ?>" <?= $f['ready'] ? 'href="' . htmlspecialchars($f['href']) . '"' : '' ?>>
                    <span class="bento-icon"><?= hmIcon($f['icon'], 26) ?></span>
                    <h3><?= htmlspecialchars($f['title']) ?></h3>
                    <p><?= htmlspecialchars($f['text']) ?></p>
                    <span class="bento-foot"><?= $f['ready'] ? 'Xem chi tiết' : 'Sắp có' ?></span>
                </<?= $tag /* audit:ok $tag chỉ là 'a' hoặc 'div' do mình đặt */ ?>>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($articleTeasers)): ?>
<section class="sec sec--dark" id="kien-thuc">
    <div class="hm-wrap">
        <div class="sec-head">
            <h2>Đọc trước khi tập</h2>
            <p>Mỗi bài nêu rõ mức độ bằng chứng và dẫn nguồn nghiên cứu. Chỉ để tham khảo, không thay tư vấn y tế. Bài viết dành cho thành viên, đăng ký miễn phí.</p>
        </div>
        <div class="arts">
            <?php foreach ($articleTeasers as $s => $a): ?>
                <a class="art" href="/article.php?slug=<?= htmlspecialchars(rawurlencode($s)) ?>">
                    <span class="art-img"><?php if ($a['image'] !== ''): ?><img src="<?= htmlspecialchars($a['image']) ?>" alt="" loading="lazy"><?php endif; ?></span>
                    <span class="art-body">
                        <b><?= htmlspecialchars($a['title']) ?></b>
                        <small><?= htmlspecialchars($a['summary']) ?></small>
                        <span class="art-foot">Đăng nhập để đọc &rarr;</span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="sec-more"><a class="hm-btn hm-btn--ghost" href="/articles.php">Xem tất cả bài viết</a></div>
    </div>
</section>
<?php endif; ?>

<section class="sec">
    <div class="hm-wrap">
        <div class="sec-head"><h2>Bắt đầu chỉ với 3 bước</h2></div>
        <ol class="steps">
            <li><h3>Tạo tài khoản và hồ sơ</h3><p>Nhập tuổi, chiều cao, cân nặng, trình độ và mục tiêu. Chỉ mất vài phút.</p></li>
            <li><h3>Nhận kế hoạch của bạn</h3><p>Hệ thống gợi ý lịch tập và mục tiêu dinh dưỡng phù hợp với hồ sơ của bạn.</p></li>
            <li><h3>Tập, ghi lại, tiến bộ</h3><p>Làm theo video hướng dẫn, ghi lại buổi tập và xem mình tiến bộ qua từng tuần.</p></li>
        </ol>
    </div>
</section>

<section class="sec sec--tight">
    <div class="hm-wrap">
        <div class="final">
            <h2>Sẵn sàng tập nặng hơn, thông minh hơn?</h2>
            <p>Tạo tài khoản miễn phí và có kế hoạch của riêng bạn ngay hôm nay.</p>
            <a class="hm-btn hm-btn--solid" href="/register.php">Tạo tài khoản miễn phí</a>
        </div>
    </div>
</section>
