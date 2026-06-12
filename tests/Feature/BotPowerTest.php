<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

class BotPowerTest extends TestCase
{
    use RefreshDatabase;

    private function sender(int $id): array
    {
        return ['id' => $id, 'is_bot' => false, 'first_name' => 'U'];
    }

    public function test_admin_can_reenable_bot_with_on_command_while_it_is_off(): void
    {
        config(['v2raybot.bot.admin_ids' => ['42']]);
        Setting::put(SettingKey::BOT_ENABLED, false);

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->hearMessage(['from' => $this->sender(42), 'text' => '/on'])->reply();

        $this->assertTrue(Setting::bool(SettingKey::BOT_ENABLED), 'Admin /on must re-enable the bot while it is off');
    }

    public function test_non_admin_cannot_reenable_bot_while_it_is_off(): void
    {
        config(['v2raybot.bot.admin_ids' => []]);
        Setting::put(SettingKey::BOT_ENABLED, false);

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->hearMessage(['from' => $this->sender(99), 'text' => '/on'])->reply();

        $this->assertFalse(Setting::bool(SettingKey::BOT_ENABLED), 'A non-admin must not be able to re-enable the bot');
    }
}
