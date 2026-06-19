<?php

namespace App\Telegram\Handlers;

use App\Models\BotUser;
use App\Models\CoinPlan;
use App\Models\Setting;
use App\Services\CoinStoreService;
use App\Services\Exceptions\InsufficientCoinsException;
use App\Support\SettingKey;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Presenter;
use App\Telegram\Reply;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/** Apply a coin package to an existing config (callback: coin:buyextc:{planId}_{configId}). */
class CoinExtendHandler
{
    public function __invoke(Nutgram $bot, string $combo): void
    {
        /** @var BotUser $user */
        $user = $bot->get('botUser');

        [$planId, $configId] = array_pad(explode('_', $combo, 2), 2, null);

        $plan = CoinPlan::where('is_active', true)->find((int) $planId);
        $config = $user->configs()->whereKey((int) $configId)->with(['panel', 'plan'])->first();

        if (! $plan || ! $config) {
            Reply::toast($bot, 'درخواست نامعتبر', alert: true);

            return;
        }

        // Respect the admin switch even against a stale/crafted callback.
        if (! Setting::bool(SettingKey::COIN_EXTEND_ENABLED, true)) {
            Reply::toast($bot, 'افزودن به اشتراک موجود غیرفعال است.', alert: true);

            return;
        }

        // Hard guard (even against a crafted callback): the free config stays fixed.
        if ($config->source !== \App\Models\Config::SOURCE_COIN) {
            Reply::toast($bot, 'فقط کانفیگ‌های خریداری‌شده با سکه قابل افزایش‌اند.', alert: true);

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

        Reply::toast($bot, '⏳ در حال اعمال...');

        try {
            $config = app(CoinStoreService::class)->buyExtend($user, $plan, $config);
            Reply::screen(
                $bot,
                Content::text('coin.extended')."\n\n".Presenter::accountStatus($config),
                Keyboards::backMenu(),
            );
        } catch (InsufficientCoinsException) {
            Reply::screen(
                $bot,
                Content::text('coin.insufficient', ['coins' => $user->fresh()->coins, 'price' => $plan->coin_price]),
                Keyboards::single('common.back', 'coin:store'),
            );
        } catch (Throwable $e) {
            Log::error('Coin buy-extend failed', ['user' => $user->telegram_id, 'plan' => $plan->id, 'error' => $e->getMessage()]);
            Reply::screen($bot, Content::text('coin.failed'), Keyboards::single('common.back', 'coin:store'));
        }
    }
}
