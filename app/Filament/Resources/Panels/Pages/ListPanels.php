<?php

namespace App\Filament\Resources\Panels\Pages;

use App\Filament\Resources\Panels\PanelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPanels extends ListRecords
{
    protected static string $resource = PanelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
