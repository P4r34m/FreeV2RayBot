<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/** Quick-settings submenu with live on/off states (callback: admin:settings). */
class AdminSettingsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $delivery = Setting::string(SettingKey::DELIVERY_MODE, 'sub') === 'configs'
            ? 'کانفیگ تکی' : 'لینک ساب';

        $kb = InlineKeyboardMarkup::make()
            ->addRow($this->toggle('ربات', SettingKey::BOT_ENABLED, true))
            ->addRow(
                $this->toggle('قفل کانال', SettingKey::CHANNEL_LOCK_ENABLED),
                $this->toggle('رفرال', SettingKey::REFERRAL_ENABLED, true),
            )
            ->addRow(
                $this->toggle('ضد اسپم', SettingKey::ANTISPAM_ENABLED, true),
                $this->toggle('گزارش‌دهی', SettingKey::REPORTS_ENABLED),
            )
            ->addRow($this->toggle('حالت تعمیر', SettingKey::MAINTENANCE_MODE))
            ->addRow(Btn::make("📦 نحوه تحویل: {$delivery}", callback_data: 'admin:delivery'))
            ->addRow(Btn::make('📨 تنظیم گروه گزارشات', callback_data: 'admin:setgroup'))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin'));

        Reply::screen($bot, "⚙️ <b>تنظیمات سریع</b>\nبرای روشن/خاموش‌کردن روی هر مورد بزنید:", $kb);
    }

    private function toggle(string $label, string $key, bool $default = false): Btn
    {
        $state = Setting::bool($key, $default) ? '🟢' : '🔴';

        return Btn::make("{$label}: {$state}", callback_data: 'admin:toggle:'.$key);
    }
}
