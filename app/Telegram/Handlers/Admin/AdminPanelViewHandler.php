<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Panel;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Panel detail screen with actions (callback: admin:panels:view:{id}). */
class AdminPanelViewHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        $panel = Panel::find((int) $id);

        if ($panel === null) {
            Reply::toast($bot, '❌ پنل یافت نشد', alert: true);
            (new AdminPanelsHandler)($bot);

            return;
        }

        $this->render($bot, $panel);
    }

    /** Build and show the detail screen for a panel (reused after toggle). */
    public static function render(Nutgram $bot, Panel $panel): void
    {
        $state = $panel->is_active ? '🟢 فعال' : '🔴 غیرفعال';
        $health = match ($panel->health_status) {
            'ok' => '✅ سالم',
            'failed' => '⚠️ خطا',
            default => '— نامشخص',
        };

        $lines = [
            "🖥 <b>{$panel->name}</b>",
            '',
            'نوع: '.$panel->type->label(),
            'آدرس: <code>'.htmlspecialchars($panel->base_url, ENT_QUOTES).'</code>',
            "وضعیت: {$state}",
            "سلامت: {$health}",
        ];

        if (! empty($panel->health_message)) {
            $lines[] = 'پیام سلامت: '.htmlspecialchars($panel->health_message, ENT_QUOTES);
        }

        $toggleLabel = $panel->is_active ? '🔴 غیرفعال‌کردن' : '🟢 فعال‌کردن';

        $kb = InlineKeyboardMarkup::make()
            ->addRow(Btn::make('🔌 تست اتصال', callback_data: 'admin:panels:test:'.$panel->id))
            ->addRow(Btn::make($toggleLabel, callback_data: 'admin:panels:toggle:'.$panel->id))
            ->addRow(Btn::make('🗑 حذف', callback_data: 'admin:panels:del:'.$panel->id))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:panels'));

        Reply::screen($bot, implode("\n", $lines), $kb);
    }
}
