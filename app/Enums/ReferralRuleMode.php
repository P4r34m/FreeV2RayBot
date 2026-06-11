<?php

namespace App\Enums;

/**
 * How a referral rule's threshold is interpreted.
 *  - Recurring: grant the reward for *every* `threshold` verified referrals
 *    (e.g. every 5 invites => +10GB, repeatedly).
 *  - Milestone: grant once when the referrer's verified count *reaches* `threshold`
 *    (e.g. at 10 invites => +30 days, one time).
 */
enum ReferralRuleMode: string
{
    case Recurring = 'recurring';
    case Milestone = 'milestone';

    public function label(): string
    {
        return match ($this) {
            self::Recurring => 'تکرارشونده (به ازای هر N نفر)',
            self::Milestone => 'پلکانی (در رسیدن به N نفر)',
        };
    }
}
