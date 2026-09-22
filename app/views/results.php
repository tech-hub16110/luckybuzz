<?php
/** @var string $todayDate */
/** @var string $yesterdayDate */
/** @var string $selectedDate */
/** @var bool $isBeforeFirstDraw */
/** @var bool $hasExplicitDate */
/** @var array{date: string, draws: array<string, array{import: array<string, mixed>|null, prizes: array<string, list<array<string, mixed>>>}>} $daySummary */
/** @var list<string> $recentDates */
/** @var list<array<string, mixed>> $sambadRecent */

$todayDate = $todayDate ?? Clock::now()->format('Y-m-d');
$yesterdayDate = $yesterdayDate ?? date('Y-m-d', strtotime('-1 day', strtotime($todayDate)));
$selectedDate = $selectedDate ?? $todayDate;
$isBeforeFirstDraw = $isBeforeFirstDraw ?? false;
$hasExplicitDate = $hasExplicitDate ?? false;

$isSelectedToday = ($selectedDate === $todayDate);
$isYesterday = ($selectedDate === $yesterdayDate);

$formattedSelectedDate = date('l, d F Y', strtotime($selectedDate));
?>

<section class="section-container mb-4">
    <div class="section-header results-official-header">
        <div class="results-header-tag">
            <span class="results-block-badge badge-sambad">OFFICIAL FEED</span>
            <span class="pill pill-gold">1 PM • 6 PM • 8 PM</span>
        </div>
        <h1 class="section-title">🏆 Lottery Sambad Official Results</h1>
        <p class="section-subtitle">Live verified winning numbers for Nagaland & West Bengal State Lotteries</p>
    </div>

    <?php if ($isBeforeFirstDraw && !$isSelectedToday && !$hasExplicitDate): ?>
        <div class="info-alert-banner mb-4">
            <span class="alert-icon">⏰</span>
            <div class="alert-text">
                <strong>Today's 1:00 PM draw is still upcoming.</strong>
                <span>Showing latest completed winning results from Yesterday (<?= e(date('d M Y', strtotime($yesterdayDate))) ?>).</span>
            </div>
            <a href="/results?date=<?= e($todayDate) ?>" class="btn btn-sm btn-secondary ml-auto">Check Today's Schedule ➔</a>
        </div>
    <?php endif; ?>

    <!-- =========================================================================
         DATE SELECTOR CONTROLLER
         ========================================================================= -->
    <div class="results-date-filter-panel mb-4">
        <form method="get" action="/results" class="date-filter-form" id="results-date-form">
            <div class="date-picker-row">
                <div class="date-picker-field">
                    <label for="date-input" class="date-filter-label">📅 Select Draw Date:</label>
                    <div class="date-input-group">
                        <input type="date" 
                               id="date-input" 
                               name="date" 
                               value="<?= e($selectedDate) ?>" 
                               max="<?= e($todayDate) ?>" 
                               class="results-date-input"
                               onchange="document.getElementById('results-date-form').submit();">
                        <button type="submit" class="btn btn-primary btn-date-go">View Results</button>
                    </div>
                </div>

                <!-- Quick Date Switcher Pills -->
                <div class="quick-dates-wrap">
                    <span class="quick-dates-label">Quick Pick:</span>
                    <div class="quick-dates-list">
                        <a href="/results?date=<?= e($todayDate) ?>" 
                           class="quick-date-btn <?= $isSelectedToday ? 'active' : '' ?>">
                            🌟 Today
                        </a>
                        <a href="/results?date=<?= e($yesterdayDate) ?>" 
                           class="quick-date-btn <?= $isYesterday ? 'active' : '' ?>">
                            ⏮ Yesterday
                        </a>
                        <?php 
                        $quickPrevCount = 0;
                        foreach ($recentDates as $availDate): 
                            if ($availDate === $todayDate || $availDate === $yesterdayDate) continue;
                            if ($quickPrevCount >= 4) break;
                            $quickPrevCount++;
                            $isAvailSelected = ($selectedDate === $availDate);
                        ?>
                            <a href="/results?date=<?= e($availDate) ?>" 
                               class="quick-date-btn <?= $isAvailSelected ? 'active' : '' ?>">
                                <?= e(date('d M', strtotime($availDate))) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </form>

        <div class="selected-date-indicator mt-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="indicator-icon">📅</span>
                <span>Showing results for: <strong class="text-amber font-mono"><?= e($formattedSelectedDate) ?></strong></span>
                <?php if ($isSelectedToday): ?>
                    <span class="pill pill-gold small">Today's Live Draw</span>
                <?php elseif ($isYesterday): ?>
                    <span class="pill pill-secondary small">Yesterday's Results</span>
                <?php else: ?>
                    <span class="pill pill-secondary small">Historical Draw</span>
                <?php endif; ?>
            </div>

            <div class="selected-date-actions ml-auto">
                <form method="post" action="/action/lottery_fetch" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
                    <button type="submit" class="quick-date-btn btn-sync-live" title="Live fetch/refresh results from official Sambad feed">
                        🔄 Sync Live Results
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         DRAW TIME RESULTS GRID (1 PM, 6 PM, 8 PM) FOR SELECTED DATE
         ========================================================================= -->
    <div class="sambad-today-grid mb-5">
        <?php foreach (LotteryImporter::DRAW_SCHEDULE as $timeSlot => $sched): ?>
            <?php 
                $drawInfo = $daySummary['draws'][$timeSlot] ?? null;
                $import = $drawInfo['import'] ?? null;
                $prizes = $drawInfo['prizes'] ?? null;
                $isAvailable = $import !== null && $import['status'] === LotteryImporter::STATUS_SUCCESS && !empty($prizes['1']);
                $sourceLabel = $import ? ($import['source'] === 'vision' ? 'Vision AI OCR' : ($import['source'] === 'manual' ? 'Verified Manual' : 'Sambad API')) : 'Pending';
                
                $modalData = null;
                if ($isAvailable) {
                    $modalData = [
                        'date' => $formattedSelectedDate,
                        'slot' => $timeSlot,
                        'source' => $sourceLabel,
                        'updated' => !empty($import['imported_at']) ? date('h:i A', strtotime((string)$import['imported_at'])) : null,
                        'prizes' => [
                            '1' => array_map(static fn($p) => $p['display_result'] ?? ($p['series'] ? $p['series'].' '.$p['number'] : $p['number']), $prizes['1'] ?? []),
                            '2' => array_column($prizes['2'] ?? [], 'number'),
                            '3' => array_column($prizes['3'] ?? [], 'number'),
                            '4' => array_column($prizes['4'] ?? [], 'number'),
                            '5' => array_column($prizes['5'] ?? [], 'number'),
                        ],
                    ];
                }
            ?>
            <div class="sambad-draw-card <?= $isAvailable ? 'has-result' : 'is-pending' ?>">
                <div class="sambad-card-top">
                    <div class="sambad-slot-title">
                        <span class="slot-badge"><?= e($timeSlot) ?></span>
                        <strong>Lottery Sambad</strong>
                    </div>
                    <div class="sambad-time-tag">
                        <span>📅 <?= e(date('d M Y', strtotime($selectedDate))) ?></span>
                    </div>
                </div>

                <?php if ($isAvailable): ?>
                    <div class="sambad-card-content">
                        <!-- 1st Prize -->
                        <?php if (!empty($prizes['1'])): ?>
                            <?php $first = $prizes['1'][0]; ?>
                            <div class="sambad-prize-box first-prize-box">
                                <span class="prize-tag gold-tag">🥇 1st PRIZE (₹1 CRORE)</span>
                                <div class="first-prize-num font-mono">
                                    <strong><?= e($first['display_result']) ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 2nd Prize Preview -->
                        <?php if (!empty($prizes['2'])): ?>
                            <div class="sambad-prize-box">
                                <span class="prize-tag">🥈 2nd Prize (₹9,000)</span>
                                <div class="prize-chips-wrap font-mono">
                                    <?php foreach (array_slice($prizes['2'], 0, 5) as $p2): ?>
                                        <span class="prize-chip"><?= e($p2['number']) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($prizes['2']) > 5): ?>
                                        <span class="prize-chip chip-more">+<?= count($prizes['2']) - 5 ?> more</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 3rd - 5th summary info -->
                        <div class="prizes-count-summary muted small mt-2">
                            <span>🥉 3rd (<?= count($prizes['3'] ?? []) ?>) • 4th (<?= count($prizes['4'] ?? []) ?>) • 5th (<?= count($prizes['5'] ?? []) ?>)</span>
                        </div>

                        <!-- Open Modal Button -->
                        <div class="sambad-card-action mt-3">
                            <button type="button" class="btn btn-primary btn-block btn-sambad-modal font-weight-bold" data-sambad-payload="<?= e(json_encode($modalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>">
                                <span>👁️ View Full Result & Prizes</span>
                            </button>
                        </div>
                    </div>

                    <div class="sambad-card-footer">
                        <div class="source-info">
                            <span class="source-lbl">Source:</span>
                            <strong class="text-green"><span class="badge-dot dot-green"></span> <?= e($sourceLabel) ?></strong>
                        </div>
                        <?php if (!empty($import['imported_at'])): ?>
                            <span class="imported-time muted small">Updated <?= e(date('h:i A', strtotime((string)$import['imported_at']))) ?></span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="sambad-card-pending">
                        <div class="pending-icon">⏳</div>
                        <h4><?= e($timeSlot) ?> Result <?= $isSelectedToday ? 'Pending' : 'Not Loaded' ?></h4>
                        <p class="muted small mb-2">
                            <?php if ($isSelectedToday): ?>
                                <?php if ($import && $import['attempt_count'] > 0): ?>
                                    Last checked at <?= e(date('h:i A', strtotime((string)$import['last_attempt_at']))) ?>.
                                <?php else: ?>
                                    Results publish daily at <?= e($timeSlot) ?>.
                                <?php endif; ?>
                            <?php else: ?>
                                Tap below to fetch live official result from Sambad feed.
                            <?php endif; ?>
                        </p>
                        <form method="post" action="/action/lottery_fetch" class="mt-1">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
                            <input type="hidden" name="draw_time" value="<?= e($timeSlot) ?>">
                            <button type="submit" class="quick-date-btn btn-fetch-single">
                                🔄 Live Fetch <?= e($timeSlot) ?> Now
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- =========================================================================
         PREVIOUS DAYS HISTORY ARCHIVE
         ========================================================================= -->
    <?php if (count($recentDates) > 1 || count($sambadRecent) > 1): ?>
        <section class="panel mb-4">
            <div class="panel-header-row">
                <div>
                    <h3 class="panel-title">📅 Browse Previous Draw Dates Archive</h3>
                    <p class="muted">Click "View Result" on any draw time to open the complete winning prize breakdown modal.</p>
                </div>
            </div>

            <div class="sambad-history-list mt-3">
                <?php foreach ($sambadRecent as $histDay): ?>
                    <?php if ($histDay['date'] === $selectedDate) continue; ?>
                    <div class="sambad-history-day-card">
                        <div class="history-day-header">
                            <div class="history-day-title">
                                <strong>📅 <?= e(date('l, d F Y', strtotime($histDay['date']))) ?></strong>
                            </div>
                            <a href="/results?date=<?= e($histDay['date']) ?>" class="history-view-day-btn">
                                Switch to this day ➔
                            </a>
                        </div>
                        <div class="history-day-slots-grid">
                            <?php foreach (['1 PM', '6 PM', '8 PM'] as $slot): ?>
                                <?php 
                                    $slotData = $histDay['draws'][$slot] ?? null; 
                                    $slotImport = $slotData['import'] ?? null;
                                    $slotPrizes = $slotData['prizes'] ?? null;
                                    $hasSlotPrizes = $slotData && !empty($slotPrizes['1']);
                                    $slotSource = $slotImport ? ($slotImport['source'] === 'vision' ? 'Vision AI OCR' : ($slotImport['source'] === 'manual' ? 'Verified Manual' : 'Sambad API')) : 'Verified';
                                    
                                    $slotModalData = null;
                                    if ($hasSlotPrizes) {
                                        $slotModalData = [
                                            'date' => date('l, d F Y', strtotime($histDay['date'])),
                                            'slot' => $slot,
                                            'source' => $slotSource,
                                            'updated' => !empty($slotImport['imported_at']) ? date('h:i A', strtotime((string)$slotImport['imported_at'])) : null,
                                            'prizes' => [
                                                '1' => array_map(static fn($p) => $p['display_result'] ?? ($p['series'] ? $p['series'].' '.$p['number'] : $p['number']), $slotPrizes['1'] ?? []),
                                                '2' => array_column($slotPrizes['2'] ?? [], 'number'),
                                                '3' => array_column($slotPrizes['3'] ?? [], 'number'),
                                                '4' => array_column($slotPrizes['4'] ?? [], 'number'),
                                                '5' => array_column($slotPrizes['5'] ?? [], 'number'),
                                            ],
                                        ];
                                    }
                                ?>
                                <div class="history-slot-box <?= $hasSlotPrizes ? 'has-result' : '' ?>">
                                    <div class="slot-header-line">
                                        <span class="slot-pill"><?= e($slot) ?></span>
                                        <?php if ($hasSlotPrizes): ?>
                                            <span class="badge-dot dot-green" title="Verified"></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($hasSlotPrizes): ?>
                                        <div class="history-1st-prize mt-1">
                                            <span class="first-lbl">1st Prize:</span>
                                            <strong class="text-amber font-mono"><?= e($slotPrizes['1'][0]['display_result']) ?></strong>
                                        </div>
                                        <span class="muted small"><?= count($slotPrizes['2'] ?? []) + count($slotPrizes['3'] ?? []) + count($slotPrizes['4'] ?? []) + count($slotPrizes['5'] ?? []) ?> prizes recorded</span>
                                        <button type="button" 
                                                class="btn btn-secondary btn-sm btn-block mt-2 btn-sambad-modal" 
                                                data-sambad-payload="<?= e(json_encode($slotModalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>">
                                            👁️ View Result
                                        </button>
                                    <?php else: ?>
                                        <span class="muted small">— No Result —</span>
                                        <form method="post" action="/action/lottery_fetch" class="mt-2">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="date" value="<?= e($histDay['date']) ?>">
                                            <input type="hidden" name="draw_time" value="<?= e($slot) ?>">
                                            <button type="submit" class="mini-btn admin-btn-action btn-block">
                                                🔄 Fetch
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Friendly Help Card -->
    <section class="how-to-play-card">
        <h3 class="how-title">💡 How to Check Your Win</h3>
        <p class="step-desc" style="font-size: 15px; line-height: 1.6; margin-top: 8px;">
            Compare your ticket numbers from <a href="/tickets" style="color: var(--amber); font-weight: bold; text-decoration: underline;">My Tickets</a> with the winning numbers above. Match all digits to win jackpot and consolidated prize coins!
        </p>
    </section>
