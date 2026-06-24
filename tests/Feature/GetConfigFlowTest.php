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
 * "دریافت کانفیگ" jumps straight to issuance when the user has no active config
 * (no separate plan step — the panel determines volume/duration), but still
 * offers the new/renew/status menu when they already have one.
 */
class GetConfigFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_config_without_active_config_shows_picker_then_issues_on_tap(): void
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

        // The server picker is shown (even for a single panel) — not auto-issued.
        $bot->hearCallbackQueryData('get_config')->reply();
        Queue::assertNotPushed(IssueConfigJob::class);

        // Tapping the panel issues on it.
        $bot->hearCallbackQueryData('config:new:'.$panel->id)->reply();
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

    public function test_expired_free_config_cannot_get_a_new_one(): void
    {
        Queue::fake();
        $panel = $this->panel();

        $bot = $this->startedBot();
        BotUser::firstOrFail()->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE, 'remote_identifier' => 'fv_exp',
            'status' => ConfigStatus::Expired, 'expires_at' => now()->subDay(),
        ]);

        // Even a direct "new on this server" tap is blocked — only renew is allowed.
        $bot->hearCallbackQueryData('config:new:'.$panel->id)->reply();

        Queue::assertNotPushed(IssueConfigJob::class);
    }

    public function test_expired_free_config_can_be_renewed(): void
    {
        Queue::fake();
        $panel = $this->panel();

        $bot = $this->startedBot();
        BotUser::firstOrFail()->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE, 'remote_identifier' => 'fv_exp2',
            'status' => ConfigStatus::Expired, 'expires_at' => now()->subDay(),
        ]);

        $bot->hearCallbackQueryData('config:renew')->reply();

        Queue::assertPushed(IssueConfigJob::class, fn (IssueConfigJob $job) => $job->mode === 'renew');
    }

    public function test_active_free_config_cannot_be_renewed_until_it_expires(): void
    {
        Queue::fake();
        $panel = $this->panel();

        $bot = $this->startedBot();
        BotUser::firstOrFail()->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE, 'remote_identifier' => 'fv_live',
            'status' => ConfigStatus::Active, 'expires_at' => now()->addDays(3),
        ]);

        $bot->hearCallbackQueryData('config:renew')->reply();

        Queue::assertNotPushed(IssueConfigJob::class);
    }

    private function panel(): Panel
    {
        return Panel::create([
            'name' => 'only', 'type' => PanelType::ThreeXui,
            'base_url' => 'https://only.example.com', 'is_active' => true,
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
}
