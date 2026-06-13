<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Keyboards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Tests\TestCase;

/**
 * The admin can hide/show individual user main-menu buttons; hidden ones drop out
 * of the keyboard while the rest stay.
 */
class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_user_buttons_are_visible_by_default(): void
    {
        $cbs = $this->callbacks(Keyboards::mainMenu());

        $this->assertContains(Keyboards::CB_GET_CONFIG, $cbs);
        $this->assertContains(Keyboards::CB_TUTORIALS, $cbs);
        $this->assertContains(Keyboards::CB_REFERRAL, $cbs);
        $this->assertContains(Keyboards::CB_PROFILE, $cbs);
    }

    public function test_hidden_button_is_excluded_but_others_remain(): void
    {
        Setting::put('menu_visible:menu.referral', false);

        $cbs = $this->callbacks(Keyboards::mainMenu());

        $this->assertNotContains(Keyboards::CB_REFERRAL, $cbs);
        $this->assertContains(Keyboards::CB_GET_CONFIG, $cbs);
        $this->assertContains(Keyboards::CB_TUTORIALS, $cbs);
        $this->assertContains(Keyboards::CB_PROFILE, $cbs);
    }

    public function test_coin_store_button_appears_only_in_coin_mode(): void
    {
        Setting::put(SettingKey::REFERRAL_MODE, 'reward');
        $this->assertNotContains('coin:store', $this->callbacks(Keyboards::mainMenu()));

        Setting::put(SettingKey::REFERRAL_MODE, 'coin');
        $this->assertContains('coin:store', $this->callbacks(Keyboards::mainMenu()));
    }

    public function test_menu_order_setting_reorders_buttons(): void
    {
        // Default order leads with "get config".
        $this->assertSame(Keyboards::CB_GET_CONFIG, $this->callbacks(Keyboards::mainMenu())[0]);

        // Put profile first; it should now lead.
        Setting::put(Keyboards::MENU_ORDER_KEY, ['profile', 'get_config']);
        $cbs = $this->callbacks(Keyboards::mainMenu());

        $this->assertSame(Keyboards::CB_PROFILE, $cbs[0]);
        $this->assertContains(Keyboards::CB_GET_CONFIG, $cbs); // others still present
    }

    public function test_joined_button_shares_the_previous_row(): void
    {
        // Default: tutorials sits on its own row.
        $this->assertCount(1, $this->rowOf(Keyboards::mainMenu(), Keyboards::CB_TUTORIALS));

        // Mark tutorials as joined → it shares the previous shown button's row.
        Setting::put(Keyboards::MENU_JOINED_KEY, ['tutorials']);
        $this->assertCount(2, $this->rowOf(Keyboards::mainMenu(), Keyboards::CB_TUTORIALS));
    }

    /** @return list<mixed> the keyboard row containing the given callback */
    private function rowOf(InlineKeyboardMarkup $kb, string $callback): array
    {
        foreach ($kb->inline_keyboard as $row) {
            foreach ($row as $btn) {
                if ($btn->callback_data === $callback) {
                    return $row;
                }
            }
        }

        return [];
    }

    /** @return list<string> every callback_data in the keyboard */
    private function callbacks(InlineKeyboardMarkup $kb): array
    {
        $out = [];
        foreach ($kb->inline_keyboard as $row) {
            foreach ($row as $btn) {
                if ($btn->callback_data !== null) {
                    $out[] = $btn->callback_data;
                }
            }
        }

        return $out;
    }
}
