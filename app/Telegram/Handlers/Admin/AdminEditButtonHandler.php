<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Content;
use App\Telegram\ContentDefaults;
use App\Telegram\Conversations\EditButtonConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Glass list of editable buttons; tap one to edit its label (admin:content:editbtn). */
class AdminEditButtonHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $kb = InlineKeyboardMarkup::make();
        foreach (array_keys(ContentDefaults::buttons()) as $key) {
            // Content::button() carries the configured premium-emoji icon + color, so
            // the admin sees exactly how each button looks here in the panel.
            $kb->addRow(Content::button($key, 'admin:content:editbtn:'.$key));
        }
        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:content'));

        Reply::screen(
            $bot,
            "🔘 <b>ویرایش دکمه‌ها</b>\nدکمه‌ای را که می‌خواهید عنوانش را تغییر دهید انتخاب کنید:",
            $kb,
        );
    }

    /** Edit a specific button's label (callback: admin:content:editbtn:{key}). */
    public static function editKey(Nutgram $bot, string $key): void
    {
        Reply::toast($bot);

        if (! array_key_exists($key, ContentDefaults::buttons())) {
            Reply::toast($bot, 'کلید نامعتبر', alert: true);

            return;
        }

        /** @var EditButtonConversation $conv */
        $conv = $bot->getContainer()->get(EditButtonConversation::class);
        $conv->key = $key;
        $conv($bot);
    }
}
