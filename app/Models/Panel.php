<?php

namespace App\Models;

use App\Enums\PanelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered V2Ray panel/server. Credentials are encrypted at rest.
 *
 * @property PanelType $type
 * @property array|null $settings
 */
class Panel extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['username', 'password', 'api_token'];

    protected function casts(): array
    {
        return [
            'type' => PanelType::class,
            'settings' => 'array',
            'username' => 'encrypted',
            'password' => 'encrypted',
            'api_token' => 'encrypted',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'capacity' => 'integer',
            'active_config_count' => 'integer',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function configs(): HasMany
    {
        return $this->hasMany(Config::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function isHealthy(): bool
    {
        return $this->health_status === 'ok';
    }

    public function hasCapacity(): bool
    {
        return $this->isUnlimited() || $this->active_config_count < $this->capacity;
    }

    /** Unlimited config capacity (no cap set, or capacity = -1). */
    public function isUnlimited(): bool
    {
        return $this->capacity === null || $this->capacity < 0;
    }

    /** Remaining config slots, or null when capacity is unlimited. */
    public function remainingConfigs(): ?int
    {
        return $this->isUnlimited() ? null : max(0, $this->capacity - $this->active_config_count);
    }

    /** "نامحدود" or the remaining count as a string. */
    public function remainingHuman(): string
    {
        $remaining = $this->remainingConfigs();

        return $remaining === null ? 'نامحدود' : (string) $remaining;
    }
}
