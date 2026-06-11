<?php

namespace App\Telegram\Conversations;

use App\Services\ChannelService;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Admin flow to add a mandatory channel: forward a message from it (to read its
 * id), then the admin pastes the dedicated invite link. The bot does not create
 * the link.
 */
class AddChannelConversation extends Conversation
{
    /** @var array{chat_id: string, title: string, username: ?string, is_private: bool}|null */
    public ?array $channel = null;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "📡 یک پیام از کانال موردنظر را به همین‌جا فوروارد کنید (عمومی یا خصوصی).\n\n".
            "⚠️ ربات باید در آن کانال ادمین باشد تا بتواند عضویت کاربران را بررسی کند و آمار ورود را ثبت کند.\n\n".
            'برای لغو: /cancel'
        );

        $this->next('captureForward');
    }

    public function captureForward(Nutgram $bot): void
    {
        $message = $bot->message();

        if (($message?->text ?? '') === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        try {
            $this->channel = app(ChannelService::class)->extract($message);
        } catch (Throwable $e) {
            $bot->sendMessage('❌ '.$e->getMessage()."\nدوباره فوروارد کنید یا /cancel را بزنید.");
            $this->next('captureForward');

            return;
        }

        $hint = $this->channel['is_private']
            ? 'این کانال خصوصی است؛ لینک دعوت اختصاصی آن را بفرستید (مثل https://t.me/+AbCdEf...).'
            : 'لینک کانال را بفرستید، یا /skip را بزنید تا لینک یوزرنیم (@'.$this->channel['username'].') استفاده شود.';

        $bot->sendMessage(
            "✅ کانال «{$this->channel['title']}» شناسایی شد.\n\n".
            "🔗 حالا <b>لینک عضویت</b> را وارد کنید:\n{$hint}\n\nبرای لغو: /cancel",
            parse_mode: 'HTML',
        );

        $this->next('captureLink');
    }

    public function captureLink(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $inviteLink = null;

        if ($text === '/skip') {
            if (empty($this->channel['username'])) {
                $bot->sendMessage('این کانال یوزرنیم ندارد؛ باید لینک اختصاصی بفرستید یا /cancel را بزنید.');
                $this->next('captureLink');

                return;
            }
            // leave $inviteLink null → save() builds the username link
        } else {
            if (! preg_match('#^(https?://)?t\.me/#i', $text)) {
                $bot->sendMessage('لینک نامعتبر است. یک لینک معتبر t.me بفرستید، یا /skip / /cancel.');
                $this->next('captureLink');

                return;
            }
            $inviteLink = str_starts_with($text, 'http') ? $text : 'https://'.$text;
        }

        $channel = app(ChannelService::class)->save($this->channel, $inviteLink);

        $type = $channel->is_private ? 'خصوصی' : 'عمومی';
        $bot->sendMessage(
            "✅ کانال «{$channel->title}» اضافه و قفل شد.\nنوع: {$type}\n🔗 لینک: {$channel->joinUrl()}"
        );

        $this->end();
    }
}
