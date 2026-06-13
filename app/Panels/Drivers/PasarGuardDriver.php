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
 * Driver for PasarGuard (PasarGuard/panel) — a FastAPI, Marzban-family panel.
 *
 * Auth is a bearer token obtained from a form-encoded admin login. The token is
 * cached in Laravel Cache and transparently refreshed once on a 401. A 403 means
 * the admin account is disabled and is treated as a hard auth failure.
 *
 * Units: data limits are raw bytes (0 = unlimited); expiry is a unix timestamp
 * in seconds (0/null = no expiry) — both already in the shape ConfigSpec exposes.
 */
final class PasarGuardDriver extends AbstractPanelDriver
{
    /** Cache TTL for the bearer token. Re-auth on 401 covers earlier expiry. */
    private const TOKEN_TTL_SECONDS = 3000; // 50 minutes

    public function testConnection(): bool
    {
        // A successful auth proves both reachability and credentials.
        $this->authenticate();

        return true;
    }

    public function createConfig(ConfigSpec $spec): IssuedConfig
    {
        $username = $this->normalizeIdentifier($spec->identifier);

        $response = $this->request('post', '/api/user', $this->userPayload($username, $spec));

        if ($response->status() !== 201) {
            $this->fail('PasarGuard user creation failed.', $this->errorContext($response, ['username' => $username]));
        }

        return $this->toIssuedConfig($username, $response->json());
    }

    public function renewConfig(string $identifier, ConfigSpec $spec): IssuedConfig
    {
        $username = $this->normalizeIdentifier($identifier);

        // Re-enable, bump expiry and (possibly) raise the data limit in one PUT.
        $response = $this->request('put', "/api/user/{$username}", $this->userPayload($username, $spec));

        if (! $response->successful()) {
            $this->fail('PasarGuard user renewal failed.', $this->errorContext($response, ['username' => $username]));
        }

        // Optionally zero the consumed traffic after the limits are in place.
        if ($spec->resetUsage) {
            $reset = $this->request('post', "/api/user/{$username}/reset");

            if (! $reset->successful()) {
                $this->fail('PasarGuard usage reset failed.', $this->errorContext($reset, ['username' => $username]));
            }
        }

        return $this->toIssuedConfig($username, $response->json());
    }

    public function getUsage(string $identifier): ?ConfigUsage
    {
        $username = $this->normalizeIdentifier($identifier);

        $response = $this->request('get', "/api/user/{$username}");

        if ($response->status() === 404) {
            return null; // config no longer exists on the panel
        }

        if (! $response->successful()) {
            $this->fail('PasarGuard usage lookup failed.', $this->errorContext($response, ['username' => $username]));
        }

        $data = $response->json();

        if (! is_array($data)) {
            $this->fail('PasarGuard returned a malformed usage payload.', $this->errorContext($response, ['username' => $username]));
        }

        return new ConfigUsage(
            usedBytes: (int) ($data['used_traffic'] ?? 0),
            totalBytes: (int) ($data['data_limit'] ?? 0),
            expiresAt: $this->expiryToCarbon($data['expire'] ?? null),
            status: isset($data['status']) ? (string) $data['status'] : null,
            raw: $data,
        );
    }

    /**
     * List the groups an admin can attach users to, for the create-config UI.
     *
     * PasarGuard exposes these at `GET /api/groups`, returning a GroupsResponse
     * of `{groups: [{id: int, name: string, ...}], total: int}`. The `id` lines
     * up with the `group_ids` sent in {@see userPayload()}. Any failure (auth,
     * network, malformed body) yields an empty list so the caller can fall back
     * to manual entry.
     *
     * @return list<array{id: string, label: string}>
     */
    public function listTargets(): array
    {
        try {
            $response = $this->request('get', '/api/groups');

            if (! $response->successful()) {
                return [];
            }

            $groups = $response->json('groups');

            if (! is_array($groups)) {
                return [];
            }

            $targets = [];

            foreach ($groups as $group) {
                if (! is_array($group) || ! isset($group['id'], $group['name'])) {
                    continue;
                }

                $targets[] = [
                    'id' => (string) $group['id'],
                    'label' => (string) $group['name'],
                ];
            }

            return $targets;
        } catch (\Throwable) {
            return [];
        }
    }

