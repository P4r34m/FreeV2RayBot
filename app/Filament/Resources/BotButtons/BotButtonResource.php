<?php

namespace App\Filament\Resources\BotButtons;

use App\Filament\Resources\BotButtons\Pages\CreateBotButton;
use App\Filament\Resources\BotButtons\Pages\EditBotButton;
use App\Filament\Resources\BotButtons\Pages\ListBotButtons;
use App\Filament\Resources\BotButtons\Schemas\BotButtonForm;
use App\Filament\Resources\BotButtons\Tables\BotButtonsTable;
use App\Models\BotButton;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BotButtonResource extends Resource
{
    protected static ?string $model = BotButton::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static string|\UnitEnum|null $navigationGroup = 'محتوا و قفل';

    protected static ?string $navigationLabel = 'دکمه‌های ربات';

    protected static ?string $modelLabel = 'دکمه ربات';

    protected static ?string $pluralModelLabel = 'دکمه‌های ربات';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return BotButtonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BotButtonsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBotButtons::route('/'),
            'create' => CreateBotButton::route('/create'),
            'edit' => EditBotButton::route('/{record}/edit'),
        ];
    }
}
