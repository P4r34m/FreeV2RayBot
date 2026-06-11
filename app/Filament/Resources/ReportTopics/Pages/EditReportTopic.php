<?php

namespace App\Filament\Resources\ReportTopics\Pages;

use App\Filament\Resources\ReportTopics\ReportTopicResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReportTopic extends EditRecord
{
    protected static string $resource = ReportTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
