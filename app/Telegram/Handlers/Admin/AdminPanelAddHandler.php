<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Step 1 of adding a panel: pick the panel TYPE via inline buttons (admin:panels:add). */
class AdminPanelAddHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $kb = InlineKeyboardMarkup::make()
            ->addRow(Btn::make('🟢 3x-ui (سنایی)', callback_data: 'admin:panels:addtype:3xui'))
            ->addRow(Btn::make('🔵 PasarGuard', callback_data: 'admin:panels:addtype:pasarguard'))
            ->addRow(Btn::make('🟣 Remnawave', callback_data: 'admin:panels:addtype:remnawave'))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:panels'));

        Reply::screen($bot, "🖥 <b>افزودن پنل</b>\n\nنوع پنل را انتخاب کنید:", $kb);
    }
}
