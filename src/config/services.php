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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'admin' => [
        'password' => env('ADMIN_PASSWORD'),
        '2fa_enabled' => filter_var(env('ADMIN_2FA_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        '2fa_secret' => env('ADMIN_2FA_SECRET'),
        'invite_expiry_hours' => (int) env('INVITE_EXPIRY_HOURS', 24),
    ],

    'bluesky' => [
        'demo_handle' => env('BLUESKY_DEMO_HANDLE', 'sklog.nono150.com'),
    ],

];
