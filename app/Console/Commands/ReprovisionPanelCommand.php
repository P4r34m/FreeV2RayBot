<?php

namespace App\Console\Commands;

use App\Enums\ConfigStatus;
use App\Models\Config;
use App\Models\Panel;
use App\Panels\Data\ConfigSpec;
use App\Panels\PanelManager;
use App\Telegram\Content;
use App\Telegram\Keyboards;
use App\Telegram\Presenter;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Re-create every active config's account on its panel — for when a panel row was
 * re-pointed at a fresh server (same panel_id, accounts gone). Works IN PLACE on
 * the existing config rows (no new rows), so a user keeps exactly one config per
 * source and never ends up with two free-tagged ones. The subscription link is
 * re-issued by the panel (it changes; pass --notify to DM users the new link).
 */
class ReprovisionPanelCommand extends Command
{
    protected $signature = 'configs:reprovision
        {panel? : panel id whose accounts must be re-created on its (new) server — omit to list panels}
        {--source= : only this source (free|coin); default both}
        {--only= : limit to these comma-separated config ids (for retrying specific failures)}
        {--notify : DM each user their new subscription link}
        {--execute : actually perform it (default: dry run)}';

    protected $description = 'Re-create active configs on a re-pointed panel, in place (no duplicates).';

    public function handle(PanelManager $panels): int
    {
        // No id given (or an unknown one) → list panels so the admin can find the id.
        if ($this->argument('panel') === null) {
            return $this->listPanels();
        }

        $panel = Panel::find((int) $this->argument('panel'));
        if (! $panel) {
            $this->error('پنل با این آیدی پیدا نشد. پنل‌های موجود:');
            $this->listPanels();

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $source = $this->option('source');

        $query = Config::with('botUser')
            ->where('panel_id', $panel->id)
            ->where('status', ConfigStatus::Active->value);

        if (in_array($source, [Config::SOURCE_FREE, Config::SOURCE_COIN], true)) {
            $query->where('source', $source);
        }

        if ($only = $this->option('only')) {
            $ids = array_filter(array_map('intval', explode(',', $only)));
            $query->whereIn('id', $ids);
        }

        $total = (clone $query)->count();
        $this->info(($execute ? 'EXEC' : 'DRY-RUN').": re-provisioning {$total} active configs on panel #{$panel->id} ({$panel->name}).");

        $driver = $panels->driver($panel);
        $ok = 0;
        $fail = 0;
        $notified = 0;

        $query->orderBy('id')->chunkById(100, function ($configs) use (&$ok, &$fail, &$notified, $driver, $panel, $execute) {
            foreach ($configs as $config) {
                $spec = $this->specFor($config);

                if (! $execute) {
                    $this->line("  would re-create #{$config->id} user=".($config->botUser?->telegram_id ?? '?')." id={$config->remote_identifier} limit={$spec->dataLimitBytes} exp={$spec->expirySeconds}s".($spec->onHold ? ' (on-hold)' : ''));
                    $ok++;

                    continue;
                }

                try {
                    $issued = $driver->createConfig($spec);

                    $config->update([
                        'remote_identifier' => $issued->identifier ?: $config->remote_identifier,
                        'remote_uuid' => $issued->remoteUuid ?: $config->remote_uuid,
                        'sub_id' => $issued->subId ?: $config->sub_id,
                        'subscription_url' => $issued->subscriptionUrl ?: $config->subscription_url,
                        'config_links' => $issued->configLinks ?: $config->config_links,
                        'data_limit_bytes' => $issued->dataLimitBytes ?: $spec->dataLimitBytes,
                        'used_bytes' => 0, // fresh account on the new server
                        'expires_at' => $issued->expiresAt ?? $config->expires_at,
                        'last_synced_at' => now(),
                        'panel_response' => $issued->raw ?: $config->panel_response,
                    ]);

                    if ($this->option('notify') && $config->botUser) {
                        $this->notify($config->fresh(['botUser']));
                        $notified++;
                    }

                    $ok++;
                } catch (Throwable $e) {
                    $fail++;
                    $detail = '';
                    if ($e instanceof \App\Panels\Exceptions\PanelException) {
                        $detail = ' [status='.($e->context['status'] ?? '?').'] body='
                            .\Illuminate\Support\Str::limit((string) ($e->context['body'] ?? ''), 300);
                    }
                    $this->error("  failed #{$config->id} (".$config->remote_identifier.'): '.$e->getMessage().$detail);
                }
            }
        });

        $this->info("Done: {$ok} ok, {$fail} failed".($this->option('notify') ? ", {$notified} notified" : '').($execute ? '.' : ' — re-run with --execute to apply.'));

        return self::SUCCESS;
    }

    /** Print every panel with its id + active-config count so the admin can pick one. */
    private function listPanels(): int
    {
        $rows = Panel::query()->orderBy('id')->get()->map(fn (Panel $p) => [
            $p->id,
            $p->name,
            $p->base_url,
            Config::where('panel_id', $p->id)->where('status', ConfigStatus::Active->value)->count(),
        ])->all();

        if ($rows === []) {
            $this->warn('هیچ پنلی ثبت نشده است.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Base URL', 'Active configs'], $rows);
        $this->info('اجرا: php artisan configs:reprovision <ID> --notify --execute');

        return self::SUCCESS;
    }

    /** Re-create with the SAME identifier, FULL original quota and the remaining time. */
    private function specFor(Config $config): ConfigSpec
    {
        $onHold = false;
        $expirySeconds = 0;

        if ($config->expires_at) {
            $expirySeconds = max(1, $config->expires_at->timestamp - now()->timestamp);
        } elseif ((int) $config->expiry_duration_days > 0) {
            // Never connected yet → re-create on-hold with the deferred duration.
            $onHold = true;
            $expirySeconds = (int) $config->expiry_duration_days * 86400;
        }

        return new ConfigSpec(
            dataLimitBytes: (int) $config->data_limit_bytes, // full original limit (0 = unlimited)
            expirySeconds: $expirySeconds,
            identifier: $config->remote_identifier,
            note: 'tg:'.($config->botUser?->telegram_id ?? ''),
            resetUsage: true,
            onHold: $onHold && $expirySeconds > 0,
        );
    }

    private function notify(Config $config): void
    {
        try {
            app(Nutgram::class)->sendMessage(
                text: Content::text('config.reprovision_notice')."\n\n".Presenter::configCaption($config),
                chat_id: $config->botUser->telegram_id,
                parse_mode: 'HTML',
                disable_web_page_preview: true,
                reply_markup: Keyboards::afterIssue($config),
            );
        } catch (Throwable) {
            // User may have blocked the bot; skip.
        }
    }
}
