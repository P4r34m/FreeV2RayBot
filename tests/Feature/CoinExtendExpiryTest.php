<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Enums\PanelType;
use App\Models\BotUser;
use App\Models\CoinPlan;
use App\Models\Config;
use App\Models\Panel;
use App\Panels\Contracts\PanelDriver;
use App\Panels\Data\ConfigSpec;
use App\Panels\Data\ConfigUsage;
use App\Panels\Data\IssuedConfig;
use App\Panels\PanelManager;
use App\Services\CoinStoreService;
use App\Support\Bytes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A coin top-up adds volume only and must NOT wipe the panel-side expiry: the
 * driver has to receive the config's CURRENT expiry, never 0 (= unlimited).
 */
class CoinExtendExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_volume_only_top_up_preserves_the_panel_expiry(): void
    {
        [$panel, $holder] = $this->capturingPanel();

        $user = BotUser::create(['telegram_id' => 8300, 'coins' => 100]);
        $config = $user->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_COIN, 'remote_identifier' => 'fv_e',
            'data_limit_bytes' => Bytes::fromGb(10), 'used_bytes' => 0,
            'status' => ConfigStatus::Active, 'expires_at' => now()->addDays(5),
        ]);
        $before = $config->fresh()->expires_at->toDateTimeString();

        // duration_days = 10 must be ignored; only the 20G volume is applied.
        $plan = CoinPlan::create([
            'name' => '+20G/10d', 'data_limit_bytes' => Bytes::fromGb(20), 'duration_days' => 10,
            'coin_price' => 50, 'is_active' => true,
        ]);

        app(CoinStoreService::class)->buyExtend($user, $plan, $config);

        // The panel got the EXISTING expiry (~5 days out), not 0 and not +10 days.
        $this->assertNotNull($holder->renewSpec);
        $this->assertGreaterThan(5 * 86400 - 120, $holder->renewSpec->expirySeconds);
        $this->assertLessThanOrEqual(5 * 86400, $holder->renewSpec->expirySeconds);

        // Volume added; local expiry untouched.
        $this->assertSame(Bytes::fromGb(30), $config->fresh()->data_limit_bytes);
        $this->assertSame($before, $config->fresh()->expires_at->toDateTimeString());
    }

    /** @return array{0: Panel, 1: object} the panel and a holder capturing renewConfig's spec */
    private function capturingPanel(): array
    {
        $panel = Panel::create([
            'name' => 'P', 'type' => PanelType::ThreeXui, 'base_url' => 'https://p.example.com', 'is_active' => true,
        ]);

        $holder = new class
        {
            public ?ConfigSpec $renewSpec = null;
        };

        $driver = new class($panel, $holder) implements PanelDriver
        {
            public function __construct(private Panel $panel, private object $holder) {}

            public function panel(): Panel { return $this->panel; }

            public function testConnection(): bool { return true; }

            public function createConfig(ConfigSpec $spec): IssuedConfig { return new IssuedConfig(identifier: $spec->identifier, dataLimitBytes: $spec->dataLimitBytes); }

            public function renewConfig(string $identifier, ConfigSpec $spec): IssuedConfig
            {
                $this->holder->renewSpec = $spec;

                return new IssuedConfig(identifier: $identifier, dataLimitBytes: $spec->dataLimitBytes);
            }

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

        return [$panel, $holder];
    }
}
