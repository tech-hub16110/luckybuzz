<?php
/** @var list<array<string, mixed>> $tickets */
/** @var array<string, int> $summary */
?>
<section class="section-container">
    <div class="section-header">
        <h1 class="section-title">🎟️ My Lottery Tickets</h1>
        <span class="section-subtitle">Check your purchased numbers and winnings</span>
    </div>

    <!-- 4 Big Summary Cards -->
    <div class="stats-overview-grid">
        <div class="stat-card">
            <span class="stat-icon-emoji">🎟️</span>
            <span class="stat-big-num"><?= coins($summary['tickets']) ?></span>
            <span class="stat-card-lbl">Total Played</span>
        </div>
        <div class="stat-card card-live">
            <span class="stat-icon-emoji">⏳</span>
            <span class="stat-big-num"><?= coins($summary['open']) ?></span>
            <span class="stat-card-lbl">Live Now</span>
        </div>
        <div class="stat-card card-won">
            <span class="stat-icon-emoji">🏆</span>
            <span class="stat-big-num"><?= coins($summary['won']) ?></span>
            <span class="stat-card-lbl">Total Wins</span>
        </div>
        <div class="stat-card card-best">
            <span class="stat-icon-emoji">⭐</span>
            <span class="stat-big-num"><?= inr($summary['best']) ?></span>
            <span class="stat-card-lbl">Best Prize</span>
        </div>
    </div>
</section>

<?php if ($tickets === []): ?>
    <div class="empty-card">
        <span class="empty-icon">🎟️</span>
        <h3>No Tickets Yet!</h3>
        <p>Pick your 5 lucky numbers and enter the draw today for free coins!</p>
        <a class="big-action-btn btn-green" href="/play">👉 Pick Lucky Numbers Now</a>
    </div>
<?php else: ?>
    <div class="ticket-cards-container">
        <?php foreach ($tickets as $t): ?>
            <?php
            $tier = Config::tier($t['tier']);
            $settled = $t['result'] !== null;
            $won = $t['match_kind'] !== null;
            $digits = str_split((string) $t['number']);
            $accent = $tier['accent'];
            $tierEmoji = $accent === 'green' ? '☀️' : ($accent === 'amber' ? '🌅' : '🌙');
            ?>
            <article class="lottery-ticket-card tier-<?= e($accent) ?> <?= $won ? 'ticket-is-winner' : ($settled ? 'ticket-is-settled' : 'ticket-is-live') ?>">
                <!-- Left Stub / Header -->
                <div class="ticket-header-strip">
                    <div class="ticket-tier-info">
                        <span class="tier-emoji"><?= $tierEmoji ?></span>
                        <strong class="tier-name"><?= e($tier['label']) ?> Draw</strong>
                    </div>
                    <span class="ticket-draw-time"><?= e((new DateTimeImmutable($t['draw_at']))->format('d M, h:i A')) ?></span>
                </div>

                <!-- Main Number Display -->
                <div class="ticket-middle-body">
                    <span class="ticket-field-label">YOUR NUMBERS:</span>
                    <div class="ticket-lotto-balls">
                        <?php foreach ($digits as $d): ?>
                            <span class="big-lotto-ball"><?= e($d) ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ((int) ($t['sem'] ?? 1) > 1): ?>
                        <span class="ticket-sem-tag">⚡ <?= (int) $t['sem'] ?>x Multiplier</span>
                    <?php endif; ?>
                </div>

                <!-- Perforation Line -->
                <div class="ticket-perforation">
                    <div class="perf-circle left"></div>
                    <div class="perf-line"></div>
                    <div class="perf-circle right"></div>
                </div>

                <!-- Bottom Status -->
                <div class="ticket-status-strip">
                    <?php if (!$settled): ?>
                        <div class="status-live-box">
                            <span class="pulse-dot"></span>
                            <strong>⏳ Drawing Soon...</strong>
                        </div>
                    <?php elseif ($won): ?>
                        <div class="status-winner-box">
                            <span class="winner-emoji">🎉</span>
                            <div class="winner-text">
                                <strong class="winner-title">YOU WON!</strong>
                                <span class="winner-prize">+<?= inr((int) $t['amount']) ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="status-miss-box">
                            <span class="miss-label">Result: <strong><?= e($t['result']) ?></strong></span>
                            <span class="miss-tag">No match this time</span>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
