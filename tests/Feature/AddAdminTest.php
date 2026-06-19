<?php

namespace Tests\Feature;

use App\Models\BotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

/** Admins can promote/demote other admins by numeric id from the Telegram panel. */
class AddAdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminBot(): Nutgram
    {
        config(['v2raybot.bot.admin_ids' => ['42']]);

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation();
        $bot->hearMessage(['from' => ['id' => 42, 'is_bot' => false, 'first_name' => 'A'], 'text' => '/start'])->reply();

        return $bot;
    }

    public function test_admin_can_promote_a_user_by_numeric_id(): void
    {
        $bot = $this->adminBot();

        $bot->hearCallbackQueryData('admin:addadmin')->reply();
        $bot->hearText('12345')->reply();

        $user = BotUser::where('telegram_id', 12345)->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
    }

    public function test_non_numeric_id_is_rejected_and_no_admin_created(): void
    {
        $bot = $this->adminBot();

        $bot->hearCallbackQueryData('admin:addadmin')->reply();
        $bot->hearText('not-a-number')->reply();

        $this->assertSame(0, BotUser::where('telegram_id', '!=', 42)->count());
    }

    public function test_admin_can_remove_a_db_granted_admin(): void
    {
        $bot = $this->adminBot();
        $target = BotUser::create(['telegram_id' => 999, 'is_admin' => true]);

        $bot->hearCallbackQueryData('admin:deladmin:999')->reply();

        $this->assertFalse($target->fresh()->is_admin);
    }

    public function test_config_admin_cannot_be_removed(): void
    {
        $bot = $this->adminBot();

        // 42 is a fixed (config) admin; removing it must not strip the flag.
        $bot->hearCallbackQueryData('admin:deladmin:42')->reply();

        $this->assertTrue(BotUser::where('telegram_id', 42)->first()->is_admin);
    }
}
