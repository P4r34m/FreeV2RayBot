<?php

namespace App\Filament\Resources\BotButtons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BotButtonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('کلید')
                    ->searchable(),
                TextColumn::make('label')
                    ->label('برچسب دکمه'),
                TextColumn::make('icon_custom_emoji_id')
                    ->label('آیدی ایموجی پریمیوم')
                    ->placeholder('—'),
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
