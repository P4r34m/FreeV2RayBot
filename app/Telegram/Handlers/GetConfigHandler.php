<?php

namespace App\Telegram\Handlers;

use App\Models\BotUser;
use App\Telegram\ChannelGate;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** "دریافت کانفیگ" — lets the user choose new vs renew (callback: get_config). */
class GetConfigHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        if (! ChannelGate::enforce($bot)) {
            return;
        }

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        // Already has a free config (active or expired)? Then only renew/status —
        // no brand-new free config. First-timers go straight to the server picker.
        if ($user->freeConfig() !== null) {
            Reply::screen($bot, Content::text('config.menu_active'), Keyboards::configMenu(true));

            return;
        }

        IssueNewHandler::start($bot, $user);
    }
}
