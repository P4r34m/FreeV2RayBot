<?php

namespace App\Models;

use App\Support\Bytes;
use Illuminate\Database\Eloquent\Model;

/**
 * A coin-priced package: volume + duration the user can buy with coins.
 */
class CoinPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_limit_bytes' => 'integer',
            'duration_days' => 'integer',
            'coin_price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** TTL in seconds for this package (0 = no expiry). */
    public function durationSeconds(): int
    {
        return $this->duration_days > 0 ? $this->duration_days * 86400 : 0;
    }

    /** "100 GB · 30 روز · 99 سکه" — one-line label for buttons/lists. */
    public function label(): string
    {
        $volume = $this->data_limit_bytes > 0 ? Bytes::human($this->data_limit_bytes) : 'نامحدود';
        $duration = $this->duration_days > 0 ? $this->duration_days.' روز' : 'بدون انقضا';

        return "{$volume} · {$duration} · {$this->coin_price} سکه";
    }
}
