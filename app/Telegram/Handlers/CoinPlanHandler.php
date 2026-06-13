<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Models\CoinPlan;
use App\Support\Bytes;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Coin package detail + buy options (callback: coin:plan:{id}). */
class CoinPlanHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        /** @var BotUser $user */
        $user = $bot->get('botUser');
        $plan = CoinPlan::where('is_active', true)->find((int) $id);

        if (! $plan) {
            Reply::screen($bot, '⚠️ بسته یافت نشد.', Keyboards::single('common.back', 'coin:store'));

            return;
        }

        $hasConfigs = $user->configs()->where('status', ConfigStatus::Active->value)->exists();

        $kb = InlineKeyboardMarkup::make()
            ->addRow(Btn::make('🆕 کانفیگ جدید', callback_data: 'coin:buynew:'.$plan->id));
        if ($hasConfigs) {
            $kb->addRow(Btn::make('➕ افزودن به اشتراک موجود', callback_data: 'coin:buyext:'.$plan->id));
        }
        $kb->addRow(Keyboards::backButton('coin:store'));

        Reply::screen($bot, Content::text('coin.plan_body', [
            'name' => $plan->name,
            'volume' => $plan->data_limit_bytes > 0 ? Bytes::human($plan->data_limit_bytes) : 'نامحدود',
            'duration' => $plan->duration_days > 0 ? $plan->duration_days.' روز' : 'بدون انقضا',
            'price' => $plan->coin_price,
            'coins' => $user->coins,
        ]), $kb);
    }
}
