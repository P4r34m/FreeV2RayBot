<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Telegram\Keyboards;
use App\Telegram\Presenter;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Show the user's active config status (callback: config:status). */
class ConfigStatusHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        // Show every active config the user holds (a user may have several).
        $configs = $user->configs()
            ->where('status', ConfigStatus::Active->value)
            ->with('plan')
            ->latest()
            ->get();

        Reply::screen($bot, Presenter::accountStatusAll($configs), Keyboards::configMenu($configs->isNotEmpty()));
    }
}
