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
 * Unit coverage for the 3x-ui driver against the v3.3.1 client API: the
 * clients/add create payload (byte units, inboundIds), the login -> session
 * cookie auth flow, the Bearer-token shortcut, and usage parsing.
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

    public function test_create_config_logs_in_then_posts_client_to_clients_add_with_byte_units(): void
    {
        Cache::flush();

        Http::fake([
            '*/login' => Http::response(['success' => true], 200, [
                'Set-Cookie' => 'session=abc123; Path=/; HttpOnly',
            ]),
            '*/panel/api/clients/add' => Http::response(['success' => true, 'msg' => 'Client added']),
        ]);

        $driver = new ThreeXuiDriver($this->makePanel());

        $spec = new ConfigSpec(
            dataLimitBytes: 5 * Bytes::GB,
            expirySeconds: 3600,
            identifier: 'user-42',
        );

        $issued = $driver->createConfig($spec);

        // (a) The create request body carries the v3.3.1 shape + the right units.
        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), '/panel/api/clients/add')) {
                return false;
            }

            $body = $request->data();

            // Client object is sent as a real nested object (no JSON-string quirk).
            $client = $body['client'];
            $this->assertIsArray($client);
            $this->assertSame(5 * Bytes::GB, $client['totalGB']); // BYTES, not GB
            $this->assertTrue($client['enable']);
            $this->assertSame('user42', $client['email']);        // normalized identifier
            $this->assertNotEmpty($client['subId']);
            // expiryTime is unix MILLISECONDS in the near future.
            $this->assertGreaterThan(time() * 1000, $client['expiryTime']);

            // The selected inbound(s) ride alongside the client.
            $this->assertSame([3], $body['inboundIds']);

            return true;
        });

        // The session cookie obtained from /login is replayed on the create call.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/panel/api/clients/add')
            && $request->header('Cookie')[0] === 'session=abc123');

        // The driver builds the subscription URL itself from the sub_* settings.
        $this->assertSame(
            'https://sub.example.com:2096/sub/'.$issued->subId,
            $issued->subscriptionUrl
        );
        // v3.3.1 generates protocol secrets server-side; we no longer carry a uuid.
        $this->assertNull($issued->remoteUuid);
        $this->assertSame('user42', $issued->identifier);
        $this->assertSame(5 * Bytes::GB, $issued->dataLimitBytes);
    }

    public function test_create_config_prefers_bearer_token_and_skips_login(): void
    {
        Cache::flush();

        Http::fake([
            '*/panel/api/clients/add' => Http::response(['success' => true]),
        ]);

        $driver = new ThreeXuiDriver($this->makePanel(['api_token' => 'tok-xyz']));

        $driver->createConfig(new ConfigSpec(identifier: 'tokenuser'));

        // No /login round-trip; the Bearer token is sent instead of a cookie.
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/login'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/panel/api/clients/add')
            && $request->header('Authorization')[0] === 'Bearer tok-xyz');
    }

    public function test_create_config_on_hold_uses_a_negative_expiry_time(): void
    {
        Cache::flush();

        Http::fake([
            '*/panel/api/clients/add' => Http::response(['success' => true]),
        ]);

        $driver = new ThreeXuiDriver($this->makePanel(['api_token' => 'tok-xyz']));

        $issued = $driver->createConfig(new ConfigSpec(
            dataLimitBytes: Bytes::GB,
            expirySeconds: 30 * 86_400,
            identifier: 'held',
            onHold: true,
        ));

        // A negative expiryTime tells 3x-ui to start the clock on first connection.
        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), '/panel/api/clients/add')) {
                return false;
            }

            return $request->data()['client']['expiryTime'] === -1 * 30 * 86_400 * 1000;
        });

        $this->assertNull($issued->expiresAt);
    }

    public function test_get_usage_sums_up_and_down_and_parses_expiry(): void
    {
        Cache::flush();

        Http::fake([
            '*/login' => Http::response(['success' => true], 200, [
                'Set-Cookie' => 'session=abc123; Path=/',
            ]),
            '*/panel/api/clients/traffic/*' => Http::response([
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

    public function test_get_usage_treats_a_negative_on_hold_expiry_as_not_started(): void
    {
        Cache::flush();

        Http::fake([
            '*/login' => Http::response(['success' => true], 200, ['Set-Cookie' => 'session=abc123']),
            '*/panel/api/clients/traffic/*' => Http::response([
                'success' => true,
                'obj' => [
                    'up' => 0,
                    'down' => 0,
                    'total' => Bytes::GB,
                    'expiryTime' => -2_592_000_000, // negative ms = on hold, clock not started
                    'enable' => true,
                ],
            ]),
        ]);

        $usage = (new ThreeXuiDriver($this->makePanel()))->getUsage('held');

        $this->assertNotNull($usage);
        $this->assertNull($usage->expiresAt); // no absolute expiry until first connection
        $this->assertSame('active', $usage->status);
    }

    public function test_get_usage_returns_null_when_client_missing(): void
    {
        Cache::flush();

        Http::fake([
            '*/login' => Http::response(['success' => true], 200, ['Set-Cookie' => 'session=abc123']),
            '*/panel/api/clients/traffic/*' => Http::response(['success' => false, 'msg' => 'not found']),
        ]);

        $driver = new ThreeXuiDriver($this->makePanel());

        $this->assertNull($driver->getUsage('ghost'));
    }

    public function test_rotate_subscription_updates_subid_and_builds_a_new_url(): void
    {
        Cache::flush();

        Http::fake([
            '*/panel/api/clients/update/*' => Http::response(['success' => true]),
        ]);

        $issued = (new ThreeXuiDriver($this->makePanel(['api_token' => 'tok-xyz'])))->rotateSubscription('user-42');

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/panel/api/clients/update/')) {
                return false;
            }
            $body = $request->data();

            return $body['email'] === 'user42' && ! empty($body['subId']);
        });

        // The new sub URL is built from the freshly generated subId + sub_* settings.
        $this->assertSame('https://sub.example.com:2096/sub/'.$issued->subId, $issued->subscriptionUrl);
    }

    public function test_create_config_throws_panel_exception_on_unsuccessful_response(): void
    {
        Cache::flush();

        Http::fake([
            '*/login' => Http::response(['success' => true], 200, ['Set-Cookie' => 'session=abc123']),
            '*/panel/api/clients/add' => Http::response(['success' => false, 'msg' => 'duplicate email']),
        ]);

        $driver = new ThreeXuiDriver($this->makePanel());

        $this->expectException(\App\Panels\Exceptions\PanelException::class);
        $this->expectExceptionMessageMatches('/duplicate email/');

        $driver->createConfig(new ConfigSpec(identifier: 'dupe'));
    }
}
