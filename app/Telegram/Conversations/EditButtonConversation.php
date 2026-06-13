<?php

namespace App\Telegram\Conversations;

use App\Models\BotButton;
use App\Telegram\ContentDefaults;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to edit a button: pick a key (validated against the defaults
 * registry or an existing override), send a new label, then optionally a
 * premium-emoji id. Persisted to bot_buttons (cache busts automatically).
 */
class EditButtonConversation extends Conversation
{
    /** The button key being edited (persists across steps). */
    public string $key = '';

    /** The new label entered in step 2 (persists into the icon step). */
    public string $label = '';

    public function start(Nutgram $bot): void
    {
        // Launched from the glass list with a key already chosen → straight to label.
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

    /** Show the current label and ask for the new one. */
    private function promptLabel(Nutgram $bot): void
    {
        $current = BotButton::where('key', $this->key)->value('label')
            ?? ContentDefaults::buttons()[$this->key]
            ?? '';

        $bot->sendMessage(
            "🔹 عنوان فعلی دکمه <code>{$this->key}</code>: {$current}\n\n".
            "عنوان جدید دکمه را بفرستید.\n\nبرای لغو: /cancel",
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
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if ($text === '') {
            $bot->sendMessage('عنوان خالی است. یک عنوان معتبر بفرستید یا /cancel.');
            $this->next('captureLabel');

            return;
        }

        $this->label = $text;

        $bot->sendMessage(
            "🌟 آیدی ایموجی پریمیوم (اختیاری) را بفرستید، یا /skip را بزنید.\n\nبرای لغو: /cancel"
        );

        $this->next('captureIcon');
    }

    public function captureIcon(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $icon = $text === '/skip' ? null : $text;

        BotButton::updateOrCreate(
            ['key' => $this->key],
            ['label' => $this->label, 'icon_custom_emoji_id' => $icon],
        );

        $bot->sendMessage("✅ دکمه‌ی کلید <code>{$this->key}</code> ذخیره شد.", parse_mode: 'HTML');

        $this->end();
    }
}
