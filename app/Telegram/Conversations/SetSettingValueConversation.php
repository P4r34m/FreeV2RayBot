<?php

namespace App\Telegram\Conversations;

use App\Models\Setting;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Generic admin flow to set a single text / multiline / integer setting value.
 * The launcher fills settingKey/label/type before starting.
 */
class SetSettingValueConversation extends Conversation
{
    public string $settingKey = '';

    public string $label = '';

    /** 'text' | 'multiline' | 'int' */
    public string $type = 'text';

    public function start(Nutgram $bot): void
    {
        $current = Setting::string($this->settingKey, '');
        $shown = $current === '' ? '—' : $current;
        $hint = $this->type === 'int' ? 'یک عدد ارسال کنید.' : 'مقدار جدید را ارسال کنید.';

        $bot->sendMessage(
            "✏️ <b>{$this->label}</b>\nمقدار فعلی: {$shown}\n\n{$hint}\nبرای خالی‌کردن: /clear — برای لغو: /cancel",
            parse_mode: 'HTML',
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

        if ($text === '/clear') {
            Setting::put($this->settingKey, $this->type === 'int' ? 0 : '');
            $bot->sendMessage('✅ خالی شد.');
            $this->end();

            return;
        }

        if ($this->type === 'int') {
            if (! ctype_digit($text)) {
                $bot->sendMessage('عدد نامعتبر است. یک عدد صحیح ارسال کنید یا /cancel.');
                $this->next('capture');

                return;
            }
            Setting::put($this->settingKey, (int) $text);
        } else {
            if ($text === '') {
                $bot->sendMessage('مقدار خالی است. متن بفرستید، یا /clear، یا /cancel.');
                $this->next('capture');

                return;
            }
            Setting::put($this->settingKey, $text);
        }

        $bot->sendMessage('✅ ذخیره شد.');
        $this->end();
    }
}
