<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

// php -S with a router script consults the router for every request, so hand
// asset files back to the server instead of 404-ing them. Scoped to /assets/
// so this can never be talked into serving anything else out of the tree.
if (PHP_SAPI === 'cli-server') {
    $requested = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#^/(assets/[A-Za-z0-9._-]+|robots\.txt)$#', (string) $requested) === 1) {
        $asset = __DIR__ . $requested;
        if (is_file($asset)) {
            return false;
        }
    }
}

Auth::startSession();

engine_tick(10);

$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_check(): bool
{
    $sent = (string) ($_POST['csrf'] ?? '');

    return $sent !== '' && hash_equals(csrf_token(), $sent);
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function needs_login(): never
{
    flash('Sign in to play — it takes one username and nothing else.', 'warn');
    redirect('/login');
}

function needs_admin(): never
{
    flash('Admin authentication required. Please sign in.', 'warn');
    redirect('/login');
}

function uid(): int
{
    return (int) Auth::userId();
}

if ($method === 'POST') {
    if (!csrf_check()) {
        http_response_code(400);
        flash('That form had expired. Try again.', 'bad');
        redirect($path === '/action/play' ? '/play' : '/');
    }

    switch ($path) {
        case '/action/register':
            $username = strtolower(trim((string) ($_POST['username'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $error = Auth::validateUsername($username) ?? Auth::validatePassword($password);

            if ($error === null) {
                try {
                    $taken = Db::conn()->prepare('SELECT id FROM users WHERE username = ?');
                    $taken->execute([$username]);
                    if ($taken->fetchColumn() !== false) {
                        $error = 'Someone already has that username.';
                    } else {
                        $user = Auth::register($username, $password);
                        Auth::login((int) $user['id']);
                        flash('Welcome! ' . coins(Config::int('signup_grant')) . ' free coins are in your wallet.', 'ok');
                        redirect('/');
                    }
                } catch (PDOException) {
                    $error = 'Could not create that account just now.';
                }
            }

            flash($error, 'bad');
            redirect('/register');

        case '/action/login':
        case '/admin/action/login':
            $rawUsername = trim((string) ($_POST['username'] ?? ''));
            $username = strtolower($rawUsername);
            $password = (string) ($_POST['password'] ?? '');

            // 1. Role Check: Admin credentials automatically redirects to Admin Dashboard
            if (Admin::login($rawUsername, $password) || Admin::login($username, $password)) {
                flash('Welcome, Admin! Signed into Owner Dashboard.', 'ok');
                redirect('/admin');
            }

            // 2. Role Check: Player credentials automatically redirects to Player Game
            $user = Auth::attempt($username, $password);
            if ($user === null) {
                flash('Wrong username or password.', 'bad');
                redirect('/login');
            }
            Auth::login((int) $user['id']);
            redirect('/');

        case '/action/logout':
        case '/admin/action/logout':
            Auth::logout();
            Admin::logout();
            redirect('/');

        case '/action/box':
            try {
                $out = Boxes::open(uid(), (int) ($_POST['draw_id'] ?? 0));
                flash("🎁 Surprise box: +" . coins($out['coins']) . ' coins.', 'ok');
            } catch (RuntimeException $e) {
                flash($e->getMessage(), 'warn');
            }

            redirect('/boxes');

        case '/action/daily':
            $out = Bonuses::claimDaily(uid());
            flash($out['message'], $out['claimed'] ? 'ok' : 'warn');
            redirect('/account');

        case '/action/refill':
            $out = Bonuses::claimRefill(uid());
            flash($out['message'], $out['claimed'] ? 'ok' : 'warn');
            redirect('/account');

        case '/action/play':
            $drawId = (int) ($_POST['draw_id'] ?? 0);
            $cartJson = trim((string) ($_POST['cart_items'] ?? ''));

            if ($cartJson !== '') {
                $items = json_decode($cartJson, true);
                if (is_array($items) && $items !== []) {
                    try {
                        $out = Tickets::purchaseBulk(uid(), $drawId, $items);
                        flash("Purchased {$out['count']} tickets (Total " . inr($out['total_cost']) . ') successfully!', 'ok');
                    } catch (InsufficientCoins) {
                        flash('Not enough coins in wallet. Coins are allocated directly by the Game Owner/Admin.', 'warn');
                    } catch (RuntimeException $e) {
                        flash($e->getMessage(), 'bad');
                    }
                    redirect('/play?draw=' . $drawId);
                }
            }

            $number = trim((string) ($_POST['number'] ?? ''));
            $sem = max(1, min(95, (int) ($_POST['sem'] ?? 1)));

            if (!Tickets::isNumber($number) && preg_match('/^\d{1,5}$/', $number)) {
                $number = strlen($number) <= 4 ? str_pad($number, 4, '0', STR_PAD_LEFT) : str_pad($number, 5, '0', STR_PAD_LEFT);
            }

            try {
                $out = Tickets::purchase(uid(), $drawId, $number, $sem);
                $semMsg = $sem > 1 ? " ({$sem} SEM)" : '';
                flash("Ticket #{$out['number']}{$semMsg} saved for " . inr($out['cost']) . '.', 'ok');
            } catch (InsufficientCoins) {
                flash('Not enough coins in wallet. Coins are allocated directly by the Game Owner/Admin.', 'warn');
            } catch (RuntimeException $e) {
                flash($e->getMessage(), 'bad');
            }

            redirect('/play?draw=' . $drawId);

        case '/action/lottery_fetch':
            $targetDate = trim((string) ($_POST['date'] ?? Clock::now()->format('Y-m-d')));
            $targetTime = trim((string) ($_POST['draw_time'] ?? ''));
            $normDate = LotteryResultNormalizer::normalizeDate($targetDate) ?? Clock::now()->format('Y-m-d');

            if ($targetTime !== '') {
                $normTime = LotteryResultNormalizer::normalizeDrawTime($targetTime) ?? '1 PM';
                $res = LotteryImporter::importDraw($normDate, $normTime, true, 3);
                if ($res['success']) {
                    flash("Live results for {$normDate} ({$normTime}) updated successfully!", 'ok');
                } else {
                    flash("Could not fetch live draw result: " . ($res['error'] ?? 'Result not available yet'), 'warn');
                }
            } else {
                $updatedCount = 0;
                foreach (array_keys(LotteryImporter::DRAW_SCHEDULE) as $slot) {
                    $res = LotteryImporter::importDraw($normDate, $slot, true, 3);
                    if ($res['success']) {
                        $updatedCount++;
                    }
                }
                if ($updatedCount > 0) {
                    flash("Live results for {$normDate} updated successfully ({$updatedCount} draws recorded)!", 'ok');
                } else {
                    flash("Live check for {$normDate} completed. No new draw records found.", 'warn');
                }
            }
            redirect('/results?date=' . urlencode($normDate));

        case '/admin/action/tick':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            $settled = Draws::settleDue();
            Draws::scheduleUpcoming();
            Db::putMeta('last_tick', (string) Clock::now()->getTimestamp());
            flash("Engine tick executed! {$settled} due draws settled and upcoming draws refreshed.", 'ok');
            redirect('/admin?tab=draws');

        case '/admin/action/force_settle':
        case '/admin/action/upload_result':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            $drawId = (int) ($_POST['draw_id'] ?? 0);
            $customResult = isset($_POST['winning_number']) && trim((string) $_POST['winning_number']) !== '' 
                ? trim((string) $_POST['winning_number']) 
                : (isset($_POST['result']) ? trim((string) $_POST['result']) : null);
            try {
                $res = Admin::forceSettle($drawId, $customResult);
                flash("Draw #{$drawId} declared and settled with winning number {$res['result']}! ({$res['winners']} winners, total " . inr($res['payout']) . ' paid)', 'ok');
            } catch (RuntimeException $e) {
                flash($e->getMessage(), 'bad');
            }
            redirect('/admin?tab=draws');

        case '/admin/action/create_draw':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            $day = (string) ($_POST['day'] ?? '');
            $tier = (string) ($_POST['tier'] ?? '');
            $time = (string) ($_POST['draw_time'] ?? '');
            $seed = isset($_POST['seed_jackpot']) && $_POST['seed_jackpot'] !== '' ? (int) $_POST['seed_jackpot'] : null;

            try {
                $newId = Admin::createDraw($day, $tier, $time, $seed);
                flash("New draw #{$newId} scheduled for {$day} ({$tier} at {$time}) with seed " . inr($seed ?? Config::int('jackpot_seed')) . '!', 'ok');
            } catch (RuntimeException $e) {
                flash($e->getMessage(), 'bad');
            }
            redirect('/admin?tab=draws');

        case '/admin/action/adjust_user':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            $targetUid = (int) ($_POST['user_id'] ?? 0);
            $delta = (int) ($_POST['delta'] ?? 0);
            try {
                Admin::adjustCoins($targetUid, $delta, 'refund');
                flash("Adjusted balance for User #{$targetUid} by " . ($delta > 0 ? '+' : '') . inr($delta) . ' coins.', 'ok');
            } catch (RuntimeException $e) {
                flash($e->getMessage(), 'bad');
            }
            redirect('/admin?tab=users');

        case '/admin/action/bulk_coins':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            $amount = (int) ($_POST['amount'] ?? 0);
            try {
                $count = Admin::grantBulkCoins($amount, 'refund');
                flash("Successfully granted " . inr($amount) . " coins to all {$count} registered players!", 'ok');
            } catch (RuntimeException $e) {
                flash($e->getMessage(), 'bad');
            }
            redirect('/admin?tab=users');

        case '/admin/action/save_economics':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            Admin::saveEconomics($_POST);
            flash('Ticket fares and game economics updated successfully!', 'ok');
            redirect('/admin?tab=settings');

        case '/admin/action/reset_streak':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            $targetUid = (int) ($_POST['user_id'] ?? 0);
            Admin::resetStreak($targetUid);
            flash("Reset streak for User #{$targetUid}.", 'ok');
            redirect('/admin?tab=users');

        case '/admin/action/create_user':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            $username = (string) ($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $coins = isset($_POST['initial_coins']) && $_POST['initial_coins'] !== '' ? (int) $_POST['initial_coins'] : null;

            try {
                $created = Admin::createUser($username, $password, $coins);
                $coinStr = $coins !== null ? inr($coins) : inr(Config::int('signup_grant'));
                flash("Player account '{$created['username']}' (ID #{$created['id']}) created successfully with {$coinStr} coins!", 'ok');
            } catch (RuntimeException $e) {
                flash($e->getMessage(), 'bad');
            }
            redirect('/admin?tab=users');

        case '/admin/action/delete_user':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            $targetUid = (int) ($_POST['user_id'] ?? 0);
            Admin::deleteUser($targetUid);
            flash("Player account #{$targetUid} deleted successfully.", 'ok');
            redirect('/admin?tab=users');

        case '/admin/action/lottery_fetch':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            $drawDate = trim((string) ($_POST['date'] ?? Clock::now()->format('Y-m-d')));
            $drawTime = trim((string) ($_POST['draw_time'] ?? '1 PM'));
            $res = LotteryImporter::importDraw($drawDate, $drawTime, true, 30);
            if ($res['success']) {
                flash("Successfully imported {$drawTime} result for {$drawDate} via {$res['source']}! ({$res['count']} prize numbers)", 'ok');
            } else {
                flash("Lottery Sambad import failed: " . ($res['error'] ?? 'Unknown error'), 'bad');
            }
            redirect('/admin?tab=lottery');

        case '/admin/action/lottery_save':
            if (!Admin::isAdmin()) {
                needs_admin();
            }
            $drawDate = trim((string) ($_POST['date'] ?? Clock::now()->format('Y-m-d')));
            $drawTime = trim((string) ($_POST['draw_time'] ?? '1 PM'));
            $prizes = [
                '1' => trim((string) ($_POST['prize_1'] ?? '')),
                '2' => trim((string) ($_POST['prize_2'] ?? '')),
                '3' => trim((string) ($_POST['prize_3'] ?? '')),
                '4' => trim((string) ($_POST['prize_4'] ?? '')),
                '5' => trim((string) ($_POST['prize_5'] ?? '')),
            ];
            $saveRes = LotteryImporter::manualSave($drawDate, $drawTime, $prizes);
            if ($saveRes['success']) {
                flash("Manual Lottery Sambad result saved for {$drawDate} ({$drawTime})! ({$saveRes['count']} numbers recorded)", 'ok');
            } else {
                flash("Failed to save result: " . ($saveRes['error'] ?? 'Validation error'), 'bad');
            }
            redirect('/admin?tab=lottery');
    }

    http_response_code(404);
    exit('Unknown action');
}

// Admin user isolation: Admin only accesses admin dashboard, never player views
if (Admin::isAdmin() && !str_starts_with($path, '/admin') && !str_starts_with($path, '/admin/')) {
    redirect('/admin');
}

$board = Draws::board();

switch ($path) {
    case '/robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\n";
        exit;

    case '/':
        $viewer = Auth::userId();
        View::render('home', [
            'board' => $board,
            'stats' => Draws::digitStats(),
            'recent' => Draws::recent(6),
            'boxes' => $viewer === null ? 0 : Boxes::waitingCount($viewer),
        ], 'Home');
        break;

    case '/play':
        if (Auth::userId() === null) {
            needs_login();
        }

        $drawId = (int) ($_GET['draw'] ?? ($board[0]['id'] ?? 0));
        $st = Db::conn()->prepare('SELECT * FROM draws WHERE id = ?');
        $st->execute([$drawId]);
        $draw = $st->fetch();

        if ($draw === false || $draw['result'] !== null) {
            redirect('/');
        }

        View::render('play', [
            'draw' => $draw,
            'jackpot' => Draws::jackpotOf($draw),
            'mine' => Tickets::mine(uid(), 40),
            'held' => Tickets::countFor(uid(), $drawId),
            'cap' => Config::int('tickets_per_draw'),
        ], 'Play');
        break;

    case '/tickets':
        if (Auth::userId() === null) {
            needs_login();
        }

        View::render('tickets', [
            'tickets' => Tickets::mine(uid(), 60),
            'summary' => Tickets::summary(uid()),
        ], 'My Tickets');
        break;

    case '/boxes':
        if (Auth::userId() === null) {
            needs_login();
        }

        View::render('boxes', [
            'waiting' => Boxes::waiting(uid(), 20),
            'history' => Boxes::history(uid(), 20),
            'odds' => Boxes::odds(),
            'ev' => Boxes::expectedValue(),
        ], 'Surprise Boxes');
        break;

    case '/results':
        $now = Clock::now();
        $today = $now->format('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
        
        // Before 1:00 PM (13:00) in local lottery time, today's draws are not yet published.
        // Default to yesterday's completed results unless a specific date is requested.
        $isBeforeFirstDraw = (int) $now->format('G') < 13;
        $defaultDate = $isBeforeFirstDraw ? $yesterday : $today;

        $hasExplicitDate = isset($_GET['date']) && trim((string) $_GET['date']) !== '';
        $rawDate = $hasExplicitDate ? trim((string) $_GET['date']) : $defaultDate;
        $selectedDate = LotteryResultNormalizer::normalizeDate($rawDate) ?? $defaultDate;
        if ($selectedDate > $today) {
            $selectedDate = $today;
        }

        View::render('results', [
            'todayDate' => $today,
            'yesterdayDate' => $yesterday,
            'selectedDate' => $selectedDate,
            'isBeforeFirstDraw' => $isBeforeFirstDraw,
            'hasExplicitDate' => $hasExplicitDate,
            'daySummary' => LotteryImporter::getDaySummary($selectedDate),
            'recentDates' => LotteryImporter::getAvailableDates(15),
            'sambadRecent' => LotteryImporter::getRecentSummary(7),
        ], 'Results');
        break;

    case '/account':
        if (Auth::userId() === null) {
            needs_login();
        }

        View::render('account', [
            'summary' => Tickets::summary(uid()),
            'ledger' => Coins::history(uid(), 25),
            'earned' => Coins::earnedFree(uid()),
        ], 'Account');
        break;

    case '/login':
        if (Admin::isAdmin()) {
            redirect('/admin');
        }
        if (Auth::userId() !== null) {
            redirect('/');
        }
        View::render('auth', ['mode' => 'login'], 'Sign in');
        break;

    case '/register':
        if (Admin::isAdmin()) {
            redirect('/admin');
        }
        if (Auth::userId() !== null) {
            redirect('/');
        }
        View::render('auth', ['mode' => 'register'], 'Create account');
        break;

    case '/fair':
        View::render('fair', ['recent' => Draws::recent(Config::int('history_depth'))], 'Provably fair');
        break;

    case '/admin/login':
        if (Admin::isAdmin()) {
            redirect('/admin');
        }
        redirect('/login');
        break;

    case '/admin':
        if (!Admin::isAdmin()) {
            needs_admin();
        }

        $tab = (string) ($_GET['tab'] ?? 'overview');
        $search = (string) ($_GET['q'] ?? '');
        $filterDraw = isset($_GET['draw_id']) && $_GET['draw_id'] !== '' ? (int) $_GET['draw_id'] : null;
        $filterUser = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int) $_GET['user_id'] : null;

        View::render('admin', [
            'tab' => $tab,
            'stats' => Admin::stats(),
            'draws' => Admin::draws(100),
            'users' => Admin::users(100, $search),
            'tickets' => Admin::tickets(100, $filterDraw, $filterUser),
            'wins' => Admin::wins(100),
            'ledger' => Admin::ledger(100, $filterUser),
            'economics' => Admin::getEconomics(),
            'search' => $search,
            'filterDraw' => $filterDraw,
            'filterUser' => $filterUser,
            'lotteryRecent' => LotteryImporter::getRecentSummary(14),
            'lotteryLogs' => LotteryImporter::getLogs(30),
        ], 'Admin Console');
        break;

    default:
        http_response_code(404);
        View::render('missing', [], 'Not found');
}
