<?php

namespace App\Filament\Resources\CoinPlans\Pages;

use App\Filament\Resources\CoinPlans\CoinPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoinPlan extends EditRecord
{
    protected static string $resource = CoinPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
