<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

/**
 * The bot ignores group/channel updates (only private chats), and a config's
 * individual links can be pulled on demand.
 */
class MessagingGuardAndLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_messages_are_ignored(): void
    {
        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->hearMessage([
            'from' => ['id' => 5, 'is_bot' => false, 'first_name' => 'X'],
            'chat' => ['id' => -100, 'type' => 'group', 'title' => 'G'],
            'text' => '/start',
        ])->reply();

        // Blocked before ResolveBotUser, so no user is even provisioned.
        $this->assertSame(0, BotUser::count());
    }

    public function test_private_messages_are_processed(): void
    {
        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->hearMessage([
            'from' => ['id' => 6, 'is_bot' => false, 'first_name' => 'Y'],
            'chat' => ['id' => 6, 'type' => 'private'],
            'text' => '/start',
        ])->reply();

        $this->assertSame(1, BotUser::count());
    }

    public function test_single_configs_button_shows_stored_links(): void
    {
        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation();
        $bot->hearText('/start')->reply();

        $config = BotUser::firstOrFail()->configs()->create([
            'remote_identifier' => 'fv_x',
            'status' => ConfigStatus::Active,
            'subscription_url' => 'https://sub.example.com/x',
            'config_links' => ['vless://aaa', 'vmess://bbb'],
        ]);

        $bot->hearCallbackQueryData('config:links:'.$config->id)->reply();

        // Links rendered via an edited screen (no subscription HTTP fetch needed).
        $bot->assertCalled('editMessageText');
    }
}
