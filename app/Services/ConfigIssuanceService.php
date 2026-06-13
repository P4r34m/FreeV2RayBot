<?php

namespace App\Services;

use App\Enums\ConfigStatus;
use App\Models\BotUser;
use App\Models\Config;
use App\Models\Panel;
use App\Models\Plan;
use App\Panels\Data\ConfigSpec;
use App\Panels\Data\IssuedConfig;
use App\Panels\PanelManager;
use App\Services\Exceptions\NoPanelAvailableException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates and renews configs for bot users, folding in the referral bonus
 * wallet (extra traffic/days) and keeping panel counters + the Config row
 * consistent with the remote panel.
 */
class ConfigIssuanceService
{
    public function __construct(
        protected readonly PanelManager $panels,
        protected readonly PanelSelector $selector,
    ) {}

    /**
     * Issue a brand-new config to the user on a freshly-selected panel.
     *
     * @throws NoPanelAvailableException
     * @throws \App\Panels\Exceptions\PanelException
     */
    public function issueNew(BotUser $user, ?Plan $plan = null, ?Panel $panel = null): Config
    {
        // Panel may be forced by the user (server picker); otherwise auto-select.
        $panel ??= $this->selector->select($plan ?? Plan::default());

        if (! $panel) {
            throw new NoPanelAvailableException('هیچ سرور فعالی برای ساخت کانفیگ در دسترس نیست.');
        }

        // Resolve the plan (volume/duration) for the chosen panel.
        $plan ??= $this->planForPanel($panel);

        $identifier = $this->generateIdentifier($user);
        [$spec, $bonusBytes, $bonusDays] = $this->buildSpec(
            $user,
            $plan,
            $identifier,
            onHold: (bool) config('v2raybot.issuance.on_hold', true),
        );

        $driver = $this->panels->driver($panel);
        $issued = $driver->createConfig($spec);

        // The panel call already succeeded. Persist locally in a short transaction
        // and, if that fails, compensate by removing the now-orphaned remote config
        // (we intentionally do NOT hold a DB transaction open across the HTTP call).
        try {
            return DB::transaction(function () use ($user, $panel, $plan, $issued, $spec, $bonusBytes, $bonusDays) {
                $config = $user->configs()->create([
                    'panel_id' => $panel->id,
                    'plan_id' => $plan?->id,
                    'source' => Config::SOURCE_FREE,
                    'remote_identifier' => $issued->identifier,
                    'remote_uuid' => $issued->remoteUuid,
                    'sub_id' => $issued->subId,
                    'subscription_url' => $issued->subscriptionUrl,
                    'config_links' => $issued->configLinks ?: null,
                    'data_limit_bytes' => $issued->dataLimitBytes ?: $spec->dataLimitBytes,
                    'used_bytes' => 0,
                    'expires_at' => $issued->expiresAt,
                    // Capture the on-hold duration so the expiry label survives plan
                    // edits/deletion (null for absolute-expiry/unlimited configs).
                    'expiry_duration_days' => $spec->onHold ? (int) round($spec->expirySeconds / 86400) : null,
                    'status' => ConfigStatus::Active,
                    'last_synced_at' => now(),
                    'panel_response' => $issued->raw ?: null,
                ]);

                $panel->increment('active_config_count');
                $this->consumeWallet($user, $bonusBytes, $bonusDays);

                return $config;
            });
        } catch (\Throwable $e) {
            rescue(fn () => $driver->deleteConfig($issued->identifier), report: false);
            throw $e;
        }
    }

    /**
     * Renew an existing config: re-grant the plan allotment (+ wallet), extend
     * the expiry from now and reset traffic.
     */
    public function renew(Config $config, ?Plan $plan = null): Config
    {
        $config->loadMissing('panel');

        if (! $config->panel) {
            throw new NoPanelAvailableException('سرور این کانفیگ دیگر در دسترس نیست.');
        }

        // Plan precedence: an explicit plan, else the config's own plan, else the
        // panel's current plan (planForPanel itself falls back to the global
        // default). This keeps a renewal tied to the panel even when the original
        // plan was deleted — we never silently drop to the global default while the
        // panel still has its own plan.
        $plan ??= $config->plan ?? $this->planForPanel($config->panel);

        $user = $config->botUser;
        [$spec, $bonusBytes, $bonusDays] = $this->buildSpec($user, $plan, $config->remote_identifier, resetUsage: true);

        $driver = $this->panels->driver($config->panel);
        $issued = $driver->renewConfig($config->remote_identifier, $spec);

        return DB::transaction(function () use ($config, $user, $plan, $issued, $spec, $bonusBytes, $bonusDays) {
            $config->update([
                'plan_id' => $plan?->id,
                'subscription_url' => $issued->subscriptionUrl ?: $config->subscription_url,
                'data_limit_bytes' => $issued->dataLimitBytes ?: $spec->dataLimitBytes,
                'used_bytes' => 0,
                'expires_at' => $issued->expiresAt,
                'status' => ConfigStatus::Active,
                'last_synced_at' => now(),
                'panel_response' => $issued->raw ?: $config->panel_response,
            ]);

            $this->consumeWallet($user, $bonusBytes, $bonusDays);

            return $config->refresh();
        });
    }

