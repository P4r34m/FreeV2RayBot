<?php

namespace App\Filament\Resources\Configs;

use App\Filament\Resources\Configs\Pages\CreateConfig;
use App\Filament\Resources\Configs\Pages\EditConfig;
use App\Filament\Resources\Configs\Pages\ListConfigs;
use App\Filament\Resources\Configs\Schemas\ConfigForm;
use App\Filament\Resources\Configs\Tables\ConfigsTable;
use App\Models\Config;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConfigResource extends Resource
{
    protected static ?string $model = Config::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'سرویس‌ها';

    protected static ?string $navigationLabel = 'کانفیگ‌ها';

    protected static ?string $modelLabel = 'کانفیگ';

    protected static ?string $pluralModelLabel = 'کانفیگ‌ها';

    public static function form(Schema $schema): Schema
    {
        return ConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConfigsTable::configure($table);
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
            'index' => ListConfigs::route('/'),
            'create' => CreateConfig::route('/create'),
            'edit' => EditConfig::route('/{record}/edit'),
        ];
    }
}
