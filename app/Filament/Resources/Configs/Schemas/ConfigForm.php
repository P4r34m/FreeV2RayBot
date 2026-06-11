<?php

namespace App\Filament\Resources\Configs\Schemas;

use App\Enums\ConfigStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('مالکیت')->columns(3)->schema([
                Select::make('bot_user_id')->label('کاربر')
                    ->relationship('botUser', 'telegram_id')
                    ->searchable()
                    ->disabled()->dehydrated(false),
                Select::make('panel_id')->label('سرور')
                    ->relationship('panel', 'name')
                    ->disabled()->dehydrated(false),
                Select::make('plan_id')->label('پلن')
                    ->relationship('plan', 'name')
                    ->disabled()->dehydrated(false),
            ]),

            Section::make('شناسه‌ها')->columns(2)->schema([
                TextInput::make('remote_identifier')->label('شناسه روی پنل')
                    ->disabled()->dehydrated(false),
                TextInput::make('remote_uuid')->label('UUID')
                    ->disabled()->dehydrated(false),
                TextInput::make('sub_id')->label('Subscription ID')
                    ->disabled()->dehydrated(false),
                Textarea::make('subscription_url')->label('لینک اشتراک')
                    ->columnSpanFull()
                    ->disabled()->dehydrated(false),
            ]),

            Section::make('مصرف و وضعیت')->columns(2)->schema([
                Select::make('status')->label('وضعیت')
                    ->options(ConfigStatus::class)
                    ->default('active')
                    ->required(),
                DateTimePicker::make('expires_at')->label('تاریخ انقضا')
                    ->disabled()->dehydrated(false),
                TextInput::make('data_limit_bytes')->label('حجم (بایت)')
                    ->numeric()
                    ->disabled()->dehydrated(false),
                TextInput::make('used_bytes')->label('مصرف‌شده (بایت)')
                    ->numeric()
                    ->disabled()->dehydrated(false),
                DateTimePicker::make('last_synced_at')->label('آخرین همگام‌سازی')
                    ->disabled()->dehydrated(false),
            ]),
        ]);
    }
}
