<?php

namespace App\Telegram\Middleware;

use App\Models\BotUser;
use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Content;
use SergiX44\Nutgram\Nutgram;

/** Master on/off switch: when the bot is disabled, only admins may use it. */
class BotEnabledGuard
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $user = $bot->get('botUser');
        $isAdmin = $user instanceof BotUser && $user->is_admin;

        if (! $isAdmin && ! Setting::bool(SettingKey::BOT_ENABLED, true)) {
            $bot->sendMessage(Content::text('bot.disabled'));

            return;
        }

        $next($bot);
    }
}
