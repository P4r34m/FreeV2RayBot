<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\RequiredChannel;
use App\Telegram\Conversations\AddChannelConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** List required channels with join stats + add launcher (callback: admin:channels). */
class AdminChannelsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $channels = RequiredChannel::orderBy('sort_order')->get();

        $lines = ['📡 <b>کانال‌های اجباری</b>', ''];
        if ($channels->isEmpty()) {
            $lines[] = 'هنوز کانالی اضافه نشده است.';
        } else {
            foreach ($channels as $c) {
                $state = $c->is_active ? '🟢' : '🔴';
                $type = $c->is_private ? 'خصوصی' : 'عمومی';
                $lines[] = "{$state} <b>{$c->title}</b> ({$type})\n   👥 عضو: ".($c->member_count ?? '—')." | ورود از لینک: {$c->join_count}";
            }
        }

        $kb = InlineKeyboardMarkup::make()
            ->addRow(Btn::make('➕ افزودن کانال (فوروارد پیام)', callback_data: 'admin:addchannel'))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin'));

        Reply::screen($bot, implode("\n", $lines), $kb);
    }

    /** Launch the add-channel forward conversation (callback: admin:addchannel). */
    public static function startAdd(Nutgram $bot): void
    {
        Reply::toast($bot);
        AddChannelConversation::begin($bot);
    }
}
