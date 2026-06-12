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
use Illuminate\Support\Str;

/**
 * Driver for 3x-ui (MHSanaei/3x-ui, the Sanaei fork).
 *
 * Auth model: POST /login with {username,password}; on success the panel sets a
 * "session" cookie we persist and replay as a Cookie header. Optionally a static
 * API token (Bearer) can be used instead of logging in. The session is cached in
 * Laravel Cache under "panel:{id}:auth" and re-acquired transparently on 401/403.
 *
 * Unit quirks handled here:
 *  - the client field is named `totalGB` but the value is BYTES (0 = unlimited);
 *  - `expiryTime` is UNIX MILLISECONDS (0 = no expiry);
 *  - the create/update body wraps the clients array as a JSON *string* under
 *    `settings` (a Go-side json.Unmarshal quirk of the addClient endpoint);
 *  - subId is not auto-generated (upstream bug #3237) so we generate it and build
 *    the subscription URL ourselves from sub_* settings.
 */
final class ThreeXuiDriver extends AbstractPanelDriver
{
    /** Cache TTL for a logged-in session cookie. 3x-ui sessions live ~1h by default. */
    private const SESSION_TTL_SECONDS = 3300;

    public function testConnection(): bool
    {
        // A successful (re-)auth plus a cheap authed read proves both reachability
        // and credentials. We hit the inbound list endpoint which always exists.
        $response = $this->send('GET', '/panel/api/inbounds/list');

        if ($response->failed()) {
            $this->fail('3x-ui connection check failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return true;
    }

    public function createConfig(ConfigSpec $spec): IssuedConfig
    {
        $inboundId = $this->inboundId();
        $email = $this->normalizeIdentifier($spec->identifier);
        $uuid = (string) Str::uuid();
        $subId = $this->generateSubId();

        $client = $this->buildClient($spec, $email, $uuid, $subId, enable: true);

        $response = $this->send('POST', '/panel/api/inbounds/addClient', [
            'id' => $inboundId,
            // 3x-ui expects `settings` as a JSON STRING, not a nested object.
            'settings' => json_encode(['clients' => [$client]]),
        ]);

        $data = $this->expectSuccess($response, 'create client', [
            'email' => $email,
            'inbound_id' => $inboundId,
        ]);

        return new IssuedConfig(
            identifier: $email,
            subscriptionUrl: $this->buildSubscriptionUrl($subId),
            configLinks: [],
            expiresAt: $this->expiryToCarbon($spec->expiresAtUnix() * 1000),
            dataLimitBytes: $spec->dataLimitBytes,
            remoteUuid: $uuid,
            subId: $subId,
            raw: $data,
        );
    }

    public function renewConfig(string $identifier, ConfigSpec $spec): IssuedConfig
    {
        $inboundId = $this->inboundId();
        $email = $this->normalizeIdentifier($identifier);

        // The updateClient endpoint is keyed by the client UUID. Re-read the
        // current client so we keep its uuid/subId even if the caller lost them.
        $current = $this->fetchClient($email);
        $uuid = $current['id'] ?? (string) Str::uuid();
        $subId = $current['subId'] ?? $this->generateSubId();

        $client = $this->buildClient($spec, $email, $uuid, $subId, enable: true);

        $response = $this->send('POST', "/panel/api/inbounds/updateClient/{$uuid}", [
            'id' => $inboundId,
            'settings' => json_encode(['clients' => [$client]]),
        ]);

        $data = $this->expectSuccess($response, 'renew client', [
            'email' => $email,
            'uuid' => $uuid,
        ]);

        if ($spec->resetUsage) {
            $this->resetTraffic($inboundId, $email);
        }

        return new IssuedConfig(
            identifier: $email,
            subscriptionUrl: $this->buildSubscriptionUrl($subId),
            configLinks: [],
            expiresAt: $this->expiryToCarbon($spec->expiresAtUnix() * 1000),
            dataLimitBytes: $spec->dataLimitBytes,
            remoteUuid: $uuid,
            subId: $subId,
            raw: $data,
        );
    }

    public function getUsage(string $identifier): ?ConfigUsage
    {
        $email = $this->normalizeIdentifier($identifier);

        $response = $this->send('GET', "/panel/api/inbounds/getClientTraffics/{$email}");

        if ($response->status() === 404) {
            return null;
        }

        $data = $this->expectSuccess($response, 'get usage', ['email' => $email]);

        // On a missing client 3x-ui returns success:true with obj:null.
        $obj = $data['obj'] ?? null;
        if (! is_array($obj)) {
            return null;
        }

        $up = (int) ($obj['up'] ?? 0);
        $down = (int) ($obj['down'] ?? 0);
        $total = (int) ($obj['total'] ?? 0);
        $enable = (bool) ($obj['enable'] ?? true);
        $expiryMs = (int) ($obj['expiryTime'] ?? 0);

        return new ConfigUsage(
            usedBytes: $up + $down,
            totalBytes: $total,
            expiresAt: $this->expiryToCarbon($expiryMs),
            status: $enable ? 'active' : 'disabled',
            raw: $obj,
        );
    }

    public function disableConfig(string $identifier): bool
    {
        $inboundId = $this->inboundId();
        $email = $this->normalizeIdentifier($identifier);

        $current = $this->fetchClient($email);
        if ($current === null) {
            // Nothing to disable — treat an absent client as already disabled.
            return true;
        }

        $uuid = $current['id'] ?? '';
        if ($uuid === '') {
            $this->fail('3x-ui client is missing a uuid; cannot disable.', ['email' => $email]);
        }

        // Re-send the client with enable:false, preserving its existing limits.
        $client = array_merge($current, ['enable' => false, 'email' => $email]);

        $response = $this->send('POST', "/panel/api/inbounds/updateClient/{$uuid}", [
            'id' => $inboundId,
            'settings' => json_encode(['clients' => [$client]]),
        ]);

        $this->expectSuccess($response, 'disable client', ['email' => $email, 'uuid' => $uuid]);

        return true;
    }

    public function deleteConfig(string $identifier): bool
    {
        $inboundId = $this->inboundId();
        $email = $this->normalizeIdentifier($identifier);

        $current = $this->fetchClient($email);
        if ($current === null) {
            return true; // already gone
        }

        $uuid = $current['id'] ?? '';
        if ($uuid === '') {
            $this->fail('3x-ui client is missing a uuid; cannot delete.', ['email' => $email]);
        }

        $response = $this->send('POST', "/panel/api/inbounds/{$inboundId}/delClient/{$uuid}");

        $this->expectSuccess($response, 'delete client', ['email' => $email, 'uuid' => $uuid]);

        return true;
    }

    /**
     * List the panel's inbounds so the operator can pick one (the `inbound_id`
     * setting this driver writes into) instead of typing it by hand.
     *
     * Hits the well-known GET /panel/api/inbounds/list via the authed client.
     * The envelope is {success:true, obj:[{id:int, remark:string,
     * protocol:string, port:int, enable:bool}, ...]}. Disabled inbounds are
     * skipped only when `enable` is explicitly false; anything else is included.
     *
     * Best-effort: any failure (auth, transport, malformed body) yields [] so
     * the caller can fall back to manual entry.
     *
     * @return list<array{id: string, label: string}>
     */
    public function listTargets(): array
    {
        try {
            $response = $this->send('GET', '/panel/api/inbounds/list');

            $data = $response->json();
            if (! is_array($data) || ($data['success'] ?? false) !== true) {
                return [];
            }

            $inbounds = $data['obj'] ?? null;
            if (! is_array($inbounds)) {
                return [];
            }

            $targets = [];

            foreach ($inbounds as $inbound) {
                if (! is_array($inbound) || ! isset($inbound['id'])) {
                    continue;
                }

                // Only skip when the panel explicitly reports enable:false.
                if (array_key_exists('enable', $inbound) && $inbound['enable'] === false) {
                    continue;
                }

                $remark = trim((string) ($inbound['remark'] ?? ''));
                $protocol = (string) ($inbound['protocol'] ?? '');
                $port = (string) ($inbound['port'] ?? '');

                $targets[] = [
                    'id' => (string) $inbound['id'],
                    'label' => $remark.' ['.$protocol.':'.$port.']',
                ];
            }

            return $targets;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ---------------------------------------------------------------------
    // Auth
    // ---------------------------------------------------------------------

    /**
     * A client carrying valid credentials. Prefers a static Bearer API token
     * (from settings or the encrypted panel column); otherwise replays the
     * cached "session" cookie (logging in transparently if it is absent).
     *
     * Re-auth on an expired session is handled by send(), which drops the cache
     * and rebuilds this client once on a 401/403.
     */
    protected function authedClient(): PendingRequest
    {
        if (($token = $this->apiToken()) !== null) {
            return $this->client()->withToken($token);
        }

        return $this->client()->withHeaders(['Cookie' => $this->sessionCookie()]);
    }

    /** Resolve the optional static API token (settings override, then panel column). */
    private function apiToken(): ?string
    {
        $fromSettings = $this->setting('api_token');
        if (is_string($fromSettings) && $fromSettings !== '') {
            return $fromSettings;
        }

        $fromPanel = $this->panel->api_token;

        return (is_string($fromPanel) && $fromPanel !== '') ? $fromPanel : null;
    }

    /** Return a cached session cookie, logging in if absent/expired. */
    private function sessionCookie(): string
    {
        $cached = Cache::get($this->authCacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->login();
    }

    /**
     * Perform the /login handshake and persist the "session" cookie string.
     * Throws PanelAuthException on bad credentials or a missing cookie.
     */
    private function login(): string
    {
        $response = $this->client()->asForm()->post('/login', [
            'username' => (string) $this->panel->username,
            'password' => (string) $this->panel->password,
        ]);

        if ($response->status() === 401 || $response->status() === 403) {
            throw new PanelAuthException('3x-ui login rejected the credentials.', [
                'panel_id' => $this->panel->id,
                'status' => $response->status(),
            ]);
        }

        if ($response->failed() || $response->json('success') !== true) {
            throw new PanelAuthException('3x-ui login failed.', [
                'panel_id' => $this->panel->id,
                'status' => $response->status(),
                'msg' => $response->json('msg'),
            ]);
        }

        $cookie = $this->extractSessionCookie($response);
        if ($cookie === null) {
            throw new PanelAuthException('3x-ui login succeeded but returned no session cookie.', [
                'panel_id' => $this->panel->id,
            ]);
        }

        Cache::put($this->authCacheKey(), $cookie, self::SESSION_TTL_SECONDS);

        return $cookie;
    }

    /** Pull the "session=..." cookie out of the login response's Set-Cookie headers. */
    private function extractSessionCookie(Response $response): ?string
    {
        foreach ($response->cookies()->toArray() as $cookie) {
            if (($cookie['Name'] ?? null) === 'session') {
                return 'session='.$cookie['Value'];
            }
        }

        // Fallback: parse the raw Set-Cookie header(s) directly.
        foreach ((array) $response->header('Set-Cookie') as $header) {
            if (preg_match('/(?:^|\s)session=([^;]+)/', (string) $header, $m) === 1) {
                return 'session='.$m[1];
            }
        }

        return null;
    }

    /** Forget the cached session so the next call re-authenticates. */
    private function forgetSession(): void
    {
        Cache::forget($this->authCacheKey());
    }

    private function authCacheKey(): string
    {
        return "panel:{$this->panel->id}:auth";
    }

    // ---------------------------------------------------------------------
    // Request helpers
    // ---------------------------------------------------------------------

    /**
     * Dispatch an authed request, transparently re-authing once on a 401/403.
     * GET requests carry no body; for everything else $json is sent as a JSON
     * object body (3x-ui's write endpoints all expect application/json).
     *
     * When the session cookie has expired the first call returns 401/403; we drop
     * the cached session, rebuild authedClient() (forcing a fresh /login) and
     * replay the exact same request once. A second rejection is terminal. Bearer
     * tokens are static, so we never retry those.
     *
     * @param  array<string, mixed>  $json
     */
    private function send(string $method, string $path, array $json = []): Response
    {
        $response = $this->dispatch($method, $path, $json);

        $unauthorized = $response->status() === 401 || $response->status() === 403;
        if ($unauthorized && $this->apiToken() === null) {
            $this->forgetSession();
            $response = $this->dispatch($method, $path, $json);

            if ($response->status() === 401 || $response->status() === 403) {
                throw new PanelAuthException('3x-ui re-authentication failed.', [
                    'panel_id' => $this->panel->id,
                    'path' => $path,
                    'status' => $response->status(),
                ]);
            }
        }

        return $response;
    }

    /**
     * Issue a single authed HTTP call (no re-auth bookkeeping).
     *
     * @param  array<string, mixed>  $json
     */
    private function dispatch(string $method, string $path, array $json): Response
    {
        $client = $this->authedClient();

        if (strtoupper($method) === 'GET') {
            return $client->get($path);
        }

        return $client->asJson()->send(strtoupper($method), $path, ['json' => $json]);
    }

    /**
     * Validate a 3x-ui JSON envelope: non-2xx or {success:false} become a
     * PanelException carrying the panel's `msg`.
     *
     * @return array<string, mixed> the decoded response body
     */
    private function expectSuccess(Response $response, string $action, array $context = []): array
    {
        if ($response->failed()) {
            $this->fail("3x-ui {$action} failed.", $context + [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        $data = $response->json();
        if (! is_array($data)) {
            $this->fail("3x-ui {$action} returned a malformed response.", $context + [
                'body' => $response->body(),
            ]);
        }

        if (($data['success'] ?? false) !== true) {
            $this->fail("3x-ui {$action} was rejected: ".($data['msg'] ?? 'unknown error'), $context + [
                'msg' => $data['msg'] ?? null,
            ]);
        }

        return $data;
    }

    /**
     * Read a single client (by email) out of the inbound so we can recover its
     * uuid/subId for renew/disable/delete. Returns null if not present.
     *
     * @return array<string, mixed>|null
     */
    private function fetchClient(string $email): ?array
    {
        $inboundId = $this->inboundId();

        $response = $this->send('GET', "/panel/api/inbounds/get/{$inboundId}");

        if ($response->status() === 404) {
            return null;
        }

        $data = $this->expectSuccess($response, 'load inbound', ['inbound_id' => $inboundId]);

        $settings = $data['obj']['settings'] ?? null;
        if (! is_string($settings)) {
            return null;
        }

        $decoded = json_decode($settings, true);
        $clients = is_array($decoded) ? ($decoded['clients'] ?? []) : [];

        foreach ($clients as $client) {
            if (is_array($client) && ($client['email'] ?? null) === $email) {
                return $client;
            }
        }

        return null;
    }

    /** POST resetClientTraffic for a single client (best-effort, validated). */
    private function resetTraffic(int $inboundId, string $email): void
    {
        $response = $this->send('POST', "/panel/api/inbounds/{$inboundId}/resetClientTraffic/{$email}");

        $this->expectSuccess($response, 'reset traffic', ['email' => $email]);
    }

    // ---------------------------------------------------------------------
    // Payload + URL builders
    // ---------------------------------------------------------------------

    /**
     * Build a single 3x-ui client payload from the normalized spec.
     *
     * @return array<string, mixed>
     */
    private function buildClient(ConfigSpec $spec, string $email, string $uuid, string $subId, bool $enable): array
    {
        return [
            'id' => $uuid,
            'email' => $email,
            // Field is named totalGB but 3x-ui treats the value as BYTES (0 = unlimited).
            'totalGB' => $spec->dataLimitBytes,
            // Unix MILLISECONDS, or 0 for no expiry.
            'expiryTime' => $spec->hasExpiry() ? $spec->expiresAtUnix() * 1000 : 0,
            'enable' => $enable,
            'flow' => (string) $this->setting('flow', ''),
            'limitIp' => (int) $this->setting('limit_ip', 0),
            'tgId' => '',
            'subId' => $subId,
            'reset' => 0,
        ];
    }

    /**
     * Build the subscription URL ourselves (the API does not return one and subId
     * is not auto-generated — upstream bug #3237). Collapses duplicate slashes.
     */
    private function buildSubscriptionUrl(string $subId): string
    {
        $scheme = (string) $this->setting('sub_scheme', 'https');
        $host = (string) $this->setting('sub_host', $this->defaultSubHost());
        $port = (int) $this->setting('sub_port', 2096);
        $path = '/'.trim((string) $this->setting('sub_path', '/sub/'), '/').'/';

        $url = sprintf('%s://%s:%d%s%s', $scheme, $host, $port, $path, $subId);

        // Collapse any accidental double slashes in the path portion only.
        return preg_replace('#(?<!:)//+#', '/', $url) ?? $url;
    }

    /** Derive a default subscription host from the panel base URL. */
    private function defaultSubHost(): string
    {
        $host = parse_url($this->panel->base_url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    /** A random 16-char alphanumeric subscription id (lowercase, like the panel). */
    private function generateSubId(): string
    {
        return Str::lower(Str::random(16));
    }

    /** The required inbound id this driver writes clients into. */
    private function inboundId(): int
    {
        $id = $this->setting('inbound_id');

        if ($id === null || (int) $id <= 0) {
            $this->fail('3x-ui driver requires a positive inbound_id setting.', [
                'panel_id' => $this->panel->id,
            ]);
        }

        return (int) $id;
    }

    /** Convert a 3x-ui millisecond expiry to a CarbonImmutable (null = no expiry). */
    private function expiryToCarbon(int $expiryMs): ?CarbonImmutable
    {
        if ($expiryMs <= 0) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp(intdiv($expiryMs, 1000));
    }
}
