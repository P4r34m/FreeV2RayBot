<?php

namespace App\Filament\Resources\ReportTopics\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ReportTopicsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')
                    ->label('رویداد')
                    ->badge()
                    ->searchable(),
                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable(),
                TextColumn::make('thread_id')
                    ->label('آیدی تاپیک')
                    ->numeric()
                    ->placeholder('General')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('فعال'),
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
