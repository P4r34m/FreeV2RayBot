<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Jobs\IssueConfigJob;
use App\Models\BotUser;
use App\Models\Config;
use App\Telegram\ChannelGate;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** "دریافت کانفیگ" — new / renew / seamless rebuild (callback: get_config). */
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

        // The user's most recent free config, whatever state it's in.
        $config = $user->configs()
            ->where('source', Config::SOURCE_FREE)
            ->latest()
            ->first();

        // True first-timer → server picker.
        if (! $config) {
            IssueNewHandler::start($bot, $user);

            return;
        }

        // A genuinely working config (active & not expired) → renew/status menu only;
        // a single free config that can't be re-issued early.
        if ($config->status === ConfigStatus::Active && ! $config->isExpired()) {
            Reply::screen($bot, Content::text('config.menu_active'), Keyboards::configMenu(true));

            return;
        }

        // A Failed row never finished issuing (no usable account/identifier) → issue a
        // fresh one instead of trying to renew a dead record.
        if ($config->status === ConfigStatus::Failed) {
            IssueNewHandler::start($bot, $user);

            return;
        }

        // Expired / deleted / disabled → rebuild it on the spot (renew the SAME record;
        // the driver recreates it if it's gone from the panel).
        Reply::screen($bot, Content::text('config.creating'), Keyboards::backMenu());
        IssueConfigJob::dispatch($user->telegram_id, (int) $bot->chatId(), 'renew', $config->id);
    }
}
