<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Plan;
use App\Support\Bytes;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Plan detail with default/toggle/delete actions (callback: admin:plans:view:{id}). */
class AdminPlanViewHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $plan = Plan::find((int) $id);

        if (! $plan) {
            Reply::toast($bot, 'پلن یافت نشد.', alert: true);
            (new AdminPlansHandler)($bot);

            return;
        }

        Reply::toast($bot);

        self::render($bot, $plan);
    }

    /** Shared renderer so action handlers can re-render the detail screen. */
    public static function render(Nutgram $bot, Plan $plan): void
    {
        $data = $plan->data_limit_bytes > 0 ? Bytes::human($plan->data_limit_bytes) : 'نامحدود';
        $duration = $plan->duration_days > 0 ? $plan->duration_days.' روز' : 'نامحدود';
        $default = $plan->is_default ? 'بله ⭐' : 'خیر';
        $state = $plan->is_active ? 'فعال 🟢' : 'غیرفعال 🔴';

        $body = "📦 <b>".htmlspecialchars($plan->name, ENT_QUOTES)."</b>\n\n"
            ."💾 حجم: <code>{$data}</code>\n"
            ."⏳ مدت: <code>{$duration}</code>\n"
            ."⭐ پیش‌فرض: {$default}\n"
            ."📶 وضعیت: {$state}";

        $toggleLabel = $plan->is_active ? '🔴 غیرفعال‌سازی' : '🟢 فعال‌سازی';

        $kb = InlineKeyboardMarkup::make()
            ->addRow(
                Btn::make('✏️ نام', callback_data: "admin:plans:editfield:{$plan->id}_name"),
                Btn::make('📦 حجم', callback_data: "admin:plans:editfield:{$plan->id}_data"),
                Btn::make('⏳ مدت', callback_data: "admin:plans:editfield:{$plan->id}_duration"),
            )
            ->addRow(
                Btn::make('⭐ پیش‌فرض', callback_data: 'admin:plans:default:'.$plan->id),
                Btn::make($toggleLabel, callback_data: 'admin:plans:toggle:'.$plan->id),
            )
            ->addRow(Btn::make('🗑 حذف', callback_data: 'admin:plans:del:'.$plan->id))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:plans'));

        Reply::screen($bot, $body, $kb);
    }
}
