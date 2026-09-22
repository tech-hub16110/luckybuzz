<?php
/** @var list<array<string, mixed>> $waiting */
/** @var list<array<string, mixed>> $history */
/** @var list<array<string, mixed>> $odds */
/** @var float $ev */
?>
<section class="section-container">
    <div class="section-header">
        <h1 class="section-title">🎁 Free Surprise Gift Boxes</h1>
        <span class="section-subtitle">Get a free gift box after every draw you enter!</span>
    </div>
</section>

<?php if ($waiting === []): ?>
    <div class="empty-card">
        <span class="empty-icon">🎁</span>
        <h3><?= $history === [] ? 'No Boxes Yet' : 'All Boxes Opened!' ?></h3>
        <p><?= $history === [] ? 'Play any ticket to earn a free surprise box after the draw.' : 'You have opened ' . count($history) . ' boxes so far. Play another draw to get more!' ?></p>
        <a class="big-action-btn btn-green" href="/play">👉 Play Draw Now</a>
    </div>
<?php else: ?>
    <div class="bonus-cards-grid">
        <?php foreach ($waiting as $box): ?>
            <?php $tier = Config::tier($box['tier']); ?>
            <form method="post" action="/action/box" class="claim-bonus-card card-active">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="draw_id" value="<?= (int) $box['id'] ?>">
                
                <div class="claim-card-top">
                    <span class="claim-icon">🎁</span>
                    <div class="claim-card-info">
                        <h3 class="claim-card-title"><?= e($tier['label']) ?> Surprise Box</h3>
                        <span class="claim-reward-val">Drew Winning Number: <?= e($box['result']) ?></span>
                    </div>
                </div>

                <button class="big-action-btn btn-green pulse-btn" type="submit">
                    <span>✨ TAP TO OPEN BOX & WIN COINS</span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($history !== []): ?>
    <section class="section-container" style="margin-top: 24px;">
        <div class="section-header">
            <h2 class="section-title">📦 Already Opened Boxes</h2>
        </div>
        <div class="ledger-cards-list">
            <?php foreach ($history as $row): ?>
                <div class="ledger-item-card">
                    <div class="ledger-item-left">
                        <span class="ledger-action-name">🎁 <?= e(Config::tier($row['tier'])['label']) ?> Box</span>
                        <span class="ledger-item-date"><?= e($row['day']) ?> · Drew <?= e($row['result']) ?></span>
                    </div>
                    <div class="ledger-item-amount amt-plus">
                        +₹<?= coins((int) $row['coins']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
