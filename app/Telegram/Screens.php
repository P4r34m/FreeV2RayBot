<?php

namespace App\Telegram;

use App\Models\BotUser;
use SergiX44\Nutgram\Nutgram;

/** Shared, reusable screens rendered from multiple handlers. */
class Screens
{
    public static function mainMenu(Nutgram $bot, BotUser $user, string $welcomeKey = 'welcome'): void
    {
        $welcome = Content::text($welcomeKey);

        // Reply keyboards are chat-level and can't ride on editMessageText, so in
        // reply mode we always send a fresh message to (re)set the keyboard.
        if (Keyboards::mode() === 'reply') {
            if ($bot->isCallbackQuery()) {
                $bot->answerCallbackQuery();
            }

            $bot->sendMessage(
                text: $welcome,
                parse_mode: 'HTML',
                reply_markup: Keyboards::mainReplyKeyboard($user->is_admin),
            );

            return;
        }

        Reply::screen($bot, $welcome, Keyboards::mainMenu($user->is_admin));
    }
}
