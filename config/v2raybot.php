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
    | Web (Filament) admin panel
    |--------------------------------------------------------------------------
    | Deploy-time defaults. The DB settings `admin_path` / `web_panel_enabled`
    | (editable from the bot) override these and apply live (no route cache in
    | the web container), so the admin can change the path / disable the panel
    | without a rebuild.
    */
    'panel' => [
        'path' => env('FILAMENT_PATH', 'admin'),
        'enabled' => filter_var(env('WEB_PANEL_ENABLED', true), FILTER_VALIDATE_BOOL),
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
