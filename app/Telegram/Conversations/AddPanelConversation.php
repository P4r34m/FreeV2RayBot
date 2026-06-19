<?php

namespace App\Telegram\Conversations;

use App\Enums\PanelType;
use App\Models\Panel;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to register a new V2Ray panel. Walks through name, type, base_url,
 * type-specific credentials and finally a type-specific setting, then persists
 * the Panel. Progress is held in public props so it survives across steps.
 */
class AddPanelConversation extends Conversation
{
    public ?string $name = null;

    public ?PanelType $type = null;

    public ?string $baseUrl = null;

    public ?string $username = null;

    public ?string $password = null;

    public ?string $apiToken = null;

    public function start(Nutgram $bot): void
    {
        // $this->type is pre-selected via inline buttons before the conversation.
        if (! $this->type instanceof PanelType) {
            $bot->sendMessage('نوع پنل مشخص نشد. دوباره از «افزودن پنل» شروع کنید.');
            $this->end();

            return;
        }

        $bot->sendMessage(
            "🖥 <b>افزودن پنل ({$this->type->label()})</b>\n\n".
            "یک نام برای این پنل بفرستید (مثلاً: سرور آلمان ۱).\n\n".
            'برای لغو: /cancel',
            parse_mode: 'HTML',
        );

        $this->next('captureName');
    }

    public function captureName(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if ($text === '') {
            $bot->sendMessage('نام نامعتبر است. یک نام بفرستید یا /cancel.');
            $this->next('captureName');

            return;
        }

        $this->name = $text;

        $bot->sendMessage(
            "🌐 آدرس پایه (base_url) پنل را بفرستید (مثلاً https://panel.example.com:2053).\n\n".
            'برای لغو: /cancel',
        );

        $this->next('captureBaseUrl');
    }

    public function captureBaseUrl(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! preg_match('#^https?://#i', $text)) {
            $bot->sendMessage('آدرس نامعتبر است. باید با http:// یا https:// شروع شود. دوباره بفرستید یا /cancel.');
            $this->next('captureBaseUrl');

            return;
        }

        $this->baseUrl = rtrim($text, '/');

        // Branch on auth style: login (3x-ui / PasarGuard) vs static token (Remnawave).
        if ($this->type->usesLogin()) {
            $bot->sendMessage("👤 نام کاربری (username) پنل را بفرستید.\n\nبرای لغو: /cancel");
            $this->next('captureUsername');

            return;
        }

        $bot->sendMessage("🔑 توکن API (api_token) پنل را بفرستید.\n\nبرای لغو: /cancel");
        $this->next('captureApiToken');
    }

    public function captureUsername(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if ($text === '') {
            $bot->sendMessage('نام کاربری نامعتبر است. دوباره بفرستید یا /cancel.');
            $this->next('captureUsername');

            return;
        }

        $this->username = $text;

        $bot->sendMessage("🔒 رمز عبور (password) پنل را بفرستید.\n\nبرای لغو: /cancel");
        $this->next('capturePassword');
    }

    public function capturePassword(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if ($text === '') {
            $bot->sendMessage('رمز عبور نامعتبر است. دوباره بفرستید یا /cancel.');
            $this->next('capturePassword');

            return;
        }

        $this->password = $text;

        $this->persist($bot);
    }

    public function captureApiToken(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if ($text === '') {
            $bot->sendMessage('توکن نامعتبر است. دوباره بفرستید یا /cancel.');
            $this->next('captureApiToken');

            return;
        }

        $this->apiToken = $text;

        $this->persist($bot);
    }

    /** Create the panel record (no targets yet) and confirm, then end. */
    private function persist(Nutgram $bot): void
    {
        $settings = [];

        $panel = Panel::create([
            'name' => $this->name,
            'type' => $this->type,
            'base_url' => $this->baseUrl,
            'username' => $this->username,
            'password' => $this->password,
            'api_token' => $this->apiToken,
            'settings' => $settings,
            'is_active' => true,
        ]);

        $bot->sendMessage(
            '✅ پنل «'.e($panel->name).'» ('.$this->type->label().") اضافه شد.\n\n".
            "حالا از فهرست پنل‌ها واردش شوید و:\n".
            "• «🔌 تست اتصال» را بزنید،\n".
            "• از «⚙️ تنظیمات بیشتر» اینباند/گروه/اسکواد را انتخاب کنید.",
            parse_mode: 'HTML',
        );

        $this->end();
    }
}
