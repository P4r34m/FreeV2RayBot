<?php

namespace App\Filament\Resources\ReferralRules\Schemas;

use App\Enums\ReferralRuleMode;
use App\Enums\RewardType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReferralRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('قانون')->columns(2)->schema([
                    TextInput::make('name')->label('نام')->required()->columnSpanFull(),
                    Select::make('mode')->label('نوع شمارش')
                        ->options(ReferralRuleMode::class)
                        ->default('recurring')
                        ->required(),
                    TextInput::make('threshold')->label('تعداد زیرمجموعه')
                        ->required()
                        ->numeric(),
                ]),

                Section::make('پاداش')->columns(2)->schema([
                    Select::make('reward_type')->label('نوع پاداش')
                        ->options(RewardType::class)
                        ->live()
                        ->required(),
                    TextInput::make('reward_amount')->label('مقدار پاداش')
                        ->required()
                        ->numeric()
                        ->helperText('برای «حجم» و «حجم + زمان»: بایت — برای «زمان»: روز'),
                    TextInput::make('reward_days')->label('روز پاداش (فقط برای حجم + زمان)')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn ($get): bool => $get('reward_type') === RewardType::Both->value)
                        ->helperText('تعداد روزی که همراه حجم هدیه داده می‌شود'),
                ]),

                Section::make('وضعیت')->columns(2)->schema([
                    Toggle::make('is_active')->label('فعال')->default(true),
                    TextInput::make('sort_order')->label('ترتیب نمایش')
                        ->required()
                        ->numeric()
                        ->default(0),
                ]),
            ]);
    }
}
