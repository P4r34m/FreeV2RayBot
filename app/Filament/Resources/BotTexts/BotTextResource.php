<?php

namespace App\Filament\Resources\BotTexts;

use App\Filament\Resources\BotTexts\Pages\CreateBotText;
use App\Filament\Resources\BotTexts\Pages\EditBotText;
use App\Filament\Resources\BotTexts\Pages\ListBotTexts;
use App\Filament\Resources\BotTexts\Schemas\BotTextForm;
use App\Filament\Resources\BotTexts\Tables\BotTextsTable;
use App\Models\BotText;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BotTextResource extends Resource
{
    protected static ?string $model = BotText::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'محتوا و قفل';

    protected static ?string $navigationLabel = 'متن‌های ربات';

    protected static ?string $modelLabel = 'متن ربات';

    protected static ?string $pluralModelLabel = 'متن‌های ربات';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return BotTextForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BotTextsTable::configure($table);
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
            'index' => ListBotTexts::route('/'),
            'create' => CreateBotText::route('/create'),
            'edit' => EditBotText::route('/{record}/edit'),
        ];
    }
}
