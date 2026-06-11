<?php

namespace App\Filament\Resources\ReferralRules\Pages;

use App\Filament\Resources\ReferralRules\ReferralRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferralRules extends ListRecords
{
    protected static string $resource = ReferralRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
