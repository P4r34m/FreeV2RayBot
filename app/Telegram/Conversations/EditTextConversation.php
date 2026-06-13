<?php

namespace App\Telegram\Conversations;

use App\Models\BotText;
use App\Telegram\ContentDefaults;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to edit a user-facing text: pick a key (validated against the
 * defaults registry or an existing override), then send the new HTML content.
 * The override is persisted to bot_texts (cache busts automatically on save).
 */
class EditTextConversation extends Conversation
{
    /** The text key being edited (persists across steps). */
    public string $key = '';

    public function start(Nutgram $bot): void
    {
        // Launched from the glass list with a key already chosen → straight to value.
        if ($this->key !== '') {
            $this->promptValue($bot);

            return;
        }

        $bot->sendMessage(
            "✏️ کلید متن را بفرستید (مثل <code>welcome</code>).\n".
            'برای دیدن همه‌ی کلیدها از «لیست کلیدها» استفاده کنید.'."\n\n".
            'برای لغو: /cancel',
            parse_mode: 'HTML',
        );

        $this->next('captureKey');
    }

    /** Show the current value (raw HTML, escaped) and ask for the new one. */
    private function promptValue(Nutgram $bot): void
    {
        $current = BotText::where('key', $this->key)->value('content')
            ?? ContentDefaults::texts()[$this->key]
            ?? '';

        $bot->sendMessage(
            '🔹 مقدار فعلی کلید <code>'.e($this->key)."</code>:\n\n<pre>".e($current).'</pre>',
            parse_mode: 'HTML',
        );

        $bot->sendMessage(
            "متن جدید را بفرستید. (HTML مجاز است؛ برای ایموجی پریمیوم از تگ tg-emoji استفاده کنید.)\n\nبرای لغو: /cancel",
            parse_mode: 'HTML',
        );

        $this->next('captureValue');
    }

    public function captureKey(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $exists = array_key_exists($text, ContentDefaults::texts())
            || BotText::where('key', $text)->exists();

        if (! $exists) {
            $bot->sendMessage('❌ این کلید وجود ندارد. یک کلید معتبر بفرستید یا /cancel.');
            $this->next('captureKey');

            return;
        }

        $this->key = $text;
        $this->promptValue($bot);
    }

    public function captureValue(Nutgram $bot): void
    {
        $message = $bot->message();
        $new = $message?->text ?? '';

        if (trim($new) === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (trim($new) === '') {
            $bot->sendMessage('متن خالی است. یک متن معتبر بفرستید یا /cancel.');
            $this->next('captureValue');

            return;
        }

        BotText::updateOrCreate(['key' => $this->key], ['content' => $new]);

        $bot->sendMessage("✅ متن کلید <code>{$this->key}</code> ذخیره شد.", parse_mode: 'HTML');

        $this->end();
    }
}
