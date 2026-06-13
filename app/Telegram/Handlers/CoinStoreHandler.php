<?php

namespace App\Telegram\Handlers;

use App\Models\BotUser;
use App\Services\CoinStoreService;
use App\Services\ReferralService;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Coin store: list purchasable packages (callback: coin:store). */
class CoinStoreHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        /** @var BotUser $user */
        $user = $bot->get('botUser');

        if (app(ReferralService::class)->mode() !== 'coin') {
            Reply::screen($bot, Content::text('coin.disabled'), Keyboards::single('common.back', Keyboards::CB_MENU));

            return;
        }

        $plans = app(CoinStoreService::class)->plans();

        if ($plans->isEmpty()) {
            Reply::screen($bot, Content::text('coin.store_empty'), Keyboards::single('common.back', Keyboards::CB_REFERRAL));

            return;
        }

        $kb = InlineKeyboardMarkup::make();
        foreach ($plans as $plan) {
            $kb->addRow(Btn::make($plan->name.' — '.$plan->label(), callback_data: 'coin:plan:'.$plan->id));
        }
        $kb->addRow(Keyboards::backButton(Keyboards::CB_REFERRAL));

        Reply::screen($bot, Content::text('coin.store_header', ['coins' => $user->coins]), $kb);
    }
}
