<?php

namespace App\Telegram\Middleware;

use App\Models\BotUser;
use App\Telegram\Content;
use SergiX44\Nutgram\Nutgram;

/** Gate admin-only handlers. */
class EnsureAdmin
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $user = $bot->get('botUser');

        if (! $user instanceof BotUser || ! $user->is_admin) {
            if ($bot->isCallbackQuery()) {
                $bot->answerCallbackQuery(text: Content::text('common.access_denied'), show_alert: true);
            } else {
                $bot->sendMessage(Content::text('common.access_denied'));
            }

            return;
        }

        $next($bot);
    }
}
