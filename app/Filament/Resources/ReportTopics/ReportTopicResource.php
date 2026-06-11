<?php

namespace App\Filament\Resources\ReportTopics;

use App\Filament\Resources\ReportTopics\Pages\CreateReportTopic;
use App\Filament\Resources\ReportTopics\Pages\EditReportTopic;
use App\Filament\Resources\ReportTopics\Pages\ListReportTopics;
use App\Filament\Resources\ReportTopics\Schemas\ReportTopicForm;
use App\Filament\Resources\ReportTopics\Tables\ReportTopicsTable;
use App\Models\ReportTopic;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReportTopicResource extends Resource
{
    protected static ?string $model = ReportTopic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = 'محتوا و قفل';

    protected static ?string $navigationLabel = 'تاپیک گزارش‌ها';

    protected static ?string $modelLabel = 'تاپیک گزارش';

    protected static ?string $pluralModelLabel = 'تاپیک گزارش‌ها';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return ReportTopicForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportTopicsTable::configure($table);
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
            'index' => ListReportTopics::route('/'),
            'create' => CreateReportTopic::route('/create'),
            'edit' => EditReportTopic::route('/{record}/edit'),
        ];
    }
}
