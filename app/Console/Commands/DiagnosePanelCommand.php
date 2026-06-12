<?php

namespace App\Console\Commands;

use App\Models\Panel;
use App\Panels\Data\ConfigSpec;
use App\Panels\Exceptions\PanelException;
use App\Panels\PanelManager;
use Illuminate\Console\Command;

/**
 * Probe a panel's API end-to-end and print the RAW status/body of each step, so a
 * "couldn't create config" failure can be diagnosed on the server (where tinker
 * isn't installed in the --no-dev image).
 *
 * Auth + target listing are read-only; --create also issues and then deletes a
 * throwaway config to exercise the exact create path that the bot uses.
 */
class DiagnosePanelCommand extends Command
{
    protected $signature = 'panel:diagnose {id? : Panel id; omit to probe every panel} {--create : Also issue and delete a throwaway config}';

    protected $description = 'Probe a panel API (auth, targets, optional create) and print raw responses';

    public function handle(PanelManager $panels): int
    {
        $query = Panel::query();
        if ($this->argument('id')) {
            $query->whereKey($this->argument('id'));
        }
        $list = $query->get();

        if ($list->isEmpty()) {
            $this->error('No panels found.');

            return self::FAILURE;
        }

        foreach ($list as $panel) {
            $this->newLine();
            $this->info("== Panel #{$panel->id}: {$panel->name} ({$panel->type->value}) ==");
            $this->line('base_url: '.$panel->base_url);
            $this->line('settings: '.json_encode($panel->settings ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $driver = $panels->driver($panel);

            // 1) Auth / reachability.
            try {
                $driver->testConnection();
                $this->info('AUTH: ok');
            } catch (\Throwable $e) {
                $this->error('AUTH FAIL: '.$e->getMessage());
                $this->dumpContext($e);

                continue; // nothing else will work without auth
            }

            // 2) Target listing — a real authenticated GET against the API.
            try {
                $targets = $driver->listTargets();
                $this->info('TARGETS: '.count($targets));
                foreach (array_slice($targets, 0, 10) as $t) {
                    $this->line('  - '.$t['id'].' => '.$t['label']);
                }
            } catch (\Throwable $e) {
                $this->error('TARGETS FAIL: '.$e->getMessage());
                $this->dumpContext($e);
            }

            // 3) Optional create — exercises the failing path, then cleans up.
            if ($this->option('create')) {
                $id = 'diag'.random_int(1000, 9999);
                try {
                    $issued = $driver->createConfig(new ConfigSpec(
                        dataLimitBytes: 1073741824, // 1 GB
                        expirySeconds: 86400,
                        identifier: $id,
                    ));
                    $this->info('CREATE: ok -> '.$issued->identifier.' | sub='.$issued->subscriptionUrl);

                    try {
                        $driver->deleteConfig($issued->identifier);
                        $this->line('cleanup: deleted '.$issued->identifier);
                    } catch (\Throwable $e) {
                        $this->warn('cleanup failed: '.$e->getMessage());
                    }
                } catch (\Throwable $e) {
                    $this->error('CREATE FAIL: '.$e->getMessage());
                    $this->dumpContext($e);
                }
            }
        }

        return self::SUCCESS;
    }

    private function dumpContext(\Throwable $e): void
    {
        if ($e instanceof PanelException && $e->context !== []) {
            $this->line('context: '.json_encode($e->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }
}
