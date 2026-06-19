<?php

namespace Tests\Feature;

use App\Enums\PanelType;
use App\Models\BotUser;
use App\Models\Panel;
use App\Models\Plan;
use App\Panels\Contracts\PanelDriver;
use App\Panels\Data\ConfigSpec;
use App\Panels\Data\ConfigUsage;
use App\Panels\Data\IssuedConfig;
use App\Panels\PanelManager;
use App\Services\ConfigIssuanceService;
use App\Support\Bytes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When a user picks a server, the issued config must take THAT panel's plan
 * (volume/duration) — the user never picks a plan. A panel without its own plan
 * falls back to the global default.
 */
class IssuancePlanResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_picking_a_panel_uses_that_panels_plan_not_the_global_default(): void
    {
        // Global default — what the old code wrongly forced onto every issuance.
        Plan::create([
            'name' => 'Global', 'data_limit_bytes' => 10 * Bytes::GB,
            'duration_days' => 7, 'is_default' => true, 'is_active' => true,
        ]);

        $panel = Panel::create([
            'name' => 'B', 'type' => PanelType::ThreeXui,
            'base_url' => 'https://b.example.com', 'is_active' => true,
        ]);

        // Panel-specific plan — choosing panel B must grant exactly this.
        Plan::create([
            'name' => 'B-plan', 'panel_id' => $panel->id,
            'data_limit_bytes' => 50 * Bytes::GB, 'duration_days' => 30,
            'is_active' => true,
        ]);

        $holder = $this->captureIssuedSpec($panel);

        app(ConfigIssuanceService::class)->issueNew(BotUser::create(['telegram_id' => 555]), null, $panel);

        $this->assertNotNull($holder->spec);
        $this->assertSame(50 * Bytes::GB, $holder->spec->dataLimitBytes);
        $this->assertSame(30 * 86400, $holder->spec->expirySeconds);
    }

    public function test_panel_without_its_own_plan_falls_back_to_global_default(): void
    {
        Plan::create([
            'name' => 'Global', 'data_limit_bytes' => 10 * Bytes::GB,
            'duration_days' => 7, 'is_default' => true, 'is_active' => true,
        ]);

        $panel = Panel::create([
            'name' => 'A', 'type' => PanelType::ThreeXui,
            'base_url' => 'https://a.example.com', 'is_active' => true,
        ]);

        $holder = $this->captureIssuedSpec($panel);

        app(ConfigIssuanceService::class)->issueNew(BotUser::create(['telegram_id' => 556]), null, $panel);

        $this->assertSame(10 * Bytes::GB, $holder->spec->dataLimitBytes);
        $this->assertSame(7 * 86400, $holder->spec->expirySeconds);
    }

    public function test_renew_uses_the_panels_current_plan_when_the_original_plan_was_deleted(): void
    {
        // Global default — a renewal must NOT silently fall back to this while the
        // panel still has its own plan.
        Plan::create([
            'name' => 'Global', 'data_limit_bytes' => 10 * Bytes::GB,
            'duration_days' => 7, 'is_default' => true, 'is_active' => true,
        ]);

        $panel = Panel::create([
            'name' => 'B', 'type' => PanelType::ThreeXui,
            'base_url' => 'https://b.example.com', 'is_active' => true,
        ]);

        Plan::create([
            'name' => 'B-plan', 'panel_id' => $panel->id,
            'data_limit_bytes' => 50 * Bytes::GB, 'duration_days' => 30,
            'is_active' => true,
        ]);

        $user = BotUser::create(['telegram_id' => 557]);

        // The config's original plan is gone (plan_id null, as nullOnDelete leaves it).
        $config = $user->configs()->create([
            'panel_id' => $panel->id,
            'plan_id' => null,
            'remote_identifier' => 'fv_557_renew',
            'status' => \App\Enums\ConfigStatus::Active,
        ]);

        $holder = $this->captureIssuedSpec($panel);

        app(ConfigIssuanceService::class)->renew($config);

        $this->assertNotNull($holder->renewSpec);
        $this->assertSame(50 * Bytes::GB, $holder->renewSpec->dataLimitBytes); // panel plan, not global
        $this->assertSame(30 * 86400, $holder->renewSpec->expirySeconds);
    }

    public function test_rotate_subscription_persists_the_new_link(): void
    {
        $panel = Panel::create([
            'name' => 'B', 'type' => PanelType::ThreeXui,
            'base_url' => 'https://b.example.com', 'is_active' => true,
        ]);

        $user = BotUser::create(['telegram_id' => 600]);
        $config = $user->configs()->create([
            'panel_id' => $panel->id,
            'remote_identifier' => 'fv_600',
            'sub_id' => 'old',
            'subscription_url' => 'https://old.example.com/sub/old',
            'status' => \App\Enums\ConfigStatus::Active,
        ]);

        $this->captureIssuedSpec($panel); // installs the fake PanelManager

        app(ConfigIssuanceService::class)->rotateSubscription($config);

        $config->refresh();
        $this->assertSame('https://sub.example.com/rotated', $config->subscription_url);
        $this->assertSame('newsub', $config->sub_id);
    }

    /**
     * Swap in a fake PanelManager whose driver records the ConfigSpec it is asked
     * to create. Returns a holder whose ->spec is populated once createConfig runs.
     */
    private function captureIssuedSpec(Panel $panel): object
    {
        $holder = new class
        {
            public ?ConfigSpec $spec = null;

            public ?ConfigSpec $renewSpec = null;
        };

        $driver = new class($panel, $holder) implements PanelDriver
        {
            public function __construct(private Panel $panel, private object $holder) {}

            public function panel(): Panel
            {
                return $this->panel;
            }

            public function testConnection(): bool
            {
                return true;
            }

            public function createConfig(ConfigSpec $spec): IssuedConfig
            {
                $this->holder->spec = $spec;

                return new IssuedConfig(
                    identifier: $spec->identifier,
                    subscriptionUrl: 'https://sub.example.com/'.$spec->identifier,
                    dataLimitBytes: $spec->dataLimitBytes,
                );
            }

            public function renewConfig(string $identifier, ConfigSpec $spec): IssuedConfig
            {
                $this->holder->renewSpec = $spec;

                return new IssuedConfig(identifier: $identifier, dataLimitBytes: $spec->dataLimitBytes);
            }

            public function getUsage(string $identifier): ?ConfigUsage
            {
                return null;
            }

            public function listTargets(): array
            {
                return [];
            }

            public function disableConfig(string $identifier): bool
            {
                return true;
            }

            public function deleteConfig(string $identifier): bool
            {
                return true;
            }

            public function rotateSubscription(string $identifier): IssuedConfig
            {
                return new IssuedConfig(
                    identifier: $identifier,
                    subscriptionUrl: 'https://sub.example.com/rotated',
                    subId: 'newsub',
                );
            }

            public function fetchConfigLinks(string $identifier): array
            {
                return [];
            }
        };

        $manager = new class($driver) extends PanelManager
        {
            public function __construct(private PanelDriver $fake) {}

            public function driver(Panel $panel): PanelDriver
            {
                return $this->fake;
            }
        };

        $this->app->instance(PanelManager::class, $manager);

        return $holder;
    }
}
