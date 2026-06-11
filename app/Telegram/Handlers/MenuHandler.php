<?php

namespace App\Telegram\Handlers;

use App\Telegram\Reply;
use App\Telegram\Screens;
use SergiX44\Nutgram\Nutgram;

/** Returns to the main menu (callback: menu). */
class MenuHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);
        Screens::mainMenu($bot, $bot->get('botUser'));
    }
}
