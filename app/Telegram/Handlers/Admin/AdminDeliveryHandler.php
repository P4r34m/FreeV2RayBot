<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Toggle config delivery mode: sub link <-> individual configs (callback: admin:delivery). */
class AdminDeliveryHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $new = Setting::string(SettingKey::DELIVERY_MODE, 'sub') === 'configs' ? 'sub' : 'configs';
        Setting::put(SettingKey::DELIVERY_MODE, $new);

        Reply::toast($bot, $new === 'configs' ? 'نحوه تحویل: کانفیگ تکی' : 'نحوه تحویل: لینک ساب');

        (new AdminSettingsHandler)($bot);
    }
}
