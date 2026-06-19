<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Panel;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Edit menu for an existing panel — pick a field to change (admin:panels:edit:{id}). */
class AdminPanelEditHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        Reply::toast($bot);

        $panel = Panel::find((int) $id);
        if (! $panel) {
            Reply::toast($bot, 'پنل پیدا نشد', alert: true);
            (new AdminPanelsHandler)($bot);

            return;
        }

        $kb = InlineKeyboardMarkup::make()
            ->addRow(
                Btn::make('✏️ نام', callback_data: "admin:panels:editfield:{$panel->id}_name"),
                Btn::make('🌐 آدرس', callback_data: "admin:panels:editfield:{$panel->id}_base_url"),
            );

        if ($panel->type->usesLogin()) {
            $kb->addRow(
                Btn::make('👤 یوزرنیم', callback_data: "admin:panels:editfield:{$panel->id}_username"),
                Btn::make('🔒 پسورد', callback_data: "admin:panels:editfield:{$panel->id}_password"),
            );
        }

        // API token is available for every panel type — for 3x-ui it bypasses the
        // CSRF protection on POST /login (recommended for v3.x panels).
        $kb->addRow(Btn::make('🔑 توکن API', callback_data: "admin:panels:editfield:{$panel->id}_api_token"));
        $kb->addRow(Btn::make('📊 ظرفیت کانفیگ', callback_data: "admin:panels:editfield:{$panel->id}_capacity"));

        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: "admin:panels:view:{$panel->id}"));

        $capacity = $panel->isUnlimited() ? 'نامحدود' : $panel->capacity.' (باقی‌مانده: '.$panel->remainingHuman().')';

        Reply::screen(
            $bot,
            '✏️ <b>ویرایش پنل — '.e($panel->name)."</b>\n".
            'نوع: '.$panel->type->label()."\n".
            'آدرس فعلی: <code>'.htmlspecialchars($panel->base_url, ENT_QUOTES)."</code>\n".
            "ظرفیت: {$capacity}\n\n".
            'کدام مورد را ویرایش می‌کنید؟ (یوزر/پس و توکن نمایش داده نمی‌شوند؛ مقدار جدید جایگزین می‌شود.)',
            $kb,
        );
    }
}
