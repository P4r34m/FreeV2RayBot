<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Plan;
use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Delete a plan (callback: admin:plans:del:{id}). */
class AdminPlanDeleteHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $plan = Plan::find((int) $id);

        if (! $plan) {
            Reply::toast($bot, 'پلن یافت نشد.', alert: true);
            (new AdminPlansHandler)($bot);

            return;
        }

        // Clear the stored default pointer if this plan was it.
        if (Setting::int(SettingKey::DEFAULT_PLAN_ID) === (int) $plan->id) {
            Setting::put(SettingKey::DEFAULT_PLAN_ID, null);
        }

        $plan->delete();

        Reply::toast($bot, 'پلن حذف شد 🗑');

        (new AdminPlansHandler)($bot);
    }
}
