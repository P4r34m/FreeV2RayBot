<?php

namespace App\Telegram\Handlers;

use App\Models\BotUser;
use App\Models\Setting;
use App\Services\ReferralService;
use App\Support\SettingKey;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Presenter;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** "زیرمجموعه‌گیری" — referral link, stats and reward rules (callback: referral). */
class ReferralHandler
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        if (! $this->referrals->enabled()) {
            Reply::screen(
                $bot,
                Content::text('referral.disabled'),
                Keyboards::single('common.back', Keyboards::CB_MENU),
            );

            return;
        }

        $link = $this->referralLink($user);
        $rules = $this->referrals->describeRules();

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(Content::button(
                'referral.share',
                url: 'https://t.me/share/url?url='.urlencode($link).'&text='.urlencode(Content::text('referral.share_text')),
            ))
            ->addRow(Keyboards::backButton());

        Reply::screen($bot, Presenter::referral($user, $link, $rules), $keyboard);
    }

    protected function referralLink(BotUser $user): string
    {
        $username = ltrim(
            Setting::string(SettingKey::BOT_USERNAME, (string) config('v2raybot.bot.username')),
            '@'
        );

        return "https://t.me/{$username}?start=ref_{$user->telegram_id}";
    }
}
