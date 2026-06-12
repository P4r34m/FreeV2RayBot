<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Enums\PanelType;
use App\Jobs\IssueConfigJob;
use App\Models\BotUser;
use App\Models\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

/**
 * A per-user override raises (or lowers) how many active configs a specific user
 * may hold, taking precedence over the global default.
 */
class UserConfigLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_max_configs_uses_override_when_set_else_global_default(): void
    {
        config(['v2raybot.limits.max_active_configs_per_user' => 1]);

        $this->assertSame(5, BotUser::create(['telegram_id' => 1, 'max_configs' => 5])->maxConfigs());
        $this->assertSame(1, BotUser::create(['telegram_id' => 2])->maxConfigs());
    }

    public function test_user_with_a_raised_limit_can_request_another_config(): void
    {
        Queue::fake();
        config(['v2raybot.limits.max_active_configs_per_user' => 1]);

        $panel = $this->activePanel();

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation();
        $bot->hearText('/start')->reply();

        $user = BotUser::firstOrFail();
        $user->update(['max_configs' => 3]);
        $user->configs()->create([
            'panel_id' => $panel->id, 'remote_identifier' => 'fv_existing', 'status' => ConfigStatus::Active,
        ]);

        $bot->hearCallbackQueryData('config:new')->reply();

        Queue::assertPushed(IssueConfigJob::class);
    }

    public function test_user_at_the_default_limit_cannot_request_another_config(): void
    {
        Queue::fake();
        config(['v2raybot.limits.max_active_configs_per_user' => 1]);

        $panel = $this->activePanel();

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation();
        $bot->hearText('/start')->reply();

        $user = BotUser::firstOrFail();
        $user->configs()->create([
            'panel_id' => $panel->id, 'remote_identifier' => 'fv_existing', 'status' => ConfigStatus::Active,
        ]);

        $bot->hearCallbackQueryData('config:new')->reply();

        Queue::assertNotPushed(IssueConfigJob::class);
    }

    private function activePanel(): Panel
    {
        return Panel::create([
            'name' => 'only', 'type' => PanelType::ThreeXui,
            'base_url' => 'https://only.example.com', 'is_active' => true,
        ]);
    }
}
