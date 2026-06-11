<?php

namespace App\Filament\Resources\Broadcasts;

use App\Filament\Resources\Broadcasts\Pages\CreateBroadcast;
use App\Filament\Resources\Broadcasts\Pages\EditBroadcast;
use App\Filament\Resources\Broadcasts\Pages\ListBroadcasts;
use App\Filament\Resources\Broadcasts\Schemas\BroadcastForm;
use App\Filament\Resources\Broadcasts\Tables\BroadcastsTable;
use App\Models\Broadcast;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BroadcastResource extends Resource
{
    protected static ?string $model = Broadcast::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'پیام‌های همگانی';

    protected static ?string $modelLabel = 'پیام همگانی';

    protected static ?string $pluralModelLabel = 'پیام‌های همگانی';

    protected static string|\UnitEnum|null $navigationGroup = 'کاربران';

    public static function form(Schema $schema): Schema
    {
        return BroadcastForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BroadcastsTable::configure($table);
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
            'index' => ListBroadcasts::route('/'),
            'create' => CreateBroadcast::route('/create'),
            'edit' => EditBroadcast::route('/{record}/edit'),
        ];
    }
}
