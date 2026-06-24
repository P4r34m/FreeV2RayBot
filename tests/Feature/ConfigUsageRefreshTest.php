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
use App\Services\ConfigUsageService;
use App\Support\Bytes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

/** Viewing a subscription pulls live usage/remaining/expiry from the panel. */
class ConfigUsageRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_pulls_live_usage_from_the_panel(): void
    {
        $panel = $this->panelReturning(fn () => new ConfigUsage(
            usedBytes: Bytes::fromGb(5),
            totalBytes: Bytes::fromGb(10),
            expiresAt: CarbonImmutable::now()->addDays(3),
        ));
        $config = $this->config($panel, ['data_limit_bytes' => Bytes::fromGb(8), 'used_bytes' => 0, 'expires_at' => now()->addDay()]);

        app(ConfigUsageService::class)->refresh($config);

        $fresh = $config->fresh();
        $this->assertSame(Bytes::fromGb(5), $fresh->used_bytes);
        $this->assertSame(Bytes::fromGb(10), $fresh->data_limit_bytes);
        $this->assertSame(now()->addDays(3)->toDateString(), $fresh->expires_at->toDateString());
    }

    public function test_refresh_marks_config_deleted_when_gone_from_panel(): void
    {
        $panel = $this->panelReturning(fn () => null);
        $panel->update(['active_config_count' => 1]);
        $config = $this->config($panel, []);

        app(ConfigUsageService::class)->refresh($config);

        $this->assertSame(ConfigStatus::Deleted, $config->fresh()->status);
        $this->assertSame(0, $panel->fresh()->active_config_count);
    }

    public function test_refresh_keeps_values_when_the_panel_errors(): void
    {
        $panel = $this->panelReturning(fn () => throw new \RuntimeException('panel down'));
        $config = $this->config($panel, ['used_bytes' => Bytes::fromGb(2)]);

        app(ConfigUsageService::class)->refresh($config);

        $fresh = $config->fresh();
        $this->assertSame(Bytes::fromGb(2), $fresh->used_bytes);
        $this->assertSame(ConfigStatus::Active, $fresh->status);
    }

    public function test_refresh_keeps_config_when_missing_but_delete_disallowed(): void
    {
        $panel = $this->panelReturning(fn () => null);
        $config = $this->config($panel, []);

        app(ConfigUsageService::class)->refresh($config, allowDelete: false);

        $this->assertSame(ConfigStatus::Active, $config->fresh()->status);
    }

    public function test_viewing_does_not_delete_config_when_panel_returns_null(): void
    {
        // A re-pointed/unreachable panel returns null for every account; opening
        // "my configs" must NOT wipe the config.
        $panel = $this->panelReturning(fn () => null);
        $config = $this->config($panel, []);

        $bot = $this->userBot(8400);
        $bot->hearCallbackQueryData('config:view:'.$config->id)->reply();

        $this->assertSame(ConfigStatus::Active, $config->fresh()->status);
    }

    public function test_viewing_a_config_refreshes_usage_live(): void
    {
        $panel = $this->panelReturning(fn () => new ConfigUsage(usedBytes: Bytes::fromGb(7), totalBytes: Bytes::fromGb(10)));
        $config = $this->config($panel, ['data_limit_bytes' => Bytes::fromGb(10), 'used_bytes' => 0]);

        $bot = $this->userBot(8400);
        $bot->hearCallbackQueryData('config:view:'.$config->id)->reply();

        $this->assertSame(Bytes::fromGb(7), $config->fresh()->used_bytes);
    }

    private function config(Panel $panel, array $attr): Config
    {
        $user = BotUser::firstOrCreate(['telegram_id' => 8400]);

        return $user->configs()->create(array_merge([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE, 'remote_identifier' => 'fv_u',
            'subscription_url' => 'https://sub.example.com/u', 'status' => ConfigStatus::Active,
        ], $attr));
    }

    private function userBot(int $id): Nutgram
    {
        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation();
        $bot->hearMessage(['from' => ['id' => $id, 'is_bot' => false, 'first_name' => 'U'], 'text' => '/start'])->reply();

        return $bot;
    }

    /** A panel whose driver's getUsage runs $usage(). */
    private function panelReturning(callable $usage): Panel
    {
        $panel = Panel::create([
            'name' => 'P', 'type' => PanelType::ThreeXui, 'base_url' => 'https://p.example.com', 'is_active' => true,
        ]);

        $driver = new class($panel, $usage) implements PanelDriver
        {
            public function __construct(private Panel $panel, private $usage) {}

            public function panel(): Panel { return $this->panel; }

            public function testConnection(): bool { return true; }

            public function createConfig(ConfigSpec $spec): IssuedConfig { return new IssuedConfig(identifier: $spec->identifier); }

            public function renewConfig(string $identifier, ConfigSpec $spec): IssuedConfig { return new IssuedConfig(identifier: $identifier); }

            public function getUsage(string $identifier): ?ConfigUsage { return ($this->usage)(); }

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
