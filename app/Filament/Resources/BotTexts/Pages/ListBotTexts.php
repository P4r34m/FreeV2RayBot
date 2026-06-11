<?php

namespace App\Filament\Resources\BotTexts\Pages;

use App\Filament\Resources\BotTexts\BotTextResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBotTexts extends ListRecords
{
    protected static string $resource = BotTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
