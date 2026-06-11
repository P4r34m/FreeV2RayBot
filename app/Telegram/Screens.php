<?php

namespace App\Telegram;

use App\Models\BotUser;
use SergiX44\Nutgram\Nutgram;

/** Shared, reusable screens rendered from multiple handlers. */
class Screens
{
    public static function mainMenu(Nutgram $bot, BotUser $user): void
    {
        Reply::screen($bot, Content::text('welcome'), Keyboards::mainMenu($user->is_admin));
    }
}
