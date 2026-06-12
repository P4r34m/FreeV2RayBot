<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\ContentDefaults;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** List every editable text & button key (callback: admin:content:keys). */
class AdminContentKeysHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $lines = ['📋 <b>کلیدهای متن</b>', ''];
        foreach (array_keys(ContentDefaults::texts()) as $key) {
            $lines[] = '<code>'.$key.'</code>';
        }

        $lines[] = '';
        $lines[] = '🔘 <b>کلیدهای دکمه</b>';
        $lines[] = '';
        foreach (array_keys(ContentDefaults::buttons()) as $key) {
            $lines[] = '<code>'.$key.'</code>';
        }

        $kb = InlineKeyboardMarkup::make()
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:content'));

        Reply::screen($bot, implode("\n", $lines), $kb);
    }
}
