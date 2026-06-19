<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Panel;
use App\Telegram\Conversations\AddPanelConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** List all panels with status + add launcher (callback: admin:panels). */
class AdminPanelsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $panels = Panel::orderBy('priority', 'desc')->orderBy('id')->get();

        $lines = ['🖥 <b>پنل‌ها</b>', ''];
        if ($panels->isEmpty()) {
            $lines[] = 'هنوز پنلی اضافه نشده است.';
        } else {
            foreach ($panels as $p) {
                $state = $p->is_active ? '🟢' : '🔴';
                $health = match ($p->health_status) {
                    'ok' => '✅ سالم',
                    'failed' => '⚠️ خطا',
                    default => '— نامشخص',
                };
                $lines[] = "{$state} <b>".e($p->name).'</b> · '.$p->type->label()." · {$health}";
            }
        }

        $kb = InlineKeyboardMarkup::make();
        foreach ($panels as $p) {
            $kb->addRow(Btn::make($p->name, callback_data: 'admin:panels:view:'.$p->id));
        }
        $kb->addRow(Btn::make('➕ افزودن پنل', callback_data: 'admin:panels:add'));
        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin'));

        Reply::screen($bot, implode("\n", $lines), $kb);
    }

    /** Launch the add-panel conversation (callback: admin:panels:add). */
    public static function startAdd(Nutgram $bot): void
    {
        Reply::toast($bot);
        AddPanelConversation::begin($bot);
    }
}
