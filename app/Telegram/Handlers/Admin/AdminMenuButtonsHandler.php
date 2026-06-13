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

        foreach (Keyboards::USER_BUTTONS as $slug => [$contentKey, $callback]) {
            $state = Keyboards::buttonVisible($contentKey) ? '🟢 نمایش' : '🔴 پنهان';
            $kb->addRow(Btn::make(
                Content::buttonLabel($contentKey).' — '.$state,
                callback_data: 'admin:menubtn:'.$slug,
            ));
        }

        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:settings'));

        Reply::screen(
            $bot,
            "👁 <b>نمایش دکمه‌های کاربر</b>\nبرای پنهان یا آشکار کردن هر دکمه روی آن بزنید:",
            $kb,
        );
    }
}
