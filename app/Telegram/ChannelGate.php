<?php

namespace App\Telegram;

use App\Services\ChannelLockService;
use SergiX44\Nutgram\Nutgram;

/**
 * Force-join gate: returns true if the user may proceed, otherwise renders the
 * "join these channels" screen and returns false.
 */
class ChannelGate
{
    public static function enforce(Nutgram $bot): bool
    {
        /** @var ChannelLockService $lock */
        $lock = app(ChannelLockService::class);

        if (! $lock->enabled()) {
            return true;
        }

        $missing = $lock->missingChannels($bot, (int) $bot->userId());

        if ($missing->isEmpty()) {
            return true;
        }

        Reply::toast($bot);
        Reply::screen(
            $bot,
            Content::text('channel.lock_prompt'),
            Keyboards::joinChannels($missing),
        );

        return false;
    }
}
