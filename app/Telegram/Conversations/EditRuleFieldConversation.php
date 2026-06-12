<?php

namespace App\Telegram\Conversations;

use App\Enums\ReferralRuleMode;
use App\Enums\RewardType;
use App\Models\ReferralRule;
use App\Support\Bytes;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

/**
 * Edit a single field of a referral rule from the bot: mode, threshold, reward
 * type, or reward amount. Changing the reward type re-asks for the amount, since
 * the stored value means GB (traffic) or days (duration) depending on the type.
 */
class EditRuleFieldConversation extends Conversation
{
    private const FIELDS = ['mode', 'threshold', 'rewardtype', 'amount'];

    public ?int $ruleId = null;

    public ?string $field = null;

    public function start(Nutgram $bot): void
    {
        $rule = ReferralRule::find($this->ruleId);

        if (! in_array($this->field, self::FIELDS, true) || ! $rule) {
            $bot->sendMessage('مورد نامعتبر است.');
            $this->end();

            return;
        }

        match ($this->field) {
            'mode' => $this->ask($bot, "نوع قانون را انتخاب کنید:\n1) تکرارشونده (هر N نفر)\n2) پلکانی (در رسیدن به N)", 'captureMode'),
            'threshold' => $this->ask($bot, '🔢 آستانه (تعداد نفرات N) را به‌صورت یک عدد ارسال کنید.', 'captureThreshold'),
            'rewardtype' => $this->ask($bot, "🎁 نوع پاداش را انتخاب کنید:\n1) حجم\n2) زمان", 'captureRewardType'),
            'amount' => $this->ask($bot, $this->amountPrompt($rule), 'captureAmount'),
        };
    }

    public function captureMode(Nutgram $bot): void
    {
        if ($this->cancelled($bot)) {
            return;
        }

        $text = trim($bot->message()?->text ?? '');
        $mode = match ($text) {
            '1' => ReferralRuleMode::Recurring,
            '2' => ReferralRuleMode::Milestone,
            default => null,
        };

        if ($mode === null) {
            $bot->sendMessage('فقط 1 یا 2 ارسال کنید، یا /cancel.');
            $this->next('captureMode');

            return;
        }

        $this->persist($bot, fn (ReferralRule $rule) => $rule->mode = $mode);
    }

    public function captureThreshold(Nutgram $bot): void
    {
        if ($this->cancelled($bot)) {
            return;
        }

        $text = trim($bot->message()?->text ?? '');
        if (! ctype_digit($text) || (int) $text < 1) {
            $bot->sendMessage('عدد نامعتبر است. یک عدد بزرگ‌تر از صفر ارسال کنید یا /cancel.');
            $this->next('captureThreshold');

            return;
        }

        $this->persist($bot, fn (ReferralRule $rule) => $rule->threshold = (int) $text);
    }

    public function captureRewardType(Nutgram $bot): void
    {
        if ($this->cancelled($bot)) {
            return;
        }

        $text = trim($bot->message()?->text ?? '');
        $type = match ($text) {
            '1' => RewardType::Traffic,
            '2' => RewardType::Duration,
            default => null,
        };

        if ($type === null) {
            $bot->sendMessage('فقط 1 یا 2 ارسال کنید، یا /cancel.');
            $this->next('captureRewardType');

            return;
        }

        $rule = ReferralRule::find($this->ruleId);
        if (! $rule) {
            $bot->sendMessage('قانون پیدا نشد.');
            $this->end();

            return;
        }

        $rule->reward_type = $type;
        $rule->save();

        // The amount's unit just changed, so ask for it again.
        $this->ask($bot, $this->amountPrompt($rule), 'captureAmount');
    }

    public function captureAmount(Nutgram $bot): void
    {
        if ($this->cancelled($bot)) {
            return;
        }

        $rule = ReferralRule::find($this->ruleId);
        if (! $rule) {
            $bot->sendMessage('قانون پیدا نشد.');
            $this->end();

            return;
        }

        $text = trim($bot->message()?->text ?? '');

        if ($rule->reward_type === RewardType::Traffic) {
            if (! is_numeric($text) || (float) $text <= 0) {
                $bot->sendMessage('مقدار نامعتبر است. یک عدد بزرگ‌تر از صفر (گیگابایت) ارسال کنید یا /cancel.');
                $this->next('captureAmount');

                return;
            }
            $amount = Bytes::fromGb((float) $text);
        } else {
            if (! ctype_digit($text) || (int) $text < 1) {
                $bot->sendMessage('مقدار نامعتبر است. یک عدد بزرگ‌تر از صفر (روز) ارسال کنید یا /cancel.');
                $this->next('captureAmount');

                return;
            }
            $amount = (int) $text;
        }

        $rule->reward_amount = $amount;
        $rule->save();

        $bot->sendMessage('✅ ذخیره شد.');
        $this->end();
    }

    private function ask(Nutgram $bot, string $text, string $next): void
    {
        $bot->sendMessage($text."\n\nبرای لغو: /cancel", parse_mode: 'HTML');
        $this->next($next);
    }

    private function amountPrompt(ReferralRule $rule): string
    {
        return $rule->reward_type === RewardType::Traffic
            ? '📦 مقدار حجم پاداش را به <b>گیگابایت</b> ارسال کنید (مثلاً 10 یا 1.5).'
            : '📅 مدت زمان پاداش را به <b>روز</b> ارسال کنید (یک عدد).';
    }

    /** Handle /cancel; returns true when the conversation was ended. */
    private function cancelled(Nutgram $bot): bool
    {
        if (trim($bot->message()?->text ?? '') === '/cancel') {
            $bot->sendMessage('لغو شد.');
            $this->end();

            return true;
        }

        return false;
    }

    /** Apply a mutation to the rule, save, confirm, and end. */
    private function persist(Nutgram $bot, callable $mutate): void
    {
        $rule = ReferralRule::find($this->ruleId);
        if (! $rule) {
            $bot->sendMessage('قانون پیدا نشد.');
            $this->end();

            return;
        }

        $mutate($rule);
        $rule->save();

        $bot->sendMessage('✅ ذخیره شد.');
        $this->end();
    }
}
