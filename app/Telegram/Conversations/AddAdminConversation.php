<?php

namespace App\Telegram\Conversations;

use App\Models\BotUser;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to promote a user to admin by numeric Telegram id. If the user has
 * never started the bot yet, a row is created so the flag sticks for when they do.
 */
class AddAdminConversation extends Conversation
{
    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "🆔 آیدی عددی کاربری که می‌خواهید ادمین شود را ارسال کنید.\n".
            "(اگر هنوز ربات را استارت نکرده باشد هم اشکالی ندارد.)\n\nبرای لغو: /cancel",
        );

        $this->next('capture');
    }

    public function capture(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! ctype_digit($text)) {
            $bot->sendMessage('آیدی نامعتبر است. فقط عدد ارسال کنید یا /cancel.');
            $this->next('capture');

            return;
        }

        $user = BotUser::firstOrNew(['telegram_id' => (int) $text]);
        $already = (bool) $user->is_admin;
        // Set the property directly so mass-assignment guarding can't drop it.
        $user->is_admin = true;
        $user->save();

        $bot->sendMessage($already
            ? "ℹ️ کاربر <code>{$text}</code> از قبل ادمین بود."
            : "✅ کاربر <code>{$text}</code> ادمین شد.",
            parse_mode: 'HTML');

        $this->end();
    }
}
