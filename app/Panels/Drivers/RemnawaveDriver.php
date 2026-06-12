<?php

namespace App\Panels\Drivers;

use App\Panels\AbstractPanelDriver;
use App\Panels\Data\ConfigSpec;
use App\Panels\Data\ConfigUsage;
use App\Panels\Data\IssuedConfig;
use App\Panels\Exceptions\PanelAuthException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Driver for the Remnawave panel (remnawave/panel).
 *
 * Unlike 3x-ui / PasarGuard there is no login handshake: Remnawave authenticates
 * with a single static API token (Authorization: Bearer ...). We still cache the
 * resolved token under "panel:{id}:auth" so token resolution/rotation has one
 * chokepoint and {@see self::request()} can transparently re-auth once on a
 * 401/403 by busting that cache and replaying the same call.
 *
 * Every successful (2xx) response body is wrapped as { "response": { ... } };
 * {@see self::unwrap()} centralises peeling that envelope.
 */
final class RemnawaveDriver extends AbstractPanelDriver
{
    /** Far-future fallback (10 years) used when a config has no expiry — expireAt is required by the API. */
    private const NO_EXPIRY_YEARS = 10;

    /** Username length bounds enforced by Remnawave. */
    private const USERNAME_MIN = 3;

    private const USERNAME_MAX = 36;

    /** Cached-token TTL. Static tokens rarely change; 30m bounds staleness after rotation. */
    private const TOKEN_TTL_MINUTES = 30;

    public function testConnection(): bool
    {
        // Cheapest authenticated read: a one-row user listing proves both
        // reachability and a valid token.
        $this->request('get', '/api/users', ['size' => 1, 'start' => 0], ['action' => 'testConnection']);

        return true;
    }

    public function createConfig(ConfigSpec $spec): IssuedConfig
    {
        $username = $this->username($spec->identifier);

        $payload = [
            'username' => $username,
            'status' => 'ACTIVE',
            'trafficLimitBytes' => max(0, $spec->dataLimitBytes),
            'trafficLimitStrategy' => (string) $this->setting('traffic_strategy', 'NO_RESET'),
            'expireAt' => $this->expireAtIso($spec),
            'activeInternalSquads' => $this->squadUuids(),
        ];

        $data = $this->request('post', '/api/users', $payload, [
            'action' => 'createConfig',
            'username' => $username,
        ]);

        return $this->toIssuedConfig($username, $data);
    }

    public function renewConfig(string $identifier, ConfigSpec $spec): IssuedConfig
    {
        $username = $this->username($identifier);
        $user = $this->fetchUser($username);

        if ($user === null) {
            $this->fail('Cannot renew unknown Remnawave user.', [
                'action' => 'renewConfig',
                'username' => $username,
            ]);
        }

        $uuid = (string) ($user['uuid'] ?? '');

        $data = $this->request('patch', '/api/users', [
            'uuid' => $uuid,
            'status' => 'ACTIVE',
            'trafficLimitBytes' => max(0, $spec->dataLimitBytes),
            'expireAt' => $this->expireAtIso($spec),
        ], ['action' => 'renewConfig', 'uuid' => $uuid]);

        // Reset consumed traffic via the dedicated action endpoint. If a given
        // Remnawave build exposes this under a different path the PATCH above
        // still renews the user; only the counter reset would be skipped (we
        // swallow non-auth failures so a renew is never lost to a 404 here).
        if ($spec->resetUsage && $uuid !== '') {
            $this->request(
                'post',
                "/api/users/{$uuid}/actions/reset-traffic",
                [],
                ['action' => 'resetTraffic', 'uuid' => $uuid],
                tolerateFailure: true,
            );
        }

        return $this->toIssuedConfig($username, $data);
    }

    public function getUsage(string $identifier): ?ConfigUsage
    {
        $username = $this->username($identifier);
        $user = $this->fetchUser($username);

        if ($user === null) {
            return null;
        }

        return new ConfigUsage(
            usedBytes: (int) ($user['usedTrafficBytes'] ?? 0),
            totalBytes: (int) ($user['trafficLimitBytes'] ?? 0),
            expiresAt: $this->parseExpiry($user['expireAt'] ?? null),
            status: isset($user['status']) ? (string) $user['status'] : null,
            raw: $user,
        );
    }

