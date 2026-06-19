<?php

namespace App\Services;

use App\Enums\ConfigStatus;
use App\Models\Config;
use App\Models\Panel;
use App\Panels\PanelManager;
use Throwable;

/** Pulls a single config's live usage/expiry from its panel and persists it. */
class ConfigUsageService
{
    public function __construct(private readonly PanelManager $panels) {}

    /**
     * Refresh one active config from its panel right now. On a panel error the
     * stored values are kept (graceful); a config that no longer exists on the
     * panel is marked deleted and its slot freed. Returns the (refreshed) config.
     */
    public function refresh(Config $config): Config
    {
        $config->loadMissing('panel');

        if (! $config->panel || $config->status !== ConfigStatus::Active) {
            return $config;
        }

        try {
            $usage = $this->panels->driver($config->panel)->getUsage($config->remote_identifier);
        } catch (Throwable $e) {
            report($e);

            return $config; // panel unreachable → show last-known values
        }

        if ($usage === null) {
            // Gone from the panel: mark deleted and free the panel's capacity slot.
            if ($config->panel_id) {
                Panel::whereKey($config->panel_id)
                    ->where('active_config_count', '>', 0)
                    ->decrement('active_config_count');
            }
            $config->update(['status' => ConfigStatus::Deleted, 'last_synced_at' => now()]);

            return $config->refresh();
        }

        $config->update([
            'used_bytes' => $usage->usedBytes,
            'data_limit_bytes' => $usage->totalBytes ?: $config->data_limit_bytes,
            'expires_at' => $usage->expiresAt ?? $config->expires_at,
            'last_synced_at' => now(),
        ]);

        return $config->refresh();
    }
}
