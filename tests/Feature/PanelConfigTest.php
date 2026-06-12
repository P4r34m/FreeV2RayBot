<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\PanelConfig;
use App\Support\SettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_when_no_setting(): void
    {
        $this->assertSame('admin', PanelConfig::path());
        $this->assertTrue(PanelConfig::enabled());
    }

    public function test_bot_set_path_overrides_default_live(): void
    {
        Setting::put(SettingKey::ADMIN_PATH, 'secret-x9');

        $this->assertSame('secret-x9', PanelConfig::path());
    }

    public function test_web_panel_can_be_disabled(): void
    {
        Setting::put(SettingKey::WEB_PANEL_ENABLED, false);

        $this->assertFalse(PanelConfig::enabled());
    }

    public function test_path_is_trimmed_of_slashes(): void
    {
        Setting::put(SettingKey::ADMIN_PATH, '/dash/');

        $this->assertSame('dash', PanelConfig::path());
    }
}
