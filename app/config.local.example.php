<?php

declare(strict_types=1);

/**
 * Copy this file to config.local.php in the same folder and fill in real values.
 * config.local.php is git-ignored, so credentials never enter the repository.
 *
 * Any key that exists in settings.php can be overridden from here — it is merged
 * over the defaults at load time.
 *
 * Two naming rules, both learned the hard way on this device:
 *   - Do not call it config.php. The mount is case-insensitive, so config.php
 *     and the class file Config.php would be the same file.
 *   - Keep the guard below as the first statement. This folder now lives inside
 *     the web root, and the guard is what stops the password being handed out if
 *     the server ever ignores .htaccess.
 */

if (!defined('LUCKY_BUZZ_APP')) {
    http_response_code(403);
    exit('forbidden');
}

return [
    'db' => [
        // InfinityFree: MySQL only. The SQLite PDO driver is not something you
        // can rely on there, and there is no durable local file store to point
        // one at anyway. Keep 'sqlite' for development on your own machine.
        'driver' => 'mysql',

        // Control Panel > MySQL Databases. The host is NOT "localhost" on
        // InfinityFree — copy the "MySQL Host" value exactly; it looks like
        // sql3xx.infinityfree.com
        'host' => 'sql000.infinityfree.com',
        'port' => 3306,

        // Both usually start with your account name, e.g. if0_12345678
        'database' => 'if0_00000000_buzz',
        'username' => 'if0_00000000',
        'password' => 'change-me',

        'charset' => 'utf8mb4',
    ],

    // To develop against SQLite instead, leave this file out entirely, or:
    // 'db' => ['driver' => 'sqlite', 'sqlite_path' => __DIR__ . '/../../data/dev.sqlite'],

    // Lottery Sambad API & AI Vision Extraction credentials (PRIVATE - KEEP SAFE)
    'lottery_import' => [
        'enabled' => true,
        'sambad' => [
            'base_url' => 'https://api.sambad.com',
            'access_token' => 'YOUR_SAMBAD_ACCESS_TOKEN_HERE',
            'timeout' => 20,
        ],
        'ai' => [
            'enabled' => true,
            'base_url' => 'https://router.bynara.id/v1',
            'api_key' => 'YOUR_BYNARA_ROUTER_API_KEY_HERE',
            'model' => 'nex-n2.5-pro',
            'timeout' => 60,
        ],
    ],
];
