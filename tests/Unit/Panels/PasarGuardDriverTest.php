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
