<?php
/** @var array<string, int> $summary */
/** @var list<array<string, mixed>> $ledger */
/** @var int $earned */
$reasonLabel = [
    'signup_grant' => '🎁 Welcome Gift',
    'ticket' => '🎟️ Ticket Entry',
    'win' => '🏆 Prize Won',
    'jackpot' => '⭐ Top Jackpot Won',
    'surprise_box' => '🎁 Gift Box',
    'refund' => '🪙 Admin Grant / Adjustment',
];
?>

<!-- Big Wallet Card -->
<section class="section-container">
    <div class="wallet-hero-card">
        <div class="wallet-hero-top">
            <div class="user-greeting">
                <span class="user-avatar-badge">👤</span>
                <div>
                    <span class="greeting-sub">Player Profile</span>
                    <h2 class="user-name"><?= e($user['username']) ?></h2>
                </div>
            </div>
            
            <form method="post" action="/action/logout">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="logout-btn" title="Sign out">
                    <?= icon('logout', 'btn-icon', 16) ?> <span>Logout</span>
                </button>
            </form>
        </div>

        <div class="wallet-balance-box">
            <span class="balance-tag">AVAILABLE WALLET BALANCE</span>
            <div class="big-balance-val">
                <span class="curr">₹</span>
                <span class="val"><?= number_format((int) $balance) ?></span>
                <span class="coins-unit-tag">Coins</span>
            </div>
            <p class="balance-sub">Coins are allocated directly by the Game Owner / Admin & won from lucky draws.</p>
        </div>

        <div class="wallet-stats-strip">
            <div class="stat-mini">
                <span class="mini-icon">🎟️</span>
                <span class="mini-val"><?= coins($summary['tickets']) ?></span>
                <span class="mini-lbl">Tickets Entered</span>
            </div>
            <div class="stat-mini">
                <span class="mini-icon">🏆</span>
                <span class="mini-val">₹<?= number_format($summary['won']) ?></span>
                <span class="mini-lbl">Total Won</span>
            </div>
            <div class="stat-mini">
                <span class="mini-icon">⭐</span>
                <span class="mini-val">₹<?= number_format($summary['best']) ?></span>
                <span class="mini-lbl">Best Prize</span>
            </div>
        </div>
    </div>
</section>

<!-- Transaction History -->
<section class="section-container">
    <div class="section-header">
        <h2 class="section-title">📋 Coin History</h2>
        <span class="section-subtitle">Recent wallet activity</span>
    </div>

    <?php if ($ledger === []): ?>
        <div class="empty-card">
            <p>Coins are managed by the admin.</p>
        </div>
    <?php else: ?>
        <div class="ledger-cards-list">
            <?php foreach ($ledger as $row): ?>
                <?php $delta = (int) $row['delta']; ?>
                <div class="ledger-item-card">
                    <div class="ledger-item-left">
                        <span class="ledger-action-name"><?= e($reasonLabel[$row['reason']] ?? $row['reason']) ?></span>
                        <span class="ledger-item-date"><?= e((new DateTimeImmutable($row['created_at']))->format('d M, h:i A')) ?></span>
                    </div>
                    <div class="ledger-item-amount <?= $delta > 0 ? 'amt-plus' : 'amt-minus' ?>">
                        <?= $delta > 0 ? '+' : '−' ?>₹<?= number_format(abs($delta)) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
