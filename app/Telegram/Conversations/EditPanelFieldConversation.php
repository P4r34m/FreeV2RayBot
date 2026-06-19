<?php

namespace App\Telegram\Conversations;

use App\Models\Panel;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/** Edit a single field of an existing panel (name / base_url / username / password / api_token). */
class EditPanelFieldConversation extends Conversation
{
    private const FIELDS = ['name', 'base_url', 'username', 'password', 'api_token', 'capacity'];

    private const LABELS = [
        'name' => 'نام',
        'base_url' => 'آدرس (base_url)',
        'username' => 'یوزرنیم',
        'password' => 'پسورد',
        'api_token' => 'توکن API',
        'capacity' => 'ظرفیت (تعداد کانفیگ مجاز، -1 = نامحدود)',
    ];

    public ?int $panelId = null;

    public ?string $field = null;

    public function start(Nutgram $bot): void
    {
        if (! in_array($this->field, self::FIELDS, true) || ! Panel::whereKey($this->panelId)->exists()) {
            $bot->sendMessage('مورد نامعتبر است.');
            $this->end();

            return;
        }

        $bot->sendMessage(
            "✏️ مقدار جدید برای «".self::LABELS[$this->field]."» را بفرستید.\n\nبرای لغو: /cancel"
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

        if ($text === '') {
            $bot->sendMessage('مقدار خالی است. دوباره بفرستید یا /cancel.');
            $this->next('capture');

            return;
        }

        if ($this->field === 'base_url' && ! preg_match('#^https?://#i', $text)) {
            $bot->sendMessage('آدرس باید با http:// یا https:// شروع شود. دوباره بفرستید یا /cancel.');
            $this->next('capture');

            return;
        }

        if ($this->field === 'capacity' && ! preg_match('/^-?\d+$/', $text)) {
            $bot->sendMessage('عدد نامعتبر است. یک عدد صحیح بفرستید (-1 یعنی نامحدود) یا /cancel.');
            $this->next('capture');

            return;
        }

        $panel = Panel::find($this->panelId);
        if (! $panel) {
            $bot->sendMessage('پنل پیدا نشد.');
            $this->end();

            return;
        }

        $value = match ($this->field) {
            'base_url' => rtrim($text, '/'),
            'capacity' => (int) $text,
            default => $text,
        };
        $panel->update([$this->field => $value]);

        // Credentials/URL changed -> drop any cached auth token/session so the next
        // call re-authenticates with the new values.
        Cache::forget("panel:{$panel->id}:auth");

        $bot->sendMessage('✅ ذخیره شد.');
        $this->end();
    }
}