    /**
     * List the internal squads a new config can be attached to (the same UUIDs
     * we send as activeInternalSquads on create). Maps each squad's uuid/name to
     * the generic id/label contract. Any failure yields [] so the caller falls
     * back to manual entry.
     *
     * @return list<array{id: string, label: string}>
     */
    public function listTargets(): array
    {
        try {
            // GET /api/internal-squads → { response: { total, internalSquads: [{ uuid, name, ... }] } }.
            // unwrap() peels the { response: ... } envelope, leaving { total, internalSquads }.
            $data = $this->request('get', '/api/internal-squads', [], ['action' => 'listTargets']);

            $squads = $data['internalSquads'] ?? [];

            if (! is_array($squads)) {
                return [];
            }

            $targets = [];

            foreach ($squads as $squad) {
                $uuid = is_array($squad) ? ($squad['uuid'] ?? null) : null;

                if (! is_string($uuid) || $uuid === '') {
                    continue;
                }

                $name = $squad['name'] ?? null;

                $targets[] = [
                    'id' => $uuid,
                    'label' => is_string($name) && $name !== '' ? $name : $uuid,
                ];
            }

            return $targets;
        } catch (\Throwable) {
            return [];
        }
    }

    public function disableConfig(string $identifier): bool
    {
        $username = $this->username($identifier);
        $user = $this->fetchUser($username);

        if ($user === null) {
            return false;
        }

        $this->request('patch', '/api/users', [
            'uuid' => (string) ($user['uuid'] ?? ''),
            'status' => 'DISABLED',
        ], ['action' => 'disableConfig', 'username' => $username]);

        return true;
    }

    public function deleteConfig(string $identifier): bool
    {
        $username = $this->username($identifier);
        $user = $this->fetchUser($username);

        if ($user === null) {
            return true; // already gone — idempotent delete
        }

        $uuid = (string) ($user['uuid'] ?? '');

        // 404 is treated as success (already deleted).
        $this->request('delete', "/api/users/{$uuid}", [], [
            'action' => 'deleteConfig',
            'uuid' => $uuid,
        ], notFoundIsOk: true);

        return true;
    }

    /**
     * HTTP client pre-loaded with the bearer token and optional extra headers.
     */
    protected function authedClient(): PendingRequest
    {
        return $this->client()->withHeaders($this->authHeaders());
    }

    /**
     * Dispatch an authed request and return the unwrapped response array.
     *
     * Transparently re-authenticates ONCE on a 401/403 by busting the cached
     * token and replaying the exact same call; if the replay is still
     * unauthorized a PanelAuthException is raised.
     *
     * @param  'get'|'post'|'patch'|'delete'  $method
     * @param  array<string, mixed>  $body  query string for GET, JSON body otherwise
     * @param  array<string, mixed>  $context  attached to any thrown exception
     * @param  bool  $notFoundIsOk  treat a 404 as success (empty array)
     * @param  bool  $tolerateFailure  swallow non-auth failures (best-effort calls)
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $url,
        array $body = [],
        array $context = [],
        bool $notFoundIsOk = false,
        bool $tolerateFailure = false,
    ): array {
        $response = $this->send($this->authedClient(), $method, $url, $body);

        if ($this->isUnauthorized($response)) {
            // Single transparent re-auth: drop the cached token and replay.
            $response = $this->send($this->reauthedClient(), $method, $url, $body);

            if ($this->isUnauthorized($response)) {
                throw new PanelAuthException('Remnawave rejected the API token.', $context + [
                    'status' => $response->status(),
                ]);
            }
        }

        if ($notFoundIsOk && $response->status() === 404) {
            return [];
        }

        if ($tolerateFailure && $response->failed()) {
            return [];
        }

        return $this->unwrap($response, $context);
    }

    /** Fire a single HTTP verb against the panel (full URL preserves any base path). */
    private function send(PendingRequest $client, string $method, string $url, array $body): Response
    {
        $url = $this->endpoint($url);

        return match ($method) {
            'get' => $client->get($url, $body),
            'delete' => $client->delete($url, $body),
            default => $client->{$method}($url, $body),
        };
    }