</section>

<!-- =========================================================================
     PRIZE BREAKDOWN MODAL DIALOG (1ST, 2ND, 3RD, 4TH, 5TH PRIZES)
     ========================================================================= -->
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
                <div class="modal-chips-grid font-mono" id="sambad-prize-2">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- 3rd Prize Card -->
            <div class="modal-prize-card mt-3">
                <div class="prize-card-badge">
                    <span>🥉 3rd Prize — ₹450</span>
                    <span class="badge-count" id="sambad-count-3">10 numbers</span>
                </div>
                <div class="modal-chips-grid font-mono" id="sambad-prize-3">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- 4th Prize Card -->
            <div class="modal-prize-card mt-3">
                <div class="prize-card-badge">
                    <span>4th Prize — ₹250</span>
                    <span class="badge-count" id="sambad-count-4">10 numbers</span>
                </div>
                <div class="modal-chips-grid font-mono" id="sambad-prize-4">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- 5th Prize Card -->
            <div class="modal-prize-card mt-3">
                <div class="prize-card-badge">
                    <span>5th Prize — ₹120</span>
                    <span class="badge-count" id="sambad-count-5">100 numbers</span>
                </div>
                <div class="modal-chips-grid font-mono chips-grid-dense" id="sambad-prize-5">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>

        <div class="modal-footer sambad-modal-footer">
            <a href="/tickets" class="btn btn-secondary">🎟️ Check My Tickets</a>
            <button type="button" class="btn btn-primary" data-close-sambad-modal>Done</button>
        </div>
    </div>
</div>
