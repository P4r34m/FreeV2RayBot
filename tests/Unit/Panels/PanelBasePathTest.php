<?php

namespace Tests\Unit\Panels;

use App\Models\Panel;
use App\Panels\Drivers\ThreeXuiDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression: 3x-ui panels are often hosted under a secret web path
 * (https://ip:port/path). Requests must PRESERVE that path instead of dropping
 * it to the host root (which caused "login rejected").
 */
class PanelBasePathTest extends TestCase
{
    private function panelWithPath(array $settings = []): Panel
    {
        $panel = new Panel;
        $panel->id = 91;
        $panel->base_url = 'https://1.2.3.4:2053/secretpath';
        $panel->username = 'admin';
        $panel->password = 'pw';
        $panel->settings = array_merge(['inbound_id' => 1, 'verify_ssl' => false], $settings);

        return $panel;
    }

    public function test_requests_preserve_the_panel_web_path(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response(['success' => true, 'obj' => []], 200)]);

        // api_token skips the login flow; testConnection hits the inbounds list.
        (new ThreeXuiDriver($this->panelWithPath(['api_token' => 'tok'])))->testConnection();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://1.2.3.4:2053/secretpath/panel/api/inbounds/list');
    }

    public function test_login_preserves_the_panel_web_path(): void
    {
        Cache::flush();
        Http::fake([
            '*/secretpath/login' => Http::response(['success' => true], 200, ['Set-Cookie' => 'session=x; Path=/']),
            '*' => Http::response(['success' => true, 'obj' => []], 200),
        ]);

        (new ThreeXuiDriver($this->panelWithPath()))->testConnection();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://1.2.3.4:2053/secretpath/login');
    }
}
