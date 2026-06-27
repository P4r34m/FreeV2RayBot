<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Enums\PanelType;
use App\Jobs\IssueConfigJob;
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
use Illuminate\Support\Facades\Queue;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

/**
 * A not-yet-expired free config whose panel account vanished (server re-pointed /
 * panel pruned old users) can still be renewed — the renew re-creates it. While it
 * genuinely exists on the panel, the "not expired yet" rule still holds.
 */
class RenewMissingAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_renew_allowed_when_account_is_missing_on_the_panel(): void
    {
        Queue::fake();
        $panel = $this->panelWhereUsage(fn () => null); // account gone from the panel

        $bot = $this->startedBot();
        $this->freeConfig($panel); // status active, expires in the future (not expired)

        $bot->hearCallbackQueryData('config:renew')->reply();

        Queue::assertPushed(IssueConfigJob::class, fn (IssueConfigJob $job) => $job->mode === 'renew');
    }

    public function test_renew_blocked_when_account_exists_and_not_expired(): void
    {
        Queue::fake();
        $panel = $this->panelWhereUsage(fn () => new ConfigUsage(usedBytes: 0, totalBytes: Bytes::GB));

        $bot = $this->startedBot();
        $this->freeConfig($panel);

        $bot->hearCallbackQueryData('config:renew')->reply();

        Queue::assertNotPushed(IssueConfigJob::class);
    }

    private function freeConfig(Panel $panel): Config
    {
        return BotUser::firstOrFail()->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE, 'remote_identifier' => 'fv_live',
            'status' => ConfigStatus::Active, 'expires_at' => now()->addDays(3),
        ]);
    }

    private function startedBot(): Nutgram
    {
        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation();
        $bot->hearText('/start')->reply();

        return $bot;
    }

    private function panelWhereUsage(callable $usage): Panel
    {
        $panel = Panel::create([
            'name' => 'P', 'type' => PanelType::PasarGuard, 'base_url' => 'https://p.example.com', 'is_active' => true,
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
