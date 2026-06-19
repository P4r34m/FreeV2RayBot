<?php

namespace App\Console\Commands;

use App\Enums\ConfigStatus;
use App\Models\Config;
use App\Services\ConfigUsageService;
use Illuminate\Console\Command;

/**
 * Pulls fresh usage/expiry for active configs from their panels so the bot and
 * admin reports show real consumption.
 */
class SyncConfigUsageCommand extends Command
{
    protected $signature = 'configs:sync-usage {--limit=500 : Max configs to sync this run}';

    protected $description = 'Sync used traffic and expiry for active configs from their panels';

    public function handle(ConfigUsageService $usage): int
    {
        $processed = 0;

        Config::with('panel')
            ->where('status', ConfigStatus::Active->value)
            ->whereNotNull('panel_id')
            ->orderBy('last_synced_at')
            ->limit((int) $this->option('limit'))
            ->chunkById(100, function ($configs) use ($usage, &$processed) {
                foreach ($configs as $config) {
                    // Same per-config refresh the bot uses at view time (errors are
                    // reported and the config kept; missing-on-panel → marked deleted).
                    $usage->refresh($config);
                    $processed++;
                }
            });

        $this->info("Processed {$processed} configs.");

        return self::SUCCESS;
    }
}
