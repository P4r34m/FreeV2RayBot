<?php

namespace App\Enums;

/**
 * Lifecycle status of an issued config (a client/account on a panel).
 */
enum ConfigStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Disabled = 'disabled';
    case Failed = 'failed';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Expired => 'منقضی',
            self::Disabled => 'غیرفعال',
            self::Failed => 'ناموفق',
            self::Deleted => 'حذف‌شده',
        };
    }

    public function isUsable(): bool
    {
        return $this === self::Active;
    }
}
