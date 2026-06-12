<?php

namespace Tests\Feature;

use App\Enums\ReferralRuleMode;
use App\Enums\RewardType;
use App\Models\ReferralRule;
use App\Support\Bytes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

/** Admins can edit a referral rule's fields from the Telegram admin panel. */
class RuleEditTest extends TestCase
{
    use RefreshDatabase;

    private function rule(): ReferralRule
    {
        return ReferralRule::create([
            'mode' => ReferralRuleMode::Recurring,
            'threshold' => 5,
            'reward_type' => RewardType::Traffic,
            'reward_amount' => Bytes::fromGb(1),
            'is_active' => true,
        ]);
    }

    private function adminBot(): Nutgram
    {
        config(['v2raybot.bot.admin_ids' => ['42']]);

        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);
        $bot->willStartConversation();
        $bot->hearMessage(['from' => ['id' => 42, 'is_bot' => false, 'first_name' => 'A'], 'text' => '/start'])->reply();

        return $bot;
    }

    public function test_admin_can_edit_a_rule_threshold(): void
    {
        $rule = $this->rule();
        $bot = $this->adminBot();

        $bot->hearCallbackQueryData("admin:rules:editfield:{$rule->id}_threshold")->reply();
        $bot->hearText('9')->reply();

        $this->assertSame(9, $rule->fresh()->threshold);
    }

    public function test_admin_can_edit_a_rule_reward_amount(): void
    {
        $rule = $this->rule();
        $bot = $this->adminBot();

        $bot->hearCallbackQueryData("admin:rules:editfield:{$rule->id}_amount")->reply();
        $bot->hearText('2')->reply(); // 2 GB

        $this->assertSame(Bytes::fromGb(2), $rule->fresh()->reward_amount);
    }
}
