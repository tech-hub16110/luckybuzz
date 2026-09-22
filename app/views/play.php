<?php
/** @var array<string, mixed> $draw */
/** @var int $jackpot */
/** @var list<array<string, mixed>> $mine */
/** @var int $held */
/** @var int $cap */

$tier = Config::tier($draw['tier']);
$at = new DateTimeImmutable($draw['draw_at']);
$cost = Config::int('ticket_cost');
$open = Tickets::mine((int) $user['id'], 60);
$inDraw = array_values(array_filter($open, static fn ($t) => (int) $t['draw_id'] === (int) $draw['id']));
$accent = $tier['accent'];
$iconEmoji = $accent === 'green' ? '☀️' : ($accent === 'amber' ? '🌅' : '🌙');
?>

<!-- Draw Banner & Switcher -->
<section class="play-draw-header tier-<?= e($accent) ?>" data-draw-at="<?= e($draw['draw_at']) ?>">
    <div class="draw-header-top">
        <div class="draw-title-group">
            <span class="draw-top-emoji"><?= $iconEmoji ?></span>
            <div>
                <h1 class="draw-top-heading"><?= e($tier['label']) ?> Draw</h1>
                <span class="draw-top-sub">⏰ Draw Time: <?= e($at->format('h:i A')) ?> (<?= e($at->format('l')) ?>)</span>
            </div>
        </div>
        <div class="draw-top-timer">
            <span class="timer-lbl">Time Left</span>
            <strong class="timer-val" data-clock>—</strong>
        </div>
    </div>

    <!-- Prize Showcase -->
    <div class="play-prize-strip">
        <span class="prize-strip-label">🏆 WINNING PRIZE</span>
        <strong class="prize-strip-amount"><span class="currency">₹</span><?= number_format($jackpot) ?> <span class="coins-txt">Coins</span></strong>
    </div>

    <!-- Quick Switch Draws -->
    <div class="draw-quick-switcher">
        <span class="switch-label">Switch Draw:</span>
        <div class="switch-pills">
            <?php foreach (Draws::board() as $row): ?>
                <?php $t = Config::tier($row['tier']); ?>
                <a class="draw-switch-pill <?= $row['tier'] === $draw['tier'] ? 'active' : '' ?>" href="/play?draw=<?= (int) $row['id'] ?>">
                    <?= e($t['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Mode Selection: 1 Ticket vs Series -->
<div class="play-mode-tabs-wrap">
    <button type="button" class="mode-tab-btn active" id="tab-single" data-mode="single">
        <span class="mode-icon">🎯</span>
        <div class="mode-text">
            <strong>Pick 1 Number</strong>
            <span>5 Digits Ticket</span>
        </div>
    </button>
    <button type="button" class="mode-tab-btn" id="tab-range" data-mode="range">
        <span class="mode-icon">🎫</span>
        <div class="mode-text">
            <strong>Pick Series</strong>
            <span>Multiple Numbers</span>
        </div>
    </button>
</div>

<!-- ================= SINGLE TICKET MODE ================= -->
<div class="play-card" id="mode-single-pane">
    <form method="post" action="/action/play" id="form-single" data-cost="<?= $cost ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="draw_id" value="<?= (int) $draw['id'] ?>">
        <input type="hidden" name="number" id="number" value="">
        <input type="hidden" name="sem" id="sem-input" value="5">

        <!-- STEP 1: PICK 5 DIGITS -->
        <div class="step-card">
            <div class="step-card-head">
                <span class="step-badge">STEP 1</span>
                <h3 class="step-title">Enter 5 Lucky Digits</h3>
            </div>

            <!-- Big Interactive Lottery Ball Slots -->
            <div class="lotto-ball-slots" id="digits">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="ball-slot <?= $i === 0 ? 'active' : '' ?>" data-slot="<?= $i ?>">
                        <input class="digitbox sr-only" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1"
                               aria-label="Digit <?= $i + 1 ?>" <?= $i === 0 ? 'autofocus' : '' ?>>
                        <span class="ball-display">?</span>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Lucky Random Pick Button -->
            <div class="lucky-pick-row">
                <button type="button" class="lucky-pick-btn" id="quickpick">
                    <span class="lucky-icon">🎲</span>
                    <span class="lucky-text">Tap for Lucky Random Numbers</span>
                </button>
            </div>

            <!-- Large Easy On-Screen Number Pad (Numpad) -->
            <div class="onscreen-keypad" id="onscreen-keypad">
                <div class="keypad-grid">
                    <button type="button" class="key-btn" data-key="1">1</button>
                    <button type="button" class="key-btn" data-key="2">2</button>
                    <button type="button" class="key-btn" data-key="3">3</button>
                    <button type="button" class="key-btn" data-key="4">4</button>
                    <button type="button" class="key-btn" data-key="5">5</button>
                    <button type="button" class="key-btn" data-key="6">6</button>
                    <button type="button" class="key-btn" data-key="7">7</button>
                    <button type="button" class="key-btn" data-key="8">8</button>
                    <button type="button" class="key-btn" data-key="9">9</button>
                    <button type="button" class="key-btn key-lucky" id="key-lucky-btn" title="Lucky Random">🎲</button>
                    <button type="button" class="key-btn" data-key="0">0</button>
                    <button type="button" class="key-btn key-backspace" id="key-backspace-btn" title="Delete Digit">⌫</button>
                </div>
            </div>
        </div>

        <!-- STEP 2: CHOOSE MULTIPLIER -->
        <div class="step-card">
            <div class="step-card-head">
                <span class="step-badge">STEP 2</span>
                <div class="step-head-info">
                    <h3 class="step-title">Choose Multiplier (Win More)</h3>
                    <span class="step-subtitle">Higher multiplier gives bigger winning prize</span>
                </div>
            </div>

            <div class="sem-preset-grid">
                <button type="button" class="sem-tile active" data-sem="5">
                    <span class="tile-mult-tag">STANDARD</span>
                    <strong class="tile-val">5x</strong>
                    <span class="tile-desc">5x Win</span>
                    <span class="tile-price">₹50</span>
                </button>
                <button type="button" class="sem-tile" data-sem="10">
                    <span class="tile-mult-tag">POPULAR</span>
                    <strong class="tile-val">10x</strong>
                    <span class="tile-desc">10x Win</span>
                    <span class="tile-price">₹100</span>
                </button>
                <button type="button" class="sem-tile" data-sem="15">
                    <span class="tile-mult-tag">TURBO</span>
                    <strong class="tile-val">15x</strong>
                    <span class="tile-desc">15x Win</span>
                    <span class="tile-price">₹150</span>
                </button>
                <button type="button" class="sem-tile" data-sem="20">
                    <span class="tile-mult-tag">MAX</span>
                    <strong class="tile-val">20x</strong>
                    <span class="tile-desc">20x Win</span>
                    <span class="tile-price">₹200</span>
                </button>
            </div>

            <div class="custom-sem-row">
                <button type="button" class="custom-sem-btn" id="open-sem-popup-single">
                    <span>⚡ Choose Custom Multiplier (up to 95x)</span>
                    <span class="arrow-icon">➔</span>
                </button>
            </div>
        </div>

        <!-- STEP 3: TICKET TOTAL & PLAY -->
        <div class="step-card total-calculation-card">
            <div class="calc-breakdown">
                <div class="calc-item">
                    <span class="calc-lbl">Ticket</span>
                    <span class="calc-val">1</span>
                </div>
                <span class="calc-symbol">×</span>
                <div class="calc-item">
                    <span class="calc-lbl">Multiplier</span>
                    <span class="calc-val" id="calc-sem-single">5 SEM</span>
                </div>
                <span class="calc-symbol">×</span>
                <div class="calc-item">
                    <span class="calc-lbl">Base Price</span>
                    <span class="calc-val">₹<?= $cost ?></span>
                </div>
                <span class="calc-symbol">=</span>
                <div class="calc-item total-item">
                    <span class="calc-lbl">Total Cost</span>
                    <strong class="calc-val-total" id="calc-total-single">₹50</strong>
                </div>
            </div>

            <div class="play-action-buttons">
                <button class="big-buy-btn btn-green pulse-btn" type="submit" id="btn-buy-single">
                    <span class="buy-icon">✅</span>
                    <span class="buy-main-text">BUY TICKET NOW</span>
                    <span class="buy-cost-badge" id="btn-single-cost-tag">₹50</span>
                </button>
                <button type="button" class="cart-action-btn" id="btn-add-cart-single">
                    <span>🛒 Add to Cart</span>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ================= SERIES / RANGE MODE ================= -->
<div class="play-card" id="mode-range-pane" style="display: none;">
    <div class="pickform" id="form-range" data-cost="<?= $cost ?>">
        <input type="hidden" id="sem-range-input" value="5">

        <div class="step-card">
            <div class="step-card-head">
                <span class="step-badge">STEP 1</span>
                <h3 class="step-title">Enter Number Range (Start & End)</h3>
            </div>

            <div class="range-inputs-box">
                <div class="range-input-group">
                    <label for="range-from" class="range-field-label">From Number (Start):</label>
                    <input type="text" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" id="range-from" class="range-box" placeholder="e.g. 10001" value="10001">
                </div>
                <div class="range-arrow">➔</div>
                <div class="range-input-group">
                    <label for="range-to" class="range-field-label">To Number (End):</label>
                    <input type="text" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" id="range-to" class="range-box" placeholder="e.g. 10010" value="10010">
                </div>
            </div>

            <!-- Quick series shortcuts -->
            <div class="series-presets">
                <span class="series-presets-label">Quick Series:</span>
                <button type="button" class="preset-chip" data-series-count="5">+5 Tickets</button>
                <button type="button" class="preset-chip active" data-series-count="10">+10 Tickets</button>
                <button type="button" class="preset-chip" data-series-count="20">+20 Tickets</button>
                <button type="button" class="preset-chip" data-series-count="50">+50 Tickets</button>
            </div>
        </div>

        <div class="step-card">
            <div class="step-card-head">
                <span class="step-badge">STEP 2</span>
                <h3 class="step-title">Choose Multiplier</h3>
            </div>

            <div class="sem-preset-grid">
                <button type="button" class="sem-range-tile active" data-sem="5">
                    <strong class="tile-val">5x</strong>
                    <span class="tile-desc">Standard</span>
                </button>
                <button type="button" class="sem-range-tile" data-sem="10">
                    <strong class="tile-val">10x</strong>
                    <span class="tile-desc">Popular</span>
                </button>
                <button type="button" class="sem-range-tile" data-sem="15">
                    <strong class="tile-val">15x</strong>
                    <span class="tile-desc">Turbo</span>
                </button>
                <button type="button" class="sem-range-tile" data-sem="20">
                    <strong class="tile-val">20x</strong>
                    <span class="tile-desc">Max</span>
                </button>
            </div>
        </div>

        <div class="step-card total-calculation-card">
            <div class="calc-breakdown">
                <div class="calc-item">
                    <span class="calc-lbl">Total Tickets</span>
                    <span class="calc-val" id="calc-range-count">10</span>
                </div>
                <span class="calc-symbol">×</span>
                <div class="calc-item">
                    <span class="calc-lbl">Multiplier</span>
                    <span class="calc-val" id="calc-range-sem">5 SEM</span>
                </div>
                <span class="calc-symbol">×</span>
                <div class="calc-item">
                    <span class="calc-lbl">Base</span>
                    <span class="calc-val">₹<?= $cost ?></span>
                </div>
                <span class="calc-symbol">=</span>
                <div class="calc-item total-item">
                    <span class="calc-lbl">Total</span>
                    <strong class="calc-val-total" id="calc-total-range">₹500</strong>
                </div>
            </div>

            <div class="play-action-buttons">
                <button type="button" class="big-buy-btn btn-green" id="btn-buy-range">
                    <span class="buy-icon">✅</span>
                    <span class="buy-main-text">BUY SERIES TICKETS</span>
                </button>
                <button type="button" class="cart-action-btn" id="btn-add-cart-range">
                    <span>🛒 Add Series to Cart</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= CUSTOM MULTIPLIER MODAL ================= -->
<div class="modal-overlay" id="sem-modal" style="display: none;">
    <div class="modal-backdrop" id="sem-modal-backdrop"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <span class="modal-emoji">⚡</span>
                <h3 class="modal-title">Select Multiplier</h3>
            </div>
            <button type="button" class="modal-close-btn" id="sem-modal-close" aria-label="Close">✕</button>
        </div>

        <div class="modal-body">
            <!-- Active Display Card -->
            <div class="modal-hero-val">
                <span class="hero-val-sub">Current Selection:</span>
                <div class="hero-val-main">
                    <span class="hero-val-num" id="modal-hero-sem-num">5</span>
                    <span class="hero-val-unit">x Multiplier</span>
                </div>
            </div>

            <!-- Quick Presets -->
            <div class="modal-quick-grid">
                <?php foreach ([5, 10, 15, 20, 25, 30, 40, 50, 75, 95] as $val): ?>
                    <button type="button" class="modal-sem-chip" data-sem="<?= $val ?>">
                        <?= $val ?>x
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Stepper Buttons -->
            <div class="modal-stepper-box">
                <button type="button" class="stepper-btn" id="modal-sem-minus">−</button>
                <div class="stepper-center">
                    <input type="number" id="modal-sem-input" class="manual-sem-input" value="5" min="5" max="95" step="5">
                    <span class="stepper-unit">Multiplier</span>
                </div>
                <button type="button" class="stepper-btn" id="modal-sem-plus">+</button>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="big-action-btn btn-amber" id="sem-modal-apply">
                <span>✅ Apply Multiplier</span>
            </button>
        </div>
    </div>
</div>

<!-- ================= SHOPPING CART MODAL ================= -->
<div class="modal-overlay" id="cart-modal" style="display: none;" data-balance="<?= (int) $balance ?>">
    <div class="modal-backdrop" id="cart-modal-backdrop"></div>
    <div class="modal-dialog cart-modal-dialog">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <span class="modal-emoji">🛒</span>
                <h3 class="modal-title">My Tickets Cart</h3>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="ghost cart-clear-btn" id="btn-modal-clear-cart" style="display: none;">Clear</button>
                <button type="button" class="modal-close-btn" id="cart-modal-close" aria-label="Close">✕</button>
            </div>
        </div>

        <div class="modal-body">
            <!-- Empty Cart -->
            <div id="cart-modal-empty" class="cart-empty-view">
                <div class="cart-empty-icon">🛒</div>
                <h4 class="cart-empty-title">Your cart is empty</h4>
                <p class="cart-empty-sub">Pick 5 digits and tap <strong>Add to Cart</strong>.</p>
                <button type="button" class="big-action-btn btn-green" id="btn-cart-empty-pick">👉 Pick Numbers</button>
            </div>

            <!-- Filled Cart -->
            <div id="cart-modal-filled" style="display: none;">
                <div class="cart-table-card">
                    <div class="cart-table-body" id="cart-modal-items-list"></div>
                </div>

                <div class="cart-modal-summary">
                    <div class="cart-summary-line subtotal-main-line">
                        <span class="summary-lbl">Total Cost:</span>
                        <strong class="summary-val summary-highlight" id="cart-modal-subtotal">₹0</strong>
                    </div>
                    <div class="cart-summary-line">
                        <span class="summary-lbl">Your Coins Balance:</span>
                        <span class="summary-val summary-balance" id="cart-modal-balance-val">₹<?= number_format((int) $balance) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer" id="cart-modal-footer" style="display: none;">
            <form method="post" action="/action/play" id="form-cart-checkout" style="width: 100%;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="draw_id" value="<?= (int) $draw['id'] ?>">
                <input type="hidden" name="cart_items" id="cart-items-json" value="">

                <button class="big-action-btn btn-green" type="submit" id="btn-modal-checkout">
                    <span>✅ Checkout & Confirm Tickets</span>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MY TICKETS IN THIS DRAW -->
<?php if ($inDraw !== []): ?>
    <section class="section-container">
        <div class="section-header">
            <h3 class="section-title">🎟️ Your Tickets in this Draw (<?= count($inDraw) ?>)</h3>
        </div>
        <div class="my-draw-tickets-grid">
            <?php foreach ($inDraw as $t): ?>
                <?php $digits = str_split((string) $t['number']); ?>
                <div class="mini-ticket-card">
                    <div class="mini-ticket-balls">
                        <?php foreach ($digits as $d): ?>
                            <span class="mini-lotto-ball"><?= e($d) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="mini-ticket-info">
                        <span class="pill pill-green"><?= inr((int) $t['cost']) ?></span>
                        <span class="mini-ticket-time"><?= e((new DateTimeImmutable($t['created_at']))->format('h:i A')) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
