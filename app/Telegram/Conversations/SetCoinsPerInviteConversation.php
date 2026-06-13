<?php

namespace App\Telegram\Conversations;

use App\Models\Setting;
use App\Support\SettingKey;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/** Admin flow to set how many coins each verified invite grants (coin mode). */
class SetCoinsPerInviteConversation extends Conversation
{
    public function start(Nutgram $bot): void
    {
        $current = Setting::int(SettingKey::REFERRAL_COINS_PER_INVITE, 1);
        $bot->sendMessage(
            "🪙 سکه به ازای هر دعوتِ تأییدشده چند باشد؟ (الان: {$current})\nیک عدد ارسال کنید.\nبرای لغو: /cancel"
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
            $bot->sendMessage('عدد نامعتبر است. یک عدد صحیح (0 یا بیشتر) ارسال کنید یا /cancel.');
            $this->next('capture');

            return;
        }

        Setting::put(SettingKey::REFERRAL_COINS_PER_INVITE, (int) $text);
        $bot->sendMessage("✅ هر دعوت = <b>{$text}</b> سکه.", parse_mode: 'HTML');
        $this->end();
    }
}
