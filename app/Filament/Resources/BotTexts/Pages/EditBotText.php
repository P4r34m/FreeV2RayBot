<?php

namespace App\Filament\Resources\BotTexts\Pages;

use App\Filament\Resources\BotTexts\BotTextResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBotText extends EditRecord
{
    protected static string $resource = BotTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
