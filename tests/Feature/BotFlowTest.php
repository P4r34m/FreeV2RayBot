<?php

namespace Tests\Feature;

use App\Models\BotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

class BotFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_provisions_user_and_replies_with_menu(): void
    {
        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);

        $bot->hearText('/start')->reply();

        $bot->assertCalled('sendMessage');
        $this->assertSame(1, BotUser::count());
    }

    public function test_get_config_callback_is_handled(): void
    {
        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);

        // Register the user first.
        $bot->hearText('/start')->reply();

        // Channel lock is off by default, so this should respond without error.
        $bot->hearCallbackQueryData('get_config')->reply();

        $bot->assertCalled('answerCallbackQuery');
    }
}
