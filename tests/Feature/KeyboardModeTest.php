<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

class KeyboardModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mode_reflects_setting(): void
    {
        $this->assertSame('inline', Keyboards::mode());

        Setting::put(SettingKey::KEYBOARD_MODE, 'reply');
        $this->assertSame('reply', Keyboards::mode());
    }

    public function test_reply_mode_routes_main_button_text_to_handler(): void
    {
        Setting::put(SettingKey::KEYBOARD_MODE, 'reply');

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $from = ['id' => 7, 'is_bot' => false, 'first_name' => 'U'];

        // Register the user (and set the reply keyboard).
        $bot->hearMessage(['from' => $from, 'text' => '/start'])->reply();

        // Press the "get config" reply button (its text).
        $label = Content::buttonLabel('menu.get_config');
        $bot->hearMessage(['from' => $from, 'text' => $label])->reply();

        $bot->assertCalled('sendMessage');
    }
}
