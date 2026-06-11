<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram bot
    |--------------------------------------------------------------------------
    */
    'bot' => [
        'username' => env('TELEGRAM_BOT_USERNAME', ''),
        // Telegram numeric ids that get admin access in the bot AND seed the
        // first web admin. Comma-separated in env.
        'admin_ids' => array_filter(array_map(
            'trim',
            explode(',', (string) env('TELEGRAM_ADMIN_IDS', ''))
        )),
        // Secret path segment for the webhook route: /webhook/{secret}
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', 'change-me'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Config issuance defaults
    |--------------------------------------------------------------------------
    | Used when no plan is configured yet. Admin overrides these via Plans.
    */
    'issuance' => [
        'default_data_gb' => (float) env('DEFAULT_DATA_GB', 10),
        'default_duration_days' => (int) env('DEFAULT_DURATION_DAYS', 30),
        // Prefix for remote identifiers created on panels.
        'identifier_prefix' => env('CONFIG_IDENTIFIER_PREFIX', 'fv'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits / safety
    |--------------------------------------------------------------------------
    */
    'limits' => [
        // Max active configs a single user may hold at once.
        'max_active_configs_per_user' => (int) env('MAX_ACTIVE_CONFIGS_PER_USER', 1),
        // Block self-referral and reward only genuinely new users.
        'prevent_self_referral' => true,
    ],
];
