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
            PanelType::ThreeXui => '🔢 شناسه‌های اینباند را با کاما جدا کنید بفرستید (مثلاً 1,2,3).',
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
            $parts = array_filter(array_map('trim', explode(',', $text)), fn ($v) => $v !== '');
            foreach ($parts as $p) {
                if (! ctype_digit($p)) {
                    $bot->sendMessage('شناسه‌ها باید عدد و با کاما جدا باشند (مثلاً 1,2). دوباره یا /cancel.');
                    $this->next('capture');

                    return;
                }
            }
            $ids = array_values(array_map('intval', $parts));
            $s['inbound_ids'] = $ids;
            // keep legacy single inbound_id in sync for back-compat
            if ($ids !== []) {
                $s['inbound_id'] = $ids[0];
            } else {
                unset($s['inbound_id']);
            }
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
