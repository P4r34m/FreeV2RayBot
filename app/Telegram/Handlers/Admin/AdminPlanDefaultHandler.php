<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Plan;
use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Make a plan the single default (callback: admin:plans:default:{id}). */
class AdminPlanDefaultHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $plan = Plan::find((int) $id);

        if (! $plan) {
            Reply::toast($bot, 'پلن یافت نشد.', alert: true);
            (new AdminPlansHandler)($bot);

            return;
        }

        Plan::query()->update(['is_default' => false]);
        $plan->is_default = true;
        $plan->save();

        Setting::put(SettingKey::DEFAULT_PLAN_ID, $plan->id);

        Reply::toast($bot, 'به‌عنوان پیش‌فرض تنظیم شد ⭐');

        AdminPlanViewHandler::render($bot, $plan->refresh());
    }
}
