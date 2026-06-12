<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Content editing menu — texts & buttons (callback: admin:content). */
class AdminContentHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $kb = InlineKeyboardMarkup::make()
            ->addRow(Btn::make('✏️ ویرایش متن‌ها', callback_data: 'admin:content:edittext'))
            ->addRow(Btn::make('🔘 ویرایش دکمه‌ها', callback_data: 'admin:content:editbtn'))
            ->addRow(Btn::make('📋 لیست کلیدها', callback_data: 'admin:content:keys'))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin'));

        Reply::screen(
            $bot,
            "✏️ <b>متن‌ها و دکمه‌ها</b>\n\n".
            "از این بخش می‌توانید متن‌های ربات و عنوان دکمه‌ها را ویرایش کنید.\n".
            'برای مشاهده‌ی کلیدهای قابل ویرایش، «لیست کلیدها» را بزنید.',
            $kb,
        );
    }
}
