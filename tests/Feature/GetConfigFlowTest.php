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
 * "دریافت کانفیگ" jumps straight to issuance when the user has no active config
 * (no separate plan step — the panel determines volume/duration), but still
 * offers the new/renew/status menu when they already have one.
 */
class GetConfigFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_config_without_active_config_dispatches_issuance_on_the_only_panel(): void
    {
        Queue::fake();

        $panel = Panel::create([
            'name' => 'only', 'type' => PanelType::ThreeXui,
            'base_url' => 'https://only.example.com', 'is_active' => true,
        ]);

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation(); // reuse one user/chat across the updates
        $bot->hearText('/start')->reply();

        $bot->hearCallbackQueryData('get_config')->reply();

        Queue::assertPushed(
            IssueConfigJob::class,
            fn (IssueConfigJob $job) => $job->mode === 'new' && $job->panelId === $panel->id,
        );
    }

    public function test_get_config_with_active_config_shows_menu_and_issues_nothing(): void
    {
        Queue::fake();

        $panel = Panel::create([
            'name' => 'only', 'type' => PanelType::ThreeXui,
            'base_url' => 'https://only.example.com', 'is_active' => true,
        ]);

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation(); // reuse one user/chat across the updates
        $bot->hearText('/start')->reply();

        // Give the user an active config so the renew/status menu is offered.
        BotUser::firstOrFail()->configs()->create([
            'panel_id' => $panel->id,
            'remote_identifier' => 'fv_existing',
            'status' => ConfigStatus::Active,
        ]);

        $bot->hearCallbackQueryData('get_config')->reply();

        Queue::assertNotPushed(IssueConfigJob::class);
    }
}
