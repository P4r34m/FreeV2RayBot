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

    public function test_capacity_minus_one_is_unlimited(): void
    {
        $panel = $this->panel(['name' => 'inf', 'capacity' => -1, 'active_config_count' => 999]);

        $this->assertTrue($panel->isUnlimited());
        $this->assertTrue($panel->hasCapacity());
        $this->assertNull($panel->remainingConfigs());
        $this->assertSame('نامحدود', $panel->remainingHuman());
    }

    public function test_remaining_configs_counts_down(): void
    {
        $panel = $this->panel(['name' => 'lim', 'capacity' => 10, 'active_config_count' => 3]);

        $this->assertSame(7, $panel->remainingConfigs());
        $this->assertSame('7', $panel->remainingHuman());
        $this->assertTrue($panel->hasCapacity());
    }
}