    /**
     * Issue a config from an explicit volume/duration package (coin store) on a
     * chosen panel. Mirrors issueNew but without a Plan; tagged with $source.
     *
     * @throws \App\Panels\Exceptions\PanelException
     */
    public function issuePackage(BotUser $user, Panel $panel, int $dataLimitBytes, int $durationDays, string $source = Config::SOURCE_COIN): Config
    {
        $identifier = $this->generateIdentifier($user);
        $onHold = (bool) config('v2raybot.issuance.on_hold', true) && $durationDays > 0;

        $spec = new ConfigSpec(
            dataLimitBytes: $dataLimitBytes,
            expirySeconds: $durationDays > 0 ? $durationDays * 86400 : 0,
            identifier: $identifier,
            note: 'tg:'.$user->telegram_id,
            onHold: $onHold,
        );

        $driver = $this->panels->driver($panel);
        $issued = $driver->createConfig($spec);

        try {
            return DB::transaction(function () use ($user, $panel, $source, $issued, $spec, $durationDays, $onHold) {
                $config = $user->configs()->create([
                    'panel_id' => $panel->id,
                    'plan_id' => null,
                    'source' => $source,
                    'remote_identifier' => $issued->identifier,
                    'remote_uuid' => $issued->remoteUuid,
                    'sub_id' => $issued->subId,
                    'subscription_url' => $issued->subscriptionUrl,
                    'config_links' => $issued->configLinks ?: null,
                    'data_limit_bytes' => $issued->dataLimitBytes ?: $spec->dataLimitBytes,
                    'used_bytes' => 0,
                    'expires_at' => $issued->expiresAt,
                    'expiry_duration_days' => $onHold ? $durationDays : null,
                    'status' => ConfigStatus::Active,
                    'last_synced_at' => now(),
                    'panel_response' => $issued->raw ?: null,
                ]);

                $panel->increment('active_config_count');

                return $config;
            });
        } catch (\Throwable $e) {
            rescue(fn () => $driver->deleteConfig($issued->identifier), report: false);
            throw $e;
        }
    }

    /**
     * Add volume and/or days to an existing config (coin top-up). Usage is kept;
     * the config becomes active with an absolute expiry counted from max(now,
     * current expiry). Adding to an unlimited dimension leaves it unlimited.
     *
     * @throws NoPanelAvailableException|\App\Panels\Exceptions\PanelException
     */
    public function extendConfig(Config $config, int $addBytes, int $addDays): Config
    {
        $config->loadMissing('panel');

        if (! $config->panel) {
            throw new NoPanelAvailableException('سرور این کانفیگ در دسترس نیست.');
        }

        $newLimit = $config->data_limit_bytes > 0 ? $config->data_limit_bytes + $addBytes : 0;

        // Extend from whichever is later: now, or the current expiry.
        $base = max(now()->timestamp, $config->expires_at?->timestamp ?? now()->timestamp);
        $expirySeconds = $addDays > 0 ? ($base + $addDays * 86400) - now()->timestamp : 0;

        $spec = new ConfigSpec(
            dataLimitBytes: $newLimit,
            expirySeconds: $expirySeconds,
            identifier: $config->remote_identifier,
            resetUsage: false,
        );

        $issued = $this->panels->driver($config->panel)->renewConfig($config->remote_identifier, $spec);

        return DB::transaction(function () use ($config, $issued, $newLimit, $expirySeconds) {
            $config->update([
                'data_limit_bytes' => $issued->dataLimitBytes ?: $newLimit,
                'expires_at' => $issued->expiresAt ?? ($expirySeconds > 0 ? now()->addSeconds($expirySeconds) : $config->expires_at),
                // Now on an absolute expiry, so the on-hold duration no longer applies.
                'expiry_duration_days' => $expirySeconds > 0 ? null : $config->expiry_duration_days,
                'status' => ConfigStatus::Active,
                'last_synced_at' => now(),
                'subscription_url' => $issued->subscriptionUrl ?: $config->subscription_url,
            ]);

            return $config->refresh();
        });
    }

    /**
     * Rotate a config's subscription link: revoke the old one on the panel and
     * persist the new URL. Quota, expiry and usage are untouched.
     *
     * @throws NoPanelAvailableException
     * @throws \App\Panels\Exceptions\PanelException
     */
    public function rotateSubscription(Config $config): Config
    {
        $config->loadMissing('panel');

        if (! $config->panel) {
            throw new NoPanelAvailableException('سرور این کانفیگ در دسترس نیست.');
        }

        $issued = $this->panels->driver($config->panel)->rotateSubscription($config->remote_identifier);

        return DB::transaction(function () use ($config, $issued) {
            $config->update([
                'subscription_url' => $issued->subscriptionUrl ?: $config->subscription_url,
                'sub_id' => $issued->subId ?: $config->sub_id,
                'last_synced_at' => now(),
            ]);

            return $config->refresh();
        });
    }