    /**
     * Build the auth/header set. The token is cached so the (decrypt + settings)
     * resolution runs once per TTL and rotation has a single home.
     *
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        $token = Cache::remember(
            "panel:{$this->panel->id}:auth",
            now()->addMinutes(self::TOKEN_TTL_MINUTES),
            fn (): string => $this->resolveToken(),
        );

        $headers = ['Authorization' => "Bearer {$token}"];

        $xApiKey = $this->setting('x_api_key');
        if (is_string($xApiKey) && $xApiKey !== '') {
            $headers['X-Api-Key'] = $xApiKey;
        }

        if ((bool) $this->setting('xforwarded', false)) {
            $headers['x-forwarded-proto'] = 'https';
            $headers['x-forwarded-for'] = '127.0.0.1';
        }

        return $headers;
    }

    /** Resolve the static API token: encrypted Panel column first, settings fallback. */
    private function resolveToken(): string
    {
        $token = $this->panel->api_token;

        if (! is_string($token) || $token === '') {
            $token = (string) $this->setting('api_token', '');
        }

        if ($token === '') {
            throw new PanelAuthException('Remnawave API token is not configured.', [
                'panel_id' => $this->panel->id,
            ]);
        }

        return $token;
    }

    /** Fresh authed client after busting the cached token (used for the re-auth retry). */
    private function reauthedClient(): PendingRequest
    {
        Cache::forget("panel:{$this->panel->id}:auth");

        return $this->authedClient();
    }

    private function isUnauthorized(Response $response): bool
    {
        return $response->status() === 401 || $response->status() === 403;
    }

    /**
     * GET /api/users/by-username/{username}. Returns the unwrapped user array,
     * or null on 404.
     *
     * @return array<string, mixed>|null
     */
    private function fetchUser(string $username): ?array
    {
        $data = $this->request('get', "/api/users/by-username/{$username}", [], [
            'action' => 'fetchUser',
            'username' => $username,
        ], notFoundIsOk: true);

        // notFoundIsOk yields [] on a real 404; a present user always carries a uuid.
        return isset($data['uuid']) ? $data : null;
    }

    /**
     * Peel the { response: ... } envelope and assert a 2xx body.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function unwrap(Response $response, array $context = []): array
    {
        if ($response->failed()) {
            $this->fail('Remnawave API call failed.', $context + [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        }

        $json = $response->json();

        if (! is_array($json)) {
            $this->fail('Remnawave returned a malformed (non-JSON) response.', $context + [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        // 2xx bodies are wrapped as { response: {...} }; fall back to the root.
        $data = $json['response'] ?? $json;

        if (! is_array($data)) {
            $this->fail('Remnawave response envelope was malformed.', $context + [
                'body' => $json,
            ]);
        }

        return $data;
    }

    /**
     * Map a Remnawave user/create response onto an IssuedConfig.
     *
     * @param  array<string, mixed>  $data
     */
    private function toIssuedConfig(string $username, array $data): IssuedConfig
    {
        return new IssuedConfig(
            identifier: $username,
            subscriptionUrl: isset($data['subscriptionUrl']) ? (string) $data['subscriptionUrl'] : null,
            expiresAt: $this->parseExpiry($data['expireAt'] ?? null),
            dataLimitBytes: (int) ($data['trafficLimitBytes'] ?? 0),
            remoteUuid: isset($data['uuid']) ? (string) $data['uuid'] : null,
            subId: isset($data['shortUuid']) ? (string) $data['shortUuid'] : null,
            raw: $data,
        );
    }

    /**
     * Normalize + length-clamp the identifier into a valid Remnawave username
     * (3-36 chars). Short results are right-padded to keep the API happy.
     */
    private function username(string $identifier): string
    {
        $username = $this->normalizeIdentifier($identifier);

        if (mb_strlen($username) > self::USERNAME_MAX) {
            $username = mb_substr($username, 0, self::USERNAME_MAX);
        }

        if (mb_strlen($username) < self::USERNAME_MIN) {
            $username = str_pad($username, self::USERNAME_MIN, '0');
        }

        return $username;
    }

    /**
     * ISO-8601 expiry. expireAt is mandatory on Remnawave, so an unlimited spec
     * is mapped to "now + 10 years" rather than omitted.
     */
    private function expireAtIso(ConfigSpec $spec): string
    {
        $unix = $spec->expiresAtUnix();

        if ($unix <= 0) {
            return CarbonImmutable::now()->addYears(self::NO_EXPIRY_YEARS)->toIso8601String();
        }

        return CarbonImmutable::createFromTimestamp($unix)->toIso8601String();
    }

    /** Parse an ISO-8601 expireAt into CarbonImmutable, null when absent/blank. */
    private function parseExpiry(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }

    /**
     * Internal squad UUIDs to attach on creation.
     *
     * @return array<int, string>
     */
    private function squadUuids(): array
    {
        $squads = $this->setting('squad_uuids', []);

        return is_array($squads) ? array_values($squads) : [];
    }
}
