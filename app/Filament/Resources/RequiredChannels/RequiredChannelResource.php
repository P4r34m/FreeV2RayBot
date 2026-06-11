<?php

namespace App\Filament\Resources\RequiredChannels;

use App\Filament\Resources\RequiredChannels\Pages\CreateRequiredChannel;
use App\Filament\Resources\RequiredChannels\Pages\EditRequiredChannel;
use App\Filament\Resources\RequiredChannels\Pages\ListRequiredChannels;
use App\Filament\Resources\RequiredChannels\Schemas\RequiredChannelForm;
use App\Filament\Resources\RequiredChannels\Tables\RequiredChannelsTable;
use App\Models\RequiredChannel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RequiredChannelResource extends Resource
{
    protected static ?string $model = RequiredChannel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $navigationLabel = 'کانال‌های اجباری';

    protected static ?string $modelLabel = 'کانال اجباری';

    protected static ?string $pluralModelLabel = 'کانال‌های اجباری';

    protected static string|\UnitEnum|null $navigationGroup = 'محتوا و قفل';

    public static function form(Schema $schema): Schema
    {
        return RequiredChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequiredChannelsTable::configure($table);
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
            'index' => ListRequiredChannels::route('/'),
            'create' => CreateRequiredChannel::route('/create'),
            'edit' => EditRequiredChannel::route('/{record}/edit'),
        ];
    }
}
