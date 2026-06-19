<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Toggle a boolean setting then re-render the admin menu (callback: admin:toggle:{key}). */
class AdminToggleHandler
{
    /** Settings the admin may flip from inside the bot. */
    private const ALLOWED = [
        SettingKey::BOT_ENABLED,
        SettingKey::CHANNEL_LOCK_ENABLED,
        SettingKey::REFERRAL_ENABLED,
        SettingKey::ANTISPAM_ENABLED,
        SettingKey::REPORTS_ENABLED,
        SettingKey::MAINTENANCE_MODE,
        SettingKey::WEB_PANEL_ENABLED,
        SettingKey::COIN_EXTEND_ENABLED,
    ];

    public function __invoke(Nutgram $bot, string $key): void
    {
        if (! in_array($key, self::ALLOWED, true)) {
            Reply::toast($bot, 'کلید نامعتبر', alert: true);

            return;
        }

        $new = ! Setting::bool($key);
        Setting::put($key, $new);

        Reply::toast($bot, $new ? '✅ روشن شد' : '⏹ خاموش شد');

        // Re-render the settings submenu with refreshed states.
        (new AdminSettingsHandler)($bot);
    }
}
