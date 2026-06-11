<?php

namespace App\Filament\Resources\RequiredChannels\Pages;

use App\Filament\Resources\RequiredChannels\RequiredChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRequiredChannel extends EditRecord
{
    protected static string $resource = RequiredChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
