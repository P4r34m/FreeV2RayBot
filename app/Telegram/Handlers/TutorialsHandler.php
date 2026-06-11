<?php

namespace App\Telegram\Handlers;

use App\Models\Tutorial;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** "آموزش‌ها" — flat list of tutorials (callback: tutorials). */
class TutorialsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $tutorials = Tutorial::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('category')
            ->get();

        if ($tutorials->isEmpty()) {
            Reply::screen(
                $bot,
                Content::text('tutorials.empty'),
                Keyboards::single('common.back', Keyboards::CB_MENU),
            );

            return;
        }

        $kb = InlineKeyboardMarkup::make();
        foreach ($tutorials as $tutorial) {
            $label = ($tutorial->category ? '['.$tutorial->category.'] ' : '').$tutorial->title;
            $kb->addRow(Btn::make('📄 '.$label, callback_data: 'tutorial:show:'.$tutorial->id));
        }
        $kb->addRow(Keyboards::backButton());

        Reply::screen($bot, Content::text('tutorials.header'), $kb);
    }
}
