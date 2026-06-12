<?php

namespace App\Services;

use App\Models\Panel;
use App\Models\Plan;

/**
 * Picks which panel a new config should be created on: respects a plan's panel
 * restriction, otherwise load-balances across active, healthy panels that still
 * have capacity (highest priority first, then least loaded).
 */
class PanelSelector
{
    public function select(?Plan $plan = null): ?Panel
    {
        if ($plan && $plan->panel_id) {
            $panel = $plan->panel;

            return $panel && $this->isUsable($panel) ? $panel : null;
        }

        return $this->available()->first();
    }

    /**
     * All currently usable panels (active, healthy, with capacity), best first.
     * Used by the user-facing server picker.
     *
     * @return \Illuminate\Support\Collection<int, Panel>
     */
    public function available(): \Illuminate\Support\Collection
    {
        return Panel::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('health_status')->orWhere('health_status', 'ok'))
            ->orderByDesc('priority')
            ->orderBy('active_config_count')
            ->get()
            ->filter(fn (Panel $panel) => $panel->hasCapacity())
            ->values();
    }

    protected function isUsable(Panel $panel): bool
    {
        return $panel->is_active
            && $panel->hasCapacity()
            && ($panel->health_status === null || $panel->health_status === 'ok');
    }
}
