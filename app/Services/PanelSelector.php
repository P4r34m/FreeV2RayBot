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

        return Panel::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('health_status')->orWhere('health_status', 'ok'))
            ->orderByDesc('priority')
            ->orderBy('active_config_count')
            ->get()
            ->first(fn (Panel $panel) => $panel->hasCapacity());
    }

    protected function isUsable(Panel $panel): bool
    {
        return $panel->is_active
            && $panel->hasCapacity()
            && ($panel->health_status === null || $panel->health_status === 'ok');
    }
}
