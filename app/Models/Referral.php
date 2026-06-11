<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(BotUser::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(BotUser::class, 'referred_id');
    }
}
