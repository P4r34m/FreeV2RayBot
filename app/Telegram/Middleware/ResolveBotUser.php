<?php

namespace App\Telegram\Middleware;

use App\Services\BotUserService;
use App\Telegram\Content;
use SergiX44\Nutgram\Nutgram;

/**
 * Global middleware: provisions/updates the BotUser for every update and
 * exposes it to handlers via $bot->get('botUser'). Stops blocked users.
 */
class ResolveBotUser
{
    public function __construct(private readonly BotUserService $users) {}

    public function __invoke(Nutgram $bot, $next): void
    {
        if ($bot->userId() === null) {
            $next($bot);

            return;
        }

        $user = $this->users->resolve($bot);

        if ($user->is_blocked) {
            $bot->sendMessage(Content::text('blocked.permanent'));

            return;
        }

        $bot->set('botUser', $user);

        $next($bot);
    }
}
