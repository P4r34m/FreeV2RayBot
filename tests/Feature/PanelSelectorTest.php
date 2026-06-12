<?php

namespace Tests\Feature;

use App\Enums\PanelType;
use App\Models\Panel;
use App\Services\PanelSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelSelectorTest extends TestCase
{
    use RefreshDatabase;

    private function panel(array $attr): Panel
    {
        return Panel::create(array_merge([
            'type' => PanelType::ThreeXui,
            'base_url' => 'https://example.com',
            'is_active' => true,
        ], $attr));
    }

    public function test_available_lists_only_usable_panels_best_first(): void
    {
        $this->panel(['name' => 'A', 'priority' => 1]);
        $this->panel(['name' => 'B', 'priority' => 5]);
        $this->panel(['name' => 'C', 'is_active' => false]);
        $this->panel(['name' => 'D', 'health_status' => 'failed']);

        $available = app(PanelSelector::class)->available();

        $this->assertSame(2, $available->count());
        $this->assertSame('B', $available->first()->name); // higher priority first
    }

    public function test_panel_at_capacity_is_excluded(): void
    {
        $this->panel(['name' => 'full', 'capacity' => 2, 'active_config_count' => 2]);

        $this->assertSame(0, app(PanelSelector::class)->available()->count());
    }
}
