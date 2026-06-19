<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Panel;
use App\Models\Plan;

/**
 * Picks which panel a new config should be created on: respects a plan's panel
 * restriction, otherwise load-balances across active, healthy panels that still
 * have capacity (highest priority first, then least loaded).
 */
class PanelSelector
{
    public function select(?Plan $plan = null, string $source = Config::SOURCE_FREE): ?Panel
    {
        if ($plan && $plan->panel_id) {
            $panel = $plan->panel;

            return $panel && $this->isUsable($panel, $source) ? $panel : null;
        }

        return $this->available($source)->first();
    }

    /**
     * All currently usable panels (active, healthy, with capacity for the given
     * config source), best first. Free and coin capacity are tracked separately.
     *
     * @return \Illuminate\Support\Collection<int, Panel>
     */
    public function available(string $source = Config::SOURCE_FREE): \Illuminate\Support\Collection
    {
        return Panel::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('health_status')->orWhere('health_status', 'ok'))
            ->orderByDesc('priority')
            ->orderBy('active_config_count')
            ->get()
            ->filter(fn (Panel $panel) => $panel->hasCapacity($source))
            ->values();
    }

    protected function isUsable(Panel $panel, string $source = Config::SOURCE_FREE): bool
    {
        return $panel->is_active
            && $panel->hasCapacity($source)
            && ($panel->health_status === null || $panel->health_status === 'ok');
    }
}
