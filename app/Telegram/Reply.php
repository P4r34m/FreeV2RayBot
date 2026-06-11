<?php

namespace App\Telegram;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Throwable;

/**
 * Renders a "screen": edits the current message in place for callback queries,
 * or sends a fresh message otherwise. Centralizes parse_mode + error swallowing.
 */
class Reply
{
    public static function screen(
        Nutgram $bot,
        string $text,
        ?InlineKeyboardMarkup $keyboard = null,
        string $parseMode = 'HTML',
    ): void {
        if ($bot->isCallbackQuery() && $bot->callbackQuery()?->message) {
            try {
                $bot->editMessageText(
                    text: $text,
                    parse_mode: $parseMode,
                    reply_markup: $keyboard,
                );

                return;
            } catch (Throwable) {
                // Message unchanged or too old to edit — fall through to send.
            }
        }

        $bot->sendMessage(
            text: $text,
            parse_mode: $parseMode,
            reply_markup: $keyboard,
        );
    }

    /** Acknowledge a callback query (optionally as a toast/alert). */
    public static function toast(Nutgram $bot, ?string $text = null, bool $alert = false): void
    {
        if ($bot->isCallbackQuery()) {
            $bot->answerCallbackQuery(text: $text, show_alert: $alert);
        }
    }
}
