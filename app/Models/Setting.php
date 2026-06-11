<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * JSON-valued key/value settings with a small cache. Use the static helpers
 * (Setting::get/put/bool/int) everywhere instead of querying directly.
 */
class Setting extends Model
{
    protected $guarded = ['id'];

    public $timestamps = true;

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }

    /** @return array<string, mixed> all settings decoded, cached for an hour */
    public static function all(...$args): array
    {
        return Cache::remember('settings.all', 3600, function () {
            return static::query()->get()->mapWithKeys(fn (Setting $s) => [
                $s->key => json_decode((string) $s->value, true),
            ])->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all()[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) static::get($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = static::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }
}
