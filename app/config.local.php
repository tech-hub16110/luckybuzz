<?php

declare(strict_types=1);

if (!defined('LUCKY_BUZZ_APP')) {
    http_response_code(403);
    exit('forbidden');
}

return [
    'lottery_import' => [
        'enabled' => true,
        'sambad' => [
            'base_url' => 'https://api.sambad.com',
            'access_token' => 'Mq8QIQHA7NpmbLNmof1dISUgFlAYqRer',
            'timeout' => 20,
        ],
    ],
];
