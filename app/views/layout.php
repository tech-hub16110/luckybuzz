<?php /** @var array<string, mixed> $_nav derived below */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#0d0926">
    <meta name="description" content="Lucky Buzz — Free fun numbers lottery game with virtual coins!">
    <meta name="robots" content="index, follow">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800;900&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='26' font-size='26'>🍀</text></svg>">
</head>
<body class="app-body">
<div class="ambient-glows" aria-hidden="true">
    <div class="ambient-orb ambient-orb-1"></div>
    <div class="ambient-orb ambient-orb-2"></div>
    <div class="ambient-orb ambient-orb-3"></div>
</div>

<?php if ($is_admin): ?>
    <!-- Dedicated Admin Layout (No Player Dashboard / No Player Tabs) -->
    <div class="admin-shell">
        <main class="admin-main-wrap">
            <?php $flash = flash(); ?>
            <?php if ($flash !== null): ?>
                <div class="flash flash-<?= e($flash['tone']) ?> bounce-in mb-4" role="alert">
                    <span class="flash-icon-box"><?= icon($flash['tone'] === 'ok' ? 'sparkles' : ($flash['tone'] === 'bad' ? 'alert' : 'info'), 'flash-svg', 22) ?></span>
                    <div class="flash-msg-text">
                        <strong><?= $flash['tone'] === 'ok' ? 'Success' : ($flash['tone'] === 'bad' ? 'Notice' : 'Information') ?></strong>
                        <span><?= e($flash['message']) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
<?php else: ?>
    <!-- Player Top Navigation Bar -->
    <header class="topbar-container">
        <div class="topbar">
            <a class="brand" href="/" aria-label="Lucky Buzz Home">
                <span class="brand-clover-badge">🍀</span>
                <div class="brand-title-wrap">
                    <span class="brand-main">LUCKY<span class="brand-highlight">BUZZ</span></span>
                    <span class="brand-sub-badge">100% FREE</span>
                </div>
            </a>
            
            <div class="topbar-actions">
                <?php if ($user !== null): ?>
                    <a class="wallet-badge" href="/account" title="My Balance">
                        <span class="wallet-coin-icon">🪙</span>
                        <div class="wallet-text">
                            <span class="wallet-label">Balance</span>
                            <span class="wallet-amount"><span class="currency">₹</span><?= number_format((int) $balance) ?></span>
                        </div>
                    </a>
                <?php else: ?>
                    <a class="login-top-btn" href="/login">
                        <?= icon('user', 'btn-icon', 16) ?> <span>Sign In</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="free-banner-strip">
        <div class="free-banner-content">
            <span class="free-badge">🎯 LIVE LOTTERY DRAWS</span>
            <span class="free-text">3 Scheduled Draws Daily: 1 PM, 6 PM & 8 PM</span>
        </div>
    </div>

    <main class="wrap">
        <?php $flash = flash(); ?>
        <?php if ($flash !== null): ?>
            <div class="flash flash-<?= e($flash['tone']) ?> bounce-in" role="alert">
                <span class="flash-icon-box"><?= icon($flash['tone'] === 'ok' ? 'sparkles' : ($flash['tone'] === 'bad' ? 'alert' : 'info'), 'flash-svg', 22) ?></span>
                <div class="flash-msg-text">
                    <strong><?= $flash['tone'] === 'ok' ? 'Success!' : ($flash['tone'] === 'bad' ? 'Notice' : 'Information') ?></strong>
                    <span><?= e($flash['message']) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <!-- Easy Accessible Bottom Navigation Bar for Players -->
    <nav class="tabbar-container">
        <div class="tabbar">
            <a href="/" class="tab <?= e($_SERVER['REQUEST_URI'] === '/' ? 'on' : '') ?>">
                <span class="tab-icon-wrap"><?= icon('home', 'tab-svg', 22) ?></span>
                <span class="tab-lbl">Home</span>
            </a>
            <a href="/play" class="tab tab-play <?= e(str_starts_with($_SERVER['REQUEST_URI'], '/play') ? 'on' : '') ?>">
                <span class="tab-icon-wrap play-glow"><?= icon('target', 'tab-svg', 24) ?></span>
                <span class="tab-lbl">Play</span>
            </a>
            <a href="/tickets" class="tab <?= e(str_starts_with($_SERVER['REQUEST_URI'], '/tickets') ? 'on' : '') ?>">
                <span class="tab-icon-wrap"><?= icon('ticket', 'tab-svg', 22) ?></span>
                <span class="tab-lbl">My Tickets</span>
            </a>
            <a href="/results" class="tab <?= e(str_starts_with($_SERVER['REQUEST_URI'], '/results') ? 'on' : '') ?>">
                <span class="tab-icon-wrap"><?= icon('trophy', 'tab-svg', 22) ?></span>
                <span class="tab-lbl">Results</span>
            </a>
            <a href="/account" class="tab <?= e(str_starts_with($_SERVER['REQUEST_URI'], '/account') || str_starts_with($_SERVER['REQUEST_URI'], '/login') ? 'on' : '') ?>">
                <span class="tab-icon-wrap"><?= icon('wallet', 'tab-svg', 22) ?></span>
                <span class="tab-lbl">Wallet</span>
            </a>
        </div>
    </nav>
<?php endif; ?>

<script src="/assets/app.js" defer></script>
</body>
</html>
