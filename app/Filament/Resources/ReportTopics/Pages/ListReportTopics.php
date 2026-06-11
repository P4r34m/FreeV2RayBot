<?php

namespace App\Filament\Resources\ReportTopics\Pages;

use App\Filament\Resources\ReportTopics\ReportTopicResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReportTopics extends ListRecords
{
    protected static string $resource = ReportTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
