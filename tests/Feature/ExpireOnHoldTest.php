<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unused on-hold configs (expires_at = null) must be reclaimed once their duration
 * window has elapsed, so they don't hold the free slot / panel capacity forever.
 */
class ExpireOnHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_unused_on_hold_config_is_expired_after_its_window(): void
    {
        $config = BotUser::create(['telegram_id' => 1])->configs()->create([
            'remote_identifier' => 'fv_old',
            'status' => ConfigStatus::Active,
            'expires_at' => null,
            'expiry_duration_days' => 1,
        ]);
        // Issued 2 days ago, 1-day window, never connected -> should be reclaimed.
        $config->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->artisan('configs:expire')->assertSuccessful();

        $this->assertSame(ConfigStatus::Expired, $config->fresh()->status);
    }

    public function test_recent_on_hold_config_is_not_expired(): void
    {
        $config = BotUser::create(['telegram_id' => 2])->configs()->create([
            'remote_identifier' => 'fv_new',
            'status' => ConfigStatus::Active,
            'expires_at' => null,
            'expiry_duration_days' => 30, // created now, well within the window
        ]);

        $this->artisan('configs:expire')->assertSuccessful();

        $this->assertSame(ConfigStatus::Active, $config->fresh()->status);
    }
}
