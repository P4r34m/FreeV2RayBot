<?php

namespace App\Telegram\Conversations;

use App\Models\BotUser;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to add (or deduct) coins for a user by numeric Telegram id.
 * Send a negative number to deduct; the balance is clamped at zero.
 */
class GrantCoinsConversation extends Conversation
{
    public ?int $telegramId = null;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage("🪙 آیدی عددی کاربری که می‌خواهید سکه‌اش را تغییر دهید را ارسال کنید.\nبرای لغو: /cancel");

        $this->next('captureId');
    }

    public function captureId(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! ctype_digit($text)) {
            $bot->sendMessage('آیدی نامعتبر است. فقط عدد ارسال کنید یا /cancel.');
            $this->next('captureId');

            return;
        }

        $this->telegramId = (int) $text;
        $user = BotUser::firstOrNew(['telegram_id' => $this->telegramId]);

        $bot->sendMessage(
            "کاربر <code>{$this->telegramId}</code> — موجودی فعلی: <b>{$user->coins}</b> سکه\n\n".
            "چند سکه اضافه شود؟ (برای کسر، عدد منفی بفرستید)\nبرای لغو: /cancel",
            parse_mode: 'HTML',
        );

        $this->next('captureAmount');
    }

    public function captureAmount(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! preg_match('/^-?\d+$/', $text)) {
            $bot->sendMessage('عدد نامعتبر است. یک عدد صحیح (مثبت یا منفی) ارسال کنید یا /cancel.');
            $this->next('captureAmount');

            return;
        }

        $user = BotUser::firstOrNew(['telegram_id' => $this->telegramId]);
        $user->coins = max(0, (int) $user->coins + (int) $text); // unsigned column → clamp at 0
        $user->save();

        $bot->sendMessage(
            "✅ موجودی سکه کاربر <code>{$this->telegramId}</code> اکنون <b>{$user->coins}</b> سکه است.",
            parse_mode: 'HTML',
        );

        $this->end();
    }
}
