<?php

namespace App\Filament\Resources\BotUsers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BotUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('مشخصات کاربر')->columns(2)->schema([
                TextInput::make('telegram_id')->label('شناسه تلگرام')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('username')->label('نام کاربری')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('first_name')->label('نام')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('last_name')->label('نام خانوادگی')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('language_code')->label('زبان')
                    ->default('fa'),
            ]),

            Section::make('دسترسی')->columns(2)->schema([
                Toggle::make('is_admin')->label('ادمین')->default(false),
                Toggle::make('is_blocked')->label('مسدود شده')->default(false),
            ]),

            Section::make('هدیه و رفرال')->columns(2)->schema([
                TextInput::make('bonus_traffic_bytes')->label('ترافیک هدیه (بایت)')
                    ->numeric()->default(0)
                    ->helperText('هدیه ادمین؛ هنگام ساخت/تمدید کانفیگ اعمال می‌شود'),
                TextInput::make('bonus_days')->label('روز هدیه')
                    ->numeric()->default(0)
                    ->helperText('هدیه ادمین؛ هنگام ساخت/تمدید کانفیگ اعمال می‌شود'),
                TextInput::make('referred_by')->label('معرف')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('referral_count')->label('تعداد رفرال تأییدشده')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
            ]),
        ]);
    }
}
