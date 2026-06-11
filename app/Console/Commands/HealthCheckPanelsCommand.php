<?php

namespace App\Console\Commands;

use App\Models\Panel;
use App\Panels\PanelManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Probes each active panel's API so the selector can avoid dead servers and the
 * admin sees health at a glance.
 */
class HealthCheckPanelsCommand extends Command
{
    protected $signature = 'panels:health-check';

    protected $description = 'Test connectivity to every active panel and record health status';

    public function handle(PanelManager $panels): int
    {
        foreach (Panel::where('is_active', true)->get() as $panel) {
            try {
                $ok = $panels->driver($panel)->testConnection();
                $panel->update([
                    'health_status' => $ok ? 'ok' : 'failed',
                    'health_message' => $ok ? null : 'تست اتصال ناموفق بود',
                    'last_health_check_at' => now(),
                ]);
                $this->line(($ok ? '✔' : '✘')." {$panel->name}");
            } catch (Throwable $e) {
                $panel->update([
                    'health_status' => 'failed',
                    'health_message' => mb_substr($e->getMessage(), 0, 250),
                    'last_health_check_at' => now(),
                ]);
                $this->line("✘ {$panel->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
