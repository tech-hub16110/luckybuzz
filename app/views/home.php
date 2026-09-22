<?php
/** @var list<array<string, mixed>> $board */
/** @var array<string, mixed> $stats */
/** @var list<array<string, mixed>> $recent */
/** @var int $boxes */
?>

<!-- Friendly Hero Banner -->
<section class="easy-hero">
    <div class="hero-glow-bubble"></div>
    <div class="easy-hero-head">
        <span class="hero-chip">🎯 3 DRAWS EVERY DAY</span>
        <h1 class="hero-big-title">Pick 5 Lucky Numbers<br><span class="hero-title-highlight">Win Jackpot Coins!</span></h1>
        <p class="hero-simple-sub">Pick your favourite numbers and win the prize pot!</p>
    </div>

    <!-- 3 Daily Schedule Pills -->
    <div class="draw-schedule-bar">
        <div class="schedule-pill pill-morning">
            <span class="sch-icon">☀️</span>
            <span class="sch-name">Morning</span>
            <strong class="sch-time">01:00 PM</strong>
        </div>
        <div class="schedule-pill pill-day">
            <span class="sch-icon">🌅</span>
            <span class="sch-name">Day</span>
            <strong class="sch-time">06:00 PM</strong>
        </div>
        <div class="schedule-pill pill-night">
            <span class="sch-icon">🌙</span>
            <span class="sch-name">Night</span>
            <strong class="sch-time">08:00 PM</strong>
        </div>
    </div>
</section>

<!-- Active Draws Section -->
<section class="section-container">
    <div class="section-header">
        <h2 class="section-title">🎲 Live Draws Available Now</h2>
        <span class="section-subtitle">Tap any card to pick your numbers</span>
    </div>

    <?php if ($board === []): ?>
        <div class="empty-card">
            <span class="empty-icon">⏳</span>
            <h3>Next draw starting soon!</h3>
            <p>The draw board refreshes automatically. Please check back in a few moments.</p>
        </div>
    <?php endif; ?>

    <div class="draw-cards-list">
        <?php foreach ($board as $draw): ?>
            <?php 
                $tier = Config::tier($draw['tier']); 
                $at = new DateTimeImmutable($draw['draw_at']); 
                $accent = $tier['accent'];
                $iconEmoji = $accent === 'green' ? '☀️' : ($accent === 'amber' ? '🌅' : '🌙');
            ?>
            <article class="big-draw-card tier-<?= e($accent) ?>" data-draw-at="<?= e($draw['draw_at']) ?>">
                <div class="card-top-row">
                    <div class="draw-badge">
                        <span class="draw-emoji"><?= $iconEmoji ?></span>
                        <span class="draw-name"><?= e($tier['label']) ?> Draw</span>
                    </div>
                    <div class="draw-countdown-box">
                        <span class="countdown-icon">⏰</span>
                        <span class="countdown-timer" data-clock>Loading...</span>
                    </div>
                </div>

                <div class="card-prize-box">
                    <span class="prize-tag">WINNING PRIZE</span>
                    <div class="prize-value-row">
                        <span class="prize-currency">₹</span>
                        <span class="prize-number"><?= number_format((int) $draw['jackpot']) ?></span>
                        <span class="prize-coins-label">Coins</span>
                    </div>
                </div>

                <div class="card-schedule-info">
                    <span class="info-pill">📅 <?= e($at->format('l, d M')) ?></span>
                    <span class="info-pill">🕒 <?= e($at->format('h:i A')) ?></span>
                </div>

                <a class="big-action-btn btn-<?= e($accent) ?>" href="/play?draw=<?= (int) $draw['id'] ?>">
                    <span class="btn-icon-left">👉</span>
                    <span class="btn-main-text">PICK NUMBERS NOW</span>
                    <span class="btn-icon-right">➔</span>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- 3-Step Visual How To Play Guide -->
<section class="how-to-play-card">
    <h3 class="how-title">✨ How To Play in 3 Easy Steps</h3>
    <div class="steps-grid">
        <div class="step-box">
            <div class="step-num-badge">1</div>
            <div class="step-icon">🎯</div>
            <h4 class="step-heading">Pick 5 Digits</h4>
            <p class="step-desc">Enter 5 numbers or tap 🎲 Lucky Pick</p>
        </div>
        <div class="step-box">
            <div class="step-num-badge">2</div>
            <div class="step-icon">⚡</div>
            <h4 class="step-heading">Choose Multiplier</h4>
            <p class="step-desc">Pick 5x, 10x or 20x to boost your win</p>
        </div>
        <div class="step-box">
            <div class="step-num-badge">3</div>
            <div class="step-icon">🏆</div>
            <h4 class="step-heading">Win Prize Coins</h4>
            <p class="step-desc">Match numbers and win big coin prizes</p>
        </div>
    </div>
</section>

<!-- Recent Draw Results -->
<section class="section-container">
    <div class="section-header">
        <h2 class="section-title">🏆 Recent Results & Winning Numbers</h2>
        <a class="section-link" href="/results">View All Results ➔</a>
    </div>

    <?php if ($recent === []): ?>
        <div class="empty-card">
            <p>Results will show here as soon as the first draw completes.</p>
        </div>
    <?php else: ?>
        <div class="recent-results-grid">
            <?php foreach ($recent as $row): ?>
                <?php 
                    $tier = Config::tier($row['tier']);
                    $accent = $tier['accent'];
                    $digits = str_split((string) $row['result']);
                ?>
                <div class="result-tile tier-<?= e($accent) ?>">
                    <div class="tile-head">
                        <span class="pill pill-<?= e($accent) ?>"><?= e($tier['label']) ?></span>
                        <span class="tile-date"><?= e((new DateTimeImmutable($row['draw_at']))->format('d M, h:i A')) ?></span>
                    </div>
                    <div class="balls-row">
                        <?php foreach ($digits as $d): ?>
                            <span class="lotto-ball"><?= e($d) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Official Lottery Partner & References -->
<div class="home-partner-link" style="text-align: center; margin: 2rem auto; padding: 1.5rem 1rem; border-top: 1px solid rgba(255,255,255,0.08);">
    <a href="https://lottery.sambad.com/">Lottery Sambad</a>
</div>


