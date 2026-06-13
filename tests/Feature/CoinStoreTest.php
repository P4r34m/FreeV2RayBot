<?php

namespace Tests\Feature;

use App\Enums\ConfigStatus;
use App\Enums\PanelType;
use App\Enums\ReferralRuleMode;
use App\Enums\RewardType;
use App\Models\BotUser;
use App\Models\CoinPlan;
use App\Models\Config;
use App\Models\Panel;
use App\Models\ReferralRule;
use App\Models\Setting;
use App\Panels\Contracts\PanelDriver;
use App\Panels\Data\ConfigSpec;
use App\Panels\Data\ConfigUsage;
use App\Panels\Data\IssuedConfig;
use App\Panels\PanelManager;
use App\Services\CoinStoreService;
use App\Services\Exceptions\InsufficientCoinsException;
use App\Services\ReferralService;
use App\Support\Bytes;
use App\Support\SettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The coin economy: earning coins from invites (coin mode), and spending them on
 * coin packages as a new config or a top-up — with atomic, refundable deduction.
 */
class CoinStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_coin_mode_grants_coins_per_invite_and_ignores_reward_rules(): void
    {
        Setting::put(SettingKey::REFERRAL_MODE, 'coin');
        Setting::put(SettingKey::REFERRAL_COINS_PER_INVITE, 5);

        // A reward rule that must be ignored while in coin mode.
        ReferralRule::create([
            'name' => 'x', 'mode' => ReferralRuleMode::Recurring, 'threshold' => 1,
            'reward_type' => RewardType::Traffic, 'reward_amount' => Bytes::fromGb(1), 'is_active' => true,
        ]);

        $referrer = BotUser::create(['telegram_id' => 7000]);
        $referred = BotUser::create(['telegram_id' => 7001]);

        $svc = app(ReferralService::class);
        $svc->register($referred, $referrer->telegram_id);
        $svc->verify($referred);

        $referrer->refresh();
        $this->assertSame(5, $referrer->coins);
        $this->assertSame(0, $referrer->bonus_traffic_bytes);
        $this->assertDatabaseCount('referral_reward_grants', 0);
    }

    public function test_buy_new_deducts_coins_and_creates_a_coin_config(): void
    {
        $panel = $this->fakePanel();
        $user = BotUser::create(['telegram_id' => 8000, 'coins' => 100]);
        $plan = CoinPlan::create([
            'name' => '100G', 'data_limit_bytes' => 100 * Bytes::GB, 'duration_days' => 30,
            'coin_price' => 99, 'is_active' => true,
        ]);

        app(CoinStoreService::class)->buyNew($user, $plan, $panel);

        $this->assertSame(1, $user->fresh()->coins); // 100 - 99
        $config = Config::first();
        $this->assertSame(Config::SOURCE_COIN, $config->source);
        $this->assertSame(100 * Bytes::GB, $config->data_limit_bytes);
    }

    public function test_buy_new_with_insufficient_coins_throws_and_changes_nothing(): void
    {
        $panel = $this->fakePanel();
        $user = BotUser::create(['telegram_id' => 8001, 'coins' => 10]);
        $plan = CoinPlan::create([
            'name' => 'x', 'data_limit_bytes' => Bytes::GB, 'duration_days' => 1,
            'coin_price' => 99, 'is_active' => true,
        ]);

        try {
            app(CoinStoreService::class)->buyNew($user, $plan, $panel);
            $this->fail('Expected InsufficientCoinsException');
        } catch (InsufficientCoinsException) {
            $this->assertSame(10, $user->fresh()->coins);
            $this->assertDatabaseCount('configs', 0);
        }
    }

    public function test_buy_extend_adds_volume_to_existing_config(): void
    {
        $panel = $this->fakePanel();
        $user = BotUser::create(['telegram_id' => 8002, 'coins' => 100]);
        $config = $user->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_COIN, 'remote_identifier' => 'fv_x',
            'data_limit_bytes' => Bytes::fromGb(10), 'used_bytes' => 0,
            'status' => ConfigStatus::Active, 'expires_at' => now()->addDays(5),
        ]);
        $plan = CoinPlan::create([
            'name' => '+20G', 'data_limit_bytes' => Bytes::fromGb(20), 'duration_days' => 10,
            'coin_price' => 50, 'is_active' => true,
        ]);

        app(CoinStoreService::class)->buyExtend($user, $plan, $config);

        $this->assertSame(50, $user->fresh()->coins);
        $this->assertSame(Bytes::fromGb(30), $config->fresh()->data_limit_bytes);
    }

    public function test_buy_extend_rejects_a_free_config_and_does_not_charge(): void
    {
        $panel = $this->fakePanel();
        $user = BotUser::create(['telegram_id' => 8003, 'coins' => 100]);
        $free = $user->configs()->create([
            'panel_id' => $panel->id, 'source' => Config::SOURCE_FREE, 'remote_identifier' => 'fv_free',
            'data_limit_bytes' => Bytes::fromGb(10), 'status' => ConfigStatus::Active,
        ]);
        $plan = CoinPlan::create([
            'name' => 'x', 'data_limit_bytes' => Bytes::fromGb(5), 'duration_days' => 5,
            'coin_price' => 10, 'is_active' => true,
        ]);

        try {
            app(CoinStoreService::class)->buyExtend($user, $plan, $free);
            $this->fail('Expected the free config to be rejected');
        } catch (\InvalidArgumentException) {
            $this->assertSame(100, $user->fresh()->coins); // never charged
        }
    }

    /** A panel backed by a fake driver that echoes the spec into the IssuedConfig. */
    private function fakePanel(): Panel
    {
        $panel = Panel::create([
            'name' => 'P', 'type' => PanelType::ThreeXui, 'base_url' => 'https://p.example.com', 'is_active' => true,
        ]);

        $driver = new class($panel) implements PanelDriver
        {
            public function __construct(private Panel $panel) {}

            public function panel(): Panel
            {
                return $this->panel;
            }

            public function testConnection(): bool
            {
                return true;
            }

            public function createConfig(ConfigSpec $spec): IssuedConfig
            {
                return new IssuedConfig(
                    identifier: $spec->identifier,
                    subscriptionUrl: 'https://sub.example.com/'.$spec->identifier,
                    dataLimitBytes: $spec->dataLimitBytes,
                );
            }

            public function renewConfig(string $identifier, ConfigSpec $spec): IssuedConfig
            {
                return new IssuedConfig(identifier: $identifier, dataLimitBytes: $spec->dataLimitBytes);
            }

            public function getUsage(string $identifier): ?ConfigUsage
            {
                return null;
            }

            public function listTargets(): array
            {
                return [];
            }

            public function disableConfig(string $identifier): bool
            {
                return true;
            }

            public function deleteConfig(string $identifier): bool
            {
                return true;
            }

            public function rotateSubscription(string $identifier): IssuedConfig
            {
                return new IssuedConfig(identifier: $identifier);
            }
        };

        $manager = new class($driver) extends PanelManager
        {
            public function __construct(private PanelDriver $fake) {}

            public function driver(Panel $panel): PanelDriver
            {
                return $this->fake;
            }
        };

        $this->app->instance(PanelManager::class, $manager);

        return $panel;
    }
}
