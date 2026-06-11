<?php

namespace App\Filament\Resources\BotUsers\Pages;

use App\Filament\Resources\BotUsers\BotUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBotUser extends EditRecord
{
    protected static string $resource = BotUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
