<?php

namespace App\Enums;

/**
 * What a referral rule grants the referrer when it triggers.
 *  - Traffic: extra data, stored as bytes in reward_amount.
 *  - Duration: extra time, stored as days in reward_amount.
 *  - Both: traffic (bytes in reward_amount) AND time (days in reward_days).
 */
enum RewardType: string
{
    case Traffic = 'traffic';
    case Duration = 'duration';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Traffic => 'حجم اضافه',
            self::Duration => 'زمان اضافه',
            self::Both => 'حجم + زمان',
        };
    }
}
