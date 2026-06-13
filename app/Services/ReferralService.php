<?php

namespace App\Services;

use App\Enums\ReferralRuleMode;
use App\Enums\RewardType;
use App\Models\BotUser;
use App\Models\Referral;
use App\Models\ReferralRule;
use App\Models\Setting;
use App\Support\SettingKey;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Referral lifecycle: link an invitee to their referrer on /start, verify the
 * link when the invitee performs the qualifying action, then evaluate the
 * admin's referral rules and pay out rewards into the referrer's bonus wallet
 * (idempotently, via the referral_reward_grants ledger).
 */
class ReferralService
{
    public function enabled(): bool
    {
        return Setting::bool(SettingKey::REFERRAL_ENABLED, true);
    }

    /** Human-readable description of the active reward rules (for the bot). */
    public function describeRules(): string
    {
        $lines = ReferralRule::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (ReferralRule $rule) {
                $reward = $rule->rewardLabel();

                return $rule->mode === ReferralRuleMode::Recurring
                    ? "• به ازای هر {$rule->threshold} زیرمجموعه: {$reward} هدیه"
                    : "• با رسیدن به {$rule->threshold} زیرمجموعه: {$reward} هدیه";
            });

        return $lines->isEmpty() ? '' : "شرایط هدیه:\n".$lines->implode("\n");
    }

    /**
     * Record that $referred was invited by the user with $referrerTelegramId.
     * No-op if disabled, self-referral, or already linked.
     */
    public function register(BotUser $referred, int $referrerTelegramId): void
    {
        if (! $this->enabled() || $referred->referred_by !== null) {
            return;
        }

        if (config('v2raybot.limits.prevent_self_referral', true)
            && $referrerTelegramId === $referred->telegram_id) {
            return;
        }

        $referrer = BotUser::where('telegram_id', $referrerTelegramId)->first();
        if (! $referrer || $referrer->id === $referred->id) {
            return;
        }

        DB::transaction(function () use ($referrer, $referred) {
            $referred->forceFill(['referred_by' => $referrer->id])->save();

            Referral::firstOrCreate(
                ['referred_id' => $referred->id],
                ['referrer_id' => $referrer->id, 'status' => Referral::STATUS_PENDING],
            );
        });
    }

    /** Whether referrals are verified on join ('start') or on first config. */
    public function qualifyEvent(): string
    {
        return Setting::string(SettingKey::REFERRAL_QUALIFY_EVENT, 'first_config');
    }

    /**
     * Mark the invitee's referral as verified (if pending), bump the referrer's
     * verified count and evaluate reward rules. Returns the referrer when newly
     * verified (so the caller can apply rewards to their active config), else null.
     */
    public function verify(BotUser $referred): ?BotUser
    {
        if (! $this->enabled() || $referred->referred_by === null) {
            return null;
        }

        // Atomically claim the pending referral: a conditional UPDATE means only
        // one of N concurrent callers (e.g. duplicate first-config jobs) wins, so
        // referral_count is never double-incremented.
        $claimed = Referral::where('referred_id', $referred->id)
            ->where('status', Referral::STATUS_PENDING)
            ->update(['status' => Referral::STATUS_VERIFIED, 'verified_at' => now()]);

        if ($claimed === 0) {
            return null;
        }

        $referrer = Referral::where('referred_id', $referred->id)->first()?->referrer;
        if (! $referrer) {
            return null;
        }

        $referrer->increment('referral_count');
        $this->evaluateRules($referrer->refresh());

        return $referrer;
    }

    /**
     * Apply every active referral rule to the referrer's current verified count,
     * crediting any not-yet-granted reward. Safe to call repeatedly.
     */
    public function evaluateRules(BotUser $referrer): void
    {
        $count = $referrer->referral_count;

        foreach (ReferralRule::where('is_active', true)->get() as $rule) {
            if ($rule->threshold < 1) {
                continue;
            }

            $checkpoints = $rule->mode === ReferralRuleMode::Recurring
                ? $this->recurringCheckpoints($rule->threshold, $count)
                : ($count >= $rule->threshold ? [$rule->threshold] : []);

            foreach ($checkpoints as $checkpoint) {
                $this->grant($referrer, $rule, $checkpoint);
            }
        }
    }

    /** @return list<int> threshold multiples reached: N, 2N, 3N, ... <= count */
    protected function recurringCheckpoints(int $threshold, int $count): array
    {
        $checkpoints = [];
        for ($c = $threshold; $c <= $count; $c += $threshold) {
            $checkpoints[] = $c;
        }

        return $checkpoints;
    }

    /**
     * Credit one reward, guarded by the unique grant ledger so a given
     * (user, rule, checkpoint) pays out exactly once.
     */
    protected function grant(BotUser $referrer, ReferralRule $rule, int $checkpoint): void
    {
        try {
            DB::transaction(function () use ($referrer, $rule, $checkpoint) {
                $referrer->rewardGrants()->create([
                    'referral_rule_id' => $rule->id,
                    'referral_count_at_grant' => $checkpoint,
                    'reward_type' => $rule->reward_type,
                    'reward_amount' => $rule->reward_amount,
                    'note' => $rule->name,
                ]);

                if ($rule->reward_type === RewardType::Traffic) {
                    $referrer->increment('bonus_traffic_bytes', $rule->reward_amount);
                } elseif ($rule->reward_type === RewardType::Duration) {
                    $referrer->increment('bonus_days', $rule->reward_amount);
                } else { // Both: traffic (bytes) + time (days)
                    $referrer->increment('bonus_traffic_bytes', $rule->reward_amount);
                    $referrer->increment('bonus_days', (int) $rule->reward_days);
                }

                $referrer->increment('referral_rewarded_count');
            });
        } catch (QueryException $e) {
            // Duplicate grant (unique violation) => already paid; ignore.
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
        }
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) ($e->errorInfo[0] ?? ''), ['23000', '23505'], true);
    }
}
