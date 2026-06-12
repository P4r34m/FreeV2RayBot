<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\ReferralRule;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Single referral rule view with toggle/delete (callback: admin:rules:view:{id}). */
class AdminRuleViewHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        $rule = ReferralRule::find((int) $id);

        if ($rule === null) {
            Reply::toast($bot, 'قانون یافت نشد', alert: true);
            (new AdminRulesHandler)($bot);

            return;
        }

        $state = $rule->is_active ? '🟢 فعال' : '🔴 غیرفعال';
        $body = "🎁 <b>قانون رفرال</b>\n\n".AdminRulesHandler::label($rule)."\n\nوضعیت: {$state}";

        $kb = InlineKeyboardMarkup::make()
            ->addRow(
                Btn::make('✏️ نوع قانون', callback_data: 'admin:rules:editfield:'.$rule->id.'_mode'),
                Btn::make('✏️ آستانه', callback_data: 'admin:rules:editfield:'.$rule->id.'_threshold'),
            )
            ->addRow(
                Btn::make('✏️ نوع پاداش', callback_data: 'admin:rules:editfield:'.$rule->id.'_rewardtype'),
                Btn::make('✏️ مقدار پاداش', callback_data: 'admin:rules:editfield:'.$rule->id.'_amount'),
            )
            ->addRow(Btn::make(
                $rule->is_active ? '🔴 غیرفعال‌کردن' : '🟢 فعال‌کردن',
                callback_data: 'admin:rules:toggle:'.$rule->id,
            ))
            ->addRow(Btn::make('🗑 حذف', callback_data: 'admin:rules:del:'.$rule->id))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:rules'));

        Reply::screen($bot, $body, $kb);
    }
}
