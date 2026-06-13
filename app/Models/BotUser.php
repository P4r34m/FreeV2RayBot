<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Telegram end-user of the bot.
 *
 * @property int $id
 * @property int $telegram_id
 * @property string|null $username
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $language_code
 * @property bool $is_admin
 * @property bool $is_blocked
 * @property int|null $referred_by
 * @property int $referral_count
 * @property int $referral_rewarded_count
 * @property int $bonus_traffic_bytes
 * @property int $bonus_days
 * @property int $coins
 * @property int|null $max_configs
 */
class BotUser extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
            'blocked_until' => 'datetime',
            'spam_strikes' => 'integer',
            'referral_count' => 'integer',
            'referral_rewarded_count' => 'integer',
            'bonus_traffic_bytes' => 'integer',
            'bonus_days' => 'integer',
            'coins' => 'integer',
            'max_configs' => 'integer',
            'last_started_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function configs(): HasMany
    {
        return $this->hasMany(Config::class);
    }

    /** The currently usable config, if any. */
    public function activeConfig(): HasMany
    {
        return $this->configs()->where('status', \App\Enums\ConfigStatus::Active->value);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(BotUser::class, 'referred_by');
    }

    public function referredUsers(): HasMany
    {
        return $this->hasMany(BotUser::class, 'referred_by');
    }

    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function rewardGrants(): HasMany
    {
        return $this->hasMany(ReferralRewardGrant::class);
    }

    /** Permanently blocked, or currently inside a temporary (anti-spam) block. */
    public function isAccessBlocked(): bool
    {
        return $this->is_blocked
            || ($this->blocked_until !== null && $this->blocked_until->isFuture());
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: ('کاربر '.$this->telegram_id);
    }

    public function displayHandle(): string
    {
        return $this->username ? '@'.$this->username : $this->fullName();
    }
}
