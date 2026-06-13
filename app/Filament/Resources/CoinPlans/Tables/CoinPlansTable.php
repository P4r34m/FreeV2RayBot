<?php

namespace App\Filament\Resources\CoinPlans\Tables;

use App\Support\Bytes;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CoinPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('data_limit_bytes')->label('حجم')
                    ->formatStateUsing(fn (int $state): string => Bytes::human($state))
                    ->sortable(),
                TextColumn::make('duration_days')->label('مدت (روز)')->numeric()->sortable(),
                TextColumn::make('coin_price')->label('قیمت (سکه)')->numeric()->sortable(),
                ToggleColumn::make('is_active')->label('فعال'),
                TextColumn::make('sort_order')->label('ترتیب')->numeric()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
