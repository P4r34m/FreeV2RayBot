<?php

namespace Tests\Feature;

use App\Models\BotButton;
use App\Models\BotText;
use App\Telegram\Content;
use App\Telegram\ContentDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContentFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_falls_back_to_defaults_when_bot_texts_empty(): void
    {
        $this->assertSame(0, BotText::count());

        $this->assertSame(
            ContentDefaults::texts()['welcome'],
            Content::text('welcome'),
        );
    }

    public function test_admin_text_overrides_default_and_substitutes_placeholders(): void
    {
        BotText::create(['key' => 'welcome', 'content' => 'X {name}']);
        Cache::flush();

        $this->assertSame('X Ali', Content::text('welcome', ['name' => 'Ali']));
    }

    public function test_button_label_for_unknown_key_returns_the_key(): void
    {
        $this->assertSame('totally.unknown.key', Content::buttonLabel('totally.unknown.key'));
    }

    public function test_coin_store_buttons_are_registered_and_editable(): void
    {
        // They must appear in the editable-buttons list (built from the defaults).
        $keys = array_keys(ContentDefaults::buttons());
        $this->assertContains('coin.buy_new', $keys);
        $this->assertContains('coin.buy_extend', $keys);

        // And an admin override applies (label + icon + color), like any other button.
        BotButton::create([
            'key' => 'coin.buy_new', 'label' => 'خرید جدید',
            'icon_custom_emoji_id' => '777', 'style' => 'success',
        ]);
        Cache::flush();

        $btn = Content::button('coin.buy_new', 'coin:buynew:1');
        $this->assertSame('خرید جدید', $btn->text);
        $this->assertSame('777', $btn->icon_custom_emoji_id);
        $this->assertSame('success', $btn->style);
    }
}
