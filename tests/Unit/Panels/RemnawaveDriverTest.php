<?php

namespace Tests\Unit\Panels;

use App\Enums\PanelType;
use App\Models\Panel;
use App\Panels\Data\ConfigSpec;
use App\Panels\Drivers\RemnawaveDriver;
use App\Panels\Exceptions\PanelAuthException;
use App\Support\Bytes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit coverage for the Remnawave driver: request shape + units on create,
 * usage parsing, and the transparent re-auth flow on 401.
 */
class RemnawaveDriverTest extends TestCase
{
    private function makePanel(array $settings = []): Panel
    {
        $panel = new Panel;
        // Not persisted; assign an id so the cache key "panel:{id}:auth" resolves.
        $panel->forceFill([
            'id' => 42,
            'type' => PanelType::Remnawave,
            'base_url' => 'https://panel.example.com',
            'settings' => array_merge(['verify_ssl' => false, 'timeout' => 5], $settings),
        ]);
        // Goes through the `encrypted` cast — read back transparently decrypts.
        $panel->api_token = 'secret-token';

        return $panel;
    }

    private function wrap(array $payload): array
    {
        return ['response' => $payload];
    }

    public function test_create_config_sends_correct_body_and_units_and_parses_response(): void
    {
        Cache::flush();

        Http::fake([
            'https://panel.example.com/api/users' => Http::response($this->wrap([
                'uuid' => 'user-uuid-1',
                'shortUuid' => 'short123',
                'subscriptionUrl' => 'https://panel.example.com/sub/short123',
                'trafficLimitBytes' => 5 * Bytes::GB,
                'expireAt' => '2026-07-01T00:00:00.000Z',
            ]), 201),
        ]);

        $driver = new RemnawaveDriver($this->makePanel([
            'traffic_strategy' => 'DAY',
            'squad_uuids' => ['squad-a', 'squad-b'],
        ]));

        $issued = $driver->createConfig(new ConfigSpec(
            dataLimitBytes: 5 * Bytes::GB,
            expirySeconds: 3600,
            identifier: 'user-007',
        ));

        // Response is parsed off the { response: {...} } envelope into the DTO.
        $this->assertSame('user007', $issued->identifier);
        $this->assertSame('short123', $issued->subId);
        $this->assertSame('https://panel.example.com/sub/short123', $issued->subscriptionUrl);
        $this->assertSame(5 * Bytes::GB, $issued->dataLimitBytes);
        $this->assertSame('user-uuid-1', $issued->remoteUuid);

        // Request body carries bearer auth + correct native fields/units.
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://panel.example.com/api/users'
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $body['username'] === 'user007'
                && $body['status'] === 'ACTIVE'
                && $body['trafficLimitBytes'] === 5 * Bytes::GB
                && $body['trafficLimitStrategy'] === 'DAY'
                && $body['activeInternalSquads'] === ['squad-a', 'squad-b']
                && str_contains($body['expireAt'], 'T'); // ISO-8601
        });
    }

    public function test_get_usage_parses_traffic_and_status(): void
    {
        Cache::flush();

        Http::fake([
            'https://panel.example.com/api/users/by-username/*' => Http::response($this->wrap([
                'uuid' => 'user-uuid-1',
                'usedTrafficBytes' => 2 * Bytes::GB,
                'trafficLimitBytes' => 10 * Bytes::GB,
                'expireAt' => '2026-08-15T12:00:00.000Z',
                'status' => 'ACTIVE',
            ]), 200),
        ]);

        $driver = new RemnawaveDriver($this->makePanel());

        $usage = $driver->getUsage('user-007');

        $this->assertNotNull($usage);
        $this->assertSame(2 * Bytes::GB, $usage->usedBytes);
        $this->assertSame(10 * Bytes::GB, $usage->totalBytes);
        $this->assertSame('ACTIVE', $usage->status);
        $this->assertSame('2026-08-15', $usage->expiresAt?->toDateString());
    }

    public function test_transparent_reauth_on_401_then_succeeds(): void
    {
        Cache::flush();

        // First call 401s, the replay (after cache-bust) succeeds.
        Http::fakeSequence('https://panel.example.com/api/users/by-username/*')
            ->push(['message' => 'unauthorized'], 401)
            ->push($this->wrap([
                'uuid' => 'user-uuid-1',
                'usedTrafficBytes' => 0,
                'trafficLimitBytes' => 0,
                'status' => 'ACTIVE',
            ]), 200);

        $driver = new RemnawaveDriver($this->makePanel());

        $usage = $driver->getUsage('user-007');

        $this->assertNotNull($usage);
        $this->assertSame('ACTIVE', $usage->status);
        Http::assertSentCount(2); // original + one transparent retry
    }

    public function test_persistent_401_throws_panel_auth_exception(): void
    {
        Cache::flush();

        Http::fake([
            'https://panel.example.com/api/users/by-username/*' => Http::response(['message' => 'nope'], 401),
        ]);

        $driver = new RemnawaveDriver($this->makePanel());

        $this->expectException(PanelAuthException::class);
        $driver->getUsage('user-007');
    }
}
