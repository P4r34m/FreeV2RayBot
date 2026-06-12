<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\ReferralRule;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Toggle a referral rule's active flag (callback: admin:rules:toggle:{id}). */
class AdminRuleToggleHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $rule = ReferralRule::find((int) $id);

        if ($rule === null) {
            Reply::toast($bot, 'قانون یافت نشد', alert: true);
            (new AdminRulesHandler)($bot);

            return;
        }

        $rule->is_active = ! $rule->is_active;
        $rule->save();

        Reply::toast($bot, $rule->is_active ? '✅ فعال شد' : '⏹ غیرفعال شد');

        // Re-render the rule view with the refreshed state.
        (new AdminRuleViewHandler)($bot, (string) $rule->id);
    }
}
