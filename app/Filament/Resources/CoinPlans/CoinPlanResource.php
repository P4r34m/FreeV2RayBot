<?php

namespace App\Filament\Resources\CoinPlans;

use App\Filament\Resources\CoinPlans\Pages\CreateCoinPlan;
use App\Filament\Resources\CoinPlans\Pages\EditCoinPlan;
use App\Filament\Resources\CoinPlans\Pages\ListCoinPlans;
use App\Filament\Resources\CoinPlans\Schemas\CoinPlanForm;
use App\Filament\Resources\CoinPlans\Tables\CoinPlansTable;
use App\Models\CoinPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CoinPlanResource extends Resource
{
    protected static ?string $model = CoinPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'بسته‌های سکه‌ای';

    protected static ?string $modelLabel = 'بسته سکه‌ای';

    protected static ?string $pluralModelLabel = 'بسته‌های سکه‌ای';

    protected static string|\UnitEnum|null $navigationGroup = 'رفرال';

    public static function form(Schema $schema): Schema
    {
        return CoinPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoinPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoinPlans::route('/'),
            'create' => CreateCoinPlan::route('/create'),
            'edit' => EditCoinPlan::route('/{record}/edit'),
        ];
    }
}
