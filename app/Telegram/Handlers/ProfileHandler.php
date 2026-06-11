<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Support\Bytes;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** "پروفایل" — the user's id, account summary and history entry (callback: profile). */
class ProfileHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        $text = Content::text('profile.body', [
            'id' => $user->telegram_id,
            'name' => $user->fullName(),
            'joined' => $user->created_at?->format('Y-m-d') ?? '-',
            'configs' => $user->configs()->count(),
            'active' => $user->configs()->where('status', ConfigStatus::Active->value)->count(),
            'referrals' => $user->referral_count,
            'bonus_traffic' => Bytes::human($user->bonus_traffic_bytes),
            'bonus_days' => $user->bonus_days,
        ]);

        $kb = InlineKeyboardMarkup::make()
            ->addRow(Content::button('profile.history', Keyboards::CB_PROFILE_HISTORY))
            ->addRow(Keyboards::backButton());

        Reply::screen($bot, $text, $kb);
    }
}
