<?php

namespace App\Telegram\Conversations;

use App\Models\BotUser;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to permanently block/unblock a user by numeric Telegram id.
 */
class BlockUserConversation extends Conversation
{
    /** 'block' | 'unblock' */
    public string $action = 'block';

    public function start(Nutgram $bot): void
    {
        $verb = $this->action === 'unblock' ? 'رفع مسدودی' : 'مسدودسازی';
        $bot->sendMessage("🆔 آیدی عددی کاربری که می‌خواهید {$verb} کنید را ارسال کنید.\nبرای لغو: /cancel");

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
        $block = $this->action !== 'unblock';

        $user->fill([
            'is_blocked' => $block,
            'blocked_until' => null,
            'block_reason' => $block ? 'admin' : null,
        ])->save();

        $bot->sendMessage($block
            ? "⛔️ کاربر <code>{$text}</code> مسدود شد."
            : "✅ کاربر <code>{$text}</code> رفع مسدودی شد.",
            parse_mode: 'HTML');

        $this->end();
    }
}
