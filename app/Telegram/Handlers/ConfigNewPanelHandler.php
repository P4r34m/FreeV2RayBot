<?php

namespace App\Telegram\Handlers;

use App\Models\BotUser;
use App\Services\PanelSelector;
use App\Telegram\ChannelGate;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** User picked a specific server for a new config (callback: config:new:{id}). */
class ConfigNewPanelHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        if (! ChannelGate::enforce($bot)) {
            return;
        }

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        // A user who already has a free config (active or expired) can't make a new
        // one — only renew. Guards a stale picker tap from slipping through.
        if ($user->freeConfig() !== null) {
            Reply::screen($bot, Content::text('config.free_not_expired'), Keyboards::configMenu(true));

            return;
        }

        $panelId = (int) $id;

        if (! app(PanelSelector::class)->available()->contains('id', $panelId)) {
            Reply::screen($bot, '⚠️ این سرور در دسترس نیست. لطفاً دوباره انتخاب کنید.', Keyboards::single('common.back', Keyboards::CB_GET_CONFIG));

            return;
        }

        IssueNewHandler::dispatch($bot, $user, $panelId);
    }
}
