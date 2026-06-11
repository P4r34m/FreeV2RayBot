<?php

namespace App\Telegram\Middleware;

use App\Models\BotUser;
use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Content;
use SergiX44\Nutgram\Nutgram;

/** Blocks non-admins while maintenance mode is on. */
class MaintenanceGuard
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $user = $bot->get('botUser');

        if (Setting::bool(SettingKey::MAINTENANCE_MODE) && ! ($user instanceof BotUser && $user->is_admin)) {
            $bot->sendMessage(Content::text('bot.maintenance'));

            return;
        }

        $next($bot);
    }
}
