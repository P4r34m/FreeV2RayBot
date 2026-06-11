<?php

namespace App\Console\Commands;

use App\Enums\ConfigStatus;
use App\Models\Config;
use App\Models\Panel;
use App\Panels\PanelManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pulls fresh usage/expiry for active configs from their panels so the bot and
 * admin reports show real consumption.
 */
class SyncConfigUsageCommand extends Command
{
    protected $signature = 'configs:sync-usage {--limit=500 : Max configs to sync this run}';

    protected $description = 'Sync used traffic and expiry for active configs from their panels';

    public function handle(PanelManager $panels): int
    {
        $synced = 0;
        $failed = 0;

        Config::with('panel')
            ->where('status', ConfigStatus::Active->value)
            ->whereNotNull('panel_id')
            ->orderBy('last_synced_at')
            ->limit((int) $this->option('limit'))
            ->chunkById(100, function ($configs) use ($panels, &$synced, &$failed) {
                foreach ($configs as $config) {
                    try {
                        $usage = $panels->driver($config->panel)->getUsage($config->remote_identifier);

                        if ($usage === null) {
                            // Gone from the panel: mark deleted and free capacity.
                            if ($config->status === ConfigStatus::Active && $config->panel_id) {
                                Panel::whereKey($config->panel_id)
                                    ->where('active_config_count', '>', 0)
                                    ->decrement('active_config_count');
                            }
                            $config->update(['status' => ConfigStatus::Deleted, 'last_synced_at' => now()]);

                            continue;
                        }

                        $config->update([
                            'used_bytes' => $usage->usedBytes,
                            'data_limit_bytes' => $usage->totalBytes ?: $config->data_limit_bytes,
                            'expires_at' => $usage->expiresAt ?? $config->expires_at,
                            'last_synced_at' => now(),
                        ]);
                        $synced++;
                    } catch (Throwable $e) {
                        $failed++;
                        report($e);
                    }
                }
            });

        $this->info("Synced {$synced} configs ({$failed} failed).");

        return self::SUCCESS;
    }
}
