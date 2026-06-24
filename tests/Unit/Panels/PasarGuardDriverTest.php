<?php

namespace Tests\Unit\Panels;

use App\Models\Panel;
use App\Panels\Data\ConfigSpec;
use App\Panels\Drivers\PasarGuardDriver;
use App\Panels\Exceptions\PanelAuthException;
use App\Support\Bytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit coverage for the PasarGuard driver: the form-encoded auth flow, the
 * create request body/units + response parsing, and usage parsing — all driven
 * with Http::fake() and the array cache so no network or DB is touched.
 */
class PasarGuardDriverTest extends TestCase
{
    private const BASE_URL = 'https://panel.example.com';

    protected function setUp(): void
    {
        parent::setUp();

        // Drivers cache the bearer token in Laravel Cache; use the array store.
        Cache::swap(Cache::store('array'));
    }

    /** A Panel instance backed only by in-memory attributes (no DB). */
    private function panel(array $settings = []): Panel
    {
        $panel = new Panel;
        // Use the model setters so the `array`/`encrypted` casts apply cleanly.
        $panel->forceFill([
            'id' => 7,
            'base_url' => self::BASE_URL,
            'username' => 'admin',
            'password' => 'secret',
            'settings' => $settings,
        ]);

        return $panel;
    }

    public function test_create_config_logs_in_then_posts_correct_body_and_parses_response(): void
    {
        Http::fake([
            self::BASE_URL.'/api/admin/token' => Http::response(['access_token' => 'tok-123', 'token_type' => 'bearer']),
            self::BASE_URL.'/api/user' => Http::response([
                'username' => 'alice',
                'subscription_url' => '/sub/abc123',
                'data_limit' => 10 * Bytes::GB,
                'used_traffic' => 0,
                'expire' => 1_900_000_000,
            ], 201),
        ]);

        $driver = new PasarGuardDriver($this->panel(['group_ids' => [3, 4]]));

        $issued = $driver->createConfig(new ConfigSpec(
            dataLimitBytes: 10 * Bytes::GB,
            expirySeconds: 86_400,
            identifier: 'alice',
            note: 'free trial',
        ));

        // (a) Request body carries the right native units and settings-driven fields.
        Http::assertSent(function ($request) {
            if ($request->url() !== self::BASE_URL.'/api/user' || $request->method() !== 'POST') {
                return false;
            }
            $body = $request->data();

            return $request->hasHeader('Authorization', 'Bearer tok-123')
                && $body['username'] === 'alice'
                && $body['status'] === 'active'
                && $body['data_limit'] === 10 * Bytes::GB
                && $body['expire'] >= time() // unix seconds, in the future
                && $body['group_ids'] === [3, 4]
                && $body['note'] === 'free trial';
        });

        // Response parsing: relative subscription_url is made absolute; DTO populated.
        $this->assertSame('alice', $issued->identifier);
        $this->assertSame(self::BASE_URL.'/sub/abc123', $issued->subscriptionUrl);
        $this->assertSame(10 * Bytes::GB, $issued->dataLimitBytes);
        $this->assertSame(1_900_000_000, $issued->expiresAt?->getTimestamp());
    }

    public function test_assign_groups_sends_only_group_ids_via_partial_put(): void
    {
        Http::fake([
            self::BASE_URL.'/api/admin/token' => Http::response(['access_token' => 'tok-123']),
            self::BASE_URL.'/api/user/alice' => Http::response(['username' => 'alice'], 200),
        ]);

        $driver = new PasarGuardDriver($this->panel());

        $this->assertTrue($driver->assignGroups('alice', [30]));

        // A partial PUT: only group_ids — never the limit/expiry (usage preserved).
        Http::assertSent(function ($request) {
            if ($request->url() !== self::BASE_URL.'/api/user/alice' || $request->method() !== 'PUT') {
                return false;
            }
            $body = $request->data();

            return $body['group_ids'] === [30]
                && ! array_key_exists('data_limit', $body)
                && ! array_key_exists('expire', $body);
        });
    }

    public function test_renew_recreates_the_user_when_missing_on_the_panel(): void
    {
        Http::fake([
            self::BASE_URL.'/api/admin/token' => Http::response(['access_token' => 'tok-123']),
            // PUT renew → user is gone from the (re-pointed) panel.
            self::BASE_URL.'/api/user/zoe' => Http::response(['detail' => 'User not found'], 404),
            // Fallback POST create → succeeds, returns the fresh account.
            self::BASE_URL.'/api/user' => Http::response([
                'username' => 'zoe', 'subscription_url' => '/sub/new', 'data_limit' => Bytes::GB, 'expire' => 1_900_000_000,
            ], 201),
        ]);

        $driver = new PasarGuardDriver($this->panel(['group_ids' => [30]]));

        $issued = $driver->renewConfig('zoe', new ConfigSpec(
            dataLimitBytes: Bytes::GB, expirySeconds: 86_400, identifier: 'zoe',
        ));

        // It fell back to a create (POST /api/user) and returned the new account.
        Http::assertSent(fn ($r) => $r->url() === self::BASE_URL.'/api/user' && $r->method() === 'POST');
        $this->assertSame('zoe', $issued->identifier);
        $this->assertSame(self::BASE_URL.'/sub/new', $issued->subscriptionUrl);
    }

