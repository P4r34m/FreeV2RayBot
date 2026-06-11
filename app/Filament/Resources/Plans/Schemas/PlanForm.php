<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات کلی')->columns(2)->schema([
                TextInput::make('name')->label('نام')->required(),
                Select::make('panel_id')->label('سرور')
                    ->relationship('panel', 'name')
                    ->placeholder('هر سرور')
                    ->helperText('خالی = هر سرور'),
                TextInput::make('data_limit_bytes')->label('حجم (بایت)')
                    ->required()->numeric()->default(0)
                    ->helperText('0 = نامحدود'),
                TextInput::make('duration_days')->label('مدت (روز)')
                    ->required()->numeric()->default(0)
                    ->helperText('0 = نامحدود'),
                Textarea::make('description')->label('توضیحات')->columnSpanFull(),
            ]),

            Section::make('تنظیمات')->columns(2)->schema([
                Toggle::make('is_default')->label('پیش‌فرض')->default(false),
                Toggle::make('is_active')->label('فعال')->default(true),
                TextInput::make('sort_order')->label('ترتیب نمایش')
                    ->required()->numeric()->default(0),
            ]),
        ]);
    }
}
