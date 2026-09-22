<?php

declare(strict_types=1);

/**
 * Single source of truth for game economics.
 *
 * Every currency value in this file is in Buzz Coins. Buzz Coins are virtual:
 * there is deliberately no price, no payment provider, no deposit route and no
 * redemption route anywhere in this codebase, and Coins::REASONS is the guard
 * that keeps it that way.
 */
return [
    'app_name' => 'Lucky Buzz',
    'tagline' => 'Free numbers game — coins only, no cash, ever',
    'timezone' => 'Asia/Kolkata',

    'session_name' => 'luckybuzz_sid',

    // Development default. On InfinityFree this whole block is replaced by
    // config.local.php — see config.local.example.php.
    //
    // dirname(__DIR__, 2) deliberately escapes htdocs/: a SQLite file inside the
    // web root is a downloadable database the moment a rewrite rule misfires,
    // and InfinityFree uses MySQL anyway, so nothing here should ever write into
    // the uploaded tree.
    'db' => [
        'driver' => 'sqlite',
        'sqlite_path' => dirname(__DIR__, 2) . '/data/luckybuzz.sqlite',
    ],

    // Draw tiers, mirroring a thrice-daily numbers game.
    'tiers' => [
        'morning' => ['label' => 'Morning', 'time' => '13:00', 'accent' => 'green'],
        'day' => ['label' => 'Day', 'time' => '18:00', 'accent' => 'amber'],
        'evening' => ['label' => 'Evening', 'time' => '20:00', 'accent' => 'blue'],
    ],

    'ticket_cost' => 10,
    'tickets_per_draw' => 5,

    // The headline number on every draw card. A straight hit wins the whole pot,
    // which opens here and grows by jackpot_take per ticket sold; an unclaimed
    // pot rolls into the same tier's next draw. This is a coin total — nothing in
    // this game converts between coins and money in either direction.
    'jackpot_seed' => 25000,
    'jackpot_take' => 1,

    // Fixed tiers. The straight is deliberately absent: it pays the pot above
    // rather than a flat amount, so there is exactly one number to change.
    'prizes' => [
        'box' => 300,
        'back3' => 600,
        'back2' => 100,
        'back1' => 12,
        'anydigit' => 2,
    ],

    // The surprise box: one per player per draw they entered, opened once that
    // draw settles. Contents come from the draw's published commitment, so they
    // are fixed before anyone taps. Weights are out of 1000 and the odds are
    // printed on the box screen — a hidden payout table would be the part worth
    // objecting to. Boxes are granted, never sold, at any coin price.
    //
    // Named surprise_box, not box: prizes.box already means the "same four
    // digits in any order" tier, and one word should not carry both.
    'surprise_box' => [
        'rewards' => [
            ['coins' => 1, 'weight' => 500],
            ['coins' => 2, 'weight' => 250],
            ['coins' => 5, 'weight' => 150],
            ['coins' => 10, 'weight' => 60],
            ['coins' => 25, 'weight' => 30],
            ['coins' => 100, 'weight' => 9],
            ['coins' => 500, 'weight' => 1],
        ],
    ],

    'signup_grant' => 200,
    'daily_bonus' => 60,
    'streak_bonus' => 15,
    'streak_bonus_max' => 90,

    'refill_amount' => 15,
    'refill_floor' => 20,
    'refill_cooldown' => 3600,
    'refill_daily_cap' => 120,

    'history_depth' => 30,
    'hot_cold_window' => 60,

    // Default admin credentials (can be overridden in config.local.php)
    'admin' => [
        'username' => 'admin',
        'password' => 'admin123',
    ],

    // Automatic Lottery Sambad result fetching & AI Vision Extraction
    'lottery_import' => [
        'enabled' => true,
        'timezone' => 'Asia/Kolkata',
        'retry_interval' => 300,
        'max_attempts' => 4,
        'sambad' => [
            'base_url' => 'https://api.sambad.com',
            'access_token' => '',
            'timeout' => 20,
            'max_attempts' => 4,
            'retry_interval' => 300,
        ],
        'ai' => [
            'enabled' => true,
            'base_url' => 'https://router.bynara.id/v1',
            'api_key' => '',
            'model' => 'nex-n2.5-pro',
            'timeout' => 60,
        ],
    ],
];
