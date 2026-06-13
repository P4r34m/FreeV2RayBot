<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\CoinPlan;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Delete a coin package (callback: admin:coinplans:del:{id}). */
class AdminCoinPlanDeleteHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        CoinPlan::whereKey((int) $id)->delete();
        Reply::toast($bot, '🗑 حذف شد');

        (new AdminCoinPlansHandler)($bot);
    }
}
