<?php

namespace Tests\Feature;

use App\Models\BotButton;
use App\Telegram\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Buttons carry an admin-set premium-emoji icon and color (Telegram ButtonStyle). */
class ButtonStyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_button_applies_stored_icon_and_style(): void
    {
        BotButton::create([
            'key' => 'menu.get_config',
            'label' => 'دریافت کانفیگ',
            'icon_custom_emoji_id' => '5123456789',
            'style' => 'success',
        ]);

        $btn = Content::button('menu.get_config', 'get_config');

        $this->assertSame('دریافت کانفیگ', $btn->text);
        $this->assertSame('5123456789', $btn->icon_custom_emoji_id);
        $this->assertSame('success', $btn->style);
    }

    public function test_button_without_overrides_has_no_icon_or_style(): void
    {
        $btn = Content::button('menu.profile', 'profile');

        $this->assertNull($btn->icon_custom_emoji_id);
        $this->assertNull($btn->style);
    }
}
