<?php

namespace App\Filament\Pages;

use App\Models\Plan;
use App\Models\Setting;
use App\Support\SettingKey;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

/**
 * Single-page editor for the runtime `settings` key/value store.
 */
class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'تنظیمات';

    protected static ?string $navigationLabel = 'تنظیمات ربات';

    protected static ?string $title = 'تنظیمات ربات';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            SettingKey::BOT_USERNAME => Setting::string(SettingKey::BOT_USERNAME),
            SettingKey::SUPPORT_USERNAME => Setting::string(SettingKey::SUPPORT_USERNAME),
            SettingKey::WELCOME_MESSAGE => Setting::string(SettingKey::WELCOME_MESSAGE),
            SettingKey::DEFAULT_PLAN_ID => Setting::get(SettingKey::DEFAULT_PLAN_ID),
            SettingKey::REFERRAL_ENABLED => Setting::bool(SettingKey::REFERRAL_ENABLED, true),
            SettingKey::REFERRAL_QUALIFY_EVENT => Setting::string(SettingKey::REFERRAL_QUALIFY_EVENT, 'first_config'),
            SettingKey::REFERRAL_INFO_TEXT => Setting::string(SettingKey::REFERRAL_INFO_TEXT),
            SettingKey::CHANNEL_LOCK_ENABLED => Setting::bool(SettingKey::CHANNEL_LOCK_ENABLED),
            SettingKey::MAINTENANCE_MODE => Setting::bool(SettingKey::MAINTENANCE_MODE),
            SettingKey::BOT_ENABLED => Setting::bool(SettingKey::BOT_ENABLED, true),
            SettingKey::DELIVERY_MODE => Setting::string(SettingKey::DELIVERY_MODE, 'sub'),
            SettingKey::REPORTS_ENABLED => Setting::bool(SettingKey::REPORTS_ENABLED),
            SettingKey::REPORTS_GROUP_ID => Setting::string(SettingKey::REPORTS_GROUP_ID),
            SettingKey::ANTISPAM_ENABLED => Setting::bool(SettingKey::ANTISPAM_ENABLED, true),
            SettingKey::ANTISPAM_MAX_ACTIONS => Setting::int(SettingKey::ANTISPAM_MAX_ACTIONS, 20),
            SettingKey::ANTISPAM_WINDOW_SECONDS => Setting::int(SettingKey::ANTISPAM_WINDOW_SECONDS, 60),
            SettingKey::ANTISPAM_BLOCK_MINUTES => Setting::int(SettingKey::ANTISPAM_BLOCK_MINUTES, 10),
        ]);
    }

    /**
     * Header actions. "Flush updates" re-sets the Telegram webhook with
     * drop_pending_updates=true — the go-to remedy when the bot is stuck behind a
     * backlog of queued updates and stops responding.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('flushUpdates')
                ->label('پاک‌سازی صف آپدیت‌ها')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('پاک‌سازی آپدیت‌های در صف ربات')
                ->modalDescription('اگر ربات هنگ کرده یا پاسخ نمی‌دهد، این کار صف آپدیت‌های در انتظارِ تلگرام را خالی کرده و وبهوک را دوباره تنظیم می‌کند.')
                ->modalSubmitActionLabel('بله، انجام بده')
                ->action(function () {
                    try {
                        Artisan::call('bot:set-webhook');
                        Notification::make()
                            ->title('✅ صف آپدیت‌ها پاک و وبهوک دوباره تنظیم شد')
                            ->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('❌ خطا در پاک‌سازی صف آپدیت‌ها')
                            ->body(mb_substr($e->getMessage(), 0, 200))
                            ->danger()->send();
                    }
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('عمومی')->columns(2)->schema([
                    TextInput::make(SettingKey::BOT_USERNAME)->label('یوزرنیم ربات (بدون @)')
                        ->placeholder('my_free_v2ray_bot'),
                    TextInput::make(SettingKey::SUPPORT_USERNAME)->label('آیدی پشتیبانی')
                        ->placeholder('@support'),
                    Textarea::make(SettingKey::WELCOME_MESSAGE)->label('پیام خوش‌آمدگویی')
                        ->rows(3)->columnSpanFull(),
                ]),

                Section::make('کانفیگ و رفرال')->columns(2)->schema([
                    Select::make(SettingKey::DEFAULT_PLAN_ID)->label('پلن پیش‌فرض')
                        ->options(fn () => Plan::where('is_active', true)->pluck('name', 'id'))
                        ->helperText('پلنی که کاربر به‌صورت پیش‌فرض دریافت می‌کند'),
                    Select::make(SettingKey::REFERRAL_QUALIFY_EVENT)->label('شرط تأیید زیرمجموعه')
                        ->options([
                            'first_config' => 'با دریافت اولین کانفیگ',
                            'start' => 'با عضویت در ربات (start)',
                        ]),
                    Toggle::make(SettingKey::REFERRAL_ENABLED)->label('سیستم زیرمجموعه‌گیری فعال باشد'),
                    Textarea::make(SettingKey::REFERRAL_INFO_TEXT)->label('متن راهنمای رفرال')
                        ->rows(2)->columnSpanFull(),
                ]),

                Section::make('قفل و نگهداری')->columns(2)->schema([
                    Toggle::make(SettingKey::CHANNEL_LOCK_ENABLED)->label('قفل عضویت اجباری کانال'),
                    Toggle::make(SettingKey::MAINTENANCE_MODE)->label('حالت تعمیر (فقط ادمین)'),
                ]),

                Section::make('ربات و تحویل کانفیگ')->columns(2)->schema([
                    Toggle::make(SettingKey::BOT_ENABLED)->label('ربات روشن باشد'),
                    Select::make(SettingKey::DELIVERY_MODE)->label('نحوه تحویل کانفیگ')
                        ->options([
                            'sub' => 'لینک اشتراک (Subscription)',
                            'configs' => 'کانفیگ‌های تکی',
                        ]),
                ]),

                Section::make('گزارش‌دهی و ضد اسپم')->columns(2)->schema([
                    Toggle::make(SettingKey::REPORTS_ENABLED)->label('ارسال گزارش به گروه'),
                    TextInput::make(SettingKey::REPORTS_GROUP_ID)->label('آیدی گروه گزارشات')
                        ->helperText('آیدی عددی گروه تاپیک‌بندی‌شده'),
                    Toggle::make(SettingKey::ANTISPAM_ENABLED)->label('ضد اسپم فعال'),
                    TextInput::make(SettingKey::ANTISPAM_MAX_ACTIONS)->label('حداکثر اقدام در بازه')->numeric(),
                    TextInput::make(SettingKey::ANTISPAM_WINDOW_SECONDS)->label('بازه (ثانیه)')->numeric(),
                    TextInput::make(SettingKey::ANTISPAM_BLOCK_MINUTES)->label('مدت بلاک موقت (دقیقه)')->numeric(),
                ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::put($key, $value);
        }

        Notification::make()->title('✅ تنظیمات ذخیره شد')->success()->send();
    }
}
