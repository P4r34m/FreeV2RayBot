<?php

namespace App\Services;

use App\Models\BotUser;
use App\Models\CoinPlan;
use App\Models\Config;
use App\Models\Panel;
use App\Services\Exceptions\InsufficientCoinsException;
use Illuminate\Support\Collection;

/**
 * The coin store: spend coins (earned from referrals) on coin-priced packages,
 * either as a new config or as a top-up on an existing one. Coins are reserved
 * atomically before the panel call and refunded if issuance fails.
 */
class CoinStoreService
{
    public function __construct(
        protected readonly ConfigIssuanceService $issuer,
    ) {}

    /** @return Collection<int, CoinPlan> active packages, cheapest-ordered. */
    public function plans(): Collection
    {
        return CoinPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('coin_price')
            ->get();
    }

    /** Buy a package as a brand-new config on $panel. */
    public function buyNew(BotUser $user, CoinPlan $plan, Panel $panel): Config
    {
        $this->reserve($user, $plan);

        try {
            return $this->issuer->issuePackage($user, $panel, $plan->data_limit_bytes, $plan->duration_days);
        } catch (\Throwable $e) {
            $this->refund($user, $plan);
            throw $e;
        }
    }

    /** Buy a package as a top-up on an existing COIN config (the free one stays fixed). */
    public function buyExtend(BotUser $user, CoinPlan $plan, Config $config): Config
    {
        if ($config->source !== Config::SOURCE_COIN) {
            throw new \InvalidArgumentException('Only coin configs can be topped up with coins.');
        }

        $this->reserve($user, $plan);

        try {
            return $this->issuer->extendConfig($config, $plan->data_limit_bytes, $plan->duration_days);
        } catch (\Throwable $e) {
            $this->refund($user, $plan);
            throw $e;
        }
    }

    /**
     * Atomically deduct the price (only if the balance covers it) so concurrent
     * purchases can't overspend. Throws InsufficientCoinsException otherwise.
     */
    protected function reserve(BotUser $user, CoinPlan $plan): void
    {
        $affected = BotUser::whereKey($user->id)
            ->where('coins', '>=', $plan->coin_price)
            ->decrement('coins', $plan->coin_price);

        if ($affected === 0) {
            throw new InsufficientCoinsException('سکه کافی نیست.');
        }

        $user->refresh();
    }

    protected function refund(BotUser $user, CoinPlan $plan): void
    {
        BotUser::whereKey($user->id)->increment('coins', $plan->coin_price);
        $user->refresh();
    }
}
