<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Enums\PanelType;
use App\Models\BotUser;
use App\Models\Config;
use App\Models\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_active_config_frees_panel_capacity(): void
    {
        $panel = Panel::create([
            'name' => 'p1',
            'type' => PanelType::ThreeXui,
            'base_url' => 'https://example.com',
            'active_config_count' => 1,
        ]);

        $user = BotUser::create(['telegram_id' => 7777]);

        $config = $user->configs()->create([
            'panel_id' => $panel->id,
            'remote_identifier' => 'fv_7777_abcde',
            'status' => ConfigStatus::Active,
        ]);

        $config->delete();

        $this->assertSame(0, $panel->fresh()->active_config_count);
    }

    public function test_deleting_non_active_config_does_not_change_counter(): void
    {
        $panel = Panel::create([
            'name' => 'p2',
            'type' => PanelType::ThreeXui,
            'base_url' => 'https://example.com',
            'active_config_count' => 1,
        ]);

        $user = BotUser::create(['telegram_id' => 8888]);

        $config = $user->configs()->create([
            'panel_id' => $panel->id,
            'remote_identifier' => 'fv_8888_zzzzz',
            'status' => ConfigStatus::Expired,
        ]);

        $config->delete();

        // Expired configs were already uncounted, so the counter must stay put.
        $this->assertSame(1, $panel->fresh()->active_config_count);
    }
}
