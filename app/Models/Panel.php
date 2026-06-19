<?php

namespace App\Models;

use App\Enums\ConfigStatus;
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
            'coin_capacity' => 'integer',
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

    /**
     * The configured cap for a given config source. Free configs are limited by
     * `capacity`, coin-purchased ones by the SEPARATE `coin_capacity` (null/-1 =
     * unlimited for each, independently).
     */
    public function capacityFor(string $source): ?int
    {
        return $source === Config::SOURCE_COIN ? $this->coin_capacity : $this->capacity;
    }

    /** Active configs of a given source currently live on this panel (live count). */
    public function activeCountFor(string $source): int
    {
        return $this->configs()
            ->where('status', ConfigStatus::Active->value)
            ->where('source', $source)
            ->count();
    }

    /** Unlimited capacity for this source (no cap set, or cap = -1). */
    public function isUnlimited(string $source = Config::SOURCE_FREE): bool
    {
        $cap = $this->capacityFor($source);

        return $cap === null || $cap < 0;
    }

    public function hasCapacity(string $source = Config::SOURCE_FREE): bool
    {
        return $this->isUnlimited($source) || $this->activeCountFor($source) < $this->capacityFor($source);
    }

    /** Remaining slots for this source, or null when unlimited. */
    public function remainingConfigs(string $source = Config::SOURCE_FREE): ?int
    {
        return $this->isUnlimited($source)
            ? null
            : max(0, $this->capacityFor($source) - $this->activeCountFor($source));
    }

    /** "نامحدود" or the remaining count for this source as a string. */
    public function remainingHuman(string $source = Config::SOURCE_FREE): string
    {
        $remaining = $this->remainingConfigs($source);

        return $remaining === null ? 'نامحدود' : (string) $remaining;
    }
}
