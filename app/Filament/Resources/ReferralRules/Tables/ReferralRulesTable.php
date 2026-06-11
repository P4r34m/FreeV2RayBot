<?php

namespace App\Filament\Resources\ReferralRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ReferralRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('نام')
                    ->searchable(),
                TextColumn::make('mode')
                    ->label('نوع شمارش')
                    ->badge(),
                TextColumn::make('threshold')
                    ->label('تعداد زیرمجموعه')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reward_type')
                    ->label('نوع پاداش')
                    ->badge(),
                TextColumn::make('reward_amount')
                    ->label('مقدار پاداش')
                    ->numeric()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('فعال'),
                TextColumn::make('sort_order')
                    ->label('ترتیب')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('آخرین بروزرسانی')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
