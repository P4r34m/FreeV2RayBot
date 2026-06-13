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
 * Driver for 3x-ui (MHSanaei/3x-ui, the Sanaei fork) — v3.3.1 client API.
 *
 * Auth model: POST /login with {username,password}; on success the panel sets a
 * "session" cookie we persist and replay as a Cookie header. Optionally a static
 * API token (Bearer) can be used instead of logging in. The session is cached in
 * Laravel Cache under "panel:{id}:auth" and re-acquired transparently on 401/403.
 *
 * v3.3.1 replaces the old /panel/api/inbounds/addClient endpoint with a
 * client-centric API under /panel/api/clients/* keyed by the client email:
 *  - create:  POST /panel/api/clients/add  {client:{...}, inboundIds:[...]}
 *  - update:  POST /panel/api/clients/update/{email}
 *  - delete:  POST /panel/api/clients/del/{email}?keepTraffic=0
 *  - reset:   POST /panel/api/clients/resetTraffic/{email}
 *  - usage:   GET  /panel/api/clients/traffic/{email}
 *
 * Unit quirks handled here:
 *  - the client field is named `totalGB` but the value is BYTES (0 = unlimited);
 *  - `expiryTime` is UNIX MILLISECONDS (0 = no expiry);
 *  - one client attaches to many inbounds in a single create via `inboundIds`;
 *  - subId is not returned, so we generate it and build the subscription URL
 *    ourselves from the sub_* settings.
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
        $inboundIds = $this->inboundIds();
        $email = $this->normalizeIdentifier($spec->identifier);
        $subId = $this->generateSubId();

        // v3.3.1 client API: create the client and attach it to all selected
        // inbounds in ONE call. The shared subId aggregates them under one sub
        // link; protocol secrets (uuid/password) are auto-generated server-side.
        $response = $this->send('POST', '/panel/api/clients/add', [
            'client' => $this->buildClientPayload($spec, $email, $subId),
            'inboundIds' => $inboundIds,
        ]);

        $data = $this->expectSuccess($response, 'create client', [
            'email' => $email,
            'inbound_ids' => $inboundIds,
        ]);

        return new IssuedConfig(
            identifier: $email,
            subscriptionUrl: $this->buildSubscriptionUrl($subId),
            configLinks: [],
            // On-hold configs have no absolute expiry until first use.
            expiresAt: $spec->onHold ? null : $this->expiryToCarbon($spec->expiresAtUnix() * 1000),
            dataLimitBytes: $spec->dataLimitBytes,
            remoteUuid: null,
            subId: $subId,
            raw: $data,
        );
    }

    public function renewConfig(string $identifier, ConfigSpec $spec): IssuedConfig
    {
        $email = $this->normalizeIdentifier($identifier);

        // update is keyed by email and propagates to every attached inbound.
        $response = $this->send('POST', "/panel/api/clients/update/{$email}", [
            'email' => $email,
            'totalGB' => $spec->dataLimitBytes,
            'expiryTime' => $spec->expiresAtUnix() * 1000,
            'limitIp' => (int) $this->setting('limit_ip', 0),
            'enable' => true,
        ]);

        $data = $this->expectSuccess($response, 'renew client', ['email' => $email]);

        if ($spec->resetUsage) {
            $this->send('POST', "/panel/api/clients/resetTraffic/{$email}");
        }

        return new IssuedConfig(
            identifier: $email,
            // subId/sub URL are unchanged on renew — caller keeps the existing one.
            subscriptionUrl: null,
            configLinks: [],
            expiresAt: $this->expiryToCarbon($spec->expiresAtUnix() * 1000),
            dataLimitBytes: $spec->dataLimitBytes,
            raw: $data,
        );
    }

    public function getUsage(string $identifier): ?ConfigUsage
    {
        $email = $this->normalizeIdentifier($identifier);

        $response = $this->send('GET', "/panel/api/clients/traffic/{$email}");

        if ($response->status() === 404) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data) || ($data['success'] ?? false) !== true) {
            return null; // client not found
        }

        $obj = $data['obj'] ?? null;
        if (! is_array($obj)) {
            return null;
        }

        $up = (int) ($obj['up'] ?? 0);
        $down = (int) ($obj['down'] ?? 0);

        return new ConfigUsage(
            usedBytes: $up + $down,
            totalBytes: (int) ($obj['total'] ?? 0),
            expiresAt: $this->expiryToCarbon((int) ($obj['expiryTime'] ?? 0)),
            status: ($obj['enable'] ?? true) ? 'active' : 'disabled',
            raw: $obj,
        );
    }

    public function disableConfig(string $identifier): bool
    {
        $email = $this->normalizeIdentifier($identifier);

        // Partial update: omitted fields (quota/expiry) are preserved server-side.
        $response = $this->send('POST', "/panel/api/clients/update/{$email}", [
            'email' => $email,
            'enable' => false,
        ]);

        $this->expectSuccess($response, 'disable client', ['email' => $email]);

        return true;
    }

    public function deleteConfig(string $identifier): bool
    {
        $email = $this->normalizeIdentifier($identifier);

        // Removes the client from every attached inbound; idempotent if absent.
        $this->send('POST', "/panel/api/clients/del/{$email}?keepTraffic=0");

        return true;
    }

    public function rotateSubscription(string $identifier): IssuedConfig
    {
        $email = $this->normalizeIdentifier($identifier);
        $subId = $this->generateSubId();

        // Partial update: only the subId changes; quota/expiry are preserved
        // server-side. The old sub link dies, the new one points to $subId.
        $response = $this->send('POST', "/panel/api/clients/update/{$email}", [
            'email' => $email,
            'subId' => $subId,
        ]);

        $this->expectSuccess($response, 'rotate subscription', ['email' => $email]);

        return new IssuedConfig(
            identifier: $email,
            subscriptionUrl: $this->buildSubscriptionUrl($subId),
            subId: $subId,
        );
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
        $response = $this->client()->asForm()->post($this->endpoint('/login'), [
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
        $url = $this->endpoint($path); // full URL, preserves any panel web path

        if (strtoupper($method) === 'GET') {
            return $client->get($url);
        }

        return $client->asJson()->send(strtoupper($method), $url, ['json' => $json]);
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
     * Build the v3.3.1 client object for clients/add. Only universal fields are
     * sent; protocol secrets (uuid/password) are generated server-side.
     *
     * @return array<string, mixed>
     */
    private function buildClientPayload(ConfigSpec $spec, string $email, string $subId): array
    {
        $client = [
            'email' => $email,
            'totalGB' => $spec->dataLimitBytes,   // bytes (0 = unlimited)
            'expiryTime' => $this->expiryMillis($spec),
            'limitIp' => (int) $this->setting('limit_ip', 0),
            'enable' => true,
            'subId' => $subId,
        ];

        $flow = (string) $this->setting('flow', '');
        if ($flow !== '') {
            $client['flow'] = $flow;
        }

        return $client;
    }

    /**
     * 3x-ui expiryTime in milliseconds: 0 = no expiry, a positive value = absolute
     * Unix ms, and a NEGATIVE value = a duration that 3x-ui starts counting from
     * the client's first connection (on-hold).
     */
    private function expiryMillis(ConfigSpec $spec): int
    {
        if (! $spec->hasExpiry()) {
            return 0;
        }

        return $spec->onHold
            ? -1 * $spec->expirySeconds * 1000
            : $spec->expiresAtUnix() * 1000;
    }

    // ---------------------------------------------------------------------
    // Payload + URL builders
    // ---------------------------------------------------------------------

    /**
     * Build the subscription URL from the configured sub host/port/path and the
     * subId we set on the client. Collapses duplicate slashes.
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

    /**
     * Every inbound id this driver writes clients into. Reads the new
     * `inbound_ids` array, falling back to the legacy single `inbound_id`.
     *
     * @return list<int>
     */
    private function inboundIds(): array
    {
        $s = $this->panel->settings ?? [];
        $raw = $s['inbound_ids'] ?? (isset($s['inbound_id']) ? [$s['inbound_id']] : []);

        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $raw),
            fn ($i) => $i > 0,
        )));

        if ($ids === []) {
            $this->fail('3x-ui driver requires at least one inbound (از «تنظیمات بیشتر» اینباند را انتخاب کنید).', [
                'panel_id' => $this->panel->id,
            ]);
        }

        return $ids;
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
