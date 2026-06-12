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

        // Multi-select for every type now: 3x-ui v3 lets one client span several
        // inbounds (shared subId), so a config can target multiple inbounds.
        $selected = self::selectedIds($panel);

        foreach ($targets as $i => $target) {
            $check = in_array((string) $target['id'], $selected, true) ? '✅ ' : '⬜️ ';
            $kb->addRow(Btn::make($check.$target['label'], callback_data: "admin:panels:tgt:{$panel->id}_{$i}"));
        }

        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: "admin:panels:cfg:{$panel->id}"));

        Reply::screen(
            $bot,
            "🎯 <b>انتخاب هدف</b>\nموارد دلخواه را بزنید تا انتخاب/لغو شوند (می‌توانید چندتا انتخاب کنید)، بعد «بازگشت»:",
            $kb,
        );
    }

    /** @return list<string> currently-selected target ids as strings */
    public static function selectedIds(Panel $panel): array
    {
        $s = $panel->settings ?? [];

        return match ($panel->type) {
            // Prefer the new inbound_ids array; fall back to the legacy single inbound_id.
            PanelType::ThreeXui => array_map(
                'strval',
                $s['inbound_ids'] ?? (isset($s['inbound_id']) ? [$s['inbound_id']] : []),
            ),
            PanelType::Remnawave => array_map('strval', $s['squad_uuids'] ?? []),
            PanelType::PasarGuard => array_map('strval', $s['group_ids'] ?? []),
        };
    }
}
