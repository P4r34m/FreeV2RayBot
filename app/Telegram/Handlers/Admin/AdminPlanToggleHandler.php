<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Plan;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Toggle a plan's active state (callback: admin:plans:toggle:{id}). */
class AdminPlanToggleHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $plan = Plan::find((int) $id);

        if (! $plan) {
            Reply::toast($bot, 'پلن یافت نشد.', alert: true);
            (new AdminPlansHandler)($bot);

            return;
        }

        $plan->is_active = ! $plan->is_active;
        $plan->save();

        Reply::toast($bot, $plan->is_active ? 'فعال شد 🟢' : 'غیرفعال شد 🔴');

        AdminPlanViewHandler::render($bot, $plan);
    }
}
