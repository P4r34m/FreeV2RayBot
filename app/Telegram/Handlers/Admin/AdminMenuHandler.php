<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Admin home (callback: admin, command: /admin). */
class AdminMenuHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $panelUrl = rtrim((string) config('app.url'), '/').'/admin';
        $on = Setting::bool(SettingKey::BOT_ENABLED, true);
        $power = $on ? '🟢 روشن — برای خاموش‌کردن بزنید' : '🔴 خاموش — برای روشن‌کردن بزنید';

        $kb = InlineKeyboardMarkup::make()
            ->addRow(Btn::make("🤖 وضعیت ربات: {$power}", callback_data: 'admin:botpower'))
            ->addRow(Btn::make('📊 آمار', callback_data: 'admin:stats'))
            ->addRow(
                Btn::make('🖥 پنل‌ها', callback_data: 'admin:panels'),
                Btn::make('📦 پلن‌ها', callback_data: 'admin:plans'),
            )
            ->addRow(
                Btn::make('🎁 قوانین رفرال', callback_data: 'admin:rules'),
                Btn::make('📚 آموزش‌ها', callback_data: 'admin:tutorials'),
            )
            ->addRow(
                Btn::make('✏️ متن‌ها و دکمه‌ها', callback_data: 'admin:content'),
                Btn::make('📡 کانال‌های اجباری', callback_data: 'admin:channels'),
            )
            ->addRow(
                Btn::make('⚙️ تنظیمات', callback_data: 'admin:settings'),
                Btn::make('⛔️ مدیریت کاربران', callback_data: 'admin:users'),
            )
            ->addRow(Btn::make('📢 پیام همگانی', callback_data: 'admin:broadcast'))
            ->addRow(Btn::make('🌐 پنل وب (گزارشات)', url: $panelUrl))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'menu'));

        Reply::screen(
            $bot,
            "⚙️ <b>پنل مدیریت</b>\n\nتنظیمات سریع از همین‌جا در دسترس است؛ مدیریت کامل پنل‌ها/پلن‌ها/قوانین و گزارش‌ها در «پنل وب».",
            $kb,
        );
    }
}
