<?php

namespace App\Telegram\Conversations;

use App\Models\BotUser;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to set a per-user active-config limit by numeric Telegram id.
 * Sending 0 clears the override and returns the user to the global default.
 */
class SetUserConfigLimitConversation extends Conversation
{
    public ?int $telegramId = null;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "🆔 آیدی عددی کاربری که می‌خواهید سقف کانفیگش را تغییر دهید را ارسال کنید.\nبرای لغو: /cancel"
        );

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
        $source = $user->max_configs !== null ? "ویژه‌ی این کاربر" : 'پیش‌فرض سیستم';

        $bot->sendMessage(
            "کاربر <code>{$this->telegramId}</code>\nسقف فعلی: <b>{$user->maxConfigs()}</b> ({$source})\n\n".
            "عدد سقف کانفیگ مجاز را وارد کنید (مثلاً 3).\n".
            "برای بازگشت به حالت پیش‌فرض سیستم، عدد 0 را بفرستید.\nبرای لغو: /cancel",
            parse_mode: 'HTML',
        );

        $this->next('captureLimit');
    }

    public function captureLimit(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! ctype_digit($text)) {
            $bot->sendMessage('عدد نامعتبر است. یک عدد صحیح (مثلاً 3 یا 0) بفرستید یا /cancel.');
            $this->next('captureLimit');

            return;
        }

        $value = (int) $text;

        $user = BotUser::firstOrNew(['telegram_id' => $this->telegramId]);
        $user->max_configs = $value > 0 ? $value : null;
        $user->save();

        $bot->sendMessage(
            $value > 0
                ? "✅ سقف کانفیگ کاربر <code>{$this->telegramId}</code> روی <b>{$value}</b> تنظیم شد."
                : "✅ سقف کانفیگ کاربر <code>{$this->telegramId}</code> به حالت پیش‌فرض سیستم بازگشت.",
            parse_mode: 'HTML',
        );

        $this->end();
    }
}
