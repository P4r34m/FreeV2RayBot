<?php

namespace App\Telegram\Handlers;

use App\Services\ChannelLockService;
use App\Telegram\Content;
use App\Telegram\Reply;
use App\Telegram\Screens;
use SergiX44\Nutgram\Nutgram;

/** Re-check force-join membership after the user taps "عضو شدم" (callback: check_join). */
class CheckJoinHandler
{
    public function __construct(private readonly ChannelLockService $lock) {}

    public function __invoke(Nutgram $bot): void
    {
        $missing = $this->lock->missingChannels($bot, (int) $bot->userId());

        if ($missing->isNotEmpty()) {
            Reply::toast($bot, Content::text('channel.join_not_yet'), alert: true);

            return;
        }

        Reply::toast($bot, Content::text('channel.join_verified'));
        Screens::mainMenu($bot, $bot->get('botUser'));
    }
}
