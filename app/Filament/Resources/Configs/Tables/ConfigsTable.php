<?php

namespace App\Filament\Resources\Configs\Tables;

use App\Support\Bytes;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConfigsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('botUser.telegram_id')
                    ->label('کاربر')
                    ->searchable(),
                TextColumn::make('panel.name')
                    ->label('سرور')
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->label('پلن')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('remote_identifier')
                    ->label('شناسه روی پنل')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->searchable(),
                TextColumn::make('data_limit_bytes')
                    ->label('حجم')
                    ->formatStateUsing(fn (int $state): string => Bytes::human($state))
                    ->sortable(),
                TextColumn::make('used_bytes')
                    ->label('مصرف‌شده')
                    ->formatStateUsing(fn (int $state): string => Bytes::human($state))
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('تاریخ انقضا')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_synced_at')
                    ->label('آخرین همگام‌سازی')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime()
                    ->sortable(),
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
