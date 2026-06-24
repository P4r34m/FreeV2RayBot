<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Enums\PanelType;
use App\Models\BotUser;
use App\Models\Config;
use App\Models\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Bulk-move every active config on a PasarGuard panel onto one group/inbound. */
class SetPanelInboundTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://panel.example.com';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::swap(Cache::store('array'));
    }

    private function pasarGuardPanel(): Panel
    {
        return Panel::create([
            'name' => 'DE', 'type' => PanelType::PasarGuard, 'base_url' => self::BASE,
            'username' => 'admin', 'password' => 'secret', 'is_active' => true,
        ]);
    }

    public function test_execute_assigns_every_active_config_to_the_group_and_sets_the_default(): void
    {
        Http::fake([
            self::BASE.'/api/admin/token' => Http::response(['access_token' => 'tok-123']),
            self::BASE.'/api/groups' => Http::response(['groups' => [['id' => 30, 'name' => 'DE-30'], ['id' => 5, 'name' => 'Other']], 'total' => 2]),
            self::BASE.'/api/user/*' => Http::response(['username' => 'x'], 200),
        ]);

        $panel = $this->pasarGuardPanel();
        $user = BotUser::create(['telegram_id' => 8600]);
        foreach (['fvuser1', 'fvuser2'] as $id) {
            $user->configs()->create([
                'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE,
                'remote_identifier' => $id, 'status' => ConfigStatus::Active,
            ]);
        }

        Artisan::call('panels:set-inbound', ['panel' => $panel->id, 'group' => '30', '--execute' => true]);

        // Panel default updated for future issuances.
        $this->assertSame([30], $panel->fresh()->settings['group_ids']);

        // A partial PUT with group_ids=[30] went out for the users.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/user/')
            && $r->method() === 'PUT'
            && $r->data()['group_ids'] === [30]);
    }

    public function test_unknown_group_is_rejected_and_nothing_changes(): void
    {
        Http::fake([
            self::BASE.'/api/admin/token' => Http::response(['access_token' => 'tok-123']),
            self::BASE.'/api/groups' => Http::response(['groups' => [['id' => 30, 'name' => 'DE-30']], 'total' => 1]),
        ]);

        $panel = $this->pasarGuardPanel();

        Artisan::call('panels:set-inbound', ['panel' => $panel->id, 'group' => '999', '--execute' => true]);

        // Group 999 doesn't exist → the panel default must stay untouched.
        $this->assertNull($panel->fresh()->settings['group_ids'] ?? null);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/api/user/') && $r->method() === 'PUT');
    }
}
