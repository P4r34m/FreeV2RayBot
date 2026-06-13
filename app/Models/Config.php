<?php

namespace App\Models;

use App\Enums\ConfigStatus;
use App\Support\Bytes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An issued config (a client/account on a panel) belonging to a bot user.
 *
 * @property ConfigStatus $status
 * @property \Carbon\CarbonImmutable|null $expires_at
 */
class Config extends Model
{
    protected $guarded = ['id'];

    /** How the config was obtained. */
    public const SOURCE_FREE = 'free';

    public const SOURCE_COIN = 'coin';

    protected static function booted(): void
    {
        // Keep the panel's active counter honest when an active config is deleted
        // (e.g. admin bulk-delete in Filament). Status transitions away from Active
        // are decremented by the commands that perform them.
        static::deleting(function (Config $config) {
            if ($config->status === ConfigStatus::Active && $config->panel_id) {
                Panel::whereKey($config->panel_id)
                    ->where('active_config_count', '>', 0)
                    ->decrement('active_config_count');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ConfigStatus::class,
            'config_links' => 'array',
            'panel_response' => 'array',
            'data_limit_bytes' => 'integer',
            'used_bytes' => 'integer',
            'expires_at' => 'immutable_datetime',
            'expiry_duration_days' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }

    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function remainingBytes(): int
    {
        if ($this->data_limit_bytes <= 0) {
            return PHP_INT_MAX;
        }

        return max(0, $this->data_limit_bytes - $this->used_bytes);
    }

    /** Consumed traffic; 0 reads as "۰", not "unlimited". */
    public function usedHuman(): string
    {
        return Bytes::human($this->used_bytes, '۰');
    }

    public function limitHuman(): string
    {
        return $this->data_limit_bytes > 0 ? Bytes::human($this->data_limit_bytes) : 'نامحدود';
    }

    /**
     * Duration in days for the on-hold timer (0 = none/unknown). Prefers the value
     * captured at issuance so it survives the plan being edited or deleted; falls
     * back to the live plan for older rows.
     */
    public function durationDays(): int
    {
        return (int) ($this->expiry_duration_days ?? $this->plan?->duration_days ?? 0);
    }

    /**
     * On-hold and not yet started: no absolute expiry has been set, but the plan
     * carries a finite duration (the clock starts on the user's first connection).
     */
    public function pendingFirstUse(): bool
    {
        return $this->expires_at === null && $this->durationDays() > 0;
    }

    /**
     * Human expiry label: an absolute date+diff once started, "<N> روز (از اولین
     * اتصال)" while on-hold, or "نامحدود ♾" for a genuinely unlimited config.
     */
    public function expiryHuman(): string
    {
        if ($this->expires_at) {
            return $this->expires_at->format('Y-m-d').' ('.$this->expires_at->diffForHumans().')';
        }

        if ($this->pendingFirstUse()) {
            return $this->durationDays().' روز (از اولین اتصال)';
        }

        return 'نامحدود ♾';
    }
}