    public function disableConfig(string $identifier): bool
    {
        $username = $this->normalizeIdentifier($identifier);

        $response = $this->request('put', "/api/user/{$username}", ['status' => 'disabled']);

        if (! $response->successful()) {
            $this->fail('PasarGuard disable failed.', $this->errorContext($response, ['username' => $username]));
        }

        return true;
    }

    public function deleteConfig(string $identifier): bool
    {
        $username = $this->normalizeIdentifier($identifier);

        $response = $this->request('delete', "/api/user/{$username}");

        // Treat an already-gone user as success (idempotent delete).
        if ($response->status() === 404) {
            return true;
        }

        if (! $response->successful()) {
            $this->fail('PasarGuard delete failed.', $this->errorContext($response, ['username' => $username]));
        }

        return true;
    }

    public function rotateSubscription(string $identifier): IssuedConfig
    {
        $username = $this->normalizeIdentifier($identifier);

        // Native revoke: mints a new subscription token (and URL); quota/expiry untouched.
        $response = $this->request('post', "/api/user/{$username}/revoke_sub");

        if (! $response->successful()) {
            $this->fail('PasarGuard subscription revoke failed.', $this->errorContext($response, ['username' => $username]));
        }

        return $this->toIssuedConfig($username, $response->json());
    }

    /**
     * Send an authenticated request, transparently re-authenticating once if the
     * cached token is rejected.
     *
     * A 401 means the token expired/was revoked: we drop the cached token, log in
     * again and replay the call exactly once. A 403 means the admin account is
     * disabled — a hard auth failure — and a 401 on the replay means the fresh
     * credentials are bad. Both raise PanelAuthException.
     *
     * @param  'get'|'post'|'put'|'delete'  $method
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $url, array $payload = []): Response
    {
        $response = $this->dispatch($method, $url, $payload, $this->token());

        if ($response->status() === 403) {
            throw new PanelAuthException('PasarGuard admin is disabled (403).', [
                'panel_id' => $this->panel->getKey(),
                'url' => $url,
            ]);
        }

        if ($response->status() !== 401) {
            return $response;
        }

        // Token rejected — force a fresh login and replay the request once.
        Cache::forget($this->authCacheKey());
        $response = $this->dispatch($method, $url, $payload, $this->authenticate());

        if (in_array($response->status(), [401, 403], true)) {
            throw new PanelAuthException('PasarGuard re-authentication failed.', [
                'panel_id' => $this->panel->getKey(),
                'url' => $url,
                'status' => $response->status(),
            ]);
        }

        return $response;
    }

    /**
     * HTTP client carrying a bearer token. Kept separate so callers (and the
     * re-auth replay) share one place that wires the Authorization header.
     */
    protected function authedClient(string $token): PendingRequest
    {
        return $this->client()->withToken($token);
    }

    /**
     * Fire a single token-authenticated HTTP call.
     *
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $method, string $url, array $payload, string $token): Response
    {
        $client = $this->authedClient($token);
        $url = $this->endpoint($url); // full URL, preserves any base path

        return match ($method) {
            'get' => $client->get($url),
            'delete' => $client->delete($url),
            'put' => $client->put($url, $payload),
            default => $client->post($url, $payload),
        };
    }

    /** Cached bearer token, logging in lazily on a cold cache. */
    private function token(): string
    {
        $cached = Cache::get($this->authCacheKey());

        return is_string($cached) && $cached !== '' ? $cached : $this->authenticate();
    }

