<?php

namespace App\Telegram\Conversations;

use App\Services\ChannelService;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Admin flow to add a mandatory channel by forwarding a message from it. The
 * bot reads the channel id, mints a dedicated invite link, and locks it.
 */
class AddChannelConversation extends Conversation
{
    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "📡 یک پیام از کانال موردنظر را به همین‌جا فوروارد کنید (عمومی یا خصوصی).\n\n".
            "⚠️ برای کانال خصوصی و ثبت آمار، ربات باید در آن کانال ادمین با دسترسی «دعوت کاربران با لینک» باشد.\n\n".
            'برای لغو: /cancel'
        );

        $this->next('capture');
    }

    public function capture(Nutgram $bot): void
    {
        $message = $bot->message();

        if (($message?->text ?? '') === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        try {
            $channel = app(ChannelService::class)->addFromForward($bot, $message);
        } catch (Throwable $e) {
            $bot->sendMessage('❌ '.$e->getMessage()."\nدوباره فوروارد کنید یا /cancel را بزنید.");
            $this->next('capture');

            return;
        }

        $type = $channel->is_private ? 'خصوصی' : 'عمومی';
        $link = $channel->invite_link
            ? "\n🔗 لینک: {$channel->invite_link}"
            : "\n⚠️ ربات در کانال ادمین نیست؛ لینک اختصاصی ساخته نشد (برای کانال عمومی، لینک یوزرنیم استفاده می‌شود).";

        $bot->sendMessage("✅ کانال «{$channel->title}» اضافه و قفل شد.\nنوع: {$type}{$link}");
        $this->end();
    }
}
