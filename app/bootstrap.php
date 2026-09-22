<?php

declare(strict_types=1);

/**
 * Proves this file is being pulled in by the running application rather than
 * fetched directly over HTTP. config.local.php checks it before handing back
 * the database password, which is the only protection that still works if the
 * server ignores .htaccess entirely.
 */
define('LUCKY_BUZZ_APP', true);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Clock.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Coins.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Bonuses.php';
require_once __DIR__ . '/Draws.php';
require_once __DIR__ . '/Tickets.php';
require_once __DIR__ . '/Boxes.php';
require_once __DIR__ . '/Icon.php';
require_once __DIR__ . '/View.php';
require_once __DIR__ . '/Admin.php';
require_once __DIR__ . '/LotteryResultNormalizer.php';
require_once __DIR__ . '/LotterySambadClient.php';
require_once __DIR__ . '/LotteryVisionExtractor.php';
require_once __DIR__ . '/LotteryImporter.php';

Config::load(__DIR__ . '/settings.php');
date_default_timezone_set(Config::get('timezone'));

/**
 * The engine tick is lazy: there is no cron on InfinityFree's free plan and a
 * phone has none either, so the site may go hours between hits. A 20-second
 * floor keeps a busy page from re-running the engine while still settling draws
 * the moment someone looks at the board.
 *
 * The stamp lives in a database row, not a lock file — this project's working
 * copy is on a fuseblk mount where file_put_contents(LOCK_EX) emits a warning
 * and writes zero bytes, which made every single request re-run the engine.
 */
function engine_tick(int $min_interval = 20): void
{
    $now = Clock::now()->getTimestamp();
    $last = Db::getMeta('last_tick');

    if ($last !== 0 && $now - $last < $min_interval) {
        return;
    }

    Db::putMeta('last_tick', (string) $now);

    Draws::tick();
    LotteryImporter::tick();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function coins(int $amount): string
{
    return number_format($amount);
}

function inr(int|float $amount, bool $decimals = false): string
{
    return '₹' . number_format((float) $amount, $decimals ? 2 : 0);
}

function icon(string $name, string $class = '', int $size = 20): string
{
    return Icon::svg($name, $class, $size);
}

function flash(?string $message = null, ?string $tone = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'tone' => $tone];

        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}
