<?php

namespace Tests\Unit;

use App\Enums\ConfigStatus;
use App\Models\Config;
use App\Models\Plan;
use App\Support\Bytes;
use App\Telegram\Presenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Display helpers on the Config model: on-hold expiry phrasing, the unlimited
 * case, and "used" reading as ۰ (not "unlimited") when nothing is consumed.
 */
class ConfigDisplayTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_account_status_all_caps_long_lists_and_notes_the_remainder(): void
    {
        $configs = collect(range(1, 10))->map(fn (int $i) => new Config([
            'data_limit_bytes' => Bytes::GB,
            'used_bytes' => 0,
            'subscription_url' => 'https://sub.example.com/'.$i,
            'expiry_duration_days' => 30, // on-hold; display uses the column, no plan needed
            'status' => ConfigStatus::Active,
        ]));

        $text = Presenter::accountStatusAll($configs);

        // 10 configs, cap of 8 -> 2 noted as remainder.
        $this->assertStringContainsString('اشتراک دیگر', $text);
    }
}