    /**
     * Immediately push a freshly-granted referral reward onto the user's active
     * config (extends limit/expiry WITHOUT resetting usage). Returns the config
     * it was applied to, or null if there was none (reward stays in the wallet).
     */
    public function applyWalletToActiveConfig(BotUser $user): ?Config
    {
        $user->refresh();
        if ($user->bonus_traffic_bytes <= 0 && $user->bonus_days <= 0) {
            return null;
        }

        $config = $user->configs()
            ->where('status', ConfigStatus::Active->value)
            ->whereNotNull('panel_id')
            ->latest()->first();

        if (! $config) {
            return null;
        }

        $config->loadMissing('panel');
        if (! $config->panel) {
            return null;
        }

        $addBytes = $config->data_limit_bytes > 0 ? $user->bonus_traffic_bytes : 0;
        $newLimit = $config->data_limit_bytes > 0 ? $config->data_limit_bytes + $addBytes : 0;

        // Extend from the current expiry (not from now) so the user keeps the
        // time they already had.
        $expirySeconds = 0;
        $addDays = 0;
        if ($config->expires_at) {
            $base = max(now()->timestamp, $config->expires_at->timestamp);
            $addDays = $user->bonus_days;
            $expirySeconds = ($base + $addDays * 86400) - now()->timestamp;
        }

        // Nothing applicable right now (e.g. an unlimited-expiry config can't take
        // bonus days). We return WITHOUT consuming the wallet, so the reward stays
        // banked and is applied on the user's next finite issuance/renewal.
        if ($addBytes === 0 && $addDays === 0) {
            return null;
        }

        $spec = new ConfigSpec(
            dataLimitBytes: $newLimit,
            expirySeconds: $expirySeconds,
            identifier: $config->remote_identifier,
            resetUsage: false,
        );

        $issued = $this->panels->driver($config->panel)->renewConfig($config->remote_identifier, $spec);

        return DB::transaction(function () use ($config, $user, $issued, $newLimit, $expirySeconds, $addBytes, $addDays) {
            $config->update([
                'data_limit_bytes' => $issued->dataLimitBytes ?: $newLimit,
                'expires_at' => $issued->expiresAt ?? ($expirySeconds > 0 ? now()->addSeconds($expirySeconds) : $config->expires_at),
                'last_synced_at' => now(),
            ]);

            $this->consumeWallet($user, $addBytes, $addDays);

            return $config->refresh();
        });
    }

    /**
     * Build the ConfigSpec for a plan, adding the user's bonus wallet to any
     * finite dimension. Returns [spec, bonusBytesApplied, bonusDaysApplied].
     *
     * @return array{0: ConfigSpec, 1: int, 2: int}
     */
    protected function buildSpec(BotUser $user, ?Plan $plan, string $identifier, bool $resetUsage = true, bool $onHold = false): array
    {
        $baseBytes = $plan?->data_limit_bytes ?? 0;
        $baseDays = $plan?->duration_days ?? 0;

        $bonusBytes = $baseBytes > 0 ? max(0, $user->bonus_traffic_bytes) : 0;
        $bonusDays = $baseDays > 0 ? max(0, $user->bonus_days) : 0;

        $effectiveBytes = $baseBytes > 0 ? $baseBytes + $bonusBytes : 0;
        $effectiveSeconds = $baseDays > 0 ? ($baseDays + $bonusDays) * 86400 : 0;

        $spec = new ConfigSpec(
            dataLimitBytes: $effectiveBytes,
            expirySeconds: $effectiveSeconds,
            identifier: $identifier,
            note: 'tg:'.$user->telegram_id,
            resetUsage: $resetUsage,
            // On-hold only matters with a finite duration; an unlimited-time plan
            // has no timer to defer.
            onHold: $onHold && $effectiveSeconds > 0,
        );

        return [$spec, $bonusBytes, $bonusDays];
    }

    protected function consumeWallet(BotUser $user, int $bytes, int $days): void
    {
        if ($bytes <= 0 && $days <= 0) {
            return;
        }

        // Re-read the wallet so a concurrent grant/issuance can't make us decrement
        // below zero (the columns are unsigned). Runs inside the caller's txn.
        $user->refresh();

        if ($bytes > 0) {
            $user->decrement('bonus_traffic_bytes', min($bytes, $user->bonus_traffic_bytes));
        }
        if ($days > 0) {
            $user->decrement('bonus_days', min($days, $user->bonus_days));
        }
    }

    /** Plan to use for a panel: a plan restricted to it, else the global default. */
    protected function planForPanel(Panel $panel): ?Plan
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('panel_id', $panel->id)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first()
            ?? Plan::default();
    }

    protected function generateIdentifier(BotUser $user): string
    {
        $prefix = config('v2raybot.issuance.identifier_prefix', 'fv');

        return $prefix.'_'.$user->telegram_id.'_'.Str::lower(Str::random(5));
    }
}
