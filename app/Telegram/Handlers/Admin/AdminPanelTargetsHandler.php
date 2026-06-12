<?php

namespace App\Telegram\Handlers\Admin;

use App\Enums\PanelType;
use App\Models\Panel;
use App\Panels\PanelManager;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Throwable;

/** Fetch inbounds/groups/squads from the panel and show them as selectable buttons (admin:panels:targets:{id}). */
class AdminPanelTargetsHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        $panel = Panel::find((int) $id);
        if (! $panel) {
            Reply::toast($bot, 'پنل پیدا نشد', alert: true);
            (new AdminPanelsHandler)($bot);

            return;
        }

        try {
            $targets = app(PanelManager::class)->driver($panel)->listTargets();
        } catch (Throwable) {
            $targets = [];
        }

        $kb = InlineKeyboardMarkup::make();

        if ($targets === []) {
            $kb->addRow(Btn::make('✍️ ورود دستی', callback_data: "admin:panels:tmanual:{$panel->id}"));
            $kb->addRow(Btn::make('🔙 بازگشت', callback_data: "admin:panels:cfg:{$panel->id}"));

            Reply::screen(
                $bot,
                "نتوانستم لیست را از پنل بگیرم.\nابتدا «🔌 تست اتصال» را بزنید و دسترسی را بررسی کنید، یا به‌صورت دستی وارد کنید.",
                $kb,
            );

            return;
        }

        $selected = self::selectedIds($panel);
        $multi = $panel->type !== PanelType::ThreeXui;

        foreach ($targets as $i => $target) {
            $check = in_array((string) $target['id'], $selected, true) ? '✅ ' : ($multi ? '⬜️ ' : '');
            $kb->addRow(Btn::make($check.$target['label'], callback_data: "admin:panels:tgt:{$panel->id}_{$i}"));
        }

        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: "admin:panels:cfg:{$panel->id}"));

        $hint = $multi
            ? 'موارد دلخواه را بزنید تا انتخاب/لغو شوند، بعد «بازگشت»:'
            : 'یک اینباند را انتخاب کنید:';

        Reply::screen($bot, "🎯 <b>انتخاب هدف</b>\n{$hint}", $kb);
    }

    /** @return list<string> currently-selected target ids as strings */
    public static function selectedIds(Panel $panel): array
    {
        $s = $panel->settings ?? [];

        return match ($panel->type) {
            PanelType::ThreeXui => isset($s['inbound_id']) ? [(string) $s['inbound_id']] : [],
            PanelType::Remnawave => array_map('strval', $s['squad_uuids'] ?? []),
            PanelType::PasarGuard => array_map('strval', $s['group_ids'] ?? []),
        };
    }
}
