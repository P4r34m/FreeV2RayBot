<?php

namespace App\Models;

use App\Enums\ReferralRuleMode;
use App\Enums\RewardType;
use Illuminate\Database\Eloquent\Model;

class ReferralRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mode' => ReferralRuleMode::class,
            'reward_type' => RewardType::class,
            'threshold' => 'integer',
            'reward_amount' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
