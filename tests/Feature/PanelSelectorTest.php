<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Enums\PanelType;
use App\Models\BotUser;
use App\Models\Config;
use App\Models\Panel;
use App\Services\PanelSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelSelectorTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function panel(array $attr): Panel
    {
        return Panel::create(array_merge([
            'type' => PanelType::ThreeXui,
            'base_url' => 'https://example.com',
            'is_active' => true,
        ], $attr));
    }

    /** Create $n active configs of $source on $panel (the live capacity source of truth). */
    private function fill(Panel $panel, int $n, string $source = Config::SOURCE_FREE): void
    {
        $user = BotUser::firstOrCreate(['telegram_id' => 700000]);

        for ($i = 0; $i < $n; $i++) {
            $user->configs()->create([
                'panel_id' => $panel->id,
                'source' => $source,
                'remote_identifier' => 'fv_'.(++$this->seq),
                'status' => ConfigStatus::Active,
            ]);
        }
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

    public function test_panel_at_free_capacity_is_excluded(): void
    {
        $panel = $this->panel(['name' => 'full', 'capacity' => 2]);
        $this->fill($panel, 2);

        $this->assertSame(0, app(PanelSelector::class)->available()->count());
    }

    public function test_capacity_minus_one_is_unlimited(): void
    {
        $panel = $this->panel(['name' => 'inf', 'capacity' => -1]);
        $this->fill($panel, 5);

        $this->assertTrue($panel->isUnlimited());
        $this->assertTrue($panel->hasCapacity());
        $this->assertNull($panel->remainingConfigs());
        $this->assertSame('نامحدود', $panel->remainingHuman());
    }

    public function test_remaining_configs_counts_down(): void
    {
        $panel = $this->panel(['name' => 'lim', 'capacity' => 10]);
        $this->fill($panel, 3);

        $this->assertSame(7, $panel->remainingConfigs());
        $this->assertSame('7', $panel->remainingHuman());
        $this->assertTrue($panel->hasCapacity());
    }

    public function test_free_and_coin_capacity_are_independent(): void
    {
        // Free capped at 1, coin unlimited. One free config fills the free cap only.
        $panel = $this->panel(['name' => 'split', 'capacity' => 1, 'coin_capacity' => null]);
        $this->fill($panel, 1, Config::SOURCE_FREE);

        $this->assertFalse($panel->hasCapacity(Config::SOURCE_FREE));   // free is full
        $this->assertTrue($panel->hasCapacity(Config::SOURCE_COIN));    // coin still open

        $selector = app(PanelSelector::class);
        $this->assertCount(0, $selector->available(Config::SOURCE_FREE));
        $this->assertCount(1, $selector->available(Config::SOURCE_COIN));
    }

    public function test_coin_capacity_does_not_count_free_configs(): void
    {
        // Coin capped at 1; a free config must NOT consume the coin slot.
        $panel = $this->panel(['name' => 'coincap', 'capacity' => null, 'coin_capacity' => 1]);
        $this->fill($panel, 3, Config::SOURCE_FREE);

        $this->assertTrue($panel->hasCapacity(Config::SOURCE_COIN));    // no coin configs yet
        $this->assertSame(1, $panel->remainingConfigs(Config::SOURCE_COIN));

        $this->fill($panel, 1, Config::SOURCE_COIN);
        $this->assertFalse($panel->fresh()->hasCapacity(Config::SOURCE_COIN)); // now full
    }
}
