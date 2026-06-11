<?php

namespace App\Filament\Resources\ReferralRules\Pages;

use App\Filament\Resources\ReferralRules\ReferralRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReferralRule extends EditRecord
{
    protected static string $resource = ReferralRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
