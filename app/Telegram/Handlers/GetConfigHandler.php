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
        $hasActive = $user->configs()->where('status', ConfigStatus::Active->value)->exists();

        $text = $hasActive
            ? Content::text('config.menu_active')
            : Content::text('config.menu_inactive');

        Reply::screen($bot, $text, Keyboards::configMenu($hasActive));
    }
}
