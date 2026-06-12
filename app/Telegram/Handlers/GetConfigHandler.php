<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
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

        // With an active config, offer the new/renew/status menu. With none, skip
        // straight to choosing a server — the user never has to pick a plan.
        if ($user->configs()->where('status', ConfigStatus::Active->value)->exists()) {
            Reply::screen($bot, Content::text('config.menu_active'), Keyboards::configMenu(true));

            return;
        }

        IssueNewHandler::start($bot, $user);
    }
}
