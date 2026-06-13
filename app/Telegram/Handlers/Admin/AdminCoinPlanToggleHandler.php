<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\CoinPlan;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Toggle a coin package active/inactive (callback: admin:coinplans:toggle:{id}). */
class AdminCoinPlanToggleHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $plan = CoinPlan::find((int) $id);
        if (! $plan) {
            Reply::toast($bot, 'بسته یافت نشد', alert: true);

            return;
        }

        $plan->update(['is_active' => ! $plan->is_active]);
        Reply::toast($bot, $plan->is_active ? '✅ فعال شد' : '⏹ غیرفعال شد');

        (new AdminCoinPlanViewHandler)($bot, (string) $plan->id);
    }
}
