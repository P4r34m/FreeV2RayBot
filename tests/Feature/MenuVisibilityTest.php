<?php

namespace Tests\Feature;

use App\Models\Setting;
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
