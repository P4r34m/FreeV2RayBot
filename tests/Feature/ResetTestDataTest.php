<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Enums\PanelType;
use App\Models\BotUser;
use App\Models\Panel;
use App\Models\Plan;
use App\Support\Bytes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** bot:reset-data clears test data but keeps panels/plans/settings and resets counters. */
class ResetTestDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_clears_users_and_configs_but_keeps_panels_and_plans(): void
    {
        $panel = Panel::create([
            'name' => 'P', 'type' => PanelType::ThreeXui, 'base_url' => 'https://p.example.com',
            'is_active' => true, 'active_config_count' => 5,
        ]);
        $plan = Plan::create(['name' => 'free', 'data_limit_bytes' => Bytes::GB, 'duration_days' => 7, 'is_active' => true]);

        $user = BotUser::create(['telegram_id' => 7, 'coins' => 50]);
        $user->configs()->create(['panel_id' => $panel->id, 'remote_identifier' => 'fv_a', 'status' => ConfigStatus::Active]);

        $this->artisan('bot:reset-data', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, BotUser::count());
        $this->assertSame(0, \App\Models\Config::count());

        // Kept.
        $this->assertSame(1, Panel::count());
        $this->assertSame(1, Plan::count());
        // Counter reset.
        $this->assertSame(0, $panel->fresh()->active_config_count);
    }
}
