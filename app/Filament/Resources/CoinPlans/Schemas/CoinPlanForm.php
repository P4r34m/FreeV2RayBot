<?php

namespace App\Filament\Resources\CoinPlans\Schemas;

use App\Support\Bytes;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CoinPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بسته')->columns(2)->schema([
                    TextInput::make('name')->label('نام')->required()->columnSpanFull(),
                    TextInput::make('data_limit_bytes')->label('حجم (گیگابایت)')
                        ->required()->numeric()->minValue(0)
                        ->formatStateUsing(fn ($state): float => $state ? round(((int) $state) / Bytes::GB, 2) : 0)
                        ->dehydrateStateUsing(fn ($state): int => (int) round(((float) $state) * Bytes::GB))
                        ->helperText('0 = نامحدود'),
                    TextInput::make('duration_days')->label('مدت (روز)')
                        ->required()->numeric()->minValue(0)
                        ->helperText('0 = بدون انقضا'),
                    TextInput::make('coin_price')->label('قیمت (سکه)')
                        ->required()->numeric()->minValue(0),
                ]),

                Section::make('وضعیت')->columns(2)->schema([
                    Toggle::make('is_active')->label('فعال')->default(true),
                    TextInput::make('sort_order')->label('ترتیب نمایش')->numeric()->default(0),
                ]),
            ]);
    }
}
