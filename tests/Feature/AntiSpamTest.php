<?php

namespace Tests\Feature;

use App\Models\BotUser;
use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AntiSpamTest extends TestCase
{
    use RefreshDatabase;

    public function test_temp_block_in_the_future_blocks_access(): void
    {
        $user = BotUser::create([
            'telegram_id' => 7001,
            'blocked_until' => now()->addMinutes(10),
        ]);

        $this->assertTrue($user->isAccessBlocked());
    }

    public function test_permanent_block_flag_blocks_access(): void
    {
        $user = BotUser::create([
            'telegram_id' => 7002,
            'is_blocked' => true,
        ]);

        $this->assertTrue($user->isAccessBlocked());
    }

    public function test_no_block_means_access_allowed(): void
    {
        $user = BotUser::create(['telegram_id' => 7003]);

        $this->assertFalse($user->isAccessBlocked());
    }

    public function test_expired_temp_block_does_not_block_access(): void
    {
        $user = BotUser::create([
            'telegram_id' => 7004,
            'blocked_until' => now()->subMinutes(5),
        ]);

        $this->assertFalse($user->isAccessBlocked());
    }

    public function test_bot_enabled_defaults_to_true_with_empty_settings_table(): void
    {
        $this->assertSame(0, Setting::count());

        $this->assertTrue(Setting::bool(SettingKey::BOT_ENABLED, true));
    }
}
