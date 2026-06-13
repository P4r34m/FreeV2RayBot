<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Show/hide the user-side main-menu buttons (callback: admin:menubtns). */
class AdminMenuButtonsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $kb = InlineKeyboardMarkup::make();

        // Each row: move up / down / join-with-previous / toggle visibility.
        foreach (Keyboards::userButtonOrder() as $slug) {
            [$contentKey] = Keyboards::USER_BUTTONS[$slug];
            $state = Keyboards::buttonVisible($contentKey) ? '🟢' : '🔴';
            $join = Keyboards::buttonJoined($slug) ? '🔗' : '▫️';
            $kb->addRow(
                Btn::make('⬆️', callback_data: 'admin:menumove:up_'.$slug),
                Btn::make('⬇️', callback_data: 'admin:menumove:down_'.$slug),
                Btn::make($join, callback_data: 'admin:menujoin:'.$slug),
                Btn::make($state.' '.Content::buttonLabel($contentKey), callback_data: 'admin:menubtn:'.$slug),
            );
        }

        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:settings'));

        Reply::screen(
            $bot,
            "👁 <b>دکمه‌های کاربر</b>\n".
            "⬆️⬇️ ترتیب — نام دکمه: نمایش/پنهان — 🔗 کنار دکمه‌ی قبلی (هم‌ردیف)، ▫️ سطر جدا.",
            $kb,
        );
    }
}
