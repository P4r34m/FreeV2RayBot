<?php

namespace App\Panels;

use App\Models\Panel;
use App\Panels\Contracts\PanelDriver;
use InvalidArgumentException;

/**
 * Resolves the right PanelDriver implementation for a Panel model based on its
 * type. Drivers are lightweight (one HTTP client) so we build them on demand.
 */
class PanelManager
{
    /** @var array<int, PanelDriver> resolved drivers keyed by panel id */
    protected array $resolved = [];

    public function driver(Panel $panel): PanelDriver
    {
        if (isset($this->resolved[$panel->id])) {
            return $this->resolved[$panel->id];
        }

        $class = $panel->type->driverClass();

        if (! is_subclass_of($class, PanelDriver::class)) {
            throw new InvalidArgumentException("Driver [$class] must implement PanelDriver.");
        }

        return $this->resolved[$panel->id] = new $class($panel);
    }
}
