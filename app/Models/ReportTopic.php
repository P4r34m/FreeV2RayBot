<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportTopic extends Model
{
    protected $guarded = ['id'];

    /** Brand prefix every report-topic name starts with. */
    public const PREFIX = 'FreeBot';

    protected function casts(): array
    {
        return [
            'thread_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Canonical report event => base (unbranded) Persian topic title.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'new_user' => 'کاربر جدید',
            'new_config' => 'کانفیگ جدید',
            'renew' => 'تمدید',
            'referral' => 'رفرال',
            'channel_join' => 'عضویت کانال',
            'blocked' => 'بلاک',
            'error' => 'خطا',
        ];
    }

    /**
     * A forum-topic name that always starts with the FreeBot brand prefix. Uses
     * the given title (admin-customized) when present, else the event default.
     */
    public static function brandedName(string $event, ?string $title = null): string
    {
        $base = trim((string) $title) !== ''
            ? trim((string) $title)
            : (self::defaults()[$event] ?? $event);

        return str_starts_with($base, self::PREFIX) ? $base : self::PREFIX.' | '.$base;
    }
}
