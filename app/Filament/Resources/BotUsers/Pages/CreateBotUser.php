<?php

namespace App\Filament\Resources\BotUsers\Pages;

use App\Filament\Resources\BotUsers\BotUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBotUser extends CreateRecord
{
    protected static string $resource = BotUserResource::class;
}
