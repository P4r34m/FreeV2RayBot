<?php

namespace App\Telegram\Conversations;

use App\Models\Panel;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/** Set the 3x-ui subscription link host/port/path used to build sub URLs. */
class EditPanelSubConversation extends Conversation
{
    public ?int $panelId = null;

    public function start(Nutgram $bot): void
    {
        if (! Panel::whereKey($this->panelId)->exists()) {
            $bot->sendMessage('پنل پیدا نشد.');
            $this->end();

            return;
        }

        $bot->sendMessage("🌐 هاست لینک ساب را بفرستید (مثلاً sub.example.com)، یا /skip برای استفاده از هاست آدرس پنل.\n\nبرای لغو: /cancel");
        $this->next('captureHost');
    }

    public function captureHost(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');
        if ($this->cancelled($bot, $text)) {
            return;
        }

        $this->put('sub_host', $text === '/skip' ? null : $text);

        $bot->sendMessage("🔌 پورت لینک ساب را بفرستید (عدد)، یا /skip برای پیش‌فرض (2096).\n\nبرای لغو: /cancel");
        $this->next('capturePort');
    }

    public function capturePort(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');
        if ($this->cancelled($bot, $text)) {
            return;
        }

        if ($text !== '/skip' && ! ctype_digit($text)) {
            $bot->sendMessage('پورت باید عدد باشد. دوباره یا /skip / /cancel.');
            $this->next('capturePort');

            return;
        }

        $this->put('sub_port', $text === '/skip' ? null : (int) $text);

        $bot->sendMessage("🛣 مسیر (path) لینک ساب را بفرستید (مثلاً /sub/)، یا /skip برای پیش‌فرض.\n\nبرای لغو: /cancel");
        $this->next('capturePath');
    }

    public function capturePath(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');
        if ($this->cancelled($bot, $text)) {
            return;
        }

        $this->put('sub_path', $text === '/skip' ? null : $text);

        $bot->sendMessage('✅ تنظیمات لینک ساب ذخیره شد.');
        $this->end();
    }

    private function cancelled(Nutgram $bot, string $text): bool
    {
        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return true;
        }

        return false;
    }

    /** Persist (or clear) one sub-setting key on the panel. */
    private function put(string $key, mixed $value): void
    {
        $panel = Panel::find($this->panelId);
        if (! $panel) {
            return;
        }

        $s = $panel->settings ?? [];
        if ($value === null) {
            unset($s[$key]);
        } else {
            $s[$key] = $value;
        }
        $panel->update(['settings' => $s]);
    }
}
