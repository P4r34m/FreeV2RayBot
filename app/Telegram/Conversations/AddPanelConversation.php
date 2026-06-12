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
        $bot->sendMessage(
            "🖥 <b>افزودن پنل جدید</b>\n\n".
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
            "نوع پنل: عدد بفرستید\n1) 3x-ui\n2) PasarGuard\n3) Remnawave\n\nبرای لغو: /cancel"
        );

        $this->next('captureType');
    }

    public function captureType(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $type = match ($text) {
            '1' => PanelType::ThreeXui,
            '2' => PanelType::PasarGuard,
            '3' => PanelType::Remnawave,
            default => null,
        };

        if ($type === null) {
            $bot->sendMessage("عدد نامعتبر است. فقط 1، 2 یا 3 بفرستید یا /cancel.");
            $this->next('captureType');

            return;
        }

        $this->type = $type;

        $bot->sendMessage(
            "✅ نوع: {$type->label()}\n\n".
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

        $this->askTypeSetting($bot);
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

        $this->askTypeSetting($bot);
    }

    /** Prompt for the single type-specific setting and route to its capture step. */
    private function askTypeSetting(Nutgram $bot): void
    {
        match ($this->type) {
            PanelType::ThreeXui => $bot->sendMessage(
                "🔢 شناسه اینباند (inbound_id) را بفرستید (عدد، الزامی).\n\nبرای لغو: /cancel"
            ),
            PanelType::Remnawave => $bot->sendMessage(
                "🧩 شناسه‌های Squad را با کاما جدا کنید بفرستید، یا /skip برای رد شدن.\n\nبرای لغو: /cancel"
            ),
            PanelType::PasarGuard => $bot->sendMessage(
                "👥 شناسه‌های گروه (group ids) را با کاما جدا کنید بفرستید، یا /skip برای رد شدن.\n\nبرای لغو: /cancel"
            ),
        };

        $this->next(match ($this->type) {
            PanelType::ThreeXui => 'captureInboundId',
            PanelType::Remnawave => 'captureSquadUuids',
            PanelType::PasarGuard => 'captureGroupIds',
        });
    }

    public function captureInboundId(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! ctype_digit($text)) {
            $bot->sendMessage('شناسه اینباند نامعتبر است. فقط عدد بفرستید یا /cancel.');
            $this->next('captureInboundId');

            return;
        }

        $this->persist($bot, ['inbound_id' => (int) $text]);
    }

    public function captureSquadUuids(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $uuids = $text === '/skip'
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $text)), fn ($v) => $v !== ''));

        $this->persist($bot, ['squad_uuids' => $uuids]);
    }

    public function captureGroupIds(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $groupIds = [];

        if ($text !== '/skip') {
            $parts = array_filter(array_map('trim', explode(',', $text)), fn ($v) => $v !== '');

            foreach ($parts as $part) {
                if (! ctype_digit($part)) {
                    $bot->sendMessage('شناسه گروه نامعتبر است. عددها را با کاما جدا کنید، یا /skip / /cancel.');
                    $this->next('captureGroupIds');

                    return;
                }
                $groupIds[] = (int) $part;
            }

            $groupIds = array_values($groupIds);
        }

        $this->persist($bot, ['group_ids' => $groupIds]);
    }

    /** Create the panel record and confirm, then end the conversation. */
    private function persist(Nutgram $bot, array $settings): void
    {
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
            "✅ پنل «{$panel->name}» ({$this->type->label()}) اضافه شد.\n\n".
            "برای بررسی سلامت، از فهرست پنل‌ها واردش شوید و «🔌 تست اتصال» را بزنید.",
            parse_mode: 'HTML',
        );

        $this->end();
    }
}
