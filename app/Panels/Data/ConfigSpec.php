<?php

namespace App\Panels\Data;

/**
 * Panel-agnostic description of the config we want a driver to create or renew.
 *
 * Drivers convert these normalized units into their native fields:
 *  - dataLimitBytes -> totalGB (3x-ui, actually bytes) / data_limit / trafficLimitBytes
 *  - expirySeconds  -> expiryTime (ms) / expire (unix s) / expireAt (ISO-8601)
 */
final readonly class ConfigSpec
{
    public function __construct(
        /** Traffic quota in bytes. 0 means unlimited. */
        public int $dataLimitBytes = 0,
        /** Time-to-live in seconds from "now". 0 means no expiry. */
        public int $expirySeconds = 0,
        /** Stable identifier used as the remote username/email/label. */
        public string $identifier = '',
        /** Optional human note stored on the panel where supported. */
        public ?string $note = null,
        /** When renewing, whether to reset the consumed traffic to zero. */
        public bool $resetUsage = true,
        /**
         * Create the account "on hold": the expiry timer starts on the user's
         * FIRST connection, not at creation. expirySeconds is then the duration
         * counted from first use (drivers map this to their native mechanism:
         * PasarGuard status=on_hold + on_hold_expire_duration; 3x-ui negative
         * expiryTime). There is no absolute expiry until the user connects.
         */
        public bool $onHold = false,
    ) {}

    public function hasDataLimit(): bool
    {
        return $this->dataLimitBytes > 0;
    }

    public function hasExpiry(): bool
    {
        return $this->expirySeconds > 0;
    }

    /** Absolute expiry as a unix timestamp in seconds (0 when no expiry). */
    public function expiresAtUnix(): int
    {
        return $this->hasExpiry() ? time() + $this->expirySeconds : 0;
    }

    public function withIdentifier(string $identifier): self
    {
        return new self(
            dataLimitBytes: $this->dataLimitBytes,
            expirySeconds: $this->expirySeconds,
            identifier: $identifier,
            note: $this->note,
            resetUsage: $this->resetUsage,
            onHold: $this->onHold,
        );
    }
}
