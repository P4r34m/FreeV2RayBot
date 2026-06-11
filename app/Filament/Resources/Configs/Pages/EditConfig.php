<?php

namespace App\Filament\Resources\Configs\Pages;

use App\Filament\Resources\Configs\ConfigResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConfig extends EditRecord
{
    protected static string $resource = ConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
