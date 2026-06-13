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

/** Renew the user's free config — only once it has expired (callback: config:renew). */
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
        $config = $user->configs()
            ->where('status', ConfigStatus::Active->value)
            ->where('source', Config::SOURCE_FREE)
            ->latest()
            ->first();

        if (! $config) {
            Reply::screen(
                $bot,
                Content::text('config.none_to_renew'),
                Keyboards::configMenu(false),
            );

            return;
        }

        // The free config can only be renewed after its time has run out.
        if (! $config->isExpired()) {
            Reply::screen(
                $bot,
                Content::text('config.renew_not_expired'),
                Keyboards::configMenu(true),
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
