<?php

namespace App\Filament\Resources\Panels\Pages;

use App\Filament\Resources\Panels\PanelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPanel extends EditRecord
{
    protected static string $resource = PanelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
