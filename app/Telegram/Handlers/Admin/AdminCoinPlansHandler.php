<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\CoinPlan;
use App\Telegram\Conversations\AddCoinPlanConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** List coin packages with an add launcher (callback: admin:coinplans). */
class AdminCoinPlansHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $plans = CoinPlan::orderBy('sort_order')->orderBy('id')->get();

        $lines = ['🛒 <b>بسته‌های سکه‌ای</b>', ''];
        if ($plans->isEmpty()) {
            $lines[] = 'هنوز بسته‌ای تعریف نشده است.';
        }

        $kb = InlineKeyboardMarkup::make();
        foreach ($plans as $plan) {
            $state = $plan->is_active ? '🟢' : '🔴';
            $kb->addRow(Btn::make("{$state} {$plan->name} — ".$plan->label(), callback_data: 'admin:coinplans:view:'.$plan->id));
        }
        $kb->addRow(Btn::make('➕ افزودن بسته', callback_data: 'admin:coinplans:add'))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin'));

        Reply::screen($bot, implode("\n", $lines), $kb);
    }

    /** Launch the add-coin-plan conversation (callback: admin:coinplans:add). */
    public static function startAdd(Nutgram $bot): void
    {
        Reply::toast($bot);
        AddCoinPlanConversation::begin($bot);
    }
}
