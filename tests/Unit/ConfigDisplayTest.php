<?php

namespace Tests\Unit;

use App\Models\Config;
use App\Models\Plan;
use App\Support\Bytes;
use Tests\TestCase;

/**
 * Display helpers on the Config model: on-hold expiry phrasing, the unlimited
 * case, and "used" reading as ۰ (not "unlimited") when nothing is consumed.
 */
class ConfigDisplayTest extends TestCase
{
    public function test_expiry_human_shows_on_hold_duration_when_pending_first_use(): void
    {
        $config = new Config(['data_limit_bytes' => Bytes::GB, 'used_bytes' => 0]);
        $config->setRelation('plan', new Plan(['duration_days' => 30]));
        // expires_at is null → on hold, timer not started yet.

        $this->assertTrue($config->pendingFirstUse());
        $this->assertSame('30 روز (از اولین اتصال)', $config->expiryHuman());
    }

    public function test_expiry_human_is_unlimited_when_there_is_no_duration(): void
    {
        $config = new Config;
        $config->setRelation('plan', new Plan(['duration_days' => 0]));

        $this->assertFalse($config->pendingFirstUse());
        $this->assertSame('نامحدود ♾', $config->expiryHuman());
    }

    public function test_used_human_reads_zero_not_unlimited_when_nothing_consumed(): void
    {
        $this->assertSame('۰', (new Config(['used_bytes' => 0]))->usedHuman());
    }

    public function test_bytes_human_zero_is_caller_defined(): void
    {
        $this->assertSame('نامحدود', Bytes::human(0));      // default: a quota of 0 = unlimited
        $this->assertSame('۰', Bytes::human(0, '۰'));        // a consumed value of 0 = nothing
        $this->assertSame('1 GB', Bytes::human(Bytes::GB));
    }

    public function test_bytes_human_keeps_whole_numbers_intact(): void
    {
        // Trailing zeros of a whole number must NOT be stripped (the "100→1" bug).
        $this->assertSame('100 GB', Bytes::human(100 * Bytes::GB));
        $this->assertSame('200 GB', Bytes::human(200 * Bytes::GB));
        $this->assertSame('10 GB', Bytes::human(10 * Bytes::GB));
        $this->assertSame('500 B', Bytes::human(500));
        $this->assertSame('1.5 GB', Bytes::human((int) (1.5 * Bytes::GB)));
    }
}
