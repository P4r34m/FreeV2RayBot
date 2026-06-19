<?php

namespace App\Telegram\Conversations;

use App\Models\Panel;
use App\Support\PremiumEmoji;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/** Edit a single field of an existing panel (name / base_url / username / password / api_token). */
class EditPanelFieldConversation extends Conversation
{
    private const FIELDS = ['name', 'base_url', 'username', 'password', 'api_token', 'capacity', 'coin_capacity'];

    private const LABELS = [
        'name' => 'نام',
        'base_url' => 'آدرس (base_url)',
        'username' => 'یوزرنیم',
        'password' => 'پسورد',
        'api_token' => 'توکن API',
        'capacity' => 'ظرفیت کانفیگ رایگان (-1 = نامحدود)',
        'coin_capacity' => 'ظرفیت کانفیگ سکه‌ای (-1 = نامحدود)',
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

        $hint = $this->field === 'name'
            ? "\n💡 اگر ایموجی پریمیوم بخواهی، همان ایموجی را داخل نام بفرست؛ خودم پیدا و روی دکمه‌ی سرور اعمالش می‌کنم."
                ."\nℹ️ تلگرام آن را همیشه «قبل از نام» (ابتدای دکمه) نشان می‌دهد."
            : '';

        $bot->sendMessage(
            "✏️ مقدار جدید برای «".self::LABELS[$this->field]."» را بفرستید.".$hint."\n\nبرای لغو: /cancel"
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

        if (in_array($this->field, ['capacity', 'coin_capacity'], true) && ! preg_match('/^-?\d+$/', $text)) {
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

        // The panel name is shown inside a button's TEXT, which can't render a
        // premium emoji — so auto-detect one, store it as the panel's icon, and
        // keep the (stripped) plain name. The picker uses the icon field for it.
        if ($this->field === 'name') {
            [$name, $iconId] = PremiumEmoji::extract($bot->message());

            if ($name === '') {
                $bot->sendMessage('نام خالی است. یک نام (به‌همراه ایموجی دلخواه) بفرستید یا /cancel.');
                $this->next('capture');

                return;
            }

            $settings = $panel->settings ?? [];
            if ($iconId !== null) {
                $settings['icon_emoji_id'] = $iconId;
            } else {
                unset($settings['icon_emoji_id']);
            }

            $panel->update(['name' => $name, 'settings' => $settings]);

            $bot->sendMessage($iconId !== null
                ? '✅ ذخیره شد (ایموجی پریمیوم برای این سرور هم اعمال شد).'
                : '✅ ذخیره شد.');
            $this->end();

            return;
        }

        $value = match ($this->field) {
            'base_url' => rtrim($text, '/'),
            // The columns are unsigned; store "unlimited" as null (a negative input
            // such as -1 means unlimited).
            'capacity', 'coin_capacity' => (int) $text < 0 ? null : (int) $text,
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
