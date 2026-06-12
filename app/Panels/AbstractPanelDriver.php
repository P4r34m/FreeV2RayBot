<?php

namespace App\Panels;

use App\Models\Panel;
use App\Panels\Contracts\PanelDriver;
use App\Panels\Exceptions\PanelException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Shared plumbing for concrete panel drivers: bound Panel, a configured HTTP
 * client, settings access and unit helpers. Auth specifics live in subclasses.
 */
abstract class AbstractPanelDriver implements PanelDriver
{
    public function __construct(protected readonly Panel $panel) {}

    public function panel(): Panel
    {
        return $this->panel;
    }

    /**
     * Base HTTP client pointed at the panel root, with a sane timeout and
     * optional TLS verification toggle (self-signed panels are common).
     */
    protected function client(): PendingRequest
    {
        $request = Http::baseUrl(rtrim($this->panel->base_url, '/'))
            ->timeout((int) $this->setting('timeout', 20))
            ->connectTimeout(10)
            ->acceptJson()
            ->withUserAgent('FreeV2RayBot/1.0');

        if (! (bool) $this->setting('verify_ssl', false)) {
            $request->withoutVerifying();
        }

        return $request;
    }

    /** Read a value from the panel's per-type `settings` JSON. */
    protected function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->panel->settings ?? [], $key, $default);
    }

    /**
     * Default: no listable targets. Concrete drivers override to fetch inbounds/
     * squads/groups from the panel.
     *
     * @return list<array{id: string, label: string}>
     */
    public function listTargets(): array
    {
        return [];
    }

    /** Raise a normalized panel error with context for logging. */
    protected function fail(string $message, array $context = [], ?\Throwable $previous = null): never
    {
        throw new PanelException($message, $context, previous: $previous);
    }

    /** Sanitize an identifier into something every panel accepts (a-z0-9_). */
    protected function normalizeIdentifier(string $identifier): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier) ?? '';

        return $clean !== '' ? $clean : 'u'.bin2hex(random_bytes(4));
    }
}
