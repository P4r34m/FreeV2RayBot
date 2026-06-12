<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\ReferralRule;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;

/** Delete a referral rule then return to the list (callback: admin:rules:del:{id}). */
class AdminRuleDeleteHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $rule = ReferralRule::find((int) $id);

        if ($rule === null) {
            Reply::toast($bot, 'قانون یافت نشد', alert: true);
            (new AdminRulesHandler)($bot);

            return;
        }

        $rule->delete();

        Reply::toast($bot, '🗑 قانون حذف شد');

        // Back to the refreshed list.
        (new AdminRulesHandler)($bot);
    }
}
