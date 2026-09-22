<?php
/** @var string $tab */
/** @var array<string, mixed> $stats */
/** @var list<array<string, mixed>> $draws */
/** @var list<array<string, mixed>> $users */
/** @var list<array<string, mixed>> $tickets */
/** @var list<array<string, mixed>> $wins */
/** @var list<array<string, mixed>> $ledger */
/** @var array<string, mixed> $economics */
/** @var string $search */
/** @var int|null $filterDraw */
/** @var int|null $filterUser */
/** @var list<array<string, mixed>> $lotteryRecent */
/** @var list<array<string, mixed>> $lotteryLogs */

$tab = $tab ?? 'overview';
$economics = $economics ?? Admin::getEconomics();
$ledger = $ledger ?? [];
$lotteryRecent = $lotteryRecent ?? [];
$lotteryLogs = $lotteryLogs ?? [];
$openDrawsList = array_values(array_filter($draws, static fn($d) => $d['result'] === null));

$reasonLabels = [
    'signup_grant' => 'Welcome Grant',
    'daily_bonus' => 'Daily Bonus',
    'streak_bonus' => 'Streak Bonus',
    'refill' => 'Refill',
    'ticket' => 'Ticket Purchase',
    'win' => 'Prize Win',
    'jackpot' => 'Straight Jackpot',
    'surprise_box' => 'Surprise Box',
    'refund' => 'Admin Grant / Adjustment',
];
?>

