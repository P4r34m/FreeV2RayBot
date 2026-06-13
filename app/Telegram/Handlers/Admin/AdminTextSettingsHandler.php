<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\Plan;
use App\Models\Setting;
use App\Support\SettingKey;
use App\Telegram\Conversations\SetSettingValueConversation;
use App\Telegram\Reply;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton as Btn;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Text / number / select settings that mirror the web panel (callback:
 * admin:txtset). Toggles live on the quick-settings screen; these are the
 * free-form and choice values.
 */
class AdminTextSettingsHandler
{
    /** slug => [SettingKey, label, type(text|multiline|int)] */
    public const FIELDS = [
        'botuser' => [SettingKey::BOT_USERNAME, 'یوزرنیم ربات', 'text'],
        'support' => [SettingKey::SUPPORT_USERNAME, 'آیدی پشتیبانی', 'text'],
        'welcome' => [SettingKey::WELCOME_MESSAGE, 'پیام خوش‌آمد', 'multiline'],
        'refinfo' => [SettingKey::REFERRAL_INFO_TEXT, 'متن راهنمای رفرال', 'multiline'],
        'spammax' => [SettingKey::ANTISPAM_MAX_ACTIONS, 'حداکثر اقدام در بازه', 'int'],
        'spamwin' => [SettingKey::ANTISPAM_WINDOW_SECONDS, 'بازه‌ی ضداسپم (ثانیه)', 'int'],
        'spamblk' => [SettingKey::ANTISPAM_BLOCK_MINUTES, 'مدت بلاک موقت (دقیقه)', 'int'],
    ];

    public function __invoke(Nutgram $bot): void
    {
        Reply::toast($bot);

        $kb = InlineKeyboardMarkup::make();
        foreach (self::FIELDS as $slug => [$key, $label, $type]) {
            $val = Setting::string($key, '');
            $preview = $val === '' ? '—' : (mb_strlen($val) > 18 ? mb_substr($val, 0, 18).'…' : $val);
            $kb->addRow(Btn::make("✏️ {$label}: {$preview}", callback_data: 'admin:txtset:edit:'.$slug));
        }

        $planId = Setting::int(SettingKey::DEFAULT_PLAN_ID);
        $planName = $planId ? (Plan::find($planId)?->name ?? '—') : '—';
        $kb->addRow(Btn::make('📦 پلن پیش‌فرض: '.$planName, callback_data: 'admin:txtset:plan'));

        $qualify = Setting::string(SettingKey::REFERRAL_QUALIFY_EVENT, 'first_config') === 'start'
            ? 'با عضویت' : 'با اولین کانفیگ';
        $kb->addRow(Btn::make('✅ شرط تأیید رفرال: '.$qualify, callback_data: 'admin:txtset:qualify'));

        $kb->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:settings'));

        Reply::screen($bot, "🛠 <b>تنظیمات متنی و عددی</b>\nهر مورد را برای ویرایش انتخاب کنید:", $kb);
    }

    /** Launch the generic value editor for a field (callback: admin:txtset:edit:{slug}). */
    public static function edit(Nutgram $bot, string $slug): void
    {
        Reply::toast($bot);

        $field = self::FIELDS[$slug] ?? null;
        if ($field === null) {
            Reply::toast($bot, 'نامعتبر', alert: true);

            return;
        }

        /** @var SetSettingValueConversation $conv */
        $conv = $bot->getContainer()->get(SetSettingValueConversation::class);
        $conv->settingKey = $field[0];
        $conv->label = $field[1];
        $conv->type = $field[2];
        $conv($bot);
    }

    /** Cycle the referral qualify event (callback: admin:txtset:qualify). */
    public static function cycleQualify(Nutgram $bot): void
    {
        $cur = Setting::string(SettingKey::REFERRAL_QUALIFY_EVENT, 'first_config');
        Setting::put(SettingKey::REFERRAL_QUALIFY_EVENT, $cur === 'start' ? 'first_config' : 'start');
        Reply::toast($bot, '✅ تغییر کرد');

        (new self)($bot);
    }

    /** Show the default-plan picker (callback: admin:txtset:plan). */
    public static function showPlans(Nutgram $bot): void
    {
        Reply::toast($bot);

        $kb = InlineKeyboardMarkup::make();
        foreach (Plan::where('is_active', true)->orderBy('sort_order')->get() as $plan) {
            $kb->addRow(Btn::make('📦 '.$plan->name, callback_data: 'admin:txtset:planset:'.$plan->id));
        }
        $kb->addRow(Btn::make('بدون پیش‌فرض', callback_data: 'admin:txtset:planset:0'))
            ->addRow(Btn::make('🔙 بازگشت', callback_data: 'admin:txtset'));

        Reply::screen($bot, '📦 پلن پیش‌فرض را انتخاب کنید:', $kb);
    }

    /** Persist the chosen default plan (callback: admin:txtset:planset:{id}). */
    public static function setPlan(Nutgram $bot, string $id): void
    {
        Setting::put(SettingKey::DEFAULT_PLAN_ID, (int) $id ?: null);
        Reply::toast($bot, '✅ ذخیره شد');

        (new self)($bot);
    }
}
