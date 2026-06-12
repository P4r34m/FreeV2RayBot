<?php

namespace App\Telegram\Handlers\Admin;

use App\Enums\RewardType;
use App\Models\ReferralRule;
use App\Support\Bytes;
use App\Telegram\Conversations\AddRuleConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** List referral rules with add launcher (callback: admin:rules). */
class AdminRulesHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $rules = ReferralRule::orderBy('id')->get();

        $lines = ['🎁 <b>قوانین رفرال</b>', ''];
        if ($rules->isEmpty()) {
            $lines[] = 'هنوز قانونی تعریف نشده است.';
        } else {
            foreach ($rules as $rule) {
                $lines[] = self::summary($rule);
            }
        }
        $lines[] = '';
        $lines[] = '💡 روشن/خاموش‌کردن کل سیستم رفرال در «⚙️ تنظیمات» است.';

        $kb = InlineKeyboardMarkup::make();
        foreach ($rules as $rule) {
            $state = $rule->is_active ? '🟢' : '🔴';
            $kb->addRow(Btn::make("{$state} ".self::label($rule), callback_data: 'admin:rules:view:'.$rule->id));
        }
        $kb->addRow(Btn::make('➕ افزودن قانون', callback_data: 'admin:rules:add'))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin'));

        Reply::screen($bot, implode("\n", $lines), $kb);
    }

    /** Launch the add-rule conversation (callback: admin:rules:add). */
    public static function startAdd(Nutgram $bot): void
    {
        Reply::toast($bot);
        AddRuleConversation::begin($bot);
    }

    /** Full one-line summary used in the list body, prefixed with its on/off dot. */
    public static function summary(ReferralRule $rule): string
    {
        $state = $rule->is_active ? '🟢' : '🔴';

        return "{$state} ".self::label($rule);
    }

    /** "تکرارشونده ... | هر/در N نفر → REWARD". */
    public static function label(ReferralRule $rule): string
    {
        $connector = $rule->mode === \App\Enums\ReferralRuleMode::Recurring ? 'هر' : 'در';
        $reward = $rule->reward_type === RewardType::Traffic
            ? Bytes::human($rule->reward_amount)
            : $rule->reward_amount.' روز';

        return $rule->mode->label()." | {$connector} {$rule->threshold} نفر → {$reward}";
    }
}
