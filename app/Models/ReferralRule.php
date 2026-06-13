<?php

namespace App\Models;

use App\Enums\ReferralRuleMode;
use App\Enums\RewardType;
use App\Support\Bytes;
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
            'reward_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Human-readable reward, e.g. "10 GB", "30 روز", or "10 GB + 30 روز". */
    public function rewardLabel(): string
    {
        return match ($this->reward_type) {
            RewardType::Traffic => Bytes::human($this->reward_amount),
            RewardType::Duration => $this->reward_amount.' روز',
            RewardType::Both => Bytes::human($this->reward_amount).' + '.((int) $this->reward_days).' روز',
        };
    }
}