<div class="admin-layout-container">
    <!-- Admin Left Sidebar Navigation -->
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="admin-sidebar-brand">
            <div class="brand-logo-icon">👑</div>
            <div class="brand-info">
                <span class="brand-badge-owner">GAME OWNER</span>
                <h1 class="brand-title">LUCKY<span>BUZZ</span></h1>
            </div>
        </div>

        <nav class="admin-sidebar-menu">
            <div class="menu-heading">MANAGEMENT</div>
            <a href="/admin?tab=overview" class="admin-menu-link <?= $tab === 'overview' ? 'active' : '' ?>">
                <span class="menu-icon"><?= icon('home', 'sidebar-svg', 18) ?></span>
                <span class="menu-label">Dashboard</span>
            </a>
            <a href="/admin?tab=draws" class="admin-menu-link <?= $tab === 'draws' ? 'active' : '' ?>">
                <span class="menu-icon"><?= icon('dice', 'sidebar-svg', 18) ?></span>
                <span class="menu-label">Draws & Results</span>
                <?php if (count($openDrawsList) > 0): ?>
                    <span class="menu-pill-count"><?= count($openDrawsList) ?></span>
                <?php endif; ?>
            </a>
            <a href="/admin?tab=users" class="admin-menu-link <?= $tab === 'users' ? 'active' : '' ?>">
                <span class="menu-icon"><?= icon('users', 'sidebar-svg', 18) ?></span>
                <span class="menu-label">Players & Coins</span>
            </a>
            <a href="/admin?tab=lottery" class="admin-menu-link <?= $tab === 'lottery' ? 'active' : '' ?>">
                <span class="menu-icon"><?= icon('target', 'sidebar-svg', 18) ?></span>
                <span class="menu-label">Lottery Sambad</span>
            </a>
            <a href="/admin?tab=tickets" class="admin-menu-link <?= $tab === 'tickets' ? 'active' : '' ?>">
                <span class="menu-icon"><?= icon('ticket', 'sidebar-svg', 18) ?></span>
                <span class="menu-label">Tickets Audit</span>
            </a>
            <a href="/admin?tab=ledger" class="admin-menu-link <?= $tab === 'ledger' ? 'active' : '' ?>">
                <span class="menu-icon"><?= icon('wallet', 'sidebar-svg', 18) ?></span>
                <span class="menu-label">Coins Ledger</span>
            </a>

            <div class="menu-heading">CONFIGURATION</div>
            <a href="/admin?tab=settings" class="admin-menu-link <?= $tab === 'settings' ? 'active' : '' ?>">
                <span class="menu-icon"><?= icon('settings', 'sidebar-svg', 18) ?></span>
                <span class="menu-label">Ticket Fares & Odds</span>
            </a>
            <a href="/fair" target="_blank" class="admin-menu-link">
                <span class="menu-icon"><?= icon('shield', 'sidebar-svg', 18) ?></span>
                <span class="menu-label">Provably Fair Tool</span>
            </a>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="admin-sidebar-user">
                <div class="user-avatar-sm">👑</div>
                <div class="user-info-text">
                    <strong><?= e(Config::get('admin')['username'] ?? 'Admin Owner') ?></strong>
                    <span class="text-green"><span class="badge-dot dot-green"></span> Online</span>
                </div>
            </div>
            <form method="post" action="/admin/action/logout" class="sidebar-logout-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="sidebar-logout-btn" title="Sign out of owner console">
                    <?= icon('logout', 'mini-svg', 16) ?>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Admin Main Body Area -->
    <div class="admin-body-area">
        <!-- Top Navigation Bar for Mobile & Quick Actions -->
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button type="button" class="admin-mobile-menu-toggle" id="admin-menu-toggle" aria-label="Toggle Admin Navigation">
                    <span class="burger-bar"></span>
                    <span class="burger-bar"></span>
                    <span class="burger-bar"></span>
                </button>
                <div class="admin-topbar-title">
                    <h2>
                        <?php if ($tab === 'overview'): ?>Dashboard Overview
                        <?php elseif ($tab === 'draws'): ?>Draws & Winning Results
                        <?php elseif ($tab === 'lottery'): ?>Lottery Sambad Results & Automated OCR
                        <?php elseif ($tab === 'users'): ?>Players Management & Coins
                        <?php elseif ($tab === 'settings'): ?>Ticket Fares & Game Economics
                        <?php elseif ($tab === 'tickets'): ?>Tickets Audit & Settlement
                        <?php elseif ($tab === 'ledger'): ?>Complete Coins Movement Ledger
                        <?php endif; ?>
                    </h2>
                </div>
            </div>

            <div class="admin-topbar-actions">
                <div class="admin-time-badge">
                    <?= icon('clock', 'admin-time-icon', 14) ?>
                    <span><?= e(Clock::now()->format('d M, h:i:s A')) ?></span>
                </div>
                <form method="post" action="/admin/action/tick" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="mini admin-btn-accent" title="Advance engine and schedule pending draws">
                        <?= icon('refresh', 'mini-svg', 13) ?> <span>Sync Engine</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Admin Content Wrapper -->
        <div class="admin-content-inner">

    <!-- ================= TAB: OVERVIEW ================= -->
    <?php if ($tab === 'overview'): ?>
        <section class="admin-section">
            <div class="admin-stats-grid">
                <div class="admin-stat-card">
                    <div class="stat-card-icon stat-icon-users"><?= icon('users', 'stat-svg', 24) ?></div>
                    <div class="stat-card-data">
                        <span class="stat-card-num"><?= number_format($stats['users']) ?></span>
                        <span class="stat-card-lbl">Registered Players</span>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-card-icon stat-icon-wallet"><?= icon('wallet', 'stat-svg', 24) ?></div>
                    <div class="stat-card-data">
                        <span class="stat-card-num"><?= inr($stats['circulation']) ?></span>
                        <span class="stat-card-lbl">Coins in Circulation</span>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-card-icon stat-icon-tickets"><?= icon('ticket', 'stat-svg', 24) ?></div>
                    <div class="stat-card-data">
                        <span class="stat-card-num"><?= number_format($stats['tickets']) ?></span>
                        <span class="stat-card-lbl">Tickets Sold (<?= inr($stats['total_spent']) ?>)</span>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-card-icon stat-icon-trophy"><?= icon('trophy', 'stat-svg', 24) ?></div>
                    <div class="stat-card-data">
                        <span class="stat-card-num"><?= inr($stats['total_won']) ?></span>
                        <span class="stat-card-lbl">Total Winnings Paid Out</span>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-card-icon stat-icon-dice"><?= icon('dice', 'stat-svg', 24) ?></div>
                    <div class="stat-card-data">
                        <span class="stat-card-num"><?= number_format($stats['open_draws']) ?> Open / <?= number_format($stats['settled_draws']) ?> Settled</span>
                        <span class="stat-card-lbl">Draws Managed</span>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-card-icon stat-icon-clock"><?= icon('zap', 'stat-svg', 24) ?></div>
                    <div class="stat-card-data">
                        <span class="stat-card-num"><?= $stats['last_tick'] > 0 ? e(date('h:i:s A', $stats['last_tick'])) : 'Just now' ?></span>
                        <span class="stat-card-lbl">Last Engine Sync</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Owner Action Bar -->
        <section class="panel admin-panel mb-4">
            <h3 class="panel-title"><?= icon('zap', 'panel-icon-svg', 16) ?> Quick Management Actions</h3>
            <div class="admin-quick-actions-bar">
                <a href="/admin?tab=users#create-player" class="quick-act-btn">
                    <span class="quick-act-icon">👤</span>
                    <div class="quick-act-text">
                        <strong>Create Player Account</strong>
                        <span>Register a new player & set balance</span>
                    </div>
                </a>
                <a href="/admin?tab=draws#declare-result" class="quick-act-btn">
                    <span class="quick-act-icon">🎯</span>
                    <div class="quick-act-text">
                        <strong>Declare / Upload Result</strong>
                        <span>Enter winning numbers for a draw</span>
                    </div>
                </a>
                <a href="/admin?tab=users#give-coins" class="quick-act-btn">
                    <span class="quick-act-icon">🪙</span>
                    <div class="quick-act-text">
                        <strong>Give Coins to Players</strong>
                        <span>Allocate or adjust player balance</span>
                    </div>
                </a>
                <a href="/admin?tab=settings" class="quick-act-btn">
                    <span class="quick-act-icon">⚙️</span>
                    <div class="quick-act-text">
                        <strong>Set Ticket Fares & Prizes</strong>
                        <span>Configure ticket prices and payouts</span>
                    </div>
                </a>
                <a href="/admin?tab=draws#schedule-draw" class="quick-act-btn">
                    <span class="quick-act-icon">📅</span>
                    <div class="quick-act-text">
                        <strong>Schedule New Draw</strong>
                        <span>Add custom draw time or tier</span>
                    </div>
                </a>
            </div>
        </section>

        <div class="admin-grid-2col">
            <!-- Active Open Draws -->
            <section class="panel admin-panel">
                <div class="panel-header-row">
                    <h3 class="panel-title"><?= icon('dice', 'panel-icon-svg', 16) ?> Active & Upcoming Draws</h3>
                    <a href="/admin?tab=draws" class="mini">Manage Draws ➔</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Draw</th>
                                <th>Schedule</th>
                                <th>Jackpot</th>
                                <th>Tickets</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($openDrawsList === []): ?>
                                <tr><td colspan="5" class="muted center">No open draws. Click "Advance Engine" to generate draws.</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($openDrawsList, 0, 5) as $d): ?>
                                    <?php $tier = Config::tier($d['tier']); ?>
                                    <tr>
                                        <td>
                                            <span class="pill pill-<?= e($tier['accent']) ?>">#<?= (int) $d['id'] ?> <?= e($tier['label']) ?></span>
                                        </td>
                                        <td><?= e($d['day']) ?><br><span class="muted"><?= e((new DateTimeImmutable($d['draw_at']))->format('h:i A')) ?></span></td>
                                        <td><strong class="text-amber"><?= inr($d['jackpot']) ?></strong></td>
                                        <td>
                                            <a href="/admin?tab=tickets&draw_id=<?= (int) $d['id'] ?>" class="link-subtle">
                                                <?= number_format((int) $d['ticket_count']) ?> tickets
                                            </a>
                                        </td>
                                        <td>
                                            <form method="post" action="/admin/action/upload_result" class="inline-action-form" onsubmit="return confirm('Settle Draw #<?= (int) $d['id'] ?> now?');">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="draw_id" value="<?= (int) $d['id'] ?>">
                                                <button type="submit" class="mini-btn admin-btn-action">Settle Draw</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Latest Registered Players -->
            <section class="panel admin-panel">
                <div class="panel-header-row">
                    <h3 class="panel-title"><?= icon('users', 'panel-icon-svg', 16) ?> Players & Balances</h3>
                    <a href="/admin?tab=users" class="mini">Manage All Players ➔</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Wallet Balance</th>
                                <th>Tickets</th>
                                <th>Quick Give</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users === []): ?>
                                <tr><td colspan="4" class="muted center">No registered players yet.</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($users, 0, 5) as $u): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($u['username']) ?></strong><br>
                                            <span class="muted small">ID: #<?= (int) $u['id'] ?></span>
                                        </td>
                                        <td><strong class="text-green"><?= inr((int) $u['balance']) ?></strong></td>
                                        <td><?= number_format((int) $u['tickets_count']) ?></td>
                                        <td>
                                            <form method="post" action="/admin/action/adjust_user" class="inline-action-form">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <input type="hidden" name="delta" value="500">
                                                <button type="submit" class="mini-btn admin-btn-action" title="Give +500 Coins">+500</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <!-- ================= TAB: DRAWS & RESULTS ================= -->
    <?php if ($tab === 'draws'): ?>
        <div class="admin-grid-2col mb-4">
            <!-- Declare / Upload Winning Result Form -->
            <section class="panel admin-panel" id="declare-result">
                <h3 class="panel-title"><?= icon('target', 'panel-icon-svg', 18) ?> Declare / Upload Winning Result</h3>
                <p class="muted">Enter or upload the winning number for any open draw. All tickets will be graded instantly and prize winnings will be credited to players' wallets.</p>

                <?php if ($openDrawsList === []): ?>
                    <div class="empty-card">
                        <p>No open draws currently waiting for results. Use the schedule form below or click Advance Engine.</p>
                    </div>
                <?php else: ?>
                    <form method="post" action="/admin/action/upload_result" class="admin-form-box">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                        <div class="form-group">
                            <label for="upload-draw-id">Select Draw to Settle:</label>
                            <select name="draw_id" id="upload-draw-id" class="admin-select" required>
                                <?php foreach ($openDrawsList as $d): ?>
                                    <?php $tier = Config::tier($d['tier']); ?>
                                    <option value="<?= (int) $d['id'] ?>">
                                        Draw #<?= (int) $d['id'] ?> - <?= e($tier['label']) ?> (<?= e($d['day']) ?> at <?= e((new DateTimeImmutable($d['draw_at']))->format('h:i A')) ?>) [<?= (int) $d['ticket_count'] ?> tickets]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="upload-winning-num">Winning Number (4 or 5 Digits):</label>
                            <div class="input-with-hint">
                                <input type="text" name="winning_number" id="upload-winning-num" class="admin-input font-mono" placeholder="Leave empty for provably-fair auto roll, or enter custom (e.g. 58291)" pattern="[0-9]{4,5}" maxlength="5">
                                <span class="input-hint">Tip: If left blank, the cryptographic SHA-256 pre-committed number will be drawn.</span>
                            </div>
                        </div>

                        <button type="submit" class="big-action-btn btn-amber mt-2" onclick="return confirm('Are you sure you want to declare this winning result and settle this draw?');">
                            <span>🏆 Declare Result & Pay Winners</span>
                        </button>
                    </form>
                <?php endif; ?>
            </section>

            <!-- Schedule Custom Draw -->
            <section class="panel admin-panel" id="schedule-draw">
                <h3 class="panel-title"><?= icon('plus-circle', 'panel-icon-svg', 18) ?> Schedule Custom Draw</h3>
                <p class="muted">Add an additional scheduled draw date or custom tier with a custom jackpot starting seed.</p>

                <form method="post" action="/admin/action/create_draw" class="admin-form-box">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <div class="admin-grid-2col-compact">
                        <div class="form-group">
                            <label for="create-day">Draw Day Date:</label>
                            <input type="date" name="day" id="create-day" class="admin-input" value="<?= e(Clock::now()->format('Y-m-d')) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="create-tier">Tier:</label>
                            <select name="tier" id="create-tier" class="admin-select" required>
                                <?php foreach (Config::tierNames() as $tierName): ?>
                                    <?php $t = Config::tier($tierName); ?>
                                    <option value="<?= e($tierName) ?>"><?= e($t['label']) ?> (<?= e($t['time']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="admin-grid-2col-compact">
                        <div class="form-group">
                            <label for="create-time">Custom Draw Time (Optional):</label>
                            <input type="time" name="draw_time" id="create-time" class="admin-input" placeholder="e.g. 13:00">
                        </div>
                        <div class="form-group">
                            <label for="create-seed">Starting Jackpot Seed (Coins):</label>
                            <input type="number" name="seed_jackpot" id="create-seed" class="admin-input" value="<?= Config::int('jackpot_seed') ?>" min="0" step="1000">
                        </div>
                    </div>

                    <button type="submit" class="big-action-btn btn-green mt-2">
                        <span>📅 Schedule Draw</span>
                    </button>
                </form>
            </section>
        </div>

        <!-- All Draws Ledger Table -->
        <section class="panel admin-panel">
            <div class="panel-header-row">
                <h3 class="panel-title"><?= icon('dice', 'panel-icon-svg', 18) ?> Complete Draws Ledger</h3>
                <div class="panel-actions">
                    <form method="post" action="/admin/action/tick" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <button type="submit" class="mini admin-btn-accent">
                            <?= icon('refresh', 'mini-svg', 13) ?> Advance Engine / Restock
                        </button>
                    </form>
                </div>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tier & Day</th>
                            <th>Draw Time</th>
                            <th>Status & Winning Result</th>
                            <th>Jackpot Pot</th>
                            <th>Tickets / Paid</th>
                            <th>Commitment Hash</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($draws === []): ?>
                            <tr><td colspan="8" class="muted center">No draws found in database.</td></tr>
                        <?php else: ?>
                            <?php foreach ($draws as $d): ?>
                                <?php 
                                $tier = Config::tier($d['tier']);
                                $isSettled = $d['result'] !== null;
                                ?>
                                <tr class="<?= $isSettled ? 'row-settled' : 'row-open' ?>">
                                    <td><strong>#<?= (int) $d['id'] ?></strong></td>
                                    <td>
                                        <span class="pill pill-<?= e($tier['accent']) ?>"><?= e($tier['label']) ?></span><br>
                                        <span class="muted"><?= e($d['day']) ?></span>
                                    </td>
                                    <td><?= e((new DateTimeImmutable($d['draw_at']))->format('d M, h:i A')) ?></td>
                                    <td>
                                        <?php if ($isSettled): ?>
                                            <div class="result-badge"><span class="badge-dot dot-green"></span> <strong><?= e($d['result']) ?></strong></div>
                                            <span class="muted small"><?= e(date('d M h:i A', strtotime((string)$d['settled_at']))) ?></span>
                                        <?php else: ?>
                                            <span class="badge-pending"><span class="badge-dot dot-amber"></span> Open</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-amber"><?= inr($d['jackpot']) ?></strong><br>
                                        <span class="muted small">Seed: <?= inr((int)$d['rollover_in']) ?></span>
                                    </td>
                                    <td>
                                        <a href="/admin?tab=tickets&draw_id=<?= (int) $d['id'] ?>" class="link-subtle">
                                            <?= number_format((int) $d['ticket_count']) ?> tickets
                                        </a><br>
                                        <span class="muted small">Paid: <?= inr((int) $d['total_won']) ?></span>
                                    </td>
                                    <td>
                                        <div class="mono-box">
                                            <span class="mono-label">Commit:</span> <code><?= e(substr((string) $d['commit_hash'], 0, 10)) ?>...</code><br>
                                            <span class="mono-label">Nonce:</span> <code><?= $isSettled ? e(substr((string) $d['nonce'], 0, 10)) . '...' : '(Hidden)' ?></code>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!$isSettled): ?>
                                            <form method="post" action="/admin/action/upload_result" onsubmit="return confirm('Declare result & settle Draw #<?= (int) $d['id'] ?> now?');">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="draw_id" value="<?= (int) $d['id'] ?>">
                                                <button type="submit" class="mini-btn admin-btn-action">Settle Now</button>
                                            </form>
                                        <?php else: ?>
                                            <a href="/fair" target="_blank" class="mini-btn is-off">Verify</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- ================= TAB: USERS & COINS ================= -->
    <?php if ($tab === 'users'): ?>
        <!-- Create Player & Individual Adjust Grid -->
        <div class="admin-grid-2col mb-4">
            <!-- Create New Player Account Form -->
            <section class="panel admin-panel" id="create-player">
                <div class="panel-header-row">
                    <h3 class="panel-title"><?= icon('plus-circle', 'panel-icon-svg', 18) ?> Create New Player Account</h3>
                    <span class="badge-pending">Account Registration</span>
                </div>
                <p class="muted">Register a new player account directly with custom credentials and set their starting wallet balance.</p>

                <form method="post" action="/admin/action/create_user" class="admin-form-box">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <div class="admin-grid-2col-compact">
                        <div class="form-group">
                            <label for="new-player-username">Player Username:</label>
                            <input type="text" name="username" id="new-player-username" class="admin-input font-mono" placeholder="e.g. player_rohit" pattern="[a-z0-9_]{3,20}" title="3-20 lowercase alphanumeric characters or underscore" required>
                            <span class="input-hint">3-20 chars (lowercase letters, digits, _)</span>
                        </div>
                        <div class="form-group">
                            <label for="new-player-password">Account Password:</label>
                            <input type="text" name="password" id="new-player-password" class="admin-input font-mono" placeholder="Min. 6 characters" minlength="6" required>
                            <span class="input-hint">Initial login password</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new-player-coins">Initial Coin Balance (Coins):</label>
                        <input type="number" name="initial_coins" id="new-player-coins" class="admin-input font-mono" value="<?= Config::int('signup_grant') ?>" min="0" required>
                    </div>

                    <div class="quick-chips-row">
                        <span class="quick-chip-lbl">Coin Presets:</span>
                        <button type="button" class="mini-btn is-off" onclick="document.getElementById('new-player-coins').value='0'">0 (No Coins)</button>
                        <button type="button" class="mini-btn" onclick="document.getElementById('new-player-coins').value='200'">200 Coins</button>
                        <button type="button" class="mini-btn" onclick="document.getElementById('new-player-coins').value='500'">500 Coins</button>
                        <button type="button" class="mini-btn" onclick="document.getElementById('new-player-coins').value='1000'">1,000 Coins</button>
                        <button type="button" class="mini-btn" onclick="document.getElementById('new-player-coins').value='5000'">5,000 Coins</button>
                    </div>

                    <button type="submit" class="big-action-btn btn-green mt-3">
                        <span>👤 Create Player Account</span>
                    </button>
                </form>
            </section>

            <!-- Individual Give Coins Form -->
            <section class="panel admin-panel" id="give-coins">
                <div class="panel-header-row">
                    <h3 class="panel-title"><?= icon('wallet', 'panel-icon-svg', 18) ?> Give / Deduct Coins</h3>
                    <span class="badge-pending">Balance Adjustment</span>
                </div>
                <p class="muted">As the game owner, you directly allocate coins to players. Positive numbers grant coins; negative numbers debit coins.</p>

                <form method="post" action="/admin/action/adjust_user" class="admin-form-box">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <div class="form-group">
                        <label for="adjust-user-id">Select Player:</label>
                        <select name="user_id" id="adjust-user-id" class="admin-select" required>
                            <option value="">-- Choose a player --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id'] ?>">
                                    #<?= (int) $u['id'] ?> - <?= e($u['username']) ?> (Current: <?= inr((int) $u['balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="adjust-delta">Coin Amount (+ to give, - to deduct):</label>
                        <input type="number" name="delta" id="adjust-delta" class="admin-input font-mono" placeholder="e.g. 500 or -100" required>
                    </div>

                    <!-- Quick Amount Chips -->
                    <div class="quick-chips-row">
                        <span class="quick-chip-lbl">Quick Presets:</span>
                        <button type="button" class="mini-btn" onclick="document.getElementById('adjust-delta').value='100'">+100</button>
                        <button type="button" class="mini-btn" onclick="document.getElementById('adjust-delta').value='500'">+500</button>
                        <button type="button" class="mini-btn" onclick="document.getElementById('adjust-delta').value='1000'">+1,000</button>
                        <button type="button" class="mini-btn" onclick="document.getElementById('adjust-delta').value='5000'">+5,000</button>
                        <button type="button" class="mini-btn is-off" onclick="document.getElementById('adjust-delta').value='-100'">-100</button>
                    </div>

                    <button type="submit" class="big-action-btn btn-green mt-3">
                        <span>🪙 Execute Coin Adjustment</span>
                    </button>
                </form>
            </section>
        </div>

        <!-- Bulk Grant Coins Panel -->
        <section class="panel admin-panel mb-4">
            <h3 class="panel-title"><?= icon('gift', 'panel-icon-svg', 18) ?> Bulk Grant Coins to ALL Players</h3>
            <p class="muted">Distribute coins to every registered player simultaneously in a single operation.</p>

            <form method="post" action="/admin/action/bulk_coins" class="admin-form-box" onsubmit="return confirm('Distribute these coins to ALL registered players?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="form-group">
                    <label for="bulk-amount">Coins Amount per Player:</label>
                    <input type="number" name="amount" id="bulk-amount" class="admin-input font-mono" placeholder="e.g. 200" min="1" required>
                    <span class="input-hint">Will be credited to all <?= count($users) ?> registered players.</span>
                </div>

                <div class="quick-chips-row">
                    <span class="quick-chip-lbl">Quick Presets:</span>
                    <button type="button" class="mini-btn" onclick="document.getElementById('bulk-amount').value='100'">100 Coins</button>
                    <button type="button" class="mini-btn" onclick="document.getElementById('bulk-amount').value='250'">250 Coins</button>
                    <button type="button" class="mini-btn" onclick="document.getElementById('bulk-amount').value='500'">500 Coins</button>
                    <button type="button" class="mini-btn" onclick="document.getElementById('bulk-amount').value='1000'">1,000 Coins</button>
                </div>

                <button type="submit" class="big-action-btn btn-amber mt-3">
                    <span>📢 Grant Coins to All <?= count($users) ?> Players</span>
                </button>
            </form>
        </section>

        <!-- Players List Table -->
        <section class="panel admin-panel">
            <div class="panel-header-row">
                <h3 class="panel-title"><?= icon('users', 'panel-icon-svg', 18) ?> Player Accounts & Balances</h3>
                <form method="get" action="/admin" class="admin-search-form">
                    <input type="hidden" name="tab" value="users">
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search username or ID..." class="admin-input-search">
                    <button type="submit" class="mini admin-btn-accent">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="/admin?tab=users" class="mini is-off">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Registered</th>
                            <th>Wallet Balance</th>
                            <th>Tickets / Won</th>
                            <th>Give Coins</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users === []): ?>
                            <tr><td colspan="7" class="muted center">No players matched your query.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><strong>#<?= (int) $u['id'] ?></strong></td>
                                    <td>
                                        <strong><?= e($u['username']) ?></strong>
                                    </td>
                                    <td><?= e(substr((string) $u['created_at'], 0, 10)) ?></td>
                                    <td><strong class="text-green"><?= inr((int) $u['balance']) ?></strong></td>
                                    <td>
                                        <a href="/admin?tab=tickets&user_id=<?= (int) $u['id'] ?>" class="link-subtle">
                                            <?= number_format((int) $u['tickets_count']) ?> tickets
                                        </a><br>
                                        <span class="muted small">Won: <?= inr((int) $u['total_won']) ?></span>
                                    </td>
                                    <td>
                                        <form method="post" action="/admin/action/adjust_user" class="inline-action-form">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <input type="number" name="delta" placeholder="+/-" class="admin-mini-input" required>
                                            <button type="submit" class="mini-btn admin-btn-action" title="Adjust user balance">Save</button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="/admin?tab=ledger&user_id=<?= (int) $u['id'] ?>" class="mini-btn is-off" title="View coin transactions">Ledger</a>
                                            <form method="post" action="/admin/action/delete_user" onsubmit="return confirm('Permanently delete player <?= e($u['username']) ?>?');" style="display:inline;">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                <button type="submit" class="mini-btn btn-danger" title="Delete account">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- ================= TAB: SETTINGS & TICKET FARES ================= -->
    <?php if ($tab === 'settings'): ?>
        <section class="panel admin-panel mb-4">
            <div class="panel-header-row">
                <h3 class="panel-title"><?= icon('settings', 'panel-icon-svg', 18) ?> Configure Ticket Fares & Game Rules</h3>
                <span class="badge-pending">Owner Configuration</span>
            </div>
            <p class="muted">Set ticket prices, player purchase limits, jackpot seed amounts, and prize tables. Changes take effect immediately across all game draws.</p>

            <form method="post" action="/admin/action/save_economics" class="admin-form-box">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <div class="admin-grid-2col">
                    <!-- Ticket Pricing & Limits -->
                    <div class="sub-panel">
                        <h4 class="sub-panel-title">🎟️ Ticket Pricing & Purchase Limits</h4>

                        <div class="form-group">
                            <label for="fare-ticket-cost">Base Ticket Fare (Cost in Coins):</label>
                            <input type="number" name="ticket_cost" id="fare-ticket-cost" class="admin-input" value="<?= (int) $economics['ticket_cost'] ?>" min="1" required>
                            <span class="input-hint">Base price for 1 ticket (multiplied by SEM multiplier).</span>
                        </div>

                        <div class="form-group">
                            <label for="fare-tickets-per-draw">Max Tickets per Draw (Player Cap):</label>
                            <input type="number" name="tickets_per_draw" id="fare-tickets-per-draw" class="admin-input" value="<?= (int) $economics['tickets_per_draw'] ?>" min="1" required>
                            <span class="input-hint">Maximum number of tickets one player can purchase per draw.</span>
                        </div>

                        <div class="form-group">
                            <label for="fare-signup-grant">New Player Welcome Grant (Coins):</label>
                            <input type="number" name="signup_grant" id="fare-signup-grant" class="admin-input" value="<?= (int) $economics['signup_grant'] ?>" min="0" required>
                            <span class="input-hint">Set to 0 if coins should only be allocated by the owner manually.</span>
                        </div>
                    </div>

                    <!-- Jackpot Economics -->
                    <div class="sub-panel">
                        <h4 class="sub-panel-title">⭐ Jackpot Pot Economics</h4>

                        <div class="form-group">
                            <label for="fare-jackpot-seed">Opening Jackpot Seed (Coins):</label>
                            <input type="number" name="jackpot_seed" id="fare-jackpot-seed" class="admin-input" value="<?= (int) $economics['jackpot_seed'] ?>" min="0" step="1000" required>
                            <span class="input-hint">Starting jackpot pot for new draws when jackpot resets.</span>
                        </div>

                        <div class="form-group">
                            <label for="fare-jackpot-take">Jackpot Pot Contribution per Ticket Sold:</label>
                            <input type="number" name="jackpot_take" id="fare-jackpot-take" class="admin-input" value="<?= (int) $economics['jackpot_take'] ?>" min="0" required>
                            <span class="input-hint">Amount of coins added to the live pot for every ticket sold.</span>
                        </div>
                    </div>
                </div>

                <!-- Prize Payout Table (Ticket Fairs) -->
                <div class="sub-panel mt-4">
                    <h4 class="sub-panel-title">🏆 Prize Payout Table (Ticket Fairs & Odds)</h4>
                    <p class="muted">Fixed coin rewards awarded when players match digits in the draw result.</p>

                    <div class="admin-grid-3col">
                        <div class="form-group">
                            <label for="prize-box">Box (Any 4 Digits Order):</label>
                            <input type="number" name="prize_box" id="prize-box" class="admin-input font-mono" value="<?= (int) $economics['prizes']['box'] ?>" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="prize-back3">Back 3 Digits Exact:</label>
                            <input type="number" name="prize_back3" id="prize-back3" class="admin-input font-mono" value="<?= (int) $economics['prizes']['back3'] ?>" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="prize-back2">Back 2 Digits Exact:</label>
                            <input type="number" name="prize_back2" id="prize-back2" class="admin-input font-mono" value="<?= (int) $economics['prizes']['back2'] ?>" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="prize-back1">Back 1 Digit Exact:</label>
                            <input type="number" name="prize_back1" id="prize-back1" class="admin-input font-mono" value="<?= (int) $economics['prizes']['back1'] ?>" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="prize-anydigit">Any Single Digit Consolation:</label>
                            <input type="number" name="prize_anydigit" id="prize-anydigit" class="admin-input font-mono" value="<?= (int) $economics['prizes']['anydigit'] ?>" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Straight (Exact 4/5 Digits):</label>
                            <input type="text" class="admin-input font-mono" value="Full Accumulated Jackpot Pot" disabled>
                        </div>
                    </div>
                </div>

                <div class="form-submit-row mt-4">
                    <button type="submit" class="big-action-btn btn-green">
                        <span>💾 Save Ticket Fares & Game Rules</span>
                    </button>
                </div>
            </form>
        </section>

        <!-- System Credentials & Environment -->
        <section class="panel admin-panel">
            <h3 class="panel-title"><?= icon('database', 'panel-icon-svg', 18) ?> Server Environment & Credentials</h3>
            <div class="admin-config-list">
                <div class="config-row">
                    <span>Database Engine</span>
                    <strong><?= e(strtoupper(Config::driver())) ?></strong>
                </div>
                <div class="config-row">
                    <span>Game Timezone</span>
                    <strong><?= e(Config::get('timezone')) ?></strong>
                </div>
                <div class="config-row">
                    <span>Admin Username</span>
                    <code><?= e(Config::get('admin')['username'] ?? 'admin') ?></code>
                </div>
                <div class="config-row">
                    <span>Admin Password Config</span>
                    <span class="muted">Configured via <code>htdocs/app/config.local.php</code></span>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ================= TAB: TICKETS AUDIT ================= -->
    <?php if ($tab === 'tickets'): ?>
        <section class="panel admin-panel">
            <div class="panel-header-row">
                <h3 class="panel-title"><?= icon('ticket', 'panel-icon-svg', 18) ?> Tickets & Settlement Audits</h3>
                <div class="panel-filter-info">
                    <?php if ($filterDraw !== null): ?>
                        <span class="filter-pill">Filtered by Draw #<?= (int) $filterDraw ?> <a href="/admin?tab=tickets">&times;</a></span>
                    <?php endif; ?>
                    <?php if ($filterUser !== null): ?>
                        <span class="filter-pill">Filtered by User #<?= (int) $filterUser ?> <a href="/admin?tab=tickets">&times;</a></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Player</th>
                            <th>Draw Tier / Day</th>
                            <th>Picked Number</th>
                            <th>Cost</th>
                            <th>Draw Result</th>
                            <th>Match / Grade</th>
                            <th>Prize Won</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tickets === []): ?>
                            <tr><td colspan="9" class="muted center">No tickets found matching filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $t): ?>
                                <?php $tier = Config::tier($t['tier']); ?>
                                <tr>
                                    <td><strong>#<?= (int) $t['id'] ?></strong></td>
                                    <td>
                                        <a href="/admin?tab=tickets&user_id=<?= (int) $t['user_id'] ?>" class="link-subtle">
                                            <?= e($t['username']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="/admin?tab=tickets&draw_id=<?= (int) $t['draw_id'] ?>" class="pill pill-<?= e($tier['accent']) ?>">
                                            #<?= (int) $t['draw_id'] ?> <?= e($tier['label']) ?>
                                        </a><br>
                                        <span class="muted small"><?= e($t['day']) ?></span>
                                    </td>
                                    <td>
                                        <span class="ticket-num-badge"><?= e($t['number']) ?></span>
                                    </td>
                                    <td><?= inr((int) $t['cost']) ?></td>
                                    <td>
                                        <?= $t['result'] !== null ? '<strong>' . e($t['result']) . '</strong>' : '<span class="muted">Pending</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($t['match_kind'] !== null): ?>
                                            <span class="win-pill win-pill-<?= e($t['match_kind'] === 'straight' ? 'gold' : 'green') ?>">
                                                <?= e(strtoupper((string) $t['match_kind'])) ?>
                                            </span>
                                        <?php elseif ($t['result'] !== null): ?>
                                            <span class="muted">No match</span>
                                        <?php else: ?>
                                            <span class="muted">Open</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int) ($t['amount'] ?? 0) > 0): ?>
                                            <strong class="text-green">+<?= inr((int) $t['amount']) ?></strong>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="muted small"><?= e((new DateTimeImmutable($t['created_at']))->format('d M, h:i A')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- ================= TAB: COINS LEDGER ================= -->
    <?php if ($tab === 'ledger'): ?>
        <section class="panel admin-panel">
            <div class="panel-header-row">
                <h3 class="panel-title"><?= icon('wallet', 'panel-icon-svg', 18) ?> Complete Coins Movement Ledger</h3>
                <div class="panel-filter-info">
                    <?php if ($filterUser !== null): ?>
                        <span class="filter-pill">Filtered by User #<?= (int) $filterUser ?> <a href="/admin?tab=ledger">&times;</a></span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="muted">All movements of virtual coins across player accounts with tamper-evident audit timestamps.</p>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Entry ID</th>
                            <th>Player</th>
                            <th>Change (Coins)</th>
                            <th>Reason / Activity</th>
                            <th>Ref Object</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($ledger === []): ?>
                            <tr><td colspan="6" class="muted center">No ledger entries found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ledger as $l): ?>
                                <?php $delta = (int) $l['delta']; ?>
                                <tr>
                                    <td>#<?= (int) $l['id'] ?></td>
                                    <td>
                                        <a href="/admin?tab=ledger&user_id=<?= (int) $l['user_id'] ?>" class="link-subtle">
                                            <strong><?= e($l['username']) ?></strong>
                                        </a>
                                    </td>
                                    <td>
                                        <strong class="<?= $delta > 0 ? 'text-green' : 'text-danger' ?>">
                                            <?= $delta > 0 ? '+' : '' ?><?= inr($delta) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="pill pill-<?= $delta > 0 ? 'green' : 'amber' ?>">
                                            <?= e($reasonLabels[$l['reason']] ?? $l['reason']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="muted small"><?= e($l['ref_table'] ? $l['ref_table'] . ' #' . $l['ref_id'] : '—') ?></span>
                                    </td>
                                    <td class="muted small"><?= e((new DateTimeImmutable($l['created_at']))->format('d M Y, h:i:s A')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- ================= TAB: LOTTERY SAMBAD ================= -->
    <?php if ($tab === 'lottery'): ?>
        <!-- Lottery Status & Quick Fetch Controls -->
        <div class="admin-grid-2col mb-4">
            <!-- Automated Fetch Trigger -->
            <section class="panel admin-panel">
                <div class="panel-header-row">
                    <h3 class="panel-title"><?= icon('zap', 'panel-icon-svg', 18) ?> Fetch Lottery Sambad Result</h3>
                    <span class="badge-pending">Auto 2-Stage Pipeline</span>
                </div>
                <p class="muted">Triggers automated result extraction: Stage 1 (Official Sambad API) with automatic fallback to Stage 2 (Multimodal Vision OCR via <code>nex-n2.5-pro</code>).</p>

                <form method="post" action="/admin/action/lottery_fetch" class="admin-form-box">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <div class="admin-grid-2col-compact">
                        <div class="form-group">
                            <label for="fetch-lottery-date">Draw Date:</label>
                            <input type="date" name="date" id="fetch-lottery-date" class="admin-input" value="<?= e(Clock::now()->format('Y-m-d')) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="fetch-lottery-time">Draw Time Slot:</label>
                            <select name="draw_time" id="fetch-lottery-time" class="admin-select" required>
                                <option value="1 PM">1:00 PM (Morning Draw)</option>
                                <option value="6 PM">6:00 PM (Day Draw)</option>
                                <option value="8 PM">8:00 PM (Evening Draw)</option>
                            </select>
                        </div>
                    </div>

                    <div class="quick-chips-row mb-3">
                        <span class="quick-chip-lbl">API Pipeline:</span>
                        <span class="pill pill-green">Primary: Official API</span>
                        <span class="pill pill-gold">Fallback: Bynara Vision OCR</span>
                    </div>

                    <button type="submit" class="big-action-btn btn-amber">
                        <span>⚡ Fetch / Re-extract Result Now</span>
                    </button>
                </form>
            </section>

            <!-- Manual Override & Correction Form -->
            <section class="panel admin-panel">
                <div class="panel-header-row">
                    <h3 class="panel-title"><?= icon('target', 'panel-icon-svg', 18) ?> Manual Result Entry / Override</h3>
                    <span class="badge-pending">Admin Override</span>
                </div>
                <p class="muted">Directly input or correct verified numbers if source API / image is delayed. Comma, space, or newline separated.</p>

                <form method="post" action="/admin/action/lottery_save" class="admin-form-box">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <div class="admin-grid-2col-compact">
                        <div class="form-group">
                            <label for="save-lottery-date">Draw Date:</label>
                            <input type="date" name="date" id="save-lottery-date" class="admin-input" value="<?= e(Clock::now()->format('Y-m-d')) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="save-lottery-time">Slot:</label>
                            <select name="draw_time" id="save-lottery-time" class="admin-select" required>
                                <option value="1 PM">1:00 PM</option>
                                <option value="6 PM">6:00 PM</option>
                                <option value="8 PM">8:00 PM</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="prize-1">1st Prize Number (with series, e.g. <code>59D 71122</code>):</label>
                        <input type="text" name="prize_1" id="prize-1" class="admin-input font-mono" placeholder="59D 71122" required>
                    </div>

                    <div class="form-group">
                        <label for="prize-2">2nd Prize Numbers (e.g. <code>01234, 56789, ...</code>):</label>
                        <input type="text" name="prize_2" id="prize-2" class="admin-input font-mono" placeholder="10 numbers separated by commas or spaces">
                    </div>

                    <div class="admin-grid-3col">
                        <div class="form-group">
                            <label for="prize-3">3rd Prize (4 Digits):</label>
                            <input type="text" name="prize_3" id="prize-3" class="admin-input font-mono" placeholder="e.g. 1234, 5678...">
                        </div>
                        <div class="form-group">
                            <label for="prize-4">4th Prize (4 Digits):</label>
                            <input type="text" name="prize_4" id="prize-4" class="admin-input font-mono" placeholder="e.g. 4321, 8765...">
                        </div>
                        <div class="form-group">
                            <label for="prize-5">5th Prize (4 Digits):</label>
                            <input type="text" name="prize_5" id="prize-5" class="admin-input font-mono" placeholder="e.g. 0012, 0034...">
                        </div>
                    </div>

                    <button type="submit" class="big-action-btn btn-green mt-2">
                        <span>💾 Save Verified Numbers</span>
                    </button>
                </form>
            </section>
        </div>

        <!-- Recent Lottery Sambad Imported Summaries -->
        <section class="panel admin-panel mb-4">
            <div class="panel-header-row">
                <h3 class="panel-title"><?= icon('trophy', 'panel-icon-svg', 18) ?> Recent Lottery Sambad Daily Results</h3>
                <a href="/results" target="_blank" class="mini">View Public Results Page ➔</a>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time Slot</th>
                            <th>Status & Source</th>
                            <th>1st Prize Number</th>
                            <th>2nd Prize Numbers</th>
                            <th>3rd - 5th Prizes</th>
                            <th>Last Sync</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lotteryRecent === []): ?>
                            <tr><td colspan="8" class="muted center">No Lottery Sambad results imported yet. Use the fetch tool above to import today's results.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lotteryRecent as $res): ?>
                                <?php
                                $prizes = $res['prizes'] ?? [];
                                $firstPrize = $prizes['1'][0] ?? null;
                                $firstDisplay = $firstPrize ? ($firstPrize['display_result'] ?? $firstPrize['number']) : '—';
                                $secondCount = count($prizes['2'] ?? []);
                                $thirdCount = count($prizes['3'] ?? []);
                                $fourthCount = count($prizes['4'] ?? []);
                                $fifthCount = count($prizes['5'] ?? []);
                                ?>
                                <tr>
                                    <td><strong><?= e($res['draw_date']) ?></strong></td>
                                    <td>
                                        <span class="pill pill-gold"><?= e($res['draw_time']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-dot dot-green"></span>
                                        <strong><?= e(ucfirst($res['status'])) ?></strong>
                                        <span class="muted small">(<?= e(strtoupper($res['source'])) ?>)</span>
                                    </td>
                                    <td>
                                        <strong class="text-amber font-mono" style="font-size:1.05rem;"><?= e($firstDisplay) ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($secondCount > 0): ?>
                                            <span class="muted small"><?= $secondCount ?> numbers</span>
                                            <div class="mono-box" style="max-width:200px; max-height:40px; overflow-y:auto; font-size:0.75rem;">
                                                <?= e(implode(', ', array_slice(array_column($prizes['2'], 'number'), 0, 5))) ?><?= $secondCount > 5 ? '...' : '' ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="muted small">3rd: <?= $thirdCount ?> | 4th: <?= $fourthCount ?> | 5th: <?= $fifthCount ?></span>
                                    </td>
                                    <td class="muted small"><?= e(date('d M h:i A', strtotime((string)$res['updated_at']))) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 6px; align-items: center;">
                                            <?php
                                            $adminModalPayload = [
                                                'date' => date('l, d F Y', strtotime((string)$res['draw_date'])),
                                                'slot' => (string)$res['draw_time'],
                                                'source' => strtoupper((string)$res['source']),
                                                'updated' => date('d M h:i A', strtotime((string)$res['updated_at'])),
                                                'prizes' => [
                                                    '1' => array_map(static fn($p) => $p['display_result'] ?? ($p['series'] ? $p['series'].' '.$p['number'] : $p['number']), $prizes['1'] ?? []),
                                                    '2' => array_column($prizes['2'] ?? [], 'number'),
                                                    '3' => array_column($prizes['3'] ?? [], 'number'),
                                                    '4' => array_column($prizes['4'] ?? [], 'number'),
                                                    '5' => array_column($prizes['5'] ?? [], 'number'),
                                                ],
                                            ];
                                            ?>
                                            <button type="button" class="mini-btn admin-btn-view btn-sambad-modal" data-sambad-payload="<?= e(json_encode($adminModalPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>" title="View all 1st-5th prizes">Prizes</button>
                                            <form method="post" action="/admin/action/lottery_fetch" class="inline-action-form" style="display:inline;">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="date" value="<?= e($res['draw_date']) ?>">
                                                <input type="hidden" name="draw_time" value="<?= e($res['draw_time']) ?>">
                                                <button type="submit" class="mini-btn admin-btn-action" title="Re-fetch this draw">Re-sync</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Automated Import & Vision OCR Logs -->
        <section class="panel admin-panel">
            <div class="panel-header-row">
                <h3 class="panel-title"><?= icon('list', 'panel-icon-svg', 18) ?> Import & OCR Extraction Audit Logs</h3>
                <span class="muted small">Last <?= count($lotteryLogs) ?> pipeline executions</span>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Timestamp</th>
                            <th>Target Draw</th>
                            <th>Source Pipeline</th>
                            <th>Status</th>
                            <th>Execution Time</th>
                            <th>Message / Error Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lotteryLogs === []): ?>
                            <tr><td colspan="7" class="muted center">No lottery import logs recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lotteryLogs as $log): ?>
                                <?php $isSuccess = $log['status'] === 'success'; ?>
                                <tr>
                                    <td>#<?= (int) $log['id'] ?></td>
                                    <td class="muted small"><?= e((new DateTimeImmutable($log['created_at']))->format('d M, h:i:s A')) ?></td>
                                    <td>
                                        <strong><?= e($log['draw_date']) ?></strong>
                                        <span class="pill pill-gold"><?= e($log['draw_time']) ?></span>
                                    </td>
                                    <td>
                                        <span class="pill pill-<?= $log['source'] === 'vision' ? 'gold' : ($log['source'] === 'manual' ? 'amber' : 'green') ?>">
                                            <?= e(strtoupper($log['source'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-dot dot-<?= $isSuccess ? 'green' : 'red' ?>"></span>
                                        <strong class="<?= $isSuccess ? 'text-green' : 'text-danger' ?>"><?= e(strtoupper($log['status'])) ?></strong>
                                    </td>
                                    <td><?= (int) $log['duration_ms'] ?> ms</td>
                                    <td>
                                        <div class="mono-box" style="max-width:350px; max-height:45px; overflow-y:auto; font-size:0.75rem;">
                                            <?= e($log['message']) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
        </div><!-- /.admin-content-inner -->
    </div><!-- /.admin-body-area -->
</div><!-- /.admin-layout-container -->

<!-- Sambad Prize Modal for Admin Dashboard -->
<div id="sambad-draw-modal" class="modal-overlay" style="display: none;" aria-hidden="true" role="dialog" aria-labelledby="sambad-modal-title">
    <div class="modal-backdrop" data-close-sambad-modal></div>
    <div class="modal-dialog sambad-modal-dialog">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <span class="modal-emoji">🏆</span>
                <div>
                    <h3 class="modal-title" id="sambad-modal-title">Lottery Sambad Result</h3>
                    <span class="modal-sub-title muted small" id="sambad-modal-subtitle">Draw Winning Numbers</span>
                </div>
            </div>
            <button type="button" class="modal-close-btn" data-close-sambad-modal aria-label="Close modal">✕</button>
        </div>

        <div class="modal-body sambad-modal-body">
            <!-- Draw Meta Header -->
            <div class="sambad-modal-meta-bar">
                <div class="meta-item">
                    <span class="meta-lbl">Date:</span>
                    <strong id="sambad-modal-date">—</strong>
                </div>
                <div class="meta-item">
                    <span class="meta-lbl">Draw Time:</span>
                    <span class="pill pill-gold" id="sambad-modal-slot">—</span>
                </div>
                <div class="meta-item">
                    <span class="meta-lbl">Source:</span>
                    <span class="text-green small" id="sambad-modal-source">Verified</span>
                </div>
            </div>

            <!-- Instant Ticket Search Box -->
            <div class="sambad-search-wrap mt-3 mb-3">
                <label for="sambad-ticket-search" class="search-label">🔎 Quick Ticket Number Check:</label>
                <div class="search-input-box">
                    <input type="text" id="sambad-ticket-search" class="sambad-search-input" placeholder="Type your 4 or 5-digit number to check win..." maxlength="10" autocomplete="off">
                    <span id="sambad-search-status" class="search-status-text"></span>
                </div>
            </div>

            <!-- 1st Prize Hero Card -->
            <div class="modal-prize-card prize-1st-card">
                <div class="prize-card-badge gold-badge">
                    <span class="badge-icon">🥇</span>
                    <strong>1st PRIZE — ₹1 CRORE (₹1,00,00,000)</strong>
                </div>
                <div class="prize-1st-display">
                    <span class="prize-1st-number font-mono" id="sambad-prize-1">—</span>
                </div>
            </div>

            <!-- 2nd Prize Card -->
            <div class="modal-prize-card mt-3">
                <div class="prize-card-badge">
                    <span>🥈 2nd Prize — ₹9,000</span>
                    <span class="badge-count" id="sambad-count-2">10 numbers</span>
                </div>
                <div class="modal-chips-grid font-mono" id="sambad-prize-2"></div>
            </div>

            <!-- 3rd Prize Card -->
            <div class="modal-prize-card mt-3">
                <div class="prize-card-badge">
                    <span>🥉 3rd Prize — ₹450</span>
                    <span class="badge-count" id="sambad-count-3">10 numbers</span>
                </div>
                <div class="modal-chips-grid font-mono" id="sambad-prize-3"></div>
            </div>

            <!-- 4th Prize Card -->
            <div class="modal-prize-card mt-3">
                <div class="prize-card-badge">
                    <span>4th Prize — ₹250</span>
                    <span class="badge-count" id="sambad-count-4">10 numbers</span>
                </div>
                <div class="modal-chips-grid font-mono" id="sambad-prize-4"></div>
            </div>

            <!-- 5th Prize Card -->
            <div class="modal-prize-card mt-3">
                <div class="prize-card-badge">
                    <span>5th Prize — ₹120</span>
                    <span class="badge-count" id="sambad-count-5">100 numbers</span>
                </div>
                <div class="modal-chips-grid font-mono chips-grid-dense" id="sambad-prize-5"></div>
            </div>
        </div>

        <div class="modal-footer sambad-modal-footer">
            <button type="button" class="btn btn-primary btn-block" data-close-sambad-modal>Close</button>
        </div>
    </div>
</div>
