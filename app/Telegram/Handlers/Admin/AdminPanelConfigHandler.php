<?php

namespace App\Telegram\Handlers\Admin;

use App\Enums\PanelType;
use App\Models\Panel;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** "تنظیمات بیشتر" for a panel: pick inbound/squad/group + sub settings (admin:panels:cfg:{id}). */
class AdminPanelConfigHandler
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

        $s = $panel->settings ?? [];

        $target = match ($panel->type) {
            PanelType::ThreeXui => 'اینباند فعلی: <b>'.($s['inbound_id'] ?? '— انتخاب نشده').'</b>',
            PanelType::Remnawave => 'اسکوادهای انتخاب‌شده: <b>'.count($s['squad_uuids'] ?? []).'</b>',
            PanelType::PasarGuard => 'گروه‌های انتخاب‌شده: <b>'.count($s['group_ids'] ?? []).'</b>',
        };

        $lines = [
            "⚙️ <b>تنظیمات بیشتر — {$panel->name}</b>",
            'نوع: '.$panel->type->label(),
            '',
            $target,
        ];

        $kb = InlineKeyboardMarkup::make()
            ->addRow(Btn::make('🎯 انتخاب اینباند/گروه/اسکواد', callback_data: "admin:panels:targets:{$panel->id}"));

        if ($panel->type === PanelType::ThreeXui) {
            $host = $s['sub_host'] ?? '(از آدرس پنل)';
            $port = $s['sub_port'] ?? 2096;
            $path = $s['sub_path'] ?? '/sub/';
            $lines[] = "لینک ساب: <code>{$host}:{$port}{$path}</code>";
            $kb->addRow(Btn::make('🌐 تنظیمات لینک ساب', callback_data: "admin:panels:sub:{$panel->id}"));
        }

        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: "admin:panels:view:{$panel->id}"));

        Reply::screen($bot, implode("\n", $lines), $kb);
    }
}
