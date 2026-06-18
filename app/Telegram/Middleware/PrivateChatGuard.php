<?php

namespace App\Telegram\Middleware;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatType;

/**
 * Restricts the user/admin handler group to private chats. The bot must never
 * react inside channels or arbitrary groups — it only talks to users in private.
 * (Reports to the admin group are sent outbound, not processed here, so they are
 * unaffected.)
 */
class PrivateChatGuard
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $type = $bot->chat()?->type;

        if ($type !== null && $type !== ChatType::PRIVATE && $type !== ChatType::SENDER) {
            return; // ignore groups and channels entirely
        }

        $next($bot);
    }
}
