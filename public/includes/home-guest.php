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
                <a class="btn" href="/register.php">Tạo tài khoản miễn phí</a>
                <a class="btn gh-btn--line" href="#tinh-nang">Xem tính năng</a>
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

<section class="gh-section" id="tinh-nang" aria-labelledby="gh-features-title">
    <div class="gh-wrap">
        <div class="gh-head">
            <h2 id="gh-features-title">Mọi thứ cần cho việc tập luyện, ở một nơi</h2>
            <p>Hồ sơ của bạn quyết định lịch tập và dinh dưỡng, còn nhật ký buổi tập cho thấy bạn tiến bộ ra sao.</p>
        </div>
        <ul class="gh-features">
            <?php foreach ($guestFeatures as [$icon, $title, $text]): ?>
                <li class="gh-feature">
                    <span class="gh-feature__icon"><?= hmIcon($icon, 24) ?></span>
                    <h3><?= htmlspecialchars($title) ?></h3>
                    <p><?= htmlspecialchars($text) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="gh-section gh-section--alt" aria-labelledby="gh-steps-title">
    <div class="gh-wrap">
        <div class="gh-head"><h2 id="gh-steps-title">Cách hoạt động</h2></div>
        <ol class="gh-steps">
            <li>
                <h3>Đăng ký</h3>
                <p>Tạo tài khoản miễn phí bằng email và mật khẩu.</p>
            </li>
            <li>
                <h3>Nhập chỉ số cơ thể và mục tiêu</h3>
                <p>Tuổi, chiều cao, cân nặng, mức vận động, trình độ tập và mục tiêu của bạn.</p>
            </li>
            <li>
                <h3>Nhận lịch tập, thực đơn và theo dõi tiến trình</h3>
                <p>Tập theo lịch gợi ý, xem gợi ý bữa ăn, ghi lại từng buổi và xem biểu đồ thay đổi.</p>
            </li>
        </ol>
    </div>
</section>

<section class="gh-section" aria-labelledby="gh-preview-title">
    <div class="gh-wrap">
        <div class="gh-head">
            <h2 id="gh-preview-title">Xem trước thư viện bài tập</h2>
            <?php if ($guestStats['exercises'] > 0 && $guestStats['muscleGroups'] > 0): ?>
                <p><?= (int)$guestStats['exercises'] ?> bài tập thuộc <?= (int)$guestStats['muscleGroups'] ?> nhóm cơ, mỗi bài có ảnh, video và hướng dẫn từng bước.</p>
            <?php endif; ?>
        </div>
        <?php if (empty($previewExercises)): ?>
            <p class="gh-empty">Thư viện bài tập đang được cập nhật. Bạn quay lại sau nhé.</p>
        <?php else: ?>
            <ul class="gh-exercises">
                <?php foreach ($previewExercises as $ex): ?>
                    <li>
                        <a class="gh-exercise" href="/exercise-detail.php?id=<?= (int)$ex['id'] ?>">
                            <?php if (($imgSrc = exerciseImageSrc($ex['image_url'])) !== null): ?>
                                <img class="gh-exercise__img" src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($ex['name']) ?>" width="800" height="448" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div class="gh-exercise__img gh-exercise__img--none">Chưa có ảnh</div>
                            <?php endif; ?>
                            <div class="gh-exercise__body">
                                <h3><?= htmlspecialchars($ex['name']) ?></h3>
                                <p class="gh-exercise__meta"><?= htmlspecialchars($muscleLabels[$ex['muscle_group']] ?? $ex['muscle_group']) ?> · <?= htmlspecialchars($levelLabels[$ex['difficulty']] ?? $ex['difficulty']) ?></p>
                                <span class="gh-link">Đăng nhập để xem <span aria-hidden="true">&rarr;</span></span>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<section class="gh-section gh-section--alt" id="kien-thuc" aria-labelledby="gh-know-title">
    <div class="gh-wrap">
        <div class="gh-head">
            <h2 id="gh-know-title">Kiến thức đáng tin: đọc trước khi tập</h2>
            <p>
                <?php if ($guestStats['sourcedArticles'] > 0): ?>
                    <?= (int)$guestStats['sourcedArticles'] ?> bài viết có dẫn nguồn nghiên cứu.
                <?php endif; ?>
                Chỉ để tham khảo, không thay tư vấn y tế.
            </p>
        </div>
        <?php if (empty($articleTeasers)): ?>
            <p class="gh-empty">Bài viết đang được cập nhật. Bạn quay lại sau nhé.</p>
        <?php else: ?>
            <ul class="gh-articles">
                <?php foreach ($articleTeasers as $s => $a): ?>
                    <li>
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
            <div class="gh-more"><a class="btn btn--ghost" href="/articles.php">Đăng nhập để xem tất cả bài viết</a></div>
        <?php endif; ?>
    </div>
</section>

<section class="gh-section gh-section--final" aria-labelledby="gh-final-title">
    <div class="gh-wrap">
        <div class="gh-final">
            <h2 id="gh-final-title">Sẵn sàng có lịch tập của riêng bạn?</h2>
            <p>Tạo tài khoản miễn phí, nhập chỉ số cơ thể và bắt đầu buổi tập đầu tiên.</p>
            <a class="btn" href="/register.php">Tạo tài khoản miễn phí</a>
        </div>
    </div>
</section>
