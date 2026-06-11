<?php

namespace Tests\Unit\Panels;

use App\Models\Panel;
use App\Panels\Data\ConfigSpec;
use App\Panels\Drivers\ThreeXuiDriver;
use App\Support\Bytes;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit coverage for the 3x-ui driver: the create payload (units + JSON-string
 * settings quirk), the login -> session-cookie auth flow, and usage parsing.
 */
class ThreeXuiDriverTest extends TestCase
{
    private function makePanel(array $settings = []): Panel
    {
        $panel = new Panel;
        $panel->id = 7;
        $panel->base_url = 'https://panel.example.com:54321';
        $panel->username = 'admin';
        $panel->password = 'secret';
        $panel->settings = array_merge([
            'inbound_id' => 3,
            'sub_host' => 'sub.example.com',
            'sub_port' => 2096,
            'sub_scheme' => 'https',
            'sub_path' => '/sub/',
            'verify_ssl' => false,
        ], $settings);

        return $panel;
    }

    public function test_create_config_logs_in_then_posts_client_with_byte_units_and_json_string_settings(): void
    {
        Cache::flush();

        Http::fake([
            '*/login' => Http::response(['success' => true], 200, [
                'Set-Cookie' => 'session=abc123; Path=/; HttpOnly',
            ]),
            '*/panel/api/inbounds/addClient' => Http::response(['success' => true, 'msg' => 'Client added']),
        ]);

        $driver = new ThreeXuiDriver($this->makePanel());

        $spec = new ConfigSpec(
            dataLimitBytes: 5 * Bytes::GB,
            expirySeconds: 3600,
            identifier: 'user-42',
        );

        $issued = $driver->createConfig($spec);

        // (a) The create request body carries the right units + the JSON-string quirk.
        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), '/panel/api/inbounds/addClient')) {
                return false;
            }

            $body = $request->data();
            $this->assertSame(3, $body['id']);                 // inbound id
            $this->assertIsString($body['settings']);          // settings is a JSON STRING

            $client = json_decode($body['settings'], true)['clients'][0];
            $this->assertSame(5 * Bytes::GB, $client['totalGB']); // BYTES, not GB
            $this->assertTrue($client['enable']);
            $this->assertSame('user42', $client['email']);     // normalized identifier
            // expiryTime is unix MILLISECONDS in the near future.
            $this->assertGreaterThan(time() * 1000, $client['expiryTime']);

            return true;
        });

        // The session cookie obtained from /login is replayed on the create call.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/panel/api/inbounds/addClient')
            && $request->header('Cookie')[0] === 'session=abc123');

        // The driver builds the subscription URL itself from the sub_* settings.
        $this->assertSame(
            'https://sub.example.com:2096/sub/'.$issued->subId,
            $issued->subscriptionUrl
        );
        $this->assertNotNull($issued->remoteUuid);
        $this->assertSame('user42', $issued->identifier);
        $this->assertSame(5 * Bytes::GB, $issued->dataLimitBytes);
    }

    public function test_create_config_prefers_bearer_token_and_skips_login(): void
    {
        Cache::flush();

        Http::fake([
            '*/panel/api/inbounds/addClient' => Http::response(['success' => true]),
        ]);

        $driver = new ThreeXuiDriver($this->makePanel(['api_token' => 'tok-xyz']));

        $driver->createConfig(new ConfigSpec(identifier: 'tokenuser'));

        // No /login round-trip; the Bearer token is sent instead of a cookie.
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/login'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/panel/api/inbounds/addClient')
            && $request->header('Authorization')[0] === 'Bearer tok-xyz');
    }

    public function test_get_usage_sums_up_and_down_and_parses_expiry(): void
    {
        Cache::flush();

        Http::fake([
            '*/login' => Http::response(['success' => true], 200, [
                'Set-Cookie' => 'session=abc123; Path=/',
            ]),
            '*/panel/api/inbounds/getClientTraffics/*' => Http::response([
                'success' => true,
                'obj' => [
                    'up' => 1_000,
                    'down' => 2_000,
                    'total' => 5 * Bytes::GB,
                    'expiryTime' => 1_900_000_000_000, // ms
                    'enable' => true,
                ],
            ]),
        ]);

        $driver = new ThreeXuiDriver($this->makePanel());

        $usage = $driver->getUsage('user-42');

        $this->assertNotNull($usage);
        $this->assertSame(3_000, $usage->usedBytes);               // up + down
        $this->assertSame(5 * Bytes::GB, $usage->totalBytes);
        $this->assertSame('active', $usage->status);
        $this->assertSame(1_900_000_000, $usage->expiresAt?->getTimestamp()); // ms -> s
    }

    public function test_create_config_throws_panel_exception_on_unsuccessful_response(): void
    {
        Cache::flush();

        Http::fake([
            '*/login' => Http::response(['success' => true], 200, ['Set-Cookie' => 'session=abc123']),
            '*/panel/api/inbounds/addClient' => Http::response(['success' => false, 'msg' => 'duplicate email']),
        ]);

        $driver = new ThreeXuiDriver($this->makePanel());

        $this->expectException(\App\Panels\Exceptions\PanelException::class);
        $this->expectExceptionMessageMatches('/duplicate email/');

        $driver->createConfig(new ConfigSpec(identifier: 'dupe'));
    }
}
