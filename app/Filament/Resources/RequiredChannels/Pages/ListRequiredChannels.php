<?php

namespace App\Filament\Resources\RequiredChannels\Pages;

use App\Filament\Resources\RequiredChannels\RequiredChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRequiredChannels extends ListRecords
{
    protected static string $resource = RequiredChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
