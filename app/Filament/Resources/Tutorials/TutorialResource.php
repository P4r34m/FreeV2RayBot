<?php

namespace App\Filament\Resources\Tutorials;

use App\Filament\Resources\Tutorials\Pages\CreateTutorial;
use App\Filament\Resources\Tutorials\Pages\EditTutorial;
use App\Filament\Resources\Tutorials\Pages\ListTutorials;
use App\Filament\Resources\Tutorials\Schemas\TutorialForm;
use App\Filament\Resources\Tutorials\Tables\TutorialsTable;
use App\Models\Tutorial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TutorialResource extends Resource
{
    protected static ?string $model = Tutorial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'آموزش‌ها';

    protected static ?string $modelLabel = 'آموزش';

    protected static ?string $pluralModelLabel = 'آموزش‌ها';

    protected static string|\UnitEnum|null $navigationGroup = 'محتوا و قفل';

    public static function form(Schema $schema): Schema
    {
        return TutorialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TutorialsTable::configure($table);
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
            'index' => ListTutorials::route('/'),
            'create' => CreateTutorial::route('/create'),
            'edit' => EditTutorial::route('/{record}/edit'),
        ];
    }
}
