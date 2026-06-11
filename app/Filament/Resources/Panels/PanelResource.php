<?php

namespace App\Filament\Resources\Panels;

use App\Filament\Resources\Panels\Pages\CreatePanel;
use App\Filament\Resources\Panels\Pages\EditPanel;
use App\Filament\Resources\Panels\Pages\ListPanels;
use App\Filament\Resources\Panels\Schemas\PanelForm;
use App\Filament\Resources\Panels\Tables\PanelsTable;
use App\Models\Panel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PanelResource extends Resource
{
    protected static ?string $model = Panel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|\UnitEnum|null $navigationGroup = 'سرویس‌ها';

    protected static ?string $navigationLabel = 'پنل‌ها (سرورها)';

    protected static ?string $modelLabel = 'پنل';

    protected static ?string $pluralModelLabel = 'پنل‌ها';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PanelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PanelsTable::configure($table);
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
            'index' => ListPanels::route('/'),
            'create' => CreatePanel::route('/create'),
            'edit' => EditPanel::route('/{record}/edit'),
        ];
    }
}
