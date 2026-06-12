<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Plan;
use App\Support\Bytes;
use App\Telegram\Conversations\AddPlanConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** List plans with a button per plan + add launcher (callback: admin:plans). */
class AdminPlansHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $plans = Plan::orderBy('sort_order')->get();

        $lines = ['📦 <b>پلن‌ها</b>', ''];
        if ($plans->isEmpty()) {
            $lines[] = 'هنوز پلنی اضافه نشده است.';
        } else {
            $lines[] = 'برای مدیریت روی هر پلن بزنید:';
        }

        $kb = InlineKeyboardMarkup::make();

        foreach ($plans as $plan) {
            $kb->addRow(Btn::make($this->label($plan), callback_data: 'admin:plans:view:'.$plan->id));
        }

        $kb->addRow(Btn::make('➕ افزودن پلن', callback_data: 'admin:plans:add'))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin'));

        Reply::screen($bot, implode("\n", $lines), $kb);
    }

    private function label(Plan $plan): string
    {
        $data = $plan->data_limit_bytes > 0 ? Bytes::human($plan->data_limit_bytes) : 'نامحدود';
        $duration = $plan->duration_days > 0 ? $plan->duration_days.' روز' : 'نامحدود';
        $default = $plan->is_default ? ' ⭐' : '';
        $state = $plan->is_active ? '🟢' : '🔴';

        return "{$plan->name} | {$data} | {$duration}{$default} {$state}";
    }
}
