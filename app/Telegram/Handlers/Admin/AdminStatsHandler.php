<?php

namespace App\Telegram\Handlers\Admin;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Models\Config;
use App\Models\Panel;
use App\Models\Referral;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Quick stats overview (callback: admin:stats). */
class AdminStatsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $today = now()->startOfDay();

        $text = implode("\n", [
            '📊 <b>آمار ربات</b>',
            '',
            '👤 کل کاربران: <b>'.BotUser::count().'</b>',
            '🆕 کاربران امروز: <b>'.BotUser::where('created_at', '>=', $today)->count().'</b>',
            '🚫 مسدودشده: <b>'.BotUser::where('is_blocked', true)->count().'</b>',
            '',
            '🔑 کل کانفیگ‌ها: <b>'.Config::count().'</b>',
            '🟢 کانفیگ فعال: <b>'.Config::where('status', ConfigStatus::Active->value)->count().'</b>',
            '📦 کانفیگ امروز: <b>'.Config::where('created_at', '>=', $today)->count().'</b>',
            '',
            '👥 رفرال تأییدشده: <b>'.Referral::where('status', Referral::STATUS_VERIFIED)->count().'</b>',
            '',
            '🖥 پنل‌ها: <b>'.Panel::count().'</b> (فعال: '.Panel::where('is_active', true)->count().')',
        ]);

        Reply::screen($bot, $text, Keyboards::single('common.back', Keyboards::CB_ADMIN));
    }
}
