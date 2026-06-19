<?php

namespace App\Telegram\Conversations;

use App\Models\BotButton;
use App\Support\PremiumEmoji;
use App\Telegram\ContentDefaults;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to edit a button: pick a key, send the new label (any premium emoji
 * in the message is auto-detected — no need to paste an emoji id), then choose a
 * color. Persisted to bot_buttons (cache busts automatically).
 */
class EditButtonConversation extends Conversation
{
    /** Color choices offered to the admin. */
    private const STYLES = ['1' => 'primary', '2' => 'success', '3' => 'danger', '4' => null];

    public string $key = '';

    public function start(Nutgram $bot): void
    {
        if ($this->key !== '') {
            $this->promptLabel($bot);

            return;
        }

        $bot->sendMessage(
            "🔘 کلید دکمه را بفرستید (مثل <code>menu.get_config</code>).\n".
            'برای دیدن همه‌ی کلیدها از «لیست کلیدها» استفاده کنید.'."\n\n".
            'برای لغو: /cancel',
            parse_mode: 'HTML',
        );

        $this->next('captureKey');
    }

    private function promptLabel(Nutgram $bot): void
    {
        $current = BotButton::where('key', $this->key)->value('label')
            ?? ContentDefaults::buttons()[$this->key]
            ?? '';

        $bot->sendMessage(
            "🔹 عنوان فعلی دکمه <code>{$this->key}</code>: {$current}\n\n".
            "عنوان جدید دکمه را بفرستید.\n".
            "💡 اگر ایموجی پریمیوم بخواهی، کافی است همان ایموجی را داخل متن بفرستی؛ خودم آیدی‌اش را پیدا می‌کنم.\n\n".
            'برای لغو: /cancel',
            parse_mode: 'HTML',
        );

        $this->next('captureLabel');
    }

    public function captureKey(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $exists = array_key_exists($text, ContentDefaults::buttons())
            || BotButton::where('key', $text)->exists();

        if (! $exists) {
            $bot->sendMessage('❌ این کلید وجود ندارد. یک کلید معتبر بفرستید یا /cancel.');
            $this->next('captureKey');

            return;
        }

        $this->key = $text;
        $this->promptLabel($bot);
    }

    public function captureLabel(Nutgram $bot): void
    {
        $message = $bot->message();

        if (trim($message?->text ?? '') === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        // Auto-detect a premium emoji from the message and strip it from the label.
        [$label, $icon] = PremiumEmoji::extract($message);

        if ($label === '') {
            $bot->sendMessage('عنوان خالی است. یک عنوان (به‌همراه ایموجی دلخواه) بفرستید یا /cancel.');
            $this->next('captureLabel');

            return;
        }

        BotButton::updateOrCreate(
            ['key' => $this->key],
            ['label' => $label, 'icon_custom_emoji_id' => $icon],
        );

        $bot->sendMessage(
            "🎨 رنگ دکمه را انتخاب کنید:\n1) آبی\n2) سبز\n3) قرمز\n4) بدون رنگ\nبرای لغو: /cancel"
        );

        $this->next('captureStyle');
    }

    public function captureStyle(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد (عنوان ذخیره شد، رنگ بدون تغییر).');
            $this->end();

            return;
        }

        if (! array_key_exists($text, self::STYLES)) {
            $bot->sendMessage('فقط 1 تا 4 ارسال کنید یا /cancel.');
            $this->next('captureStyle');

            return;
        }

        BotButton::updateOrCreate(['key' => $this->key], ['style' => self::STYLES[$text]]);

        $bot->sendMessage("✅ دکمه‌ی کلید <code>{$this->key}</code> ذخیره شد.", parse_mode: 'HTML');

        $this->end();
    }
}
