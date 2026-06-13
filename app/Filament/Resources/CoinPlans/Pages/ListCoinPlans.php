<?php

namespace App\Filament\Resources\CoinPlans\Pages;

use App\Filament\Resources\CoinPlans\CoinPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoinPlans extends ListRecords
{
    protected static string $resource = CoinPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
