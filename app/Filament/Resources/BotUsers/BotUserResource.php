<?php

namespace App\Filament\Resources\BotUsers;

use App\Filament\Resources\BotUsers\Pages\CreateBotUser;
use App\Filament\Resources\BotUsers\Pages\EditBotUser;
use App\Filament\Resources\BotUsers\Pages\ListBotUsers;
use App\Filament\Resources\BotUsers\Schemas\BotUserForm;
use App\Filament\Resources\BotUsers\Tables\BotUsersTable;
use App\Models\BotUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BotUserResource extends Resource
{
    protected static ?string $model = BotUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'کاربران ربات';

    protected static ?string $modelLabel = 'کاربر ربات';

    protected static ?string $pluralModelLabel = 'کاربران ربات';

    protected static string|\UnitEnum|null $navigationGroup = 'کاربران';

    public static function form(Schema $schema): Schema
    {
        return BotUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BotUsersTable::configure($table);
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
            'index' => ListBotUsers::route('/'),
            'create' => CreateBotUser::route('/create'),
            'edit' => EditBotUser::route('/{record}/edit'),
        ];
    }
}
