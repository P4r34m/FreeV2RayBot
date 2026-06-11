<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Turn the whole bot on/off from the main admin menu (callback: admin:botpower). */
class AdminBotPowerHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $new = ! Setting::bool(SettingKey::BOT_ENABLED, true);
        Setting::put(SettingKey::BOT_ENABLED, $new);

        Reply::toast($bot, $new ? '🟢 ربات روشن شد' : '🔴 ربات خاموش شد', alert: true);

        // Re-render the admin menu so the new state shows immediately.
        (new AdminMenuHandler)($bot);
    }
}
