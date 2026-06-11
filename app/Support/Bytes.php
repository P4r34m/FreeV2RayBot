<?php

namespace App\Support;

/**
 * Byte <-> human-readable helpers. Everything in the app stores bytes; this is
 * only for display and for converting admin-entered GB into bytes.
 */
class Bytes
{
    public const GB = 1073741824;        // 1024^3
    public const MB = 1048576;           // 1024^2

    public static function fromGb(float $gb): int
    {
        return (int) round($gb * self::GB);
    }

    public static function toGb(int $bytes): float
    {
        return $bytes / self::GB;
    }

    /** "12.5 GB", "750 MB", "نامحدود" for 0/negative. */
    public static function human(int $bytes): string
    {
        if ($bytes <= 0) {
            return 'نامحدود';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) min(floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        $formatted = $power === 0 ? (string) $bytes : number_format($value, $value >= 100 ? 0 : 2);

        return rtrim(rtrim($formatted, '0'), '.').' '.$units[$power];
    }
}
