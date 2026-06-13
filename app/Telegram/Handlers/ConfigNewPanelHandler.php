<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
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

        $activeFree = $user->configs()
            ->where('status', ConfigStatus::Active->value)
            ->where('source', \App\Models\Config::SOURCE_FREE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
        if ($activeFree >= $user->maxConfigs()) {
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
