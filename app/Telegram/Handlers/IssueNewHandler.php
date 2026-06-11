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

/** Issue a brand-new config (callback: config:new). */
class IssueNewHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        if (! ChannelGate::enforce($bot)) {
            return;
        }

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        $activeCount = $user->configs()->where('status', ConfigStatus::Active->value)->count();
        $max = (int) config('v2raybot.limits.max_active_configs_per_user', 1);

        if ($activeCount >= $max) {
            Reply::screen(
                $bot,
                Content::text('config.max_reached', ['max' => $max]),
                Keyboards::configMenu(true),
            );

            return;
        }

        Reply::screen(
            $bot,
            Content::text('config.creating'),
            Keyboards::backMenu(),
        );

        IssueConfigJob::dispatch($user->telegram_id, (int) $bot->chatId(), 'new');
    }
}
