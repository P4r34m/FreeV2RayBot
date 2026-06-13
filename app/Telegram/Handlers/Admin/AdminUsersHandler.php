<?php

namespace App\Telegram\Handlers\Admin;

use App\Telegram\Conversations\BlockUserConversation;
use App\Telegram\Conversations\GrantCoinsConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** User moderation submenu (callback: admin:users), plus block/unblock launchers. */
class AdminUsersHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $kb = InlineKeyboardMarkup::make()
            ->addRow(
                Btn::make('⛔️ مسدودسازی کاربر', callback_data: 'admin:block'),
                Btn::make('✅ رفع مسدودی', callback_data: 'admin:unblock'),
            )
            ->addRow(Btn::make('🪙 افزایش/کسر سکه کاربر', callback_data: 'admin:addcoins'))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin'));

        Reply::screen($bot, "⛔️ <b>مدیریت کاربران</b>\nمسدودسازی/رفع مسدودی یا تعیین سقف کانفیگ بر اساس آیدی عددی:", $kb);
    }

    public static function startGrantCoins(Nutgram $bot): void
    {
        Reply::toast($bot);

        /** @var GrantCoinsConversation $conv */
        $conv = $bot->getContainer()->get(GrantCoinsConversation::class);
        $conv($bot);
    }

    public static function startBlock(Nutgram $bot, string $action): void
    {
        Reply::toast($bot);

        /** @var BlockUserConversation $conv */
        $conv = $bot->getContainer()->get(BlockUserConversation::class);
        $conv->action = $action;
        $conv($bot);
    }
}
