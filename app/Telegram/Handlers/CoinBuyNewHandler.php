<?php

namespace App\Telegram\Handlers;

use App\Models\BotUser;
use App\Models\CoinPlan;
use App\Services\CoinStoreService;
use App\Services\Exceptions\InsufficientCoinsException;
use App\Services\PanelSelector;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Presenter;
use App\Telegram\Reply;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/** Buy a coin package as a NEW config (callback: coin:buynew:{id}). */
class CoinBuyNewHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        /** @var BotUser $user */
        $user = $bot->get('botUser');
        $plan = CoinPlan::where('is_active', true)->find((int) $id);

        if (! $plan) {
            Reply::toast($bot, 'بسته نامعتبر', alert: true);

            return;
        }

        if ($user->coins < $plan->coin_price) {
            Reply::screen(
                $bot,
                Content::text('coin.insufficient', ['coins' => $user->coins, 'price' => $plan->coin_price]),
                Keyboards::single('common.back', 'coin:store'),
            );

            return;
        }

        $panel = app(PanelSelector::class)->select();
        if (! $panel) {
            Reply::screen($bot, '⚠️ در حال حاضر سروری در دسترس نیست.', Keyboards::single('common.back', 'coin:store'));

            return;
        }

        Reply::toast($bot, '⏳ در حال ساخت...');

        try {
            $config = app(CoinStoreService::class)->buyNew($user, $plan, $panel);
            Reply::screen($bot, Presenter::configCaption($config), Keyboards::backMenu());
        } catch (InsufficientCoinsException) {
            Reply::screen(
                $bot,
                Content::text('coin.insufficient', ['coins' => $user->fresh()->coins, 'price' => $plan->coin_price]),
                Keyboards::single('common.back', 'coin:store'),
            );
        } catch (Throwable $e) {
            Log::error('Coin buy-new failed', ['user' => $user->telegram_id, 'plan' => $plan->id, 'error' => $e->getMessage()]);
            Reply::screen($bot, Content::text('coin.failed'), Keyboards::single('common.back', 'coin:store'));
        }
    }
}
