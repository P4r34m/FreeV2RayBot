<?php

namespace App\Telegram\Conversations;

use App\Enums\PanelType;
use App\Models\Panel;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/** Manual fallback for setting a panel's inbound/squad/group when fetch fails. */
class EditPanelTargetConversation extends Conversation
{
    public ?int $panelId = null;

    public function start(Nutgram $bot): void
    {
        $panel = Panel::find($this->panelId);
        if (! $panel) {
            $bot->sendMessage('پنل پیدا نشد.');
            $this->end();

            return;
        }

        $prompt = match ($panel->type) {
            PanelType::ThreeXui => '🔢 شناسه اینباند (inbound_id) را بفرستید (عدد).',
            PanelType::Remnawave => '🧩 UUIDهای Squad را با کاما جدا کنید بفرستید.',
            PanelType::PasarGuard => '👥 شناسه‌های گروه (group ids) را با کاما جدا کنید بفرستید (عدد).',
        };

        $bot->sendMessage($prompt."\n\nبرای لغو: /cancel");
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

        $panel = Panel::find($this->panelId);
        if (! $panel) {
            $bot->sendMessage('پنل پیدا نشد.');
            $this->end();

            return;
        }

        $s = $panel->settings ?? [];

        if ($panel->type === PanelType::ThreeXui) {
            if (! ctype_digit($text)) {
                $bot->sendMessage('فقط عدد بفرستید یا /cancel.');
                $this->next('capture');

                return;
            }
            $s['inbound_id'] = (int) $text;
        } elseif ($panel->type === PanelType::Remnawave) {
            $s['squad_uuids'] = array_values(array_filter(array_map('trim', explode(',', $text)), fn ($v) => $v !== ''));
        } else {
            $parts = array_filter(array_map('trim', explode(',', $text)), fn ($v) => $v !== '');
            foreach ($parts as $p) {
                if (! ctype_digit($p)) {
                    $bot->sendMessage('عددها را با کاما جدا کنید یا /cancel.');
                    $this->next('capture');

                    return;
                }
            }
            $s['group_ids'] = array_values(array_map('intval', $parts));
        }

        $panel->update(['settings' => $s]);
        $bot->sendMessage('✅ ذخیره شد.');
        $this->end();
    }
}
