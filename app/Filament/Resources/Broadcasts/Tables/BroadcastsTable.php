<?php

namespace App\Filament\Resources\Broadcasts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BroadcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('message')->label('پیام')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('media_type')->label('نوع رسانه')
                    ->badge(),
                TextColumn::make('status')->label('وضعیت')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'done' => 'success',
                        'running' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sent')->label('ارسال‌شده')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('failed')->label('ناموفق')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total')->label('کل')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')->label('تاریخ')
                    ->dateTime()
                    ->sortable(),
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
