<?php

namespace Tests\Feature;

use App\Enums\ReferralRuleMode;
use App\Enums\RewardType;
use App\Models\BotUser;
use App\Models\Referral;
use App\Models\ReferralRule;
use App\Services\ReferralService;
use App\Support\Bytes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReferralService
    {
        return app(ReferralService::class);
    }

    public function test_register_links_referred_to_referrer(): void
    {
        $referrer = BotUser::create(['telegram_id' => 1001]);
        $referred = BotUser::create(['telegram_id' => 2002]);

        $this->service()->register($referred, $referrer->telegram_id);

        $this->assertSame($referrer->id, $referred->fresh()->referred_by);
        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
            'status' => Referral::STATUS_PENDING,
        ]);
    }

    public function test_self_referral_is_ignored(): void
    {
        $user = BotUser::create(['telegram_id' => 5005]);

        $this->service()->register($user, $user->telegram_id);

        $this->assertNull($user->fresh()->referred_by);
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_recurring_rule_grants_reward_only_at_threshold_and_is_idempotent(): void
    {
        ReferralRule::create([
            'name' => 'هر ۲ نفر',
            'mode' => ReferralRuleMode::Recurring,
            'threshold' => 2,
            'reward_type' => RewardType::Traffic,
            'reward_amount' => Bytes::fromGb(5),
            'is_active' => true,
        ]);

        $referrer = BotUser::create(['telegram_id' => 9000]);

        // First verified referral — below threshold, no reward yet.
        $this->verifyNewReferral($referrer, 9001);
        $this->assertSame(0, $referrer->fresh()->bonus_traffic_bytes);

        // Second verified referral — hits threshold 2 => +5GB once.
        $this->verifyNewReferral($referrer, 9002);
        $referrer->refresh();
        $this->assertSame(Bytes::fromGb(5), $referrer->bonus_traffic_bytes);
        $this->assertDatabaseCount('referral_reward_grants', 1);

        // Re-evaluating must not double-grant.
        $this->service()->evaluateRules($referrer);
        $this->assertDatabaseCount('referral_reward_grants', 1);
        $this->assertSame(Bytes::fromGb(5), $referrer->fresh()->bonus_traffic_bytes);
    }

    private function verifyNewReferral(BotUser $referrer, int $referredTelegramId): void
    {
        $referred = BotUser::create(['telegram_id' => $referredTelegramId]);
        $this->service()->register($referred, $referrer->telegram_id);
        $this->service()->verify($referred);
    }
}
