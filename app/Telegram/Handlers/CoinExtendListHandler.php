<?php

namespace App\Telegram\Handlers;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Models\CoinPlan;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Pick which existing config a coin package tops up (callback: coin:buyext:{id}). */
class CoinExtendListHandler
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

        $configs = $user->configs()
            ->where('status', ConfigStatus::Active->value)
            ->with('panel')
            ->latest()
            ->get();

        if ($configs->isEmpty()) {
            Reply::screen($bot, Content::text('coin.no_configs'), Keyboards::single('common.back', 'coin:plan:'.$plan->id));

            return;
        }

        $kb = InlineKeyboardMarkup::make();
        foreach ($configs as $config) {
            $kb->addRow(Btn::make(
                '🔑 '.($config->panel?->name ?? 'اشتراک').' — '.$config->limitHuman(),
                callback_data: 'coin:buyextc:'.$plan->id.'_'.$config->id,
            ));
        }
        $kb->addRow(Keyboards::backButton('coin:plan:'.$plan->id));

        Reply::screen($bot, Content::text('coin.pick_config'), $kb);
    }
}
