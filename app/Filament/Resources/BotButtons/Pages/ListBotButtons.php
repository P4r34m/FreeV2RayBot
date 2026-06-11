<?php

namespace App\Filament\Resources\BotButtons\Pages;

use App\Filament\Resources\BotButtons\BotButtonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBotButtons extends ListRecords
{
    protected static string $resource = BotButtonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
