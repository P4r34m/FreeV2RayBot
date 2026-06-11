<?php

namespace App\Models;

use App\Enums\RewardType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralRewardGrant extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reward_type' => RewardType::class,
            'reward_amount' => 'integer',
            'referral_count_at_grant' => 'integer',
        ];
    }

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ReferralRule::class, 'referral_rule_id');
    }
}
