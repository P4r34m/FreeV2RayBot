<?php

namespace App\Panels\Data;

use Carbon\CarbonImmutable;

/**
 * Result of creating/renewing a config on a panel — everything the app needs
 * to persist the config row and hand the link to the user.
 */
final readonly class IssuedConfig
{
    public function __construct(
        /** Canonical remote identifier (email/username) to persist & reuse. */
        public string $identifier,
        /** Subscription URL to give the user (may be built client-side for 3x-ui). */
        public ?string $subscriptionUrl = null,
        /** Raw protocol links (vless://, vmess://...) when the panel exposes them. */
        public array $configLinks = [],
        /** Actual expiry as reported/computed, null when unlimited. */
        public ?CarbonImmutable $expiresAt = null,
        /** Effective data limit in bytes (0 = unlimited). */
        public int $dataLimitBytes = 0,
        /** Client UUID (3x-ui / vless id) when applicable. */
        public ?string $remoteUuid = null,
        /** Subscription id (3x-ui subId) when applicable. */
        public ?string $subId = null,
        /** Raw decoded panel response, stored for debugging. */
        public array $raw = [],
    ) {}
}
