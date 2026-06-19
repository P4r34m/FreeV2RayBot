<?php

namespace App\Filament\Resources\Panels\Schemas;

use App\Enums\PanelType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PanelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات کلی')->columns(2)->schema([
                TextInput::make('name')->label('نام')->required(),
                Select::make('type')->label('نوع پنل')
                    ->options(PanelType::class)
                    ->required()
                    ->live(),
                TextInput::make('base_url')->label('آدرس پنل (Base URL)')
                    ->url()->required()->columnSpanFull()
                    ->placeholder('https://panel.example.com:2053'),
                Toggle::make('is_active')->label('فعال')->default(true),
                TextInput::make('priority')->label('اولویت انتخاب')->numeric()->default(0)
                    ->helperText('بالاتر = ترجیح بیشتر هنگام ساخت کانفیگ'),
                TextInput::make('capacity')->label('ظرفیت (حداکثر کانفیگ)')->numeric()->minValue(-1)
                    ->helperText('خالی یا -1 = نامحدود'),
            ]),

            Section::make('احراز هویت')->columns(2)->schema([
                TextInput::make('username')->label('نام کاربری')
                    ->visible(fn (Get $get) => self::loginBased($get))
                    ->requiredIf('type', [PanelType::ThreeXui->value, PanelType::PasarGuard->value]),
                TextInput::make('password')->label('رمز عبور')
                    ->password()->revealable()
                    ->visible(fn (Get $get) => self::loginBased($get))
                    ->dehydrated(fn ($state) => filled($state)),
                TextInput::make('api_token')->label('توکن API')
                    ->password()->revealable()
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => $get('type') === PanelType::Remnawave->value || $get('type') === PanelType::ThreeXui->value)
                    ->helperText('Remnawave: الزامی. 3x-ui: اختیاری (در صورت تنظیم، جایگزین ورود با یوزر/پسورد).')
                    ->dehydrated(fn ($state) => filled($state)),
            ]),

            Section::make('اتصال')->columns(2)->schema([
                Toggle::make('settings.verify_ssl')->label('بررسی گواهی SSL')->default(false)
                    ->helperText('برای پنل‌های self-signed خاموش بماند'),
                TextInput::make('settings.timeout')->label('مهلت پاسخ (ثانیه)')->numeric()->default(20),
            ]),

            // ---- 3x-ui ----
            Section::make('تنظیمات 3x-ui')
                ->visible(fn (Get $get) => $get('type') === PanelType::ThreeXui->value)
                ->columns(2)->schema([
                    TextInput::make('settings.inbound_id')->label('Inbound ID')->numeric()
                        ->helperText('شناسه‌ی inbound که کلاینت‌ها در آن ساخته می‌شوند'),
                    TextInput::make('settings.flow')->label('Flow')->placeholder('xtls-rprx-vision'),
                    TextInput::make('settings.sub_scheme')->label('Subscription Scheme')->default('https'),
                    TextInput::make('settings.sub_host')->label('Subscription Host')
                        ->helperText('خالی = هاست آدرس پنل'),
                    TextInput::make('settings.sub_port')->label('Subscription Port')->numeric()->default(2096),
                    TextInput::make('settings.sub_path')->label('Subscription Path')->default('/sub/'),
                    TextInput::make('settings.limit_ip')->label('محدودیت IP همزمان')->numeric()->default(0),
                ]),

            // ---- PasarGuard ----
            Section::make('تنظیمات PasarGuard')
                ->visible(fn (Get $get) => $get('type') === PanelType::PasarGuard->value)
                ->columns(2)->schema([
                    TagsInput::make('settings.group_ids')->label('Group IDs')
                        ->helperText('شناسه‌ی گروه‌های اینباند (عدد)'),
                    Textarea::make('settings.proxy_settings')->label('Proxy Settings (JSON)')
                        ->columnSpanFull()
                        ->helperText('اختیاری؛ خالی = پیش‌فرض {"vless":{"flow":""},"vmess":{}}')
                        ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? json_decode($state, true) : null),
                ]),

            // ---- Remnawave ----
            Section::make('تنظیمات Remnawave')
                ->visible(fn (Get $get) => $get('type') === PanelType::Remnawave->value)
                ->columns(2)->schema([
                    TagsInput::make('settings.squad_uuids')->label('Internal Squad UUIDs')
                        ->helperText('squad هایی که کاربر به آن‌ها متصل می‌شود'),
                    Select::make('settings.traffic_strategy')->label('استراتژی ریست ترافیک')
                        ->options([
                            'NO_RESET' => 'بدون ریست',
                            'DAY' => 'روزانه',
                            'WEEK' => 'هفتگی',
                            'MONTH' => 'ماهانه',
                            'MONTH_ROLLING' => 'ماهانه (چرخشی)',
                        ])->default('NO_RESET'),
                    TextInput::make('settings.x_api_key')->label('X-Api-Key (Caddy)')
                        ->helperText('در صورت قرارگیری پشت Caddy'),
                    Toggle::make('settings.xforwarded')->label('ارسال هدرهای X-Forwarded')->default(false),
                ]),
        ]);
    }

    protected static function loginBased(Get $get): bool
    {
        return in_array($get('type'), [PanelType::ThreeXui->value, PanelType::PasarGuard->value], true);
    }
}
