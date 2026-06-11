<?php

namespace App\Panels\Data;

use Carbon\CarbonImmutable;

/**
 * Snapshot of a config's consumption as reported by the panel.
 */
final readonly class ConfigUsage
{
    public function __construct(
        public int $usedBytes = 0,
        public int $totalBytes = 0,
        public ?CarbonImmutable $expiresAt = null,
        /** Remote status string as the panel reports it (active/limited/expired/...). */
        public ?string $status = null,
        public array $raw = [],
    ) {}

    public function remainingBytes(): int
    {
        if ($this->totalBytes <= 0) {
            return PHP_INT_MAX; // unlimited
        }

        return max(0, $this->totalBytes - $this->usedBytes);
    }

    public function isExhausted(): bool
    {
        return $this->totalBytes > 0 && $this->usedBytes >= $this->totalBytes;
    }
}
