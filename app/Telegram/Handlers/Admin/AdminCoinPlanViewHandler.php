<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\CoinPlan;
use App\Support\Bytes;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Single coin package view with toggle/delete (callback: admin:coinplans:view:{id}). */
class AdminCoinPlanViewHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        $plan = CoinPlan::find((int) $id);
        if (! $plan) {
            Reply::toast($bot, 'بسته یافت نشد', alert: true);
            (new AdminCoinPlansHandler)($bot);

            return;
        }

        $volume = $plan->data_limit_bytes > 0 ? Bytes::human($plan->data_limit_bytes) : 'نامحدود';
        $duration = $plan->duration_days > 0 ? $plan->duration_days.' روز' : 'بدون انقضا';
        $state = $plan->is_active ? '🟢 فعال' : '🔴 غیرفعال';

        $body = "🛒 <b>{$plan->name}</b>\n\n📦 حجم: {$volume}\n⏳ مدت: {$duration}\n🪙 قیمت: {$plan->coin_price} سکه\n\nوضعیت: {$state}";

        $kb = InlineKeyboardMarkup::make()
            ->addRow(
                Btn::make('✏️ نام', callback_data: 'admin:coinplans:edit:'.$plan->id.'_name'),
                Btn::make('✏️ حجم', callback_data: 'admin:coinplans:edit:'.$plan->id.'_data'),
            )
            ->addRow(
                Btn::make('✏️ مدت', callback_data: 'admin:coinplans:edit:'.$plan->id.'_duration'),
                Btn::make('✏️ قیمت', callback_data: 'admin:coinplans:edit:'.$plan->id.'_price'),
            )
            ->addRow(Btn::make(
                $plan->is_active ? '🔴 غیرفعال‌کردن' : '🟢 فعال‌کردن',
                callback_data: 'admin:coinplans:toggle:'.$plan->id,
            ))
            ->addRow(Btn::make('🗑 حذف', callback_data: 'admin:coinplans:del:'.$plan->id))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:coinplans'));

        Reply::screen($bot, $body, $kb);
    }
}
