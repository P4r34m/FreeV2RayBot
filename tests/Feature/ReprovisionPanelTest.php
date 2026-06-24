<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Enums\PanelType;
use App\Models\BotUser;
use App\Models\Config;
use App\Models\Panel;
use App\Panels\Contracts\PanelDriver;
use App\Panels\Data\ConfigSpec;
use App\Panels\Data\ConfigUsage;
use App\Panels\Data\IssuedConfig;
use App\Panels\PanelManager;
use App\Support\Bytes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Re-creating accounts on a re-pointed panel works in place: same row, new link. */
class ReprovisionPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_recreates_in_place_without_duplicating(): void
    {
        $panel = $this->fakePanel();
        $user = BotUser::create(['telegram_id' => 8500]);
        $config = $user->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE, 'remote_identifier' => 'fv_8500_abcde',
            'subscription_url' => 'https://old.example.com/sub/abcde',
            'data_limit_bytes' => Bytes::fromGb(10), 'used_bytes' => Bytes::fromGb(5),
            'status' => ConfigStatus::Active, 'expires_at' => now()->addDays(5),
        ]);

        Artisan::call('configs:reprovision', ['panel' => $panel->id, '--execute' => true]);

        $fresh = $config->fresh();
        $this->assertSame('https://new.example.com/sub/fv_8500_abcde', $fresh->subscription_url); // new link
        $this->assertSame('fv_8500_abcde', $fresh->remote_identifier);          // same user
        $this->assertSame(Bytes::fromGb(10), $fresh->data_limit_bytes);         // full original quota
        $this->assertSame(0, $fresh->used_bytes);                               // fresh account
        $this->assertSame(Config::SOURCE_FREE, $fresh->source);

        // Crucially: still exactly ONE config for the user (no duplicate free tag).
        $this->assertSame(1, $user->configs()->count());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $panel = $this->fakePanel();
        $user = BotUser::create(['telegram_id' => 8501]);
        $config = $user->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE, 'remote_identifier' => 'fv_8501_zzzzz',
            'subscription_url' => 'https://old.example.com/sub/zzzzz',
            'data_limit_bytes' => Bytes::fromGb(10), 'status' => ConfigStatus::Active,
        ]);

        Artisan::call('configs:reprovision', ['panel' => $panel->id]); // no --execute

        $this->assertSame('https://old.example.com/sub/zzzzz', $config->fresh()->subscription_url);
    }

    private function fakePanel(): Panel
    {
        $panel = Panel::create([
            'name' => 'P', 'type' => PanelType::PasarGuard, 'base_url' => 'https://new.example.com', 'is_active' => true,
        ]);

        $driver = new class($panel) implements PanelDriver
        {
            public function __construct(private Panel $panel) {}

            public function panel(): Panel { return $this->panel; }

            public function testConnection(): bool { return true; }

            public function createConfig(ConfigSpec $spec): IssuedConfig
            {
                // The new server mints a fresh sub link for the (same) username.
                return new IssuedConfig(
                    identifier: $spec->identifier,
                    subscriptionUrl: 'https://new.example.com/sub/'.$spec->identifier,
                    subId: 'newsub',
                    dataLimitBytes: $spec->dataLimitBytes,
                );
            }

            public function renewConfig(string $identifier, ConfigSpec $spec): IssuedConfig { return new IssuedConfig(identifier: $identifier); }

            public function getUsage(string $identifier): ?ConfigUsage { return null; }

            public function listTargets(): array { return []; }

            public function disableConfig(string $identifier): bool { return true; }

            public function deleteConfig(string $identifier): bool { return true; }

            public function rotateSubscription(string $identifier): IssuedConfig { return new IssuedConfig(identifier: $identifier); }

            public function fetchConfigLinks(string $identifier): array { return []; }
        };

        $manager = new class($driver) extends PanelManager
        {
            public function __construct(private PanelDriver $fake) {}

            public function driver(Panel $panel): PanelDriver { return $this->fake; }
        };

        $this->app->instance(PanelManager::class, $manager);

        return $panel;
    }
}
