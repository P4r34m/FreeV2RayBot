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

        // With an active FREE config, offer the new/renew/status menu. With none,
        // skip straight to choosing a server for the free config.
        $hasActiveFree = $user->configs()
            ->where('status', ConfigStatus::Active->value)
            ->where('source', \App\Models\Config::SOURCE_FREE)
            ->exists();

        if ($hasActiveFree) {
            Reply::screen($bot, Content::text('config.menu_active'), Keyboards::configMenu(true));

            return;
        }

        IssueNewHandler::start($bot, $user);
    }
}
