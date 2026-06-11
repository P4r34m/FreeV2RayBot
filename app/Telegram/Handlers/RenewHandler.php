<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Jobs\IssueConfigJob;
use App\Models\BotUser;
use App\Telegram\ChannelGate;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Renew/extend the user's active config (callback: config:renew). */
class RenewHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        if (! ChannelGate::enforce($bot)) {
            return;
        }

        /** @var BotUser $user */
        $user = $bot->get('botUser');
        $config = $user->configs()->where('status', ConfigStatus::Active->value)->latest()->first();

        if (! $config) {
            Reply::screen(
                $bot,
                Content::text('config.none_to_renew'),
                Keyboards::configMenu(false),
            );

            return;
        }

        Reply::screen(
            $bot,
            Content::text('config.renewing'),
            Keyboards::backMenu(),
        );

        IssueConfigJob::dispatch($user->telegram_id, (int) $bot->chatId(), 'renew', $config->id);
    }
}
