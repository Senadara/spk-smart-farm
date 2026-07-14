<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'node_notifications' => [
        'base_url' => env('NODE_NOTIFICATION_BASE_URL', env('API_BASE_URL', 'http://node-api:4000/api')),
        'internal_token' => env('SPK_INTERNAL_NOTIFICATION_TOKEN'),
        'timeout' => env('NODE_NOTIFICATION_TIMEOUT', 10),
    ],

    'spk_notifications' => [
        'enabled' => env('SPK_NOTIFICATION_ENABLED', true),
        'cooldown_minutes' => env('SPK_NOTIFICATION_COOLDOWN_MINUTES', 30),
    ],

];
