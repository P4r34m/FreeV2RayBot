<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Toggle referral payout mode reward<->coin (callback: admin:refmode). */
class AdminReferralModeHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $coin = Setting::string(SettingKey::REFERRAL_MODE, 'reward') === 'coin';
        Setting::put(SettingKey::REFERRAL_MODE, $coin ? 'reward' : 'coin');

        Reply::toast($bot, $coin ? '🎁 حالت پاداش' : '🪙 حالت سکه‌ای');

        (new AdminSettingsHandler)($bot);
    }
}