    /**
     * Perform the form-encoded admin login, cache and return the access token.
     *
     * @throws PanelAuthException on bad credentials, disabled admin or a
     *                            missing/malformed token in the response.
     */
    private function authenticate(): string
    {
        $response = $this->client()->asForm()->post($this->endpoint('/api/admin/token'), [
            'username' => (string) $this->panel->username,
            'password' => (string) $this->panel->password,
        ]);

        if ($response->status() === 403) {
            throw new PanelAuthException('PasarGuard admin is disabled (403).', [
                'panel_id' => $this->panel->getKey(),
            ]);
        }

        if (! $response->successful()) {
            throw new PanelAuthException('PasarGuard login failed.', [
                'panel_id' => $this->panel->getKey(),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new PanelAuthException('PasarGuard login returned no access_token.', [
                'panel_id' => $this->panel->getKey(),
            ]);
        }

        Cache::put($this->authCacheKey(), $token, self::TOKEN_TTL_SECONDS);

        return $token;
    }

    private function authCacheKey(): string
    {
        return "panel:{$this->panel->getKey()}:auth";
    }

    /**
     * Build the create/renew request body in PasarGuard's native shape.
     *
     * @return array<string, mixed>
     */
    private function userPayload(string $username, ConfigSpec $spec): array
    {
        $payload = [
            'username' => $username,
            // Raw bytes; 0 = unlimited (PasarGuard's own convention).
            'data_limit' => $spec->dataLimitBytes,
            'proxy_settings' => $this->proxySettings(),
            'group_ids' => $this->setting('group_ids', []),
            'note' => $spec->note,
        ];

        // On-hold: the expiry timer starts on first connection, so there is no
        // absolute expire yet — PasarGuard takes a duration instead.
        if ($spec->onHold) {
            return $payload + [
                'status' => 'on_hold',
                'expire' => 0,
                'on_hold_expire_duration' => $spec->expirySeconds, // seconds from first use
            ];
        }

        return $payload + [
            'status' => 'active',
            // Unix seconds; 0 when there is no expiry.
            'expire' => $spec->expiresAtUnix(),
        ];
    }

    /**
     * Per-protocol proxy settings for the user. PasarGuard's pydantic model wants
     * each protocol's value as a JSON OBJECT; an empty PHP array would serialize to
     * `[]` and be rejected ("Input should be a valid dictionary..."). So we coerce
     * any empty value — at the top level and per protocol — into an object so `{}`
     * is sent instead of `[]`.
     *
     * @return object|array<string, mixed>
     */
    private function proxySettings(): object|array
    {
        $raw = $this->setting('proxy_settings', ['vless' => ['flow' => '']]);

        if (! is_array($raw) || $raw === []) {
            return (object) [];
        }

        $normalized = [];
        foreach ($raw as $protocol => $settings) {
            $normalized[$protocol] = (is_array($settings) && $settings === []) ? (object) [] : $settings;
        }

        return $normalized;
    }

    /**
     * Map a PasarGuard UserResponse into the app's IssuedConfig DTO.
     *
     * @param  mixed  $data  decoded JSON body
     */
    private function toIssuedConfig(string $username, mixed $data): IssuedConfig
    {
        if (! is_array($data)) {
            $this->fail('PasarGuard returned a malformed user payload.', [
                'username' => $username,
                'panel_id' => $this->panel->getKey(),
            ]);
        }

        return new IssuedConfig(
            identifier: $username,
            subscriptionUrl: $this->absoluteSubscriptionUrl($data['subscription_url'] ?? null),
            expiresAt: $this->expiryToCarbon($data['expire'] ?? null),
            // Effective limit echoed back by the panel (0 = unlimited).
            dataLimitBytes: (int) ($data['data_limit'] ?? 0),
            raw: $data,
        );
    }

    /** Prefix relative subscription paths with the panel base URL. */
    private function absoluteSubscriptionUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim($this->panel->base_url, '/').'/'.ltrim($url, '/');
    }

    /**
     * Convert a PasarGuard `expire` into CarbonImmutable (null = no expiry).
     *
     * Newer PasarGuard returns `expire` as an ISO-8601 string, older/Marzban as a
     * unix-seconds integer; on-hold users report 0/null. Handle all three — a
     * blind (int) cast on an ISO string yields a tiny number (→ 1970).
     */
    private function expiryToCarbon(mixed $expire): ?CarbonImmutable
    {
        if ($expire === null || $expire === '' || $expire === 0 || $expire === '0') {
            return null;
        }

        if (is_numeric($expire)) {
            $seconds = (int) $expire;

            return $seconds > 0 ? CarbonImmutable::createFromTimestampUTC($seconds) : null;
        }

        try {
            return CarbonImmutable::parse((string) $expire);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Assemble a logging context array from a failed response.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function errorContext(Response $response, array $extra = []): array
    {
        return array_merge([
            'panel_id' => $this->panel->getKey(),
            'status' => $response->status(),
            'body' => $response->body(),
        ], $extra);
    }
}