    public function test_create_config_sends_empty_proxy_protocols_as_json_objects_not_arrays(): void
    {
        Http::fake([
            self::BASE_URL.'/api/admin/token' => Http::response(['access_token' => 'tok-123']),
            self::BASE_URL.'/api/user' => Http::response(['username' => 'carol'], 201),
        ]);

        // An empty protocol entry is exactly what triggered PasarGuard's 422
        // ("Input should be a valid dictionary or object") in production.
        $driver = new PasarGuardDriver($this->panel([
            'group_ids' => [26],
            'proxy_settings' => ['vless' => ['flow' => ''], 'vmess' => []],
        ]));

        $driver->createConfig(new ConfigSpec(dataLimitBytes: Bytes::GB, expirySeconds: 3_600, identifier: 'carol'));

        Http::assertSent(function ($request) {
            if ($request->url() !== self::BASE_URL.'/api/user') {
                return false;
            }

            $raw = $request->body();

            // The empty protocol must serialize as {} (object), never [] (array).
            return str_contains($raw, '"vmess":{}')
                && ! str_contains($raw, '"vmess":[]')
                && str_contains($raw, '"vless":{"flow":""}');
        });
    }

    public function test_create_config_on_hold_sends_on_hold_status_and_duration(): void
    {
        Http::fake([
            self::BASE_URL.'/api/admin/token' => Http::response(['access_token' => 'tok-123']),
            self::BASE_URL.'/api/user' => Http::response(['username' => 'dan', 'expire' => 0], 201),
        ]);

        $driver = new PasarGuardDriver($this->panel(['group_ids' => [26]]));

        $issued = $driver->createConfig(new ConfigSpec(
            dataLimitBytes: Bytes::GB,
            expirySeconds: 30 * 86_400,
            identifier: 'dan',
            onHold: true,
        ));

        Http::assertSent(function ($request) {
            if ($request->url() !== self::BASE_URL.'/api/user') {
                return false;
            }
            $b = $request->data();

            return $b['status'] === 'on_hold'
                && $b['expire'] === 0
                && $b['on_hold_expire_duration'] === 30 * 86_400;
        });

        // No absolute expiry until the user first connects.
        $this->assertNull($issued->expiresAt);
    }

    public function test_get_usage_parses_iso_expire_string_not_as_1970(): void
    {
        Http::fake([
            self::BASE_URL.'/api/admin/token' => Http::response(['access_token' => 'tok-123']),
            self::BASE_URL.'/api/user/eve' => Http::response([
                'used_traffic' => 0,
                'data_limit' => Bytes::GB,
                'expire' => '2027-01-01T00:00:00Z', // ISO, not unix seconds
                'status' => 'active',
            ]),
        ]);

        $usage = (new PasarGuardDriver($this->panel()))->getUsage('eve');

        $this->assertSame('2027-01-01', $usage->expiresAt?->format('Y-m-d'));
    }

    public function test_fetch_config_links_returns_links_from_the_user_api(): void
    {
        Http::fake([
            self::BASE_URL.'/api/admin/token' => Http::response(['access_token' => 'tok-123']),
            self::BASE_URL.'/api/user/dan' => Http::response([
                'username' => 'dan',
                'links' => ['vless://aaa', 'vmess://bbb'],
            ]),
        ]);

        $links = (new PasarGuardDriver($this->panel()))->fetchConfigLinks('dan');

        $this->assertSame(['vless://aaa', 'vmess://bbb'], $links);
    }

    public function test_get_usage_parses_traffic_limit_and_status(): void
    {
        Http::fake([
            self::BASE_URL.'/api/admin/token' => Http::response(['access_token' => 'tok-123']),
            self::BASE_URL.'/api/user/bob' => Http::response([
                'used_traffic' => 3 * Bytes::GB,
                'data_limit' => 5 * Bytes::GB,
                'expire' => 1_900_000_000,
                'status' => 'active',
            ]),
        ]);

        $usage = (new PasarGuardDriver($this->panel()))->getUsage('bob');

        $this->assertNotNull($usage);
        $this->assertSame(3 * Bytes::GB, $usage->usedBytes);
        $this->assertSame(5 * Bytes::GB, $usage->totalBytes);
        $this->assertSame('active', $usage->status);
        $this->assertSame(1_900_000_000, $usage->expiresAt?->getTimestamp());
    }

    public function test_rotate_subscription_calls_revoke_sub_and_returns_the_new_url(): void
    {
        Http::fake([
            self::BASE_URL.'/api/admin/token' => Http::response(['access_token' => 'tok-123']),
            self::BASE_URL.'/api/user/frank/revoke_sub' => Http::response([
                'username' => 'frank',
                'subscription_url' => '/sub/NEWTOKEN',
            ]),
        ]);

        $issued = (new PasarGuardDriver($this->panel()))->rotateSubscription('frank');

        Http::assertSent(fn ($request) => $request->url() === self::BASE_URL.'/api/user/frank/revoke_sub'
            && $request->method() === 'POST');

        $this->assertSame(self::BASE_URL.'/sub/NEWTOKEN', $issued->subscriptionUrl);
    }

    public function test_disabled_admin_login_raises_auth_exception(): void
    {
        // 403 on the token endpoint == disabled admin.
        Http::fake([
            self::BASE_URL.'/api/admin/token' => Http::response(['detail' => 'disabled'], 403),
        ]);

        $this->expectException(PanelAuthException::class);

        (new PasarGuardDriver($this->panel()))->testConnection();
    }
}
