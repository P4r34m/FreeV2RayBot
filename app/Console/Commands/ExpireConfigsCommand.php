<?php

namespace App\Console\Commands;

use App\Enums\ConfigStatus;
use App\Models\Config;
use App\Panels\PanelManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Marks configs whose time or traffic is up as expired, disables them on the
 * panel (best effort) and frees the panel capacity counter.
 */
class ExpireConfigsCommand extends Command
{
    protected $signature = 'configs:expire';

    protected $description = 'Expire configs that ran out of time or traffic and disable them remotely';

    public function handle(PanelManager $panels): int
    {
        $expired = 0;

        Config::with('panel')
            ->where('status', ConfigStatus::Active->value)
            ->chunkById(200, function ($configs) use ($panels, &$expired) {
                foreach ($configs as $config) {
                    if (! $this->shouldExpire($config)) {
                        continue;
                    }

                    $config->update(['status' => ConfigStatus::Expired]);
                    $expired++;

                    if ($config->panel) {
                        $config->panel->decrement('active_config_count');
                        $this->disableRemotely($panels, $config);
                    }
                }
            });

        $this->info("Expired {$expired} configs.");

        return self::SUCCESS;
    }

    protected function shouldExpire(Config $config): bool
    {
        $timeUp = $config->expires_at !== null && $config->expires_at->isPast();
        $trafficUp = $config->data_limit_bytes > 0 && $config->used_bytes >= $config->data_limit_bytes;

        return $timeUp || $trafficUp;
    }

    protected function disableRemotely(PanelManager $panels, Config $config): void
    {
        try {
            $panels->driver($config->panel)->disableConfig($config->remote_identifier);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
