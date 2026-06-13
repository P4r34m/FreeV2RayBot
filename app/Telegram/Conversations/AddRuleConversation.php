<?php

namespace App\Telegram\Conversations;

use App\Enums\ReferralRuleMode;
use App\Enums\RewardType;
use App\Models\ReferralRule;
use App\Support\Bytes;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin flow to add a referral rule:
 * mode → threshold → reward type → reward amount [→ days for "both"] → create.
 */
class AddRuleConversation extends Conversation
{
    /** 'recurring' | 'milestone' */
    public ?string $mode = null;

    public ?int $threshold = null;

    /** 'traffic' | 'duration' | 'both' */
    public ?string $rewardType = null;

    /** Carried between steps for the combined ("both") reward. */
    public ?int $rewardAmount = null;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            "🎁 <b>افزودن قانون رفرال</b>\n\n".
            "نوع قانون را انتخاب کنید:\n".
            "1) تکرارشونده (هر N نفر)\n".
            "2) پلکانی (در رسیدن به N)\n\n".
            'برای لغو: /cancel',
            parse_mode: 'HTML',
        );

        $this->next('captureMode');
    }

    public function captureMode(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $this->mode = match ($text) {
            '1' => ReferralRuleMode::Recurring->value,
            '2' => ReferralRuleMode::Milestone->value,
            default => null,
        };

        if ($this->mode === null) {
            $bot->sendMessage('فقط 1 یا 2 ارسال کنید، یا /cancel.');
            $this->next('captureMode');

            return;
        }

        $bot->sendMessage("🔢 آستانه (تعداد نفرات N) را به‌صورت یک عدد ارسال کنید.\nبرای لغو: /cancel");

        $this->next('captureThreshold');
    }

    public function captureThreshold(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! ctype_digit($text) || (int) $text < 1) {
            $bot->sendMessage('عدد نامعتبر است. یک عدد بزرگ‌تر از صفر ارسال کنید یا /cancel.');
            $this->next('captureThreshold');

            return;
        }

        $this->threshold = (int) $text;

        $bot->sendMessage(
            "🎁 نوع پاداش را انتخاب کنید:\n".
            "1) حجم\n".
            "2) زمان\n".
            "3) حجم + زمان\n\n".
            'برای لغو: /cancel'
        );

        $this->next('captureRewardType');
    }

    public function captureRewardType(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        $this->rewardType = match ($text) {
            '1' => RewardType::Traffic->value,
            '2' => RewardType::Duration->value,
            '3' => RewardType::Both->value,
            default => null,
        };

        if ($this->rewardType === null) {
            $bot->sendMessage('فقط 1 تا 3 ارسال کنید، یا /cancel.');
            $this->next('captureRewardType');

            return;
        }

        $prompt = $this->rewardType === RewardType::Duration->value
            ? '📅 مدت زمان پاداش را به <b>روز</b> ارسال کنید (یک عدد).'
            : '📦 مقدار حجم پاداش را به <b>گیگابایت</b> ارسال کنید (مثلاً 10 یا 1.5).';

        $bot->sendMessage($prompt."\nبرای لغو: /cancel", parse_mode: 'HTML');

        $this->next('captureAmount');
    }

    public function captureAmount(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if ($this->mode === null || $this->rewardType === null) {
            $bot->sendMessage('خطای داخلی در روند ساخت قانون. دوباره از ابتدا شروع کنید.');
            $this->end();

            return;
        }

        // Duration-only: the amount is days.
        if ($this->rewardType === RewardType::Duration->value) {
            if (! ctype_digit($text) || (int) $text < 1) {
                $bot->sendMessage('مقدار نامعتبر است. یک عدد بزرگ‌تر از صفر (روز) ارسال کنید یا /cancel.');
                $this->next('captureAmount');

                return;
            }

            $this->createRule($bot, (int) $text);

            return;
        }

        // Traffic or Both: the amount is GB.
        if (! is_numeric($text) || (float) $text <= 0) {
            $bot->sendMessage('مقدار نامعتبر است. یک عدد بزرگ‌تر از صفر (گیگابایت) ارسال کنید یا /cancel.');
            $this->next('captureAmount');

            return;
        }

        $bytes = Bytes::fromGb((float) $text);

        // Combined reward: capture the time dimension next.
        if ($this->rewardType === RewardType::Both->value) {
            $this->rewardAmount = $bytes;
            $bot->sendMessage("📅 تعداد روز پاداش را ارسال کنید (یک عدد).\nبرای لغو: /cancel");
            $this->next('captureDays');

            return;
        }

        $this->createRule($bot, $bytes);
    }

    public function captureDays(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($text === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return;
        }

        if (! ctype_digit($text) || (int) $text < 1) {
            $bot->sendMessage('مقدار نامعتبر است. یک عدد بزرگ‌تر از صفر (روز) ارسال کنید یا /cancel.');
            $this->next('captureDays');

            return;
        }

        $this->createRule($bot, (int) $this->rewardAmount, (int) $text);
    }

    /** Persist the rule and confirm. */
    private function createRule(Nutgram $bot, int $amount, ?int $days = null): void
    {
        $rule = ReferralRule::create([
            'mode' => ReferralRuleMode::from($this->mode),
            'threshold' => $this->threshold,
            'reward_type' => RewardType::from($this->rewardType),
            'reward_amount' => $amount,
            'reward_days' => $days,
            'is_active' => true,
        ]);

        $bot->sendMessage(
            "✅ قانون رفرال اضافه شد.\n{$rule->mode->label()} → {$rule->rewardLabel()}",
            parse_mode: 'HTML',
        );

        $this->end();
    }
}
