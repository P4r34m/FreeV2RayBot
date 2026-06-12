<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Tutorial;
use App\Telegram\Conversations\AddTutorialConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** List tutorials with add launcher (callback: admin:tutorials). */
class AdminTutorialsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $tutorials = Tutorial::orderBy('sort_order')->orderBy('id')->get();

        $lines = ['📚 <b>آموزش‌ها</b>', ''];
        if ($tutorials->isEmpty()) {
            $lines[] = 'هنوز آموزشی اضافه نشده است.';
        } else {
            $lines[] = 'برای مشاهده/مدیریت، روی هر آموزش بزنید:';
        }

        $kb = InlineKeyboardMarkup::make();

        foreach ($tutorials as $t) {
            $cat = $t->category ? ' ['.$t->category.']' : '';
            $state = $t->is_active ? '🟢' : '🔴';
            $kb->addRow(Btn::make(
                "{$t->title}{$cat} {$state}",
                callback_data: 'admin:tutorials:view:'.$t->id,
            ));
        }

        $kb->addRow(Btn::make('➕ افزودن آموزش', callback_data: 'admin:tutorials:add'));
        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin'));

        Reply::screen($bot, implode("\n", $lines), $kb);
    }

    /** Launch the add-tutorial conversation (callback: admin:tutorials:add). */
    public static function startAdd(Nutgram $bot): void
    {
        Reply::toast($bot);
        AddTutorialConversation::begin($bot);
    }
}
