<?php

namespace App\Panels\Contracts;

use App\Models\Panel;
use App\Panels\Data\ConfigSpec;
use App\Panels\Data\ConfigUsage;
use App\Panels\Data\IssuedConfig;

/**
 * Unified abstraction over the supported V2Ray panels (3x-ui, PasarGuard,
 * Remnawave). Every driver is bound to a single Panel model and converts the
 * normalized ConfigSpec (bytes + seconds) into panel-native API calls.
 *
 * Implementations MUST be side-effect free until a method is called and must
 * translate transport/HTTP failures into App\Panels\Exceptions\PanelException.
 */
interface PanelDriver
{
    /** The panel instance this driver operates against. */
    public function panel(): Panel;

    /**
     * Verify credentials + reachability. Returns true on success.
     * Should throw PanelAuthException on auth failure and PanelException on
     * transport errors so the caller can surface a precise reason.
     */
    public function testConnection(): bool;

    /**
     * Create a brand-new config/client and return everything needed to persist
     * it and hand the subscription link to the user.
     */
    public function createConfig(ConfigSpec $spec): IssuedConfig;

    /**
     * Renew/extend an existing config identified by $identifier: bump the
     * expiry, (optionally) reset traffic, raise the data limit and re-enable it.
     */
    public function renewConfig(string $identifier, ConfigSpec $spec): IssuedConfig;

    /**
     * Fetch current usage/quota/expiry for a config, or null if it no longer
     * exists on the panel.
     */
    public function getUsage(string $identifier): ?ConfigUsage;

    /** Disable (but keep) a config. Returns true on success. */
    public function disableConfig(string $identifier): bool;

    /** Permanently delete a config. Returns true on success (or already gone). */
    public function deleteConfig(string $identifier): bool;
}
