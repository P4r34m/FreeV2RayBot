<?php

namespace App\Support;

/**
 * Canonical keys for the `settings` table, with defaults. Reference these
 * constants instead of magic strings.
 */
final class SettingKey
{
    public const BOT_USERNAME = 'bot_username';

    public const CHANNEL_LOCK_ENABLED = 'channel_lock_enabled';

    public const REFERRAL_ENABLED = 'referral_enabled';

    // When a referral counts toward rewards: 'start' (on join) or 'first_config'.
    public const REFERRAL_QUALIFY_EVENT = 'referral_qualify_event';

    public const DEFAULT_PLAN_ID = 'default_plan_id';

    public const SUPPORT_USERNAME = 'support_username';

    public const WELCOME_MESSAGE = 'welcome_message';

    public const REFERRAL_INFO_TEXT = 'referral_info_text';

    public const MAINTENANCE_MODE = 'maintenance_mode';

    // Master on/off for the whole bot (non-admins get bot.disabled).
    public const BOT_ENABLED = 'bot_enabled';

    // How configs are delivered: 'sub' (subscription link) or 'configs' (individual links).
    public const DELIVERY_MODE = 'delivery_mode';

    // Forum-group reporting.
    public const REPORTS_ENABLED = 'reports_enabled';

    public const REPORTS_GROUP_ID = 'reports_group_id';

    // Anti-spam / flood control.
    public const ANTISPAM_ENABLED = 'antispam_enabled';

    public const ANTISPAM_MAX_ACTIONS = 'antispam_max_actions';

    public const ANTISPAM_WINDOW_SECONDS = 'antispam_window_seconds';

    public const ANTISPAM_BLOCK_MINUTES = 'antispam_block_minutes';

    // Web (Filament) panel: custom URL path + on/off, both live-editable.
    public const ADMIN_PATH = 'admin_path';

    public const WEB_PANEL_ENABLED = 'web_panel_enabled';

    /** @return array<string, mixed> default values seeded on install */
    public static function defaults(): array
    {
        return [
            self::BOT_USERNAME => env('TELEGRAM_BOT_USERNAME', ''),
            self::CHANNEL_LOCK_ENABLED => false,
            self::REFERRAL_ENABLED => true,
            self::REFERRAL_QUALIFY_EVENT => 'first_config',
            self::DEFAULT_PLAN_ID => null,
            self::SUPPORT_USERNAME => '',
            self::WELCOME_MESSAGE => "به ربات کانفیگ رایگان خوش آمدید 🌐\nبرای دریافت کانفیگ از دکمه‌های زیر استفاده کنید.",
            self::REFERRAL_INFO_TEXT => 'با دعوت دوستان خود، حجم و زمان بیشتری هدیه بگیرید!',
            self::MAINTENANCE_MODE => false,
            self::BOT_ENABLED => true,
            self::DELIVERY_MODE => 'sub',
            self::REPORTS_ENABLED => false,
            self::REPORTS_GROUP_ID => '',
            self::ANTISPAM_ENABLED => true,
            self::ANTISPAM_MAX_ACTIONS => 20,
            self::ANTISPAM_WINDOW_SECONDS => 60,
            self::ANTISPAM_BLOCK_MINUTES => 10,
            self::ADMIN_PATH => env('FILAMENT_PATH', 'admin'),
            self::WEB_PANEL_ENABLED => true,
        ];
    }
}
