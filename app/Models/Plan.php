<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The volume/duration package issued to users.
 */
class Plan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_limit_bytes' => 'integer',
            'duration_days' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    /** TTL in seconds for this plan (0 = no expiry). */
    public function durationSeconds(): int
    {
        return $this->duration_days > 0 ? $this->duration_days * 86400 : 0;
    }

    public static function default(): ?self
    {
        // Honor an explicitly chosen default plan id (set from the bot/web panel),
        // as long as it's still active; otherwise fall back to the is_default flag.
        $id = \App\Models\Setting::int(\App\Support\SettingKey::DEFAULT_PLAN_ID);
        if ($id > 0) {
            $plan = static::query()->where('is_active', true)->find($id);
            if ($plan) {
                return $plan;
            }
        }

        return static::query()->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();
    }
}
