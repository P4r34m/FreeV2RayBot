<?php

namespace App\Console\Commands;

use App\Enums\ConfigStatus;
use App\Models\Config;
use App\Models\Panel;
use App\Panels\Drivers\PasarGuardDriver;
use App\Panels\PanelManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Move every active config on a PasarGuard panel onto a single group/inbound
 * (e.g. after a reprovision left users on "all inbounds"). Only the user's
 * group_ids change on the panel — traffic/limit/expiry are preserved. Dry-run by
 * default; also sets the panel's default group so future issuances match.
 */
class SetPanelInboundCommand extends Command
{
    protected $signature = 'panels:set-inbound
        {panel? : panel id — omit to list panels}
        {group? : group (inbound) id to put every user on — omit to list the panel groups}
        {--execute : actually apply it (default: dry run)}';

    protected $description = 'Put every active config on a PasarGuard panel onto one group/inbound (in place).';

    public function handle(PanelManager $panels): int
    {
        if ($this->argument('panel') === null) {
            return $this->listPanels();
        }

        $panel = Panel::find((int) $this->argument('panel'));
        if (! $panel) {
            $this->error('پنل با این آیدی پیدا نشد. پنل‌های موجود:');

            return $this->listPanels();
        }

        $driver = $panels->driver($panel);
        if (! $driver instanceof PasarGuardDriver) {
            $this->error('این دستور فقط برای پنل‌های پاسارگارد است.');

            return self::FAILURE;
        }

        $targets = $driver->listTargets(); // [{id, label}]
        $group = $this->argument('group');

        // No group chosen → show the panel's groups so the admin can pick one.
        if ($group === null) {
            if ($targets === []) {
                $this->warn('هیچ گروهی از پنل دریافت نشد (احتمالاً احراز هویت/دسترسی).');

                return self::SUCCESS;
            }

            $this->table(['Group ID', 'Name'], array_map(fn ($t) => [$t['id'], $t['label']], $targets));
            $this->info("اجرا: php artisan panels:set-inbound {$panel->id} <Group ID> --execute");

            return self::SUCCESS;
        }

        // Guard: the chosen group must actually exist on the panel.
        $ids = array_column($targets, 'id');
        if ($ids !== [] && ! in_array((string) $group, $ids, true)) {
            $this->error("گروه #{$group} روی این پنل وجود ندارد. گروه‌های موجود:");
            $this->table(['Group ID', 'Name'], array_map(fn ($t) => [$t['id'], $t['label']], $targets));

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');

        // Make future issuances use this group too (the per-panel default target).
        if ($execute) {
            $settings = $panel->settings ?? [];
            $settings['group_ids'] = [(int) $group];
            $panel->update(['settings' => $settings]);
        }

        $query = Config::with('botUser')
            ->where('panel_id', $panel->id)
            ->where('status', ConfigStatus::Active->value);

        $total = (clone $query)->count();
        $this->info(($execute ? 'EXEC' : 'DRY-RUN').": moving {$total} active configs on panel #{$panel->id} ({$panel->name}) to group #{$group}.");

        $ok = 0;
        $fail = 0;

        $query->orderBy('id')->chunkById(100, function ($configs) use (&$ok, &$fail, $driver, $group, $execute) {
            foreach ($configs as $config) {
                if (! $execute) {
                    $ok++;

                    continue;
                }

                try {
                    $driver->assignGroups($config->remote_identifier, [(int) $group]);
                    $ok++;
                } catch (Throwable $e) {
                    $fail++;
                    $detail = $e instanceof \App\Panels\Exceptions\PanelException
                        ? ' [status='.($e->context['status'] ?? '?').'] body='.\Illuminate\Support\Str::limit((string) ($e->context['body'] ?? ''), 200)
                        : '';
                    $this->error("  failed #{$config->id} (".$config->remote_identifier.'): '.$e->getMessage().$detail);
                }
            }
        });

        $this->info("Done: {$ok} ok, {$fail} failed".($execute ? '.' : ' — re-run with --execute to apply.'));

        return self::SUCCESS;
    }

    private function listPanels(): int
    {
        $rows = Panel::query()->orderBy('id')->get()
            ->map(fn (Panel $p) => [$p->id, $p->name, $p->base_url])->all();

        if ($rows === []) {
            $this->warn('هیچ پنلی ثبت نشده است.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Base URL'], $rows);
        $this->info('اجرا: php artisan panels:set-inbound <ID>            # لیست گروه‌ها');

        return self::SUCCESS;
    }
}
