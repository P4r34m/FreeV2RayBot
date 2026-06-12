<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Toggle the main-menu button style: inline (glass) <-> reply (keyboard). (admin:kbmode) */
class AdminKeyboardModeHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $new = Setting::string(SettingKey::KEYBOARD_MODE, 'inline') === 'reply' ? 'inline' : 'reply';
        Setting::put(SettingKey::KEYBOARD_MODE, $new);

        Reply::toast($bot, $new === 'reply' ? 'دکمه‌ها: کیبوردی ⌨️' : 'دکمه‌ها: شیشه‌ای 🔘');

        (new AdminSettingsHandler)($bot);
    }
}
