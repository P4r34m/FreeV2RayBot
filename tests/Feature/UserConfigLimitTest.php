<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Enums\PanelType;
use App\Jobs\IssueConfigJob;
use App\Models\BotUser;
use App\Models\Config;
use App\Models\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

/**
 * Exactly one free config per user: a still-running free config blocks a new one,
 * but once it has expired the user can get a fresh one.
 */
class UserConfigLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_running_free_config_blocks_a_new_one(): void
    {
        Queue::fake();
        $panel = $this->activePanel();

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation();
        $bot->hearText('/start')->reply();

        BotUser::firstOrFail()->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE,
            'remote_identifier' => 'fv_existing', 'status' => ConfigStatus::Active,
        ]);

        $bot->hearCallbackQueryData('config:new')->reply();

        Queue::assertNotPushed(IssueConfigJob::class);
    }

    public function test_new_free_config_allowed_once_the_existing_one_has_expired(): void
    {
        Queue::fake();
        $panel = $this->activePanel();

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation();
        $bot->hearText('/start')->reply();

        // An active but EXPIRED free config must not block a fresh free config.
        BotUser::firstOrFail()->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE, 'remote_identifier' => 'fv_old',
            'status' => ConfigStatus::Active, 'expires_at' => now()->subDay(),
        ]);

        $bot->hearCallbackQueryData('config:new')->reply();

        Queue::assertPushed(IssueConfigJob::class);
    }

    public function test_coin_configs_do_not_block_a_free_config(): void
    {
        Queue::fake();
        $panel = $this->activePanel();

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation();
        $bot->hearText('/start')->reply();

        // A running COIN config must not count against the single free config.
        BotUser::firstOrFail()->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_COIN, 'remote_identifier' => 'fv_coin',
            'status' => ConfigStatus::Active, 'expires_at' => now()->addDays(30),
        ]);

        $bot->hearCallbackQueryData('config:new')->reply();

        Queue::assertPushed(IssueConfigJob::class);
    }

    private function activePanel(): Panel
    {
        return Panel::create([
            'name' => 'only', 'type' => PanelType::ThreeXui,
            'base_url' => 'https://only.example.com', 'is_active' => true,
        ]);
    }
}
