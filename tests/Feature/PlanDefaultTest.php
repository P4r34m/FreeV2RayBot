<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_plan_id_setting_is_honored_when_active(): void
    {
        $a = Plan::create(['name' => 'A', 'is_active' => true, 'is_default' => true]);
        $b = Plan::create(['name' => 'B', 'is_active' => true, 'is_default' => false]);

        Setting::put(SettingKey::DEFAULT_PLAN_ID, $b->id);

        $this->assertSame($b->id, Plan::default()?->id);
    }

    public function test_falls_back_to_is_default_flag_when_setting_plan_inactive(): void
    {
        $a = Plan::create(['name' => 'A', 'is_active' => true, 'is_default' => true]);
        $b = Plan::create(['name' => 'B', 'is_active' => false, 'is_default' => false]);

        Setting::put(SettingKey::DEFAULT_PLAN_ID, $b->id);

        $this->assertSame($a->id, Plan::default()?->id);
    }

    public function test_falls_back_to_flag_with_no_setting(): void
    {
        Plan::create(['name' => 'A', 'is_active' => true, 'is_default' => false]);
        $def = Plan::create(['name' => 'B', 'is_active' => true, 'is_default' => true]);

        $this->assertSame($def->id, Plan::default()?->id);
    }
}
