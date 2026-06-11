<?php

namespace App\Filament\Resources\BotButtons\Pages;

use App\Filament\Resources\BotButtons\BotButtonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBotButton extends EditRecord
{
    protected static string $resource = BotButtonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
